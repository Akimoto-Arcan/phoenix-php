<?php

namespace Phoenix;

/**
 * Square Payment Gateway
 *
 * Integration with Square Payments using the official square/square SDK.
 * Degrades gracefully when the SDK is not installed or the access token is
 * not configured -- every public method returns a descriptive
 * PaymentResult::fail() instead of throwing.
 *
 * Install the SDK:
 *   composer require square/square
 *
 * Configure the access token via the Settings table:
 *   Settings::set('pos.square_access_token', 'EAAAl...');
 * or via environment variable:
 *   SQUARE_ACCESS_TOKEN=EAAAl...
 *
 * Optionally set the location ID:
 *   Settings::set('pos.square_location_id', 'L...');
 *   or SQUARE_LOCATION_ID env var
 *
 * By default the gateway targets the Square production environment.
 * Set pos.square_environment to 'sandbox' (or SQUARE_ENVIRONMENT=sandbox)
 * for testing.
 */
class SquareGateway implements PaymentGatewayInterface
{
    /** @var string */
    private $accessToken;

    /** @var string */
    private $locationId;

    /** @var string 'production' or 'sandbox' */
    private $environment;

    public function __construct()
    {
        $this->accessToken = '';
        $this->locationId  = '';
        $this->environment = 'production';

        // --- Access token ---
        try {
            $token = Settings::get('pos.square_access_token', '');
            if (is_string($token) && $token !== '') {
                $this->accessToken = $token;
            }
        } catch (\Exception $e) {
            // Settings table may not be available; fall through to env.
        }

        if ($this->accessToken === '') {
            $env = isset($_ENV['SQUARE_ACCESS_TOKEN']) ? $_ENV['SQUARE_ACCESS_TOKEN'] : (getenv('SQUARE_ACCESS_TOKEN') ?: '');
            if (is_string($env) && $env !== '') {
                $this->accessToken = $env;
            }
        }

        // --- Location ID ---
        try {
            $loc = Settings::get('pos.square_location_id', '');
            if (is_string($loc) && $loc !== '') {
                $this->locationId = $loc;
            }
        } catch (\Exception $e) {
            // ignore
        }

        if ($this->locationId === '') {
            $env = isset($_ENV['SQUARE_LOCATION_ID']) ? $_ENV['SQUARE_LOCATION_ID'] : (getenv('SQUARE_LOCATION_ID') ?: '');
            if (is_string($env) && $env !== '') {
                $this->locationId = $env;
            }
        }

        // --- Environment ---
        try {
            $envSetting = Settings::get('pos.square_environment', 'production');
            if (is_string($envSetting) && $envSetting !== '') {
                $this->environment = $envSetting;
            }
        } catch (\Exception $e) {
            // ignore
        }

        $envVar = isset($_ENV['SQUARE_ENVIRONMENT']) ? $_ENV['SQUARE_ENVIRONMENT'] : (getenv('SQUARE_ENVIRONMENT') ?: '');
        if (is_string($envVar) && $envVar !== '') {
            $this->environment = $envVar;
        }
    }

    // ------------------------------------------------------------------
    // Interface methods
    // ------------------------------------------------------------------

    /**
     * Create a Square payment.
     *
     * Optional $metadata keys:
     *   - source_id     string  Payment source (nonce or card-on-file ID)
     *   - note          string  Note attached to the payment
     *   - reference_id  string  Your own reference/order ID
     *   - customer_id   string  Square customer ID
     *
     * @param float  $amount   Charge amount in major currency units
     * @param string $currency ISO 4217 currency code
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
            $client = $this->buildClient();
            $paymentsApi = $client->getPaymentsApi();

            $money = new \Square\Models\Money();
            $money->setAmount($this->toMinorUnits($amount, $currency));
            $money->setCurrency(strtoupper($currency));

            $body = new \Square\Models\CreatePaymentRequest(
                isset($metadata['source_id']) ? $metadata['source_id'] : 'EXTERNAL',
                $this->idempotencyKey()
            );
            $body->setAmountMoney($money);
            $body->setLocationId($this->locationId);

            if (isset($metadata['note']) && $metadata['note'] !== '') {
                $body->setNote($metadata['note']);
            }
            if (isset($metadata['reference_id']) && $metadata['reference_id'] !== '') {
                $body->setReferenceId($metadata['reference_id']);
            }
            if (isset($metadata['customer_id']) && $metadata['customer_id'] !== '') {
                $body->setCustomerId($metadata['customer_id']);
            }

            $response = $paymentsApi->createPayment($body);

            if ($response->isSuccess()) {
                $payment = $response->getResult()->getPayment();
                return PaymentResult::ok($payment->getId(), [
                    'method'   => 'square',
                    'status'   => $payment->getStatus(),
                    'amount'   => $payment->getAmountMoney()->getAmount(),
                    'currency' => $payment->getAmountMoney()->getCurrency(),
                ]);
            }

            $errors = $response->getErrors();
            $msg = 'Square payment failed';
            if (is_array($errors) && count($errors) > 0) {
                $msg = $errors[0]->getDetail() ?: $errors[0]->getCategory();
            }
            return PaymentResult::fail('Square error: ' . $msg);
        } catch (\Exception $e) {
            return PaymentResult::fail('Payment failed: ' . $e->getMessage());
        }
    }

    /**
     * Refund a previous Square payment.
     *
     * @param string $transactionId Square payment ID
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
            $client = $this->buildClient();
            $refundsApi = $client->getRefundsApi();

            // Retrieve original payment to get the currency.
            $paymentResp = $client->getPaymentsApi()->getPayment($transactionId);
            $currency = 'USD';
            if ($paymentResp->isSuccess()) {
                $currency = $paymentResp->getResult()->getPayment()->getAmountMoney()->getCurrency();
            }

            $money = new \Square\Models\Money();
            $money->setAmount($this->toMinorUnits($amount, $currency));
            $money->setCurrency($currency);

            $body = new \Square\Models\RefundPaymentRequest(
                $this->idempotencyKey(),
                $money
            );
            $body->setPaymentId($transactionId);

            $response = $refundsApi->refundPayment($body);

            if ($response->isSuccess()) {
                $refund = $response->getResult()->getRefund();
                return PaymentResult::ok($refund->getId(), [
                    'method'               => 'square',
                    'refund_amount'        => $refund->getAmountMoney()->getAmount(),
                    'currency'             => $refund->getAmountMoney()->getCurrency(),
                    'status'               => $refund->getStatus(),
                    'original_transaction' => $transactionId,
                ]);
            }

            $errors = $response->getErrors();
            $msg = 'Square refund failed';
            if (is_array($errors) && count($errors) > 0) {
                $msg = $errors[0]->getDetail() ?: $errors[0]->getCategory();
            }
            return PaymentResult::fail('Square refund error: ' . $msg);
        } catch (\Exception $e) {
            return PaymentResult::fail('Refund failed: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve the status of a Square payment.
     *
     * @param string $transactionId Square payment ID
     * @return PaymentResult
     */
    public function getTransactionStatus(string $transactionId): PaymentResult
    {
        $check = $this->preflight();
        if ($check !== null) {
            return $check;
        }

        try {
            $client = $this->buildClient();
            $paymentsApi = $client->getPaymentsApi();

            $response = $paymentsApi->getPayment($transactionId);

            if ($response->isSuccess()) {
                $payment = $response->getResult()->getPayment();
                return PaymentResult::ok($payment->getId(), [
                    'method'   => 'square',
                    'status'   => $payment->getStatus(),
                    'amount'   => $payment->getAmountMoney()->getAmount(),
                    'currency' => $payment->getAmountMoney()->getCurrency(),
                ]);
            }

            $errors = $response->getErrors();
            $msg = 'Square lookup failed';
            if (is_array($errors) && count($errors) > 0) {
                $msg = $errors[0]->getDetail() ?: $errors[0]->getCategory();
            }
            return PaymentResult::fail('Square error: ' . $msg);
        } catch (\Exception $e) {
            return PaymentResult::fail('Status check failed: ' . $e->getMessage());
        }
    }

    /**
     * Square Terminal is a supported product.
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
        return 'Square';
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Verify that the SDK is installed and credentials are configured.
     *
     * @return PaymentResult|null  null when ready, PaymentResult on failure
     */
    private function preflight()
    {
        if (!class_exists('\\Square\\SquareClient')) {
            return PaymentResult::fail(
                'Square SDK not installed. Run: composer require square/square'
            );
        }

        if ($this->accessToken === '') {
            return PaymentResult::fail(
                'Square access token not configured. Set pos.square_access_token in Settings or the SQUARE_ACCESS_TOKEN environment variable.'
            );
        }

        if ($this->locationId === '') {
            return PaymentResult::fail(
                'Square location ID not configured. Set pos.square_location_id in Settings or the SQUARE_LOCATION_ID environment variable.'
            );
        }

        return null;
    }

    /**
     * Build a configured Square client instance.
     *
     * @return \Square\SquareClient
     */
    private function buildClient()
    {
        $config = [
            'accessToken' => $this->accessToken,
            'environment' => $this->environment === 'sandbox' ? 'sandbox' : 'production',
        ];

        return new \Square\SquareClient($config);
    }

    /**
     * Generate an idempotency key for Square API requests.
     *
     * @return string
     */
    private function idempotencyKey(): string
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(16));
        }
        return md5(uniqid((string) mt_rand(), true));
    }

    /**
     * Convert a major-unit amount to the smallest currency unit.
     * Zero-decimal currencies are left as-is.
     *
     * @param float  $amount
     * @param string $currency
     * @return int
     */
    private function toMinorUnits(float $amount, string $currency): int
    {
        $zeroDecimal = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
            'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];

        if (in_array(strtoupper($currency), $zeroDecimal, true)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }
}
