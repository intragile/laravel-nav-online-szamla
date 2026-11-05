<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Console;

use Illuminate\Console\Command;
use IntrAgile\NavOnlineSzamla\Exceptions\NavApiException;
use IntrAgile\NavOnlineSzamla\Exceptions\NavException;
use IntrAgile\NavOnlineSzamla\NavManager;

final class TestConnectionCommand extends Command
{
    protected $signature = 'nav:test-connection {--tenant= : Tenant key; the default taxpayer when omitted}';

    protected $description = 'Check the NAV credentials with a token exchange and a taxpayer query';

    public function handle(NavManager $nav): int
    {
        $tenant = $this->option('tenant');
        $tenant = is_string($tenant) && $tenant !== '' ? $tenant : null;
        $environment = config('nav-online-szamla.environment');

        try {
            $client = $nav->client($tenant);

            $this->components->twoColumnDetail('Environment', is_string($environment) ? $environment : '');
            $this->components->twoColumnDetail('Technical user', $client->credentials->login);
            $this->components->twoColumnDetail('Tax number', $client->credentials->taxNumber);
            $this->components->twoColumnDetail('Software ID', $client->software->id);

            $token = $client->tokenExchange();
            $this->components->twoColumnDetail('Token exchange', '<fg=green>OK</>, valid until '.$token->validTo->format('Y-m-d H:i:s T'));

            $taxpayer = $client->queryTaxpayer($client->credentials->taxNumber);
            $this->components->twoColumnDetail('Taxpayer', $taxpayer === null ? '<fg=yellow>not found</>' : $taxpayer->name);
        } catch (NavApiException $e) {
            $this->components->error($e->getMessage());
            if ($e->isAuthenticationError()) {
                $this->components->warn('The credentials were rejected. Check the login, password, sign key and exchange key of the technical user, and that it belongs to this environment.');
            }

            return self::FAILURE;
        } catch (NavException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('The NAV connection works.');

        return self::SUCCESS;
    }
}
