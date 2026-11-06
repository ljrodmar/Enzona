<?php

namespace Macroseso\Enzona\Payment;

use InvalidArgumentException;
use Macroseso\Enzona\Exceptions\EnzonaException;
use Macroseso\Enzona\Models\EnzonaSetting;

class CreatePayment
{
    private $settings;
    private $request;

    public function __construct(EnzonaSetting $settings, PaymentRequest $request)
    {
        $this->settings = $settings;
        $this->request = $request;
    }

    public function processPayment(array $data): array
    {
        if (!$this->settings->is_enabled) {
            throw new EnzonaException('La pasarela de EnZona está deshabilitada.');
        }

        $payload = $this->buildPayload($data);
        $response = $this->request->send('POST', '', $payload);

        return [
            'response' => $response,
            'transaction_uuid' => $response['transaction_uuid'] ?? null,
            'status_code' => $response['status_code'] ?? null,
            'status_denom' => $response['status_denom'] ?? null,
            'checkout_url' => $this->checkoutUrl($response),
        ];
    }

    private function buildPayload(array $data): array
    {
        $required = [
            'merchant_uuid',
            'merchant_op_id',
            'amount',
            'description',
            'currency',
            'items',
            'invoice_number',
            'buyer_identity_code',
            'terminal_id',
        ];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException("Falta el campo obligatorio '{$field}'.");
            }
        }

        $this->requireString($data['merchant_uuid'], 'merchant_uuid');
        $this->requireInteger($data['merchant_op_id'], 'merchant_op_id', 1);
        $this->requireString($data['description'], 'description');
        $this->requireString($data['currency'], 'currency');
        $this->requireInteger($data['invoice_number'], 'invoice_number', 0);
        $this->requireString($data['buyer_identity_code'], 'buyer_identity_code');
        $this->requireInteger($data['terminal_id'], 'terminal_id', 1);

        if (!is_array($data['amount']) || !array_key_exists('total', $data['amount'])
            || !isset($data['amount']['details']) || !is_array($data['amount']['details'])) {
            throw new InvalidArgumentException("El campo 'amount' debe incluir total y details.");
        }
        $this->requireNumber($data['amount']['total'], 'amount.total');
        if ($data['amount']['total'] <= 0) {
            throw new InvalidArgumentException("El campo 'amount.total' debe ser mayor que cero.");
        }

        $details = [];
        foreach (['shipping', 'discount', 'tax', 'tip'] as $field) {
            if (!array_key_exists($field, $data['amount']['details'])) {
                throw new InvalidArgumentException("Falta el campo obligatorio 'amount.details.{$field}'.");
            }
            $this->requireNumber($data['amount']['details'][$field], 'amount.details.' . $field);
            if ($data['amount']['details'][$field] < 0) {
                throw new InvalidArgumentException("El campo 'amount.details.{$field}' no puede ser negativo.");
            }
            $details[$field] = $data['amount']['details'][$field];
        }

        if (!is_array($data['items']) || count($data['items']) === 0) {
            throw new InvalidArgumentException("El campo 'items' debe contener al menos un elemento.");
        }
        $items = [];
        foreach (array_values($data['items']) as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException("El elemento items.{$index} debe ser un objeto.");
            }
            foreach (['quantity', 'price', 'name', 'description', 'tax'] as $field) {
                if (!array_key_exists($field, $item)) {
                    throw new InvalidArgumentException("Falta el campo obligatorio 'items.{$index}.{$field}'.");
                }
            }
            $this->requireInteger($item['quantity'], "items.{$index}.quantity", 1);
            $this->requireNumber($item['price'], "items.{$index}.price");
            $this->requireString($item['name'], "items.{$index}.name");
            $this->requireString($item['description'], "items.{$index}.description");
            $this->requireNumber($item['tax'], "items.{$index}.tax");
            if ($item['price'] < 0 || $item['tax'] < 0) {
                throw new InvalidArgumentException("Los importes de items.{$index} no pueden ser negativos.");
            }
            $items[] = [
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'name' => $item['name'],
                'description' => $item['description'],
                'tax' => $item['tax'],
            ];
        }

        $this->settings->ensureReturnUrls();

        return [
            'merchant_uuid' => $data['merchant_uuid'],
            'merchant_op_id' => $data['merchant_op_id'],
            'amount' => [
                'total' => $data['amount']['total'],
                'details' => $details,
            ],
            'description' => $data['description'],
            'return_url' => $this->settings->return_url,
            'currency' => $data['currency'],
            'items' => $items,
            'invoice_number' => $data['invoice_number'],
            'cancel_url' => $this->settings->cancel_url,
            'buyer_identity_code' => $data['buyer_identity_code'],
            'terminal_id' => $data['terminal_id'],
        ];
    }

    private function checkoutUrl(array $response)
    {
        foreach (($response['links'] ?? []) as $link) {
            if (is_array($link)
                && ($link['rel'] ?? null) === 'confirm'
                && ($link['method'] ?? null) === 'REDIRECT'
                && is_string($link['href'] ?? null)) {
                return $link['href'];
            }
        }

        return null;
    }

    private function requireString($value, $field): void
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("El campo '{$field}' debe ser una cadena no vacía.");
        }
    }

    private function requireNumber($value, $field): void
    {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
            throw new InvalidArgumentException("El campo '{$field}' debe ser un número entero o decimal.");
        }
    }

    private function requireInteger($value, $field, $minimum): void
    {
        if (!is_int($value) || $value < $minimum) {
            throw new InvalidArgumentException("El campo '{$field}' debe ser un entero mayor o igual que {$minimum}.");
        }
    }
}
