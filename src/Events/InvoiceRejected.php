<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Events;

use Illuminate\Queue\SerializesModels;
use IntrAgile\NavOnlineSzamla\Models\NavTransactionItem;
use IntrAgile\NavOnlineSzamla\Responses\ProcessingResult;

/**
 * NAV processed the document with status ABORTED.
 */
final readonly class InvoiceRejected
{
    use SerializesModels;

    public function __construct(
        public NavTransactionItem $item,
        public ProcessingResult $result,
    ) {}
}
