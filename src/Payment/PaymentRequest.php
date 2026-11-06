<?php

namespace Macroseso\Enzona\Payment;

use Illuminate\Support\Facades\Http;
use Macroseso\Enzona\Exceptions\EnzonaException;
use Macroseso\Enzona\Models\EnzonaSetting;
use Macroseso\Enzona\RequestToken;

class PaymentRequest
{
    private $settings;
    private $tokenProvider;

    public function __construct(EnzonaSetting $settings, RequestToken $tokenProvider)
    {
        $this->settings = $settings;
        $this->tokenProvider = $tokenProvider;
    }

    public function send($method, $route = '', array $data = null): array
    {
        if (!$this->settings->is_enabled) {
            throw new EnzonaException('La pasarela de EnZona está deshabilitada.');
        }

        $url = $this->tokenProvider->apiHost($this->settings)
            . rtrim(config('enzona.payment_path', '/payment/v1.0.0/payments'), '/');
        if ($route !== '') {
            $url .= '/' . ltrim($route, '/');
        }

        $request = Http::acceptJson()
            ->withToken($this->tokenProvider->accessToken($this->settings))
            ->timeout((int) config('enzona.timeout', 30));

        $response = $request->send(strtoupper($method), $url, $data === null ? [] : ['json' => $data]);
        if (!$response->successful()) {
            throw new EnzonaException(
                'EnZona respondió con un error HTTP ' . $response->status() . '.',
                $response->status()
            );
        }

        if ($response->status() === 204 || $response->body() === '') {
            return [];
        }

        $decoded = $response->json();
        if (!is_array($decoded)) {
            throw new EnzonaException('La respuesta de EnZona no contiene un objeto JSON válido.');
        }

        return $decoded;
    }
}
