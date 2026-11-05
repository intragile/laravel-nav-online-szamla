<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Feature\Laravel;

use Illuminate\Support\Facades\Http;
use IntrAgile\NavOnlineSzamla\Config\Credentials;
use IntrAgile\NavOnlineSzamla\Exceptions\ConfigurationException;
use IntrAgile\NavOnlineSzamla\Facades\Nav;
use IntrAgile\NavOnlineSzamla\NavClient;
use IntrAgile\NavOnlineSzamla\NavManager;
use IntrAgile\NavOnlineSzamla\Tenancy\CredentialsResolver;

final class ContainerTest extends IntegrationTestCase
{
    public function test_nav_client_is_bound_to_the_default_taxpayer(): void
    {
        $client = $this->app->make(NavClient::class);

        $this->assertSame('12345678', $client->credentials->taxNumber);
        $this->assertSame('HU12345678-ABC1234', $client->software->id);
        $this->assertSame($client, $this->app->make(NavManager::class)->client());
    }

    public function test_tenants_resolve_their_own_credentials(): void
    {
        $this->assertSame('87654321', Nav::client('acme')->credentials->taxNumber);
        $this->assertSame('87654321', Nav::for('acme')->credentials->taxNumber);
        $this->assertSame(Nav::client('acme'), Nav::for('acme'));
    }

    public function test_unknown_tenant_fails_with_a_configuration_error(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unknown NAV tenant: nope');

        Nav::client('nope');
    }

    public function test_missing_software_id_fails_before_any_request(): void
    {
        config(['nav-online-szamla.software.id' => null]);
        Http::fake();

        try {
            Nav::client();
            $this->fail('Expected a configuration error.');
        } catch (ConfigurationException) {
            Http::assertNothingSent();
        }
    }

    public function test_a_custom_credentials_resolver_can_be_bound(): void
    {
        $this->app->instance(CredentialsResolver::class, new class implements CredentialsResolver
        {
            public function resolve(?string $tenant): Credentials
            {
                return new Credentials('customuser1', 'pw', '11111111', 'sign', 'abcdefghijklmnop');
            }
        });
        $this->app->forgetScopedInstances();

        $this->assertSame('11111111', Nav::client('anything')->credentials->taxNumber);
    }

    public function test_the_facade_forwards_operations_to_the_default_client(): void
    {
        Http::fake([self::URL.'/queryTaxpayer' => Http::response((string) file_get_contents(__DIR__.'/../../fixtures/nav-responses/queryTaxpayer.xml'))]);

        $taxpayer = Nav::queryTaxpayer('12345678-2-41');

        $this->assertNotNull($taxpayer);
        Http::assertSent(fn ($request) => str_contains($request->body(), '>'.self::LOGIN.'<'));
    }

    public function test_the_production_environment_uses_the_production_url(): void
    {
        config(['nav-online-szamla.environment' => 'production']);
        Http::fake(['https://api.onlineszamla.nav.gov.hu/invoiceService/v3/queryTaxpayer' => Http::response((string) file_get_contents(__DIR__.'/../../fixtures/nav-responses/queryTaxpayer.xml'))]);

        Nav::queryTaxpayer('12345678');

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.onlineszamla.nav.gov.hu/'));
    }

    public function test_an_unknown_environment_is_rejected(): void
    {
        config(['nav-online-szamla.environment' => 'staging']);

        $this->expectException(ConfigurationException::class);

        Nav::client();
    }
}
