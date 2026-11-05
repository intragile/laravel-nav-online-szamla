<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Enums;

/**
 * Processing status of a whole transaction.
 */
enum RequestStatus: string
{
    case Received = 'RECEIVED';
    case Processing = 'PROCESSING';
    case Saved = 'SAVED';
    case Finished = 'FINISHED';
    case Notified = 'NOTIFIED';

    public function isFinal(): bool
    {
        return $this === self::Finished || $this === self::Notified;
    }
}
