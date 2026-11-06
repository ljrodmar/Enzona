<?php

namespace Lmacroseso\Enzona\Payment;

use Exception;

class CancelPayment
{
    private $accessToken;
    private $usedHost;
    private $apiRoute;
    private $cancelRoute = "cancel"; // Asegúrate de definir la ruta correcta

    public function __construct($accessToken, $usedHost)
    {
        $this->accessToken = $accessToken;
        $this->usedHost = $usedHost;
        $this->apiRoute = $apiRoute;
    }

    public function cancel($uuid)
    {
        $uri = "{$this->usedHost}/{$uuid}/{$this->cancelRoute}";

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
            ]
        ]);

        $result = curl_exec($ch);
        $error = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            return json_decode($result, true);
        }

        throw new Exception("Error al cancelar el pago: {$error}", $code);
    }
}
