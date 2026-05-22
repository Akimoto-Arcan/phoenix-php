<?php

namespace Phoenix;

/**
 * Manual Card Gateway
 *
 * Used when payments are processed on an external card terminal (e.g., Verifone,
 * Ingenico). The POS operator confirms that the transaction completed on the
 * physical device and enters the last-4, card type, and approval code into the
 * system for record-keeping.
 *
 * charge()  always succeeds because the operator has already confirmed payment.
 * refund()  records the refund; the operator handles it on the terminal.
 */
class ManualCardGateway implements PaymentGatewayInterface
{
    /**
     * Record a charge that was already processed on the external terminal.
     *
     * Expected $metadata keys (all optional but recommended):
     *   - last4         string   Last four digits of the card
     *   - card_type     string   Visa, Mastercard, Amex, etc.
     *   - approval_code string   Approval/auth code from the terminal
     *
     * @param float  $amount   Charge amount
     * @param string $currency ISO 4217 currency code
     * @param array  $metadata Additional payment context
     * @return PaymentResult
     */
    public function charge(float $amount, string $currency, array $metadata = []): PaymentResult
    {
        $txId = 'MANUAL-' . date('Ymd-His') . '-' . mt_rand(1000, 9999);

        return PaymentResult::ok($txId, [
            'method'        => 'manual_card',
            'amount'        => $amount,
            'currency'      => $currency,
            'last4'         => isset($metadata['last4']) ? substr($metadata['last4'], -4) : null,
            'card_type'     => isset($metadata['card_type']) ? $metadata['card_type'] : null,
            'approval_code' => isset($metadata['approval_code']) ? $metadata['approval_code'] : null,
            'terminal_verified' => true,
        ]);
    }

    /**
     * Record a refund that will be (or was) processed on the external terminal.
     *
     * @param string $transactionId Original transaction ID
     * @param float  $amount        Refund amount
     * @return PaymentResult
     */
    public function refund(string $transactionId, float $amount): PaymentResult
    {
        $refundId = 'MANUALREF-' . date('Ymd-His') . '-' . mt_rand(1000, 9999);

        return PaymentResult::ok($refundId, [
            'method'               => 'manual_card',
            'refund_amount'        => $amount,
            'original_transaction' => $transactionId,
            'terminal_verified'    => true,
        ]);
    }

    /**
     * Manual card transactions are always considered completed once recorded.
     *
     * @param string $transactionId
     * @return PaymentResult
     */
    public function getTransactionStatus(string $transactionId): PaymentResult
    {
        return PaymentResult::ok($transactionId, [
            'status' => 'completed',
            'method' => 'manual_card',
        ]);
    }

    /**
     * This gateway represents an external terminal, so terminal support is true.
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
        return 'Manual Card Entry';
    }
}
