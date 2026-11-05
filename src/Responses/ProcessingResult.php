<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Responses;

use IntrAgile\NavOnlineSzamla\Enums\BusinessResultCode;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceStatus;
use IntrAgile\NavOnlineSzamla\Exceptions\TechnicalValidationMessage;

/**
 * Processing outcome of one invoice within a transaction.
 */
final readonly class ProcessingResult
{
    /**
     * @param  list<TechnicalValidationMessage>  $technicalValidationMessages
     * @param  list<BusinessValidationMessage>  $businessValidationMessages
     * @param  string|null  $originalRequest  Decoded (and decompressed) submitted XML, if requested.
     */
    public function __construct(
        public int $index,
        public ?int $batchIndex,
        public InvoiceStatus $status,
        public array $technicalValidationMessages,
        public array $businessValidationMessages,
        public bool $compressedContent,
        public ?string $originalRequest = null,
    ) {}

    public function isAccepted(): bool
    {
        return $this->status === InvoiceStatus::Done;
    }

    public function isRejected(): bool
    {
        return $this->status === InvoiceStatus::Aborted;
    }

    /**
     * @return list<BusinessValidationMessage>
     */
    public function warnings(): array
    {
        return array_values(array_filter(
            $this->businessValidationMessages,
            static fn (BusinessValidationMessage $m): bool => $m->resultCode === BusinessResultCode::Warn,
        ));
    }

    /**
     * @return list<BusinessValidationMessage>
     */
    public function errors(): array
    {
        return array_values(array_filter(
            $this->businessValidationMessages,
            static fn (BusinessValidationMessage $m): bool => $m->resultCode === BusinessResultCode::Error,
        ));
    }
}
