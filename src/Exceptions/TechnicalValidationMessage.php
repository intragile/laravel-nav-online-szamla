<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Exceptions;

/**
 * One entry of the technicalValidationMessages list returned in a GeneralErrorResponse.
 */
final readonly class TechnicalValidationMessage
{
    public function __construct(
        public string $resultCode,
        public ?string $errorCode,
        public ?string $message,
    ) {}
}
