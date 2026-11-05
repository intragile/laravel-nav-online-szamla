<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice;

use IntrAgile\NavOnlineSzamla\Invoice\Model\Line;
use IntrAgile\NavOnlineSzamla\Invoice\Model\VatRate;
use IntrAgile\NavOnlineSzamla\Support\Decimal;

/**
 * @internal A built line with the amounts that feed the invoice summary.
 */
final readonly class ComputedLine
{
    public function __construct(
        public Line $line,
        public VatRate $classification,
        public Decimal $net,
        public Decimal $netHuf,
        public Decimal $vat,
        public Decimal $vatHuf,
        public Decimal $gross,
        public Decimal $grossHuf,
    ) {}
}
