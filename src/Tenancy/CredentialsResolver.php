<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tenancy;

use IntrAgile\NavOnlineSzamla\Config\Credentials;
use IntrAgile\NavOnlineSzamla\Exceptions\ConfigurationException;

/**
 * Supplies the technical user of a taxpayer.
 *
 * Bind an implementation in the container to load credentials per tenant from any source.
 * The null tenant is the application's default taxpayer.
 */
interface CredentialsResolver
{
    /**
     * @throws ConfigurationException When the tenant is unknown or its credentials are incomplete.
     */
    public function resolve(?string $tenant): Credentials;
}
