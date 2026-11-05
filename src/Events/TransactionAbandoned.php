<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Events;

use Illuminate\Queue\SerializesModels;
use IntrAgile\NavOnlineSzamla\Models\NavTransaction;

/**
 * Automatic handling gave up; the transaction needs manual attention. The reason is one of the NavTransaction::ERROR_* codes.
 */
final readonly class TransactionAbandoned
{
    use SerializesModels;

    public function __construct(
        public NavTransaction $transaction,
        public string $reason,
    ) {}
}
