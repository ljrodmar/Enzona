<?php

namespace Macroseso\Enzona\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Macroseso\Enzona\Enzona;
use Macroseso\Enzona\Exceptions\EnzonaException;
use Macroseso\Enzona\Models\EnzonaSetting;
use Macroseso\Enzona\EnzonaServiceProvider;
use Orchestra\Testbench\TestCase;
use InvalidArgumentException;

class EnzonaTest extends TestCase
{
    private $settings;

    protected function getPackageProviders($app)
    {
        return [EnzonaServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('cache.default', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->artisan('migrate');
        $this->settings = EnzonaSetting::global();
        $this->settings->updateCredentials('test-consumer-key', 'test-consumer-secret');
        $this->settings->updateReturnUrls('https://merchant.test/return', 'https://merchant.test/cancel');
        $this->settings->activate();
    }

    public function test_credentials_are_encrypted_and_hidden_from_serialized_settings()
    {
        $stored = $this->app['db']->table('enzona_settings')->where('id', 1)->first();

        $this->assertNotSame('test-consumer-key', $stored->consumer_key);
        $this->assertNotSame('test-consumer-secret', $stored->consumer_secret);
        $this->assertSame('test-consumer-key', $this->settings->consumer_key);
        $this->assertArrayNotHasKey('consumer_key', $this->settings->toArray());
        $this->assertArrayNotHasKey('consumer_secret', $this->settings->toArray());
        $this->assertSame(1, EnzonaSetting::global()->id);
    }

    public function test_payment_uses_exact_payload_and_returns_checkout_metadata()
    {
        $this->fakeResponses([
            'transaction_uuid' => 'txn-123',
            'status_code' => 1,
            'status_denom' => 'PENDING',
            'links' => [
                ['rel' => 'self', 'method' => 'GET', 'href' => 'https://api.test/payment'],
                ['rel' => 'confirm', 'method' => 'REDIRECT', 'href' => 'https://checkout.enzona.test/pay'],
            ],
        ]);

        $input = $this->paymentData();
        $input['return_url'] = 'https://attacker.test/return';
        $input['cancel_url'] = 'https://attacker.test/cancel';
        $result = app(Enzona::class)->createPayment($input);

        $expected = $this->paymentData();
        $expected['return_url'] = 'https://merchant.test/return';
        $expected['cancel_url'] = 'https://merchant.test/cancel';
        Http::assertSent(function (Request $request) use ($expected) {
            if ($request->url() !== 'https://apisandbox.enzona.net/payment/v1.0.0/payments'
                || !$request->hasHeader('Authorization')) {
                return false;
            }

            $actual = $request->data();
            ksort($actual);
            ksort($expected);

            return $actual === $expected;
        });
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://apisandbox.enzona.net/token'
                && $request->method() === 'POST'
                && $request->isForm()
                && $request->data()['grant_type'] === 'client_credentials'
                && $request->hasHeader('Authorization');
        });

        $verifiedSettings = $this->settings->fresh();
        $this->assertNotNull($verifiedSettings->token_verified_at);
        $this->assertSame(3600, $verifiedSettings->token_expires_in);
        $this->assertSame('txn-123', $result['transaction_uuid']);
        $this->assertSame(1, $result['status_code']);
        $this->assertSame('PENDING', $result['status_denom']);
        $this->assertSame('https://checkout.enzona.test/pay', $result['checkout_url']);
        $this->assertSame('txn-123', $result['response']['transaction_uuid']);
    }

    public function test_missing_return_urls_are_generated_from_named_routes()
    {
        $this->settings->updateReturnUrls(null, null);
        $this->fakeResponses(['transaction_uuid' => 'txn-456']);

        app(Enzona::class)->createPayment($this->paymentData());

        $this->assertSame(route('enzona.return'), $this->settings->fresh()->return_url);
        $this->assertSame(route('enzona.cancel'), $this->settings->fresh()->cancel_url);
    }

    public function test_environment_selects_production_api_host()
    {
        $this->settings->changeEnvironment('production');
        $this->fakeResponses(['transaction_uuid' => 'txn-prod'], 'https://api.enzona.net');

        app(Enzona::class)->getPayment('txn-prod');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.enzona.net/payment/v1.0.0/payments/txn-prod';
        });
    }

    public function test_changing_credentials_or_environment_clears_token_verification_metadata()
    {
        $this->settings->markTokenVerified(3600);
        $this->settings->updateCredentials('rotated-consumer-key', 'rotated-consumer-secret');
        $this->assertNull($this->settings->fresh()->token_verified_at);
        $this->assertNull($this->settings->fresh()->token_expires_in);

        $this->settings->markTokenVerified(1800);
        $this->settings->changeEnvironment('production');

        $this->assertNull($this->settings->fresh()->token_verified_at);
        $this->assertNull($this->settings->fresh()->token_expires_in);
    }

    public function test_payment_status_query_returns_the_original_response()
    {
        $response = ['transaction_uuid' => 'txn-789', 'status_code' => 2, 'status_denom' => 'COMPLETED'];
        $this->fakeResponses($response);

        $this->assertSame($response, app(Enzona::class)->getPayment('txn-789'));
    }

    public function test_cancel_sends_request_to_the_transaction_cancel_endpoint()
    {
        $response = ['transaction_uuid' => 'txn-cancelled', 'status_denom' => 'CANCELLED'];
        $this->fakeResponses($response);

        $this->assertSame($response, app(Enzona::class)->cancelPayment('txn-cancelled'));
        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://apisandbox.enzona.net/payment/v1.0.0/payments/txn-cancelled/cancel';
        });
    }

    public function test_refund_sends_the_official_payload_and_returns_the_original_response()
    {
        $response = ['transaction_uuid' => 'refund-321', 'status_denom' => 'REFUNDED'];
        $this->fakeResponses($response);
        $refund = [
            'amount' => ['total' => '0.05'],
            'commerce_refund_id' => 'refund-order-123',
            'username' => 'merchant-user',
            'description' => 'Customer refund',
        ];

        $this->assertSame($response, app(Enzona::class)->refundPayment('txn-789', $refund));
        Http::assertSent(function (Request $request) use ($refund) {
            return $request->url() === 'https://apisandbox.enzona.net/payment/v1.0.0/payments/txn-789/refund'
                && $request->data() === $refund;
        });
    }

    public function test_return_and_cancel_routes_are_registered_without_business_logic()
    {
        $this->assertSame(204, $this->get(route('enzona.return'))->getStatusCode());
        $this->assertSame(204, $this->get(route('enzona.cancel'))->getStatusCode());
    }

    public function test_logo_asset_can_be_published()
    {
        $this->artisan('vendor:publish', [
            '--tag' => 'enzona-assets',
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertFileExists(public_path('vendor/enzona/enzona-logo.svg'));
    }

    public function test_http_errors_are_reported_with_the_status_code()
    {
        Http::fake([
            '*apisandbox.enzona.net/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            '*apisandbox.enzona.net/payment/*' => Http::response(['error' => 'unavailable'], 503),
            '*' => Http::response([], 500),
        ]);

        try {
            app(Enzona::class)->getPayment('txn-fail');
            $this->fail('Expected EnzonaException was not thrown.');
        } catch (EnzonaException $exception) {
            $this->assertSame(503, $exception->getHttpStatus());
        }
    }

    public function test_disabled_gateway_does_not_make_http_requests()
    {
        $this->settings->deactivate();
        Http::fake(['*' => Http::response([], 500)]);

        try {
            app(Enzona::class)->getPayment('txn-disabled');
            $this->fail('Expected EnzonaException was not thrown.');
        } catch (EnzonaException $exception) {
            $this->assertNull($exception->getHttpStatus());
        }

        Http::assertNothingSent();
    }

    public function test_invalid_payment_payload_is_rejected_before_an_http_request()
    {
        Http::fake(['*' => Http::response([], 500)]);
        $data = $this->paymentData();
        unset($data['amount']['details']['tip']);

        try {
            app(Enzona::class)->createPayment($data);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('amount.details.tip', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    private function fakeResponses(array $paymentResponse, $host = 'https://apisandbox.enzona.net')
    {
        Http::fake([
            $host . '/token' => Http::response([
                'access_token' => 'test-access-token',
                'expires_in' => 3600,
            ]),
            $host . '/payment/*' => Http::response($paymentResponse, 200),
            '*' => Http::response([], 500),
        ]);
    }

    private function paymentData(): array
    {
        return [
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
            'description' => 'Test payment',
            'currency' => 'CUP',
            'items' => [
                [
                    'quantity' => 1,
                    'price' => 0.03,
                    'name' => 'Product',
                    'description' => 'Product description',
                    'tax' => 0.01,
                ],
                [
                    'quantity' => 2,
                    'price' => 0.04,
                    'name' => 'Second product',
                    'description' => 'Second description',
                    'tax' => 0.00,
                ],
            ],
            'invoice_number' => 1212,
            'buyer_identity_code' => 'identity-code',
            'terminal_id' => 12121,
        ];
    }
}
