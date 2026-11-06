<?php

namespace Lmacroseso\Enzona\Payment;

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
            // Validación de los datos requeridos
            if (!isset($data['merchant_uuid'], $data['merchant_op_id'], $data['amount'], $data['description'], $data['return_url'], $data['currency'], $data['items'], $data['invoice_number'], $data['cancel_url'], $data['buyer_identity_code'], $data['terminal_id'])) {
                throw new Exception("Faltan datos obligatorios en la solicitud.");
            }

            // Construcción del JSON de pago
            $paymentData = [
                "merchant_uuid" => $data['merchant_uuid'],
                "merchant_op_id" => $data['merchant_op_id'],
                "amount" => [
                    "total" => $data['amount']['total'] ?? 0.01,
                    "details" => [
                        "shipping" => $data['amount']['details']['shipping'] ?? 0.00,
                        "discount" => $data['amount']['details']['discount'] ?? 0.00,
                        "tax" => $data['amount']['details']['tax'] ?? 0.00,
                        "tip" => $data['amount']['details']['tip'] ?? 0.00
                    ]
                ],
                "description" => $data['description'],
                "return_url" => $data['return_url'],
                "currency" => $data['currency'],
                "items" => $data['items'], // Array de productos
                "invoice_number" => $data['invoice_number'],
                "cancel_url" => $data['cancel_url'],
                "buyer_identity_code" => $data['buyer_identity_code'],
                "terminal_id" => $data['terminal_id']
            ];

            $body = json_encode($paymentData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // URL de la API de Enzona
            $uri = $this->usedHost;

            // Inicializar cURL
            $ch = curl_init($uri);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HEADER => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Accept: application/json",
                    "Authorization: Bearer " . $this->accessToken,
                    "Content-Type: application/json",
                ],
                CURLOPT_POSTFIELDS => $body,
            ]);

            $result = curl_exec($ch);
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Manejo de errores en cURL
            if ($errno) {
                throw new Exception("cURL Error ({$errno}): {$error}");
            }

            // Manejo de códigos HTTP
            if ($httpCode !== 200) {
                throw new Exception("Error en la solicitud: Código HTTP {$httpCode}");
            }

            // Decodificar la respuesta JSON
            $response = json_decode($result, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Error al decodificar JSON: " . json_last_error_msg());
            }

            return $response ?? [];
        } catch (Exception $e) {
            return ["error" => $e->getMessage()];
        }
    }
}
