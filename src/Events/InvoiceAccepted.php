<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Events;

use Illuminate\Queue\SerializesModels;
use IntrAgile\NavOnlineSzamla\Models\NavTransactionItem;
use IntrAgile\NavOnlineSzamla\Responses\ProcessingResult;

/**
 * NAV processed the document with status DONE. For an annulment this means the request awaits approval on the Online Számla web interface.
 */
final readonly class InvoiceAccepted
{
    use SerializesModels;

    public function __construct(
        public NavTransactionItem $item,
        public ProcessingResult $result,
    ) {}
}
