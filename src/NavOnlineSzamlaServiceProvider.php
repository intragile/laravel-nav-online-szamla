<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use IntrAgile\NavOnlineSzamla\Console\ResumeCommand;
use IntrAgile\NavOnlineSzamla\Console\SoftwareIdCommand;
use IntrAgile\NavOnlineSzamla\Console\StatusCommand;
use IntrAgile\NavOnlineSzamla\Console\TestConnectionCommand;
use IntrAgile\NavOnlineSzamla\Exceptions\ConfigurationException;
use IntrAgile\NavOnlineSzamla\Invoice\Validation\InvoiceValidator;
use IntrAgile\NavOnlineSzamla\Support\RequestIdGenerator;
use IntrAgile\NavOnlineSzamla\Support\UlidRequestIdGenerator;
use IntrAgile\NavOnlineSzamla\Tenancy\ConfigCredentialsResolver;
use IntrAgile\NavOnlineSzamla\Tenancy\CredentialsResolver;

final class NavOnlineSzamlaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nav-online-szamla.php', 'nav-online-szamla');

        $this->app->bindIf(RequestIdGenerator::class, UlidRequestIdGenerator::class);

        $this->app->bindIf(CredentialsResolver::class, static function (Application $app): CredentialsResolver {
            $class = $app->make(Repository::class)->get('nav-online-szamla.credentials_resolver') ?: ConfigCredentialsResolver::class;
            $resolver = $app->make(is_string($class) ? $class : ConfigCredentialsResolver::class);

            if (! $resolver instanceof CredentialsResolver) {
                throw new ConfigurationException('nav-online-szamla.credentials_resolver must implement '.CredentialsResolver::class.'.');
            }

            return $resolver;
        });

        // Scoped rather than singleton: under Octane, credentials resolved for one request
        // must not leak into the next.
        $this->app->scoped(NavManager::class, static fn (Application $app): NavManager => new NavManager(
            $app->make(Repository::class),
            $app->make(CredentialsResolver::class),
            $app->make(HttpFactory::class),
            $app->make(RequestIdGenerator::class),
            $app->make('log'),
        ));

        $this->app->bind(NavClient::class, static fn (Application $app): NavClient => $app->make(NavManager::class)->client());

        $this->app->bindIf(InvoiceValidator::class, static function (Application $app): InvoiceValidator {
            $skip = $app->make(Repository::class)->get('nav-online-szamla.validation.skip', []);

            return new InvoiceValidator(...array_values(array_filter(is_array($skip) ? $skip : [], 'is_string')));
        });
    }

    public function boot(): void
    {
        if ($this->app->make(Repository::class)->get('nav-online-szamla.transactions.run_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/nav-online-szamla.php' => config_path('nav-online-szamla.php'),
            ], 'nav-online-szamla-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'nav-online-szamla-migrations');

            $this->commands([
                SoftwareIdCommand::class,
                TestConnectionCommand::class,
                StatusCommand::class,
                ResumeCommand::class,
            ]);
        }
    }
}
