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
        $this->usedHost = $this->sandBox ? "https://sandbox.enzona.net/" : "https://api.enzona.net/";
    }

    public function requestToken()
    {
        try {
            $uri = $this->usedHost . $this->tokenRoute;
            $param = http_build_query([
                "grant_type" => "password",
                "username" => $this->username,
                "password" => $this->password,
                "scope" => "enzona_business_payment enzona_business_qr"
            ]);

            $ch = curl_init($uri);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Basic " . base64_encode($this->apiKey . ':' . $this->apiSecret),
                "Content-Type: application/x-www-form-urlencoded"
            ]);

            $result = curl_exec($ch);
            $error = curl_error($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 200) {
                $res = json_decode($result, true);
                return $res['access_token'] ?? false;
            } else {
                throw new Exception("Error en la solicitud: Código $code - $error");
            }
        } catch (Exception $e) {
            throw new Exception("Error en requestToken: " . $e->getMessage());
        }
    }
}
