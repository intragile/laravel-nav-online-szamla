<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Events;

use Illuminate\Queue\SerializesModels;
use IntrAgile\NavOnlineSzamla\Models\NavTransaction;

/**
 * Reconciliation of an unconfirmed transaction finished: either NAV had received it, or it was queued for resubmission.
 */
final readonly class TransactionReconciled
{
    use SerializesModels;

    public function __construct(
        public NavTransaction $transaction,
        public bool $found,
    ) {}
}
