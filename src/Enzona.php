<?php

namespace Macroseso\Enzona;

use Macroseso\Enzona\Payment\CreatePayment;
use Macroseso\Enzona\Payment\CompletePayment;
use Macroseso\Enzona\Payment\CancelPayment;
use Macroseso\Enzona\Payment\RefundPayment;
use Macroseso\Enzona\RequestToken;
use Exception;

class Enzona
{
    private $accessToken;
    private $username;
    private $password;
    private $apiKey;
    private $apiSecret;
    private $sandbox;
    private $usedHost;

    public function __construct()
    {
        $this->username = config('enzona.username');
        $this->password = config('enzona.password');
        $this->apiKey = config('enzona.api_key');
        $this->apiSecret = config('enzona.api_secret');
        $this->sandbox = config('enzona.sandbox', true);
        $apiHost = $this->sandbox ? 'https://apisandbox.enzona.net' : 'https://api.enzona.net';
        $this->usedHost = $apiHost . '/payment/v1.0.0/payments';

        try {
            $this->accessToken = (new RequestToken(
                $this->username,
                $this->password,
                $this->apiKey,
                $this->apiSecret,
                $this->sandbox
            ))->requestToken();
        } catch (Exception $e) {
            throw new Exception('Error al obtener el token de Enzona: ' . $e->getMessage(), 0, $e);
        }
    }

    public function createPayment(array $data)
    {
        try {
            $createPayment = new CreatePayment($this->accessToken, $this->usedHost);
            return $createPayment->processPayment($data);
        } catch (Exception $e) {
            return ["error" => $e->getMessage()];
        }
    }

    public function cancelPayment($paymentId)
    {
        try {
            return (new CancelPayment($this->accessToken, $this->usedHost))->cancel($paymentId);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function completePayment($paymentId)
    {
        try {
            return (new CompletePayment($this->accessToken, $this->usedHost))->complete($paymentId);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function refundPayment($paymentId, $amount)
    {
        try {
            return (new RefundPayment($this->accessToken, $this->usedHost))->refund($paymentId, $amount);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }
}
