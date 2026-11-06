<?php

namespace Macroseso\Enzona;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Macroseso\Enzona\Exceptions\EnzonaException;
use Macroseso\Enzona\Models\EnzonaSetting;

class RequestToken
{
    public function accessToken(EnzonaSetting $settings): string
    {
        $consumerKey = $settings->consumer_key;
        $consumerSecret = $settings->consumer_secret;
        if (!$consumerKey || !$consumerSecret) {
            throw new EnzonaException('Configure las credenciales de EnZona antes de operar.');
        }

        $cacheKey = 'enzona.access_token.' . hash(
            'sha256',
            $settings->environment . "\0" . $consumerKey . "\0" . $consumerSecret
        );
        $cachedToken = Cache::get($cacheKey);
        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        $host = $this->apiHost($settings);
        $response = Http::acceptJson()
            ->asForm()
            ->withBasicAuth($consumerKey, $consumerSecret)
            ->timeout((int) config('enzona.timeout', 30))
            ->post($host . config('enzona.oauth_path', '/token'), [
                'grant_type' => 'client_credentials',
                'scope' => config('enzona.oauth_scope', 'enzona_business_payment enzona_business_qr'),
            ]);

        if (!$response->successful()) {
            throw new EnzonaException(
                'EnZona rechazó la solicitud de autenticación (HTTP ' . $response->status() . ').',
                $response->status()
            );
        }

        $tokenResponse = $response->json();
        $accessToken = is_array($tokenResponse) ? ($tokenResponse['access_token'] ?? null) : null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new EnzonaException('La respuesta de autenticación no contiene un token válido.');
        }

        $expiresIn = is_array($tokenResponse) && is_numeric($tokenResponse['expires_in'] ?? null)
            ? (int) $tokenResponse['expires_in']
            : null;
        $settings->markTokenVerified($expiresIn);

        if ($expiresIn !== null && $expiresIn > 0) {
            $buffer = (int) config('enzona.token_expiration_buffer', 30);
            Cache::put($cacheKey, $accessToken, max(1, $expiresIn - $buffer));
        }

        return $accessToken;
    }

    public function apiHost(EnzonaSetting $settings): string
    {
        $host = config('enzona.api_hosts.' . $settings->environment);
        if (!is_string($host) || $host === '') {
            throw new EnzonaException('No existe una URL de API configurada para el entorno seleccionado.');
        }

        return rtrim($host, '/');
    }
}
