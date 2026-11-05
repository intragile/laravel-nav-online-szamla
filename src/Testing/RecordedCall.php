<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Testing;

/**
 * One operation called on a faked NAV client.
 */
final readonly class RecordedCall
{
    /**
     * @param  list<mixed>  $arguments
     */
    public function __construct(
        public string $operation,
        public array $arguments,
        public ?string $tenant,
    ) {}
}
