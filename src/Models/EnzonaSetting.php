<?php

namespace Macroseso\Enzona\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EnzonaSetting extends Model
{
    protected $table = 'enzona_settings';

    protected $hidden = [
        'consumer_key',
        'consumer_secret',
    ];

    protected $fillable = [
        'environment',
        'is_enabled',
        'consumer_key',
        'consumer_secret',
        'return_url',
        'cancel_url',
        'token_verified_at',
        'token_expires_in',
    ];

    protected $casts = [
        'consumer_key' => 'encrypted',
        'consumer_secret' => 'encrypted',
        'is_enabled' => 'boolean',
        'token_verified_at' => 'datetime',
        'token_expires_in' => 'integer',
    ];

    protected static function booted()
    {
        static::saving(function (self $setting) {
            if ((int) $setting->getKey() !== 1) {
                throw new InvalidArgumentException('Solo se permite el registro global de configuración con id 1.');
            }
            if (!in_array($setting->environment, ['sandbox', 'production'], true)) {
                throw new InvalidArgumentException('El entorno debe ser sandbox o production.');
            }
        });
    }

    public static function global(): self
    {
        $setting = static::query()->find(1);
        if ($setting) {
            return $setting;
        }

        $setting = new static();
        $setting->setAttribute('id', 1);
        $setting->setAttribute('environment', 'sandbox');
        $setting->setAttribute('is_enabled', false);

        return $setting;
    }

    public function updateCredentials($consumerKey, $consumerSecret): self
    {
        if (!is_string($consumerKey) || trim($consumerKey) === ''
            || !is_string($consumerSecret) || trim($consumerSecret) === '') {
            throw new InvalidArgumentException('Las credenciales de EnZona son obligatorias.');
        }

        $this->consumer_key = $consumerKey;
        $this->consumer_secret = $consumerSecret;
        $this->token_verified_at = null;
        $this->token_expires_in = null;
        $this->save();

        return $this;
    }

    public function changeEnvironment($environment): self
    {
        if (!in_array($environment, ['sandbox', 'production'], true)) {
            throw new InvalidArgumentException('El entorno debe ser sandbox o production.');
        }

        $this->environment = $environment;
        $this->token_verified_at = null;
        $this->token_expires_in = null;
        $this->save();

        return $this;
    }

    public function activate(): self
    {
        if (!$this->consumer_key || !$this->consumer_secret) {
            throw new InvalidArgumentException('Configure las credenciales antes de activar la pasarela.');
        }

        $this->is_enabled = true;
        $this->save();

        return $this;
    }

    public function deactivate(): self
    {
        $this->is_enabled = false;
        $this->save();

        return $this;
    }

    public function updateReturnUrls($returnUrl, $cancelUrl): self
    {
        foreach ([$returnUrl, $cancelUrl] as $url) {
            if ($url !== null && (!is_string($url) || trim($url) === '')) {
                throw new InvalidArgumentException('Las URLs de retorno deben ser cadenas no vacías o null.');
            }
        }

        $this->return_url = $returnUrl;
        $this->cancel_url = $cancelUrl;
        $this->save();

        return $this;
    }

    public function ensureReturnUrls(): self
    {
        $changed = false;
        if (!$this->return_url) {
            $this->return_url = route('enzona.return');
            $changed = true;
        }
        if (!$this->cancel_url) {
            $this->cancel_url = route('enzona.cancel');
            $changed = true;
        }

        if ($changed && $this->exists) {
            $this->save();
        }

        return $this;
    }

    public function markTokenVerified($expiresIn): self
    {
        $this->token_verified_at = now();
        $this->token_expires_in = is_numeric($expiresIn) && $expiresIn >= 0
            ? (int) $expiresIn
            : null;
        $this->save();

        return $this;
    }
}
