<?php

namespace Macroseso\Enzona;

use Exception;

class RequestToken
{
    protected $apiKey;
    protected $apiSecret;
    protected $username;
    protected $password;
    protected $sandBox;
    protected $usedHost;
    protected $tokenRoute = "oauth/token";

    public function __construct($username, $password, $key, $secret, $sandBox = true)
    {
        $this->apiKey = $key;
        $this->apiSecret = $secret;
        $this->username = $username;
        $this->password = $password;
        $this->sandBox = $sandBox;
        $this->usedHost = $this->sandBox ? "https://apisandbox.enzona.net/" : "https://api.enzona.net/";
    }

    public function requestToken()
    {
        if (!$this->username || !$this->password || !$this->apiKey || !$this->apiSecret) {
            throw new Exception('Debe configurar las credenciales de Enzona.');
        }

        $uri = $this->usedHost . $this->tokenRoute;
        $param = http_build_query([
            'grant_type' => 'password',
            'username' => $this->username,
            'password' => $this->password,
            'scope' => 'enzona_business_payment enzona_business_qr',
        ]);

        $ch = curl_init($uri);
        if ($ch === false) {
            throw new Exception('No se pudo inicializar la solicitud de token.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $param,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode($this->apiKey . ':' . $this->apiSecret),
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $result = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new Exception("Error de cURL ({$errno}): {$error}");
        }

        if ($code < 200 || $code >= 300) {
            throw new Exception("Error al solicitar el token: Código HTTP {$code}.");
        }

        $response = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($response['access_token'])) {
            throw new Exception('La respuesta de autenticación no contiene un token válido.');
        }

        return $response['access_token'];
    }
}
