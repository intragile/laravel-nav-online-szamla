<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Responses;

use DateTimeImmutable;
use IntrAgile\NavOnlineSzamla\Enums\Incorporation;
use IntrAgile\NavOnlineSzamla\Enums\TaxpayerAddressType;

final readonly class Taxpayer
{
    /**
     * @param  list<TaxpayerAddress>  $addresses
     */
    public function __construct(
        public bool $valid,
        public ?DateTimeImmutable $infoDate,
        public string $name,
        public ?string $shortName,
        public string $taxpayerId,
        public ?string $vatCode,
        public ?string $countyCode,
        public Incorporation $incorporation,
        public ?string $vatGroupMembership,
        public array $addresses,
    ) {}

    /**
     * Full tax number in the 12345678-1-12 format, when NAV returned all parts.
     */
    public function taxNumber(): string
    {
        return implode('-', array_filter([$this->taxpayerId, $this->vatCode, $this->countyCode], static fn (?string $p): bool => $p !== null));
    }

    public function headquarters(): ?TaxpayerAddress
    {
        foreach ($this->addresses as $address) {
            if ($address->type === TaxpayerAddressType::Hq) {
                return $address;
            }
        }

        return $this->addresses[0] ?? null;
    }
}
