<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Enums;

/**
 * Lifecycle of a queued submission in the local transaction log.
 */
enum TransactionState: string
{
    /** Waiting for the submission job. */
    case Pending = 'pending';

    /** The request is being sent; NAV may or may not have received it yet. */
    case Sending = 'sending';

    /** The request was sent but the response was lost; reconciliation decides the outcome. */
    case Unconfirmed = 'unconfirmed';

    /** NAV returned a transaction ID and is processing the documents. */
    case Processing = 'processing';

    /** NAV processed every document; each item carries DONE or ABORTED. */
    case Finished = 'finished';

    /** Never accepted: rejected by client-side validation or by NAV before a transaction ID was issued. */
    case Rejected = 'rejected';

    /** Automatic handling gave up; the transaction needs manual attention. */
    case Abandoned = 'abandoned';

    public function isFinal(): bool
    {
        return in_array($this, [self::Finished, self::Rejected, self::Abandoned], true);
    }
}
