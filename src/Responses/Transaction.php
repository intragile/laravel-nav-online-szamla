<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Responses;

use DateTimeImmutable;
use IntrAgile\NavOnlineSzamla\Enums\RequestStatus;
use IntrAgile\NavOnlineSzamla\Enums\Source;

/**
 * One transaction returned by queryTransactionList.
 */
final readonly class Transaction
{
    public function __construct(
        public string $transactionId,
        public DateTimeImmutable $insertedAt,
        public string $insertedBy,
        public Source $source,
        public RequestStatus $status,
        public bool $technicalAnnulment,
        public string $originalRequestVersion,
        public int $itemCount,
    ) {}
}
