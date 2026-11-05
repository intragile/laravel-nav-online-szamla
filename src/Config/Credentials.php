<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Config;

use IntrAgile\NavOnlineSzamla\Exceptions\ConfigurationException;
use SensitiveParameter;

/**
 * Technical user of one taxpayer. Secrets are masked in debug output.
 */
final readonly class Credentials
{
    public function __construct(
        public string $login,
        #[SensitiveParameter] public string $password,
        public string $taxNumber,
        #[SensitiveParameter] public string $signKey,
        #[SensitiveParameter] public string $exchangeKey,
    ) {
        if (! preg_match('/^[a-zA-Z0-9]{6,15}$/', $login)) {
            throw new ConfigurationException('The NAV login must be 6-15 alphanumeric characters.');
        }
        if (! preg_match('/^\d{8}$/', $taxNumber)) {
            throw new ConfigurationException('The NAV tax number must be the first 8 digits of the Hungarian tax number.');
        }
        if ($password === '' || $signKey === '') {
            throw new ConfigurationException('The NAV password and sign key must not be empty.');
        }
        if (strlen($exchangeKey) !== 16) {
            throw new ConfigurationException('The NAV exchange key must be exactly 16 characters.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        foreach (['login', 'password', 'tax_number', 'sign_key', 'exchange_key'] as $key) {
            if (! isset($config[$key]) || ! is_string($config[$key]) || $config[$key] === '') {
                throw new ConfigurationException("Missing NAV setting: credentials.{$key}");
            }
        }

        /** @var array{login: string, password: string, tax_number: string, sign_key: string, exchange_key: string} $config */
        return new self(
            $config['login'],
            $config['password'],
            substr(preg_replace('/\D/', '', $config['tax_number']) ?? '', 0, 8),
            $config['sign_key'],
            $config['exchange_key'],
        );
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['login' => $this->login, 'taxNumber' => $this->taxNumber, 'password' => '***', 'signKey' => '***', 'exchangeKey' => '***'];
    }
}
