<?php

namespace Macroseso\Enzona\Payment;

use InvalidArgumentException;

class RefundPayment
{
    private $request;

    public function __construct(PaymentRequest $request)
    {
        $this->request = $request;
    }

    public function refund($transactionUuid, array $data): array
    {
        if (!is_string($transactionUuid) || trim($transactionUuid) === '') {
            throw new InvalidArgumentException('El identificador de la transacción es obligatorio.');
        }

        foreach (['amount', 'commerce_refund_id', 'username', 'description'] as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException("Falta el campo obligatorio '{$field}'.");
            }
        }

        if (!is_array($data['amount'])
            || !isset($data['amount']['total'])
            || !is_string($data['amount']['total'])
            || !is_numeric($data['amount']['total'])) {
            throw new InvalidArgumentException("El campo 'amount.total' debe ser una cadena numérica.");
        }
        foreach (['commerce_refund_id', 'username', 'description'] as $field) {
            if (!is_string($data[$field]) || trim($data[$field]) === '') {
                throw new InvalidArgumentException("El campo '{$field}' debe ser una cadena no vacía.");
            }
        }

        $payload = [
            'amount' => [
                'total' => $data['amount']['total'],
            ],
            'commerce_refund_id' => $data['commerce_refund_id'],
            'username' => $data['username'],
            'description' => $data['description'],
        ];

        return $this->request->send('POST', rawurlencode($transactionUuid) . '/refund', $payload);
    }
}
