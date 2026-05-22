<?php

namespace Phoenix;

interface PaymentGatewayInterface
{
    public function charge(float $amount, string $currency, array $metadata = []): PaymentResult;
    public function refund(string $transactionId, float $amount): PaymentResult;
    public function getTransactionStatus(string $transactionId): PaymentResult;
    public function supportsTerminal(): bool;
    public function getName(): string;
}

class PaymentResult
{
    /** @var bool */
    public $success;
    /** @var string|null */
    public $transactionId;
    /** @var string|null */
    public $error;
    /** @var array */
    public $raw;

    public function __construct(bool $success, ?string $transactionId = null, ?string $error = null, array $raw = [])
    {
        $this->success = $success;
        $this->transactionId = $transactionId;
        $this->error = $error;
        $this->raw = $raw;
    }

    public static function ok(string $transactionId, array $raw = []): self
    {
        return new self(true, $transactionId, null, $raw);
    }

    public static function fail(string $error, array $raw = []): self
    {
        return new self(false, null, $error, $raw);
    }
}

class PaymentGatewayFactory
{
    private static $gateways = [];

    public static function register(string $name, string $className): void
    {
        self::$gateways[$name] = $className;
    }

    public static function create(?string $name = null): PaymentGatewayInterface
    {
        if ($name === null) {
            try {
                $name = Settings::get('pos.payment_gateway', 'cash');
            } catch (\Exception $e) {
                $name = 'cash';
            }
        }

        if (isset(self::$gateways[$name])) {
            $class = self::$gateways[$name];
            return new $class();
        }

        switch ($name) {
            case 'cash':
                return new CashGateway();
            case 'manual_card':
                $file = dirname(__DIR__) . '/modules/pos/gateways/ManualCardGateway.php';
                if (file_exists($file)) {
                    require_once $file;
                    return new \Phoenix\ManualCardGateway();
                }
                throw new \RuntimeException("ManualCardGateway not found");
            case 'stripe':
                $file = dirname(__DIR__) . '/modules/pos/gateways/StripeGateway.php';
                if (file_exists($file)) {
                    require_once $file;
                    return new \Phoenix\StripeGateway();
                }
                throw new \RuntimeException("StripeGateway not found");
            case 'square':
                $file = dirname(__DIR__) . '/modules/pos/gateways/SquareGateway.php';
                if (file_exists($file)) {
                    require_once $file;
                    return new \Phoenix\SquareGateway();
                }
                throw new \RuntimeException("SquareGateway not found");
            default:
                throw new \RuntimeException("Unknown payment gateway: {$name}");
        }
    }

    public static function available(): array
    {
        $list = ['cash' => 'Cash'];
        $gatewayDir = dirname(__DIR__) . '/modules/pos/gateways/';
        if (file_exists($gatewayDir . 'ManualCardGateway.php')) $list['manual_card'] = 'Manual Card Entry';
        if (file_exists($gatewayDir . 'StripeGateway.php')) $list['stripe'] = 'Stripe';
        if (file_exists($gatewayDir . 'SquareGateway.php')) $list['square'] = 'Square';
        foreach (self::$gateways as $name => $class) {
            $list[$name] = $name;
        }
        return $list;
    }
}

class CashGateway implements PaymentGatewayInterface
{
    public function charge(float $amount, string $currency, array $metadata = []): PaymentResult
    {
        $tendered = $metadata['amount_tendered'] ?? $amount;
        $change = max(0, $tendered - $amount);
        $txId = 'CASH-' . date('Ymd-His') . '-' . mt_rand(1000, 9999);

        return PaymentResult::ok($txId, [
            'method' => 'cash',
            'amount' => $amount,
            'amount_tendered' => $tendered,
            'change_due' => $change,
            'currency' => $currency,
        ]);
    }

    public function refund(string $transactionId, float $amount): PaymentResult
    {
        $txId = 'CASHREF-' . date('Ymd-His') . '-' . mt_rand(1000, 9999);
        return PaymentResult::ok($txId, [
            'method' => 'cash',
            'refund_amount' => $amount,
            'original_transaction' => $transactionId,
        ]);
    }

    public function getTransactionStatus(string $transactionId): PaymentResult
    {
        return PaymentResult::ok($transactionId, ['status' => 'completed']);
    }

    public function supportsTerminal(): bool
    {
        return false;
    }

    public function getName(): string
    {
        return 'Cash';
    }
}
