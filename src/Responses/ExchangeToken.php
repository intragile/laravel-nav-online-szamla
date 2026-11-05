<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Responses;

use DateTimeImmutable;
use DateTimeInterface;
use SensitiveParameter;

final readonly class ExchangeToken
{
    public function __construct(
        #[SensitiveParameter] public string $token,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validTo,
    ) {}

    /**
     * Whether the token is usable at the given moment, keeping a safety margin before expiry.
     *
     * The validity window comes from NAV's clock, so the same margin is tolerated before the
     * start: a freshly issued token often starts a few milliseconds "in the future".
     */
    public function isValidAt(DateTimeInterface $moment, int $marginSeconds = 30): bool
    {
        return $moment->getTimestamp() + $marginSeconds >= $this->validFrom->getTimestamp()
            && $moment->getTimestamp() + $marginSeconds < $this->validTo->getTimestamp();
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['token' => '***', 'validFrom' => $this->validFrom->format(DATE_ATOM), 'validTo' => $this->validTo->format(DATE_ATOM)];
    }
}
