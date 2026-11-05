<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Responses;

use IntrAgile\NavOnlineSzamla\Enums\BusinessResultCode;

final readonly class BusinessValidationMessage
{
    /**
     * @param  string|null  $tag  Pointer to the offending element.
     * @param  int|null  $line  Invoice line number the message refers to.
     */
    public function __construct(
        public BusinessResultCode $resultCode,
        public ?string $errorCode,
        public ?string $message,
        public ?string $tag = null,
        public ?string $value = null,
        public ?int $line = null,
        public ?string $originalInvoiceNumber = null,
    ) {}
}
