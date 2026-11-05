<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Events;

use Illuminate\Queue\SerializesModels;
use IntrAgile\NavOnlineSzamla\Models\NavTransaction;

/**
 * The request was sent but its outcome is unknown; reconciliation will decide whether NAV received it.
 *
 * The cause is in the transaction's error_code and error_message.
 */
final readonly class TransactionUnconfirmed
{
    use SerializesModels;

    public function __construct(
        public NavTransaction $transaction,
    ) {}
}
