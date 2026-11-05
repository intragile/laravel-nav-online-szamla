<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Config;

use IntrAgile\NavOnlineSzamla\Exceptions\ConfigurationException;

/**
 * The software block sent with every request (SoftwareType).
 */
final readonly class Software
{
    public function __construct(
        public string $id,
        public string $name,
        public string $operation,
        public string $mainVersion,
        public string $devName,
        public string $devContact,
        public ?string $devCountryCode = null,
        public ?string $devTaxNumber = null,
    ) {
        if (! preg_match('/^[0-9A-Z\-]{18}$/', $id)) {
            throw new ConfigurationException('The softwareId must be exactly 18 characters of [0-9A-Z-].');
        }
        if (! in_array($operation, ['LOCAL_SOFTWARE', 'ONLINE_SERVICE'], true)) {
            throw new ConfigurationException('The softwareOperation must be LOCAL_SOFTWARE or ONLINE_SERVICE.');
        }
        foreach (['name' => $name, 'mainVersion' => $mainVersion, 'devName' => $devName, 'devContact' => $devContact] as $field => $value) {
            if (trim($value) === '') {
                throw new ConfigurationException("Missing NAV setting: software.{$field}");
            }
        }
        if ($devCountryCode !== null && ! preg_match('/^[A-Z]{2}$/', $devCountryCode)) {
            throw new ConfigurationException('The softwareDevCountryCode must be a two-letter ISO country code.');
        }
    }

    /**
     * Generate a well-formed softwareId: "HU", the developer's 8-digit tax number, "-" and
     * 7 random characters.
     *
     * Generate it once and keep it in configuration: the ID identifies the software across
     * all of its installations.
     */
    public static function generateId(string $developerTaxNumber): string
    {
        $base = substr(preg_replace('/\D/', '', $developerTaxNumber) ?? '', 0, 8);
        if (strlen($base) !== 8) {
            throw new ConfigurationException('Generating a softwareId requires the first 8 digits of the developer tax number.');
        }

        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $suffix = '';
        for ($i = 0; $i < 7; $i++) {
            $suffix .= $alphabet[random_int(0, 35)];
        }

        return 'HU'.$base.'-'.$suffix;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $get = static function (string $key) use ($config): string {
            $value = $config[$key] ?? null;

            return is_scalar($value) ? (string) $value : '';
        };

        return new self(
            id: strtoupper($get('id')),
            name: $get('name'),
            operation: $get('operation') ?: 'ONLINE_SERVICE',
            mainVersion: $get('main_version'),
            devName: $get('dev_name'),
            devContact: $get('dev_contact'),
            devCountryCode: $get('dev_country_code') ?: null,
            devTaxNumber: $get('dev_tax_number') ?: null,
        );
    }
}
