<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Responses;

use DateTimeImmutable;
use IntrAgile\NavOnlineSzamla\Enums\Source;

final readonly class AuditData
{
    public function __construct(
        public DateTimeImmutable $insertedAt,
        public string $insertedBy,
        public Source $source,
        public ?string $transactionId,
        public ?int $index,
        public ?int $batchIndex,
        public string $originalRequestVersion,
    ) {}
}
