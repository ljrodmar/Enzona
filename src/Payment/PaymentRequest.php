<?php

namespace Macroseso\Enzona\Payment;

use Exception;

class PaymentRequest
{
    private $accessToken;
    private $baseUrl;

    public function __construct($accessToken, $baseUrl)
    {
        $this->accessToken = $accessToken;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function send($method, $route, ?array $data = null): array
    {
        $url = $route === '' ? $this->baseUrl : $this->baseUrl . '/' . ltrim($route, '/');
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->accessToken,
        ];
        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($data !== null) {
            $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($body === false) {
                throw new Exception('No se pudo codificar la solicitud como JSON.');
            }

            $options[CURLOPT_POSTFIELDS] = $body;
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new Exception('No se pudo inicializar la solicitud de pago.');
        }

        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new Exception("Error de cURL ({$errno}): {$error}");
        }

        if ($status < 200 || $status >= 300) {
            throw new Exception("Error en la solicitud a Enzona: Código HTTP {$status}.");
        }

        if ($result === '' || $result === false) {
            return [];
        }

        $response = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($response)) {
            throw new Exception('La respuesta de Enzona no contiene JSON válido.');
        }

        return $response;
    }
}
