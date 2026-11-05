<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Responses;

/**
 * One page of a paginated NAV query.
 *
 * @template T
 */
final readonly class Page
{
    /**
     * @param  list<T>  $items
     */
    public function __construct(
        public int $currentPage,
        public int $availablePages,
        public array $items,
    ) {}

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->availablePages;
    }
}
