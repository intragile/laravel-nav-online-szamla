<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Enums;

/**
 * Processing status of one submitted invoice.
 */
enum InvoiceStatus: string
{
    case Received = 'RECEIVED';
    case Processing = 'PROCESSING';
    case Saved = 'SAVED';
    case Done = 'DONE';
    case Aborted = 'ABORTED';

    /**
     * Whether NAV has finished processing the invoice, successfully or not.
     */
    public function isFinal(): bool
    {
        return $this === self::Done || $this === self::Aborted;
    }
}
