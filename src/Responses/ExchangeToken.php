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
     * Whether the token is still usable at the given moment, keeping a safety margin.
     */
    public function isValidAt(DateTimeInterface $moment, int $marginSeconds = 30): bool
    {
        return $moment >= $this->validFrom
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
