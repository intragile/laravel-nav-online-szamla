<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Events;

use Illuminate\Queue\SerializesModels;
use IntrAgile\NavOnlineSzamla\Models\NavTransaction;
use IntrAgile\NavOnlineSzamla\Responses\TransactionStatus;

/**
 * NAV finished processing every document of the transaction.
 */
final readonly class TransactionFinished
{
    use SerializesModels;

    public function __construct(
        public NavTransaction $transaction,
        public TransactionStatus $status,
    ) {}
}
