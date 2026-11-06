# Integración Laravel con EnZona

Paquete Laravel para autenticar un comercio con EnZona y crear, consultar, cancelar y reembolsar transacciones. Las credenciales y URLs de retorno se administran en un registro global de la base de datos. El paquete no actualiza órdenes, facturas ni pagos de la aplicación.

## Requisitos

- PHP 7.4 o posterior.
- Laravel 8 a 12.
- Una base de datos soportada por Laravel y una `APP_KEY` configurada.

## Instalación

```bash
composer require ljrodmar/enzona
php artisan vendor:publish --tag=enzona-config
php artisan vendor:publish --tag=enzona-migrations
php artisan migrate
```

Laravel descubre automáticamente el proveedor del paquete. La migración del paquete también se carga desde su directorio; publicarla permite mantenerla dentro de la aplicación.

## Credenciales y entorno

Configure el registro único `id = 1` desde la aplicación administrativa, un seeder protegido o Tinker:

```php
use Macroseso\Enzona\Models\EnzonaSetting;

$settings = EnzonaSetting::global();
$settings->updateCredentials($consumerKey, $consumerSecret);
$settings->changeEnvironment('sandbox');
```

Use `changeEnvironment('production')` para cambiar a producción. Los entornos permitidos son `sandbox` y `production`. Las credenciales se cifran con la clave Laravel (`APP_KEY`), se guardan en columnas `TEXT` y se excluyen de la serialización JSON del modelo. No las imprima ni las registre en logs. No configure credenciales o URLs de retorno en `.env`.

La pasarela comienza deshabilitada. Después de verificar las credenciales y el entorno, habilítela explícitamente:

```php
$settings->activate();
// $settings->deactivate(); // Para deshabilitarla
```

## URLs de retorno

El proveedor registra las rutas nombradas `enzona.return` y `enzona.cancel`:

```php
$returnUrl = route('enzona.return');
$cancelUrl = route('enzona.cancel');
```

También se pueden establecer URLs personalizadas:

```php
$settings->updateReturnUrls($returnUrl, $cancelUrl);
```

Si una URL no está configurada, se genera con la ruta nombrada correspondiente y se guarda al crear el siguiente pago. Los valores personalizados existentes no se reemplazan. Los únicos endpoints configurables del paquete están en `config/enzona.php`; el entorno, los datos de acceso y las URLs de retorno pertenecen al registro de base de datos.

## Crear un pago

Resuelva el cliente desde el contenedor de Laravel. La aplicación proporciona los datos comerciales; el paquete toma `return_url` y `cancel_url` de `enzona_settings` e ignora esos campos si vienen en los datos recibidos:

```php
use Macroseso\Enzona\Enzona;

$result = app(Enzona::class)->createPayment([
    'merchant_uuid' => 'merchant-uuid',
    'merchant_op_id' => 123456789123,
    'amount' => [
        'total' => 0.05,
        'details' => [
            'shipping' => 0.01,
            'discount' => 0.01,
            'tax' => 0.01,
            'tip' => 0.01,
        ],
    ],
    'description' => 'Compra en tienda',
    'currency' => 'CUP',
    'items' => [
        [
            'quantity' => 1,
            'price' => 0.03,
            'name' => 'Producto',
            'description' => 'Descripción del producto',
            'tax' => 0.01,
        ],
    ],
    'invoice_number' => 1212,
    'buyer_identity_code' => 'identificador-del-comprador',
    'terminal_id' => 12121,
]);
```

### Campos del pago

- `merchant_uuid`: identificador del comercio en EnZona.
- `merchant_op_id`: identificador de operación del comercio.
- `amount.total`: importe total numérico.
- `amount.details.shipping`, `discount`, `tax`, `tip`: importes numéricos del desglose.
- `description`: descripción del pago.
- `return_url` y `cancel_url`: los administra el paquete desde `enzona_settings`; no se toman del payload de la aplicación.
- `currency`: moneda admitida por la API.
- `items`: uno o más elementos con `quantity`, `price`, `name`, `description` y `tax`.
- `invoice_number`: número de factura.
- `buyer_identity_code`: identificador del comprador que requiere la API.
- `terminal_id`: identificador del terminal del comercio.

Los nombres y la estructura enviados coinciden con el payload de EnZona. No se inventan importes predeterminados; los valores numéricos se envían conservando su tipo.

## Respuesta y redirección al checkout

El resultado de `createPayment` contiene la respuesta completa de EnZona en `response`, además de los datos extraídos:

```php
$result['response'];       // Respuesta completa original
$result['transaction_uuid'];
$result['status_code'];
$result['status_denom'];
$result['checkout_url'];   // href del link rel=confirm y method=REDIRECT
```

La URL de checkout se extrae de los enlaces recibidos; nunca se construye manualmente. Si EnZona no devuelve el enlace `confirm` con método `REDIRECT`, `checkout_url` será `null`.

```php
if (!$result['checkout_url']) {
    throw new RuntimeException('EnZona no devolvió un enlace de checkout.');
}

return redirect()->away($result['checkout_url']);
```

## Retornos y lógica de la aplicación

`GET /enzona/return` y `GET /enzona/cancel` están disponibles con los nombres `enzona.return` y `enzona.cancel`. El controlador incluido solo responde `204 No Content`: no marca transacciones como pagadas ni modifica registros de la aplicación. La aplicación debe consultar a EnZona y aplicar su propia lógica.

Para implementar un controlador propio, publique `config/enzona.php`, desactive allí `register_routes` y registre rutas con los mismos nombres en la aplicación:

```php
Route::get('/enzona/return', [App\Http\Controllers\EnzonaCallbackController::class, 'return'])
    ->name('enzona.return');
Route::get('/enzona/cancel', [App\Http\Controllers\EnzonaCallbackController::class, 'cancel'])
    ->name('enzona.cancel');
```

La lógica de órdenes, facturas y pagos pertenece exclusivamente a la aplicación que instala el paquete. En particular, el retorno del navegador no confirma por sí solo el pago.

## Consultar estado, cancelar y completar

Consulte la transacción original antes de tomar decisiones sobre una orden:

```php
$transaction = app(Enzona::class)->getPayment($transactionUuid);
```

La respuesta es el arreglo original de EnZona, incluyendo el estado real de la transacción. La aplicación decide si y cuándo actualizar sus propios datos.

Para solicitar una cancelación:

```php
$cancellation = app(Enzona::class)->cancelPayment($transactionUuid);
```

`completePayment($transactionUuid)` también está disponible para la operación de completar expuesta por la API.

## Reembolsar

`refundPayment` recibe el UUID de la transacción original y el payload de reembolso. El total debe proporcionarse como cadena:

```php
$refund = app(Enzona::class)->refundPayment($transactionUuid, [
    'amount' => [
        'total' => '0.05',
    ],
    'commerce_refund_id' => 'refund-123',
    'username' => 'usuario-comercio',
    'description' => 'Reembolso solicitado por el comprador',
]);
```

El JSON conserva exactamente los campos y anidamiento requeridos: `amount.total`, `commerce_refund_id`, `username` y `description`. La respuesta original se devuelve directamente; un reembolso no requiere redirección del navegador.

## Autenticación, errores y seguridad

El paquete obtiene y reutiliza temporalmente el token OAuth según el tiempo de expiración informado por EnZona. Tras obtenerlo, actualiza `token_verified_at` y `token_expires_in`. Selecciona automáticamente los hosts de sandbox o producción según `enzona_settings.environment` y usa el cliente HTTP de Laravel con HTTPS y validación TLS habilitada.

Las operaciones lanzan `EnzonaException` si el gateway está deshabilitado o EnZona devuelve un error HTTP. `getHttpStatus()` devuelve el código HTTP remoto, si corresponde. Los payloads inválidos lanzan `InvalidArgumentException`. Maneje estas excepciones en su aplicación y no exponga credenciales, tokens ni respuestas sensibles al comprador.

La configuración publicable del paquete contiene solo opciones internas, como hosts oficiales, rutas de API, timeout, scope y middleware de callback. Las credenciales cifradas y las URLs del comercio no se guardan allí. Use HTTPS para las URLs de retorno y proteja las credenciales y la clave `APP_KEY`.

## Pruebas

```bash
composer test
```

La suite usa `Http::fake()` de Laravel y SQLite en memoria; no realiza solicitudes reales a EnZona.
