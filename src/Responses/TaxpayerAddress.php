<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Responses;

use IntrAgile\NavOnlineSzamla\Enums\TaxpayerAddressType;

final readonly class TaxpayerAddress
{
    public function __construct(
        public TaxpayerAddressType $type,
        public string $countryCode,
        public ?string $region,
        public string $postalCode,
        public string $city,
        public string $streetName,
        public string $publicPlaceCategory,
        public ?string $number = null,
        public ?string $building = null,
        public ?string $staircase = null,
        public ?string $floor = null,
        public ?string $door = null,
        public ?string $lotNumber = null,
    ) {}
}
