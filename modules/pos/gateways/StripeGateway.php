<?php

namespace Phoenix;

/**
 * Stripe Payment Gateway
 *
 * Full Stripe integration using the official stripe/stripe-php SDK.
 * Degrades gracefully when the SDK is not installed or the API key is
 * not configured -- every public method returns a descriptive
 * PaymentResult::fail() instead of throwing.
 *
 * Install the SDK:
 *   composer require stripe/stripe-php
 *
 * Configure the secret key via the Settings table:
 *   Settings::set('pos.stripe_secret_key', 'sk_live_...');
 * or via environment variable:
 *   STRIPE_SECRET_KEY=sk_live_...
 */
class StripeGateway implements PaymentGatewayInterface
{
    /** @var string */
    private $secretKey;

    public function __construct()
    {
        $this->secretKey = '';

        try {
            $key = Settings::get('pos.stripe_secret_key', '');
            if (is_string($key) && $key !== '') {
                $this->secretKey = $key;
            }
        } catch (\Exception $e) {
            // Settings table may not be available; fall through to env.
        }

        if ($this->secretKey === '') {
            $env = isset($_ENV['STRIPE_SECRET_KEY']) ? $_ENV['STRIPE_SECRET_KEY'] : (getenv('STRIPE_SECRET_KEY') ?: '');
            if (is_string($env) && $env !== '') {
                $this->secretKey = $env;
            }
        }
    }

    // ------------------------------------------------------------------
    // Interface methods
    // ------------------------------------------------------------------

    /**
     * Create a Stripe PaymentIntent and confirm it immediately.
     *
     * Optional $metadata keys:
     *   - payment_method  string  Stripe PaymentMethod ID (pm_...)
     *   - description     string  Human-readable description
     *   - receipt_email   string  Customer email for Stripe receipt
     *
     * @param float  $amount   Charge amount in major currency units (e.g. 12.50)
     * @param string $currency ISO 4217 currency code (e.g. 'usd')
     * @param array  $metadata
     * @return PaymentResult
     */
    public function charge(float $amount, string $currency, array $metadata = []): PaymentResult
    {
        $check = $this->preflight();
        if ($check !== null) {
            return $check;
        }

        try {
            \Stripe\Stripe::setApiKey($this->secretKey);

            $params = [
                'amount'               => $this->toMinorUnits($amount, $currency),
                'currency'             => strtolower($currency),
                'confirm'              => true,
                'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
            ];

            if (isset($metadata['payment_method']) && $metadata['payment_method'] !== '') {
                $params['payment_method'] = $metadata['payment_method'];
            }
            if (isset($metadata['description']) && $metadata['description'] !== '') {
                $params['description'] = $metadata['description'];
            }
            if (isset($metadata['receipt_email']) && $metadata['receipt_email'] !== '') {
                $params['receipt_email'] = $metadata['receipt_email'];
            }

            /** @var \Stripe\PaymentIntent $intent */
            $intent = \Stripe\PaymentIntent::create($params);

            return PaymentResult::ok($intent->id, [
                'method'   => 'stripe',
                'status'   => $intent->status,
                'amount'   => $intent->amount,
                'currency' => $intent->currency,
            ]);
        } catch (\Stripe\Exception\CardException $e) {
            return PaymentResult::fail('Card declined: ' . $e->getMessage(), [
                'stripe_code' => $e->getStripeCode(),
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return PaymentResult::fail('Stripe error: ' . $e->getMessage());
        } catch (\Exception $e) {
            return PaymentResult::fail('Payment failed: ' . $e->getMessage());
        }
    }

    /**
     * Refund a previous Stripe PaymentIntent.
     *
     * @param string $transactionId Stripe PaymentIntent ID (pi_...)
     * @param float  $amount        Refund amount in major currency units
     * @return PaymentResult
     */
    public function refund(string $transactionId, float $amount): PaymentResult
    {
        $check = $this->preflight();
        if ($check !== null) {
            return $check;
        }

        try {
            \Stripe\Stripe::setApiKey($this->secretKey);

            // Retrieve the intent to determine the currency for unit conversion.
            $intent = \Stripe\PaymentIntent::retrieve($transactionId);

            $refund = \Stripe\Refund::create([
                'payment_intent' => $transactionId,
                'amount'         => $this->toMinorUnits($amount, $intent->currency),
            ]);

            return PaymentResult::ok($refund->id, [
                'method'               => 'stripe',
                'refund_amount'        => $refund->amount,
                'currency'             => $refund->currency,
                'status'               => $refund->status,
                'original_transaction' => $transactionId,
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return PaymentResult::fail('Stripe refund error: ' . $e->getMessage());
        } catch (\Exception $e) {
            return PaymentResult::fail('Refund failed: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve the status of a Stripe PaymentIntent.
     *
     * @param string $transactionId Stripe PaymentIntent ID (pi_...)
     * @return PaymentResult
     */
    public function getTransactionStatus(string $transactionId): PaymentResult
    {
        $check = $this->preflight();
        if ($check !== null) {
            return $check;
        }

        try {
            \Stripe\Stripe::setApiKey($this->secretKey);

            $intent = \Stripe\PaymentIntent::retrieve($transactionId);

            return PaymentResult::ok($intent->id, [
                'method'   => 'stripe',
                'status'   => $intent->status,
                'amount'   => $intent->amount,
                'currency' => $intent->currency,
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return PaymentResult::fail('Stripe lookup error: ' . $e->getMessage());
        } catch (\Exception $e) {
            return PaymentResult::fail('Status check failed: ' . $e->getMessage());
        }
    }

    /**
     * Stripe Terminal is a supported product.
     *
     * @return bool
     */
    public function supportsTerminal(): bool
    {
        return true;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'Stripe';
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Verify that the SDK is installed and a key is configured.
     * Returns null when everything is ready, or a PaymentResult on failure.
     *
     * @return PaymentResult|null
     */
    private function preflight()
    {
        if (!class_exists('\\Stripe\\Stripe')) {
            return PaymentResult::fail(
                'Stripe SDK not installed. Run: composer require stripe/stripe-php'
            );
        }

        if ($this->secretKey === '') {
            return PaymentResult::fail(
                'Stripe API key not configured. Set pos.stripe_secret_key in Settings or the STRIPE_SECRET_KEY environment variable.'
            );
        }

        return null;
    }

    /**
     * Convert a major-unit amount (e.g. 12.50) to the smallest currency unit
     * (e.g. 1250 cents).  Zero-decimal currencies (JPY, KRW, etc.) are left
     * as-is.
     *
     * @param float  $amount
     * @param string $currency
     * @return int
     */
    private function toMinorUnits(float $amount, string $currency): int
    {
        $zeroDecimal = [
            'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga',
            'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf',
        ];

        if (in_array(strtolower($currency), $zeroDecimal, true)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }
}
