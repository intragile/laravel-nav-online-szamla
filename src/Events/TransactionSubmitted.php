<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Events;

use Illuminate\Queue\SerializesModels;
use IntrAgile\NavOnlineSzamla\Models\NavTransaction;

/**
 * NAV accepted the request and issued a transaction ID; processing has started.
 */
final readonly class TransactionSubmitted
{
    use SerializesModels;

    public function __construct(
        public NavTransaction $transaction,
    ) {}
}
