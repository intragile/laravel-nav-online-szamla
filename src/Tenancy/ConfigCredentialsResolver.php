<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tenancy;

use Illuminate\Contracts\Config\Repository;
use IntrAgile\NavOnlineSzamla\Config\Credentials;
use IntrAgile\NavOnlineSzamla\Exceptions\ConfigurationException;

/**
 * Reads the default taxpayer from "credentials" and every other tenant from "tenants.{key}".
 */
final readonly class ConfigCredentialsResolver implements CredentialsResolver
{
    public function __construct(private Repository $config) {}

    public function resolve(?string $tenant): Credentials
    {
        $key = $tenant === null ? 'nav-online-szamla.credentials' : 'nav-online-szamla.tenants.'.$tenant;
        $settings = $this->config->get($key);

        if (! is_array($settings)) {
            throw new ConfigurationException("Unknown NAV tenant: {$tenant}");
        }

        /** @var array<string, mixed> $settings */
        return Credentials::fromArray($settings);
    }
}
