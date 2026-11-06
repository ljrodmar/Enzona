<?php

namespace Lmacroseso\Enzona\Payment;

use Exception;

class RefundPayment
{
    private $accessToken;
    private $usedHost;
    private $refundRoute = "refund"; // Ruta para reembolsar un pago

    public function __construct($accessToken, $usedHost)
    {
        $this->accessToken = $accessToken;
        $this->usedHost = $usedHost;
        $this->apiRoute = $apiRoute;
    }

    public function refund($uuid, $amount)
    {
        $uri = "{$this->usedHost}/{$uuid}/{$this->refundRoute}";

        // Datos del reembolso
        $data = [
            'amount' => $amount, // El monto del reembolso
        ];

        $body = json_encode($data);

        $ch = curl_init($uri);
        curl_setopt_array($ch, [
            CURLOPT_HEADER => false,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Authorization: Bearer {$this->accessToken}",
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => $body,
        ]);

        $result = curl_exec($ch);
        $error = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            return json_decode($result, true);
        }

        throw new Exception("Error al reembolsar el pago: {$error}", $code);
    }
}
