<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Responses;

use DateTimeImmutable;
use IntrAgile\NavOnlineSzamla\Enums\AnnulmentVerificationStatus;

final readonly class TransactionStatus
{
    /**
     * @param  list<ProcessingResult>  $results
     */
    public function __construct(
        public string $transactionId,
        public array $results,
        public ?string $originalRequestVersion = null,
        public ?AnnulmentVerificationStatus $annulmentStatus = null,
        public ?DateTimeImmutable $annulmentDecisionDate = null,
        public ?string $annulmentDecisionUser = null,
    ) {}

    /**
     * Whether every invoice of the transaction reached a final status.
     */
    public function isFinished(): bool
    {
        if ($this->results === []) {
            return false;
        }

        foreach ($this->results as $result) {
            if (! $result->status->isFinal()) {
                return false;
            }
        }

        return true;
    }

    public function result(int $index): ?ProcessingResult
    {
        foreach ($this->results as $result) {
            if ($result->index === $index) {
                return $result;
            }
        }

        return null;
    }
}
