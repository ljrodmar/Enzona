<?php

namespace Macroseso\Enzona\Payment;

use Exception;

class CreatePayment
{
    private $accessToken;
    private $usedHost;

    public function __construct($accessToken, $usedHost)
    {
        $this->accessToken = $accessToken;
        $this->usedHost = $usedHost;
    }

    public function processPayment(array $data): array
    {
        try {
            $required = [
                'merchant_uuid',
                'merchant_op_id',
                'amount',
                'description',
                'return_url',
                'currency',
                'items',
                'invoice_number',
                'cancel_url',
                'buyer_identity_code',
                'terminal_id',
            ];
            foreach ($required as $field) {
                if (!array_key_exists($field, $data)) {
                    throw new Exception("Falta el campo obligatorio '{$field}'.");
                }
            }

            if (!is_array($data['amount']) || !isset($data['amount']['total']) || !is_numeric($data['amount']['total'])) {
                throw new Exception("El campo 'amount.total' debe ser numérico.");
            }
            if (!is_array($data['items'])) {
                throw new Exception("El campo 'items' debe ser una lista.");
            }

            $paymentData = [
                'merchant_uuid' => $data['merchant_uuid'],
                'merchant_op_id' => $data['merchant_op_id'],
                'amount' => [
                    'total' => $data['amount']['total'],
                    'details' => [
                        'shipping' => $data['amount']['details']['shipping'] ?? 0.00,
                        'discount' => $data['amount']['details']['discount'] ?? 0.00,
                        'tax' => $data['amount']['details']['tax'] ?? 0.00,
                        'tip' => $data['amount']['details']['tip'] ?? 0.00,
                    ],
                ],
                'description' => $data['description'],
                'return_url' => $data['return_url'],
                'currency' => $data['currency'],
                'items' => $data['items'],
                'invoice_number' => $data['invoice_number'],
                'cancel_url' => $data['cancel_url'],
                'buyer_identity_code' => $data['buyer_identity_code'],
                'terminal_id' => $data['terminal_id'],
            ];

            return (new PaymentRequest($this->accessToken, $this->usedHost))->send('POST', '', $paymentData);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
