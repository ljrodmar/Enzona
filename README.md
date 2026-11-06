# Enzona para Laravel

Cliente Laravel para crear, completar, cancelar y reembolsar pagos mediante la API de Enzona.

## Instalación

```bash
composer require ljrodmar/enzona
php artisan vendor:publish --provider="Macroseso\Enzona\EnzonaServiceProvider" --tag="enzona-config"
```

Configure estas variables en el archivo `.env`:

```dotenv
ENZONA_API_KEY=
ENZONA_API_SECRET=
ENZONA_USERNAME=
ENZONA_PASSWORD=
ENZONA_SANDBOX=true
```

Use las credenciales de prueba con `ENZONA_SANDBOX=true`. Cambie a `false` solo para operar en producción.

## Uso

Resuelva el cliente desde el contenedor de Laravel:

```php
use Macroseso\Enzona\Enzona;

$enzona = app(Enzona::class);
$payment = $enzona->createPayment($paymentData);
```

`$paymentData` debe incluir `merchant_uuid`, `merchant_op_id`, `amount` (con `total` numérico), `description`, `return_url`, `currency`, `items`, `invoice_number`, `cancel_url`, `buyer_identity_code` y `terminal_id`. `amount.details` puede incluir `shipping`, `discount`, `tax` y `tip`.

El cliente expone también `completePayment($paymentId)`, `cancelPayment($paymentId)` y `refundPayment($paymentId, $amount)`. Las respuestas de la API se devuelven como arreglos; los errores de operación se devuelven como `['error' => '...']`. La obtención del token falla con una excepción si las credenciales o la autenticación no son válidas.

Se requiere PHP 7.4 o posterior y la extensión cURL.
