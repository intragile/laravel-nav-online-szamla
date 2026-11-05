<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Events;

use Illuminate\Queue\SerializesModels;
use IntrAgile\NavOnlineSzamla\Models\NavTransaction;

/**
 * The transaction was never accepted: client-side validation or NAV rejected the request itself.
 *
 * The reason is in the transaction's error_code and error_message; client-side violations
 * are recorded on its items.
 */
final readonly class TransactionRejected
{
    use SerializesModels;

    public function __construct(
        public NavTransaction $transaction,
    ) {}
}
