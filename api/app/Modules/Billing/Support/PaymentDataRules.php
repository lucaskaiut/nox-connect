<?php

namespace App\Modules\Billing\Support;

/**
 * Whitelist de campos permitidos em payment_data (fronteira PCI).
 * PAN/CVV nunca devem trafegar pela API — use credit_card_token ou invoiceUrl.
 */
final class PaymentDataRules
{
    /**
     * @return array<string, mixed>
     */
    public static function validationRules(string $prefix = 'payment_data'): array
    {
        $key = static fn (string $field): string => "{$prefix}.{$field}";

        return [
            $prefix => ['nullable', 'array'],
            $key('remote_ip') => ['nullable', 'string', 'max:45'],
            $key('remoteIp') => ['nullable', 'string', 'max:45'],
            $key('credit_card_token') => ['nullable', 'string', 'max:255'],
            $key('creditCardToken') => ['nullable', 'string', 'max:255'],
            $key('installments') => ['nullable', 'integer', 'min:1', 'max:21'],
            $key('installment_count') => ['prohibited'],
            $key('installmentCount') => ['prohibited'],
            $key('authorize_only') => ['nullable', 'boolean'],
            $key('authorizeOnly') => ['nullable', 'boolean'],
            $key('number') => ['prohibited'],
            $key('cvv') => ['prohibited'],
            $key('ccv') => ['prohibited'],
            $key('pan') => ['prohibited'],
            $key('holder_name') => ['prohibited'],
            $key('holderName') => ['prohibited'],
            $key('exp_month') => ['prohibited'],
            $key('exp_year') => ['prohibited'],
            $key('expiry_month') => ['prohibited'],
            $key('expiry_year') => ['prohibited'],
            $key('credit_card') => ['prohibited'],
            $key('creditCard') => ['prohibited'],
            $key('credit_card_holder_info') => ['prohibited'],
            $key('creditCardHolderInfo') => ['prohibited'],
        ];
    }

    /**
     * @param  array<string, mixed>  $paymentData
     * @return array<string, mixed>
     */
    public static function sanitize(array $paymentData): array
    {
        $allowed = [
            'remote_ip',
            'remoteIp',
            'credit_card_token',
            'creditCardToken',
            'installments',
            'authorize_only',
            'authorizeOnly',
        ];

        return array_intersect_key($paymentData, array_flip($allowed));
    }
}
