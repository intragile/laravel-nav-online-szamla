<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * NAV timestamp formats, always in UTC.
 *
 * The header timestamp and the requestSignature mask must be derived from the same
 * instant, so both are produced here.
 */
final class Timestamp
{
    /**
     * Header timestamp, e.g. 2017-12-30T18:25:45.000Z.
     */
    public static function header(DateTimeInterface $moment): string
    {
        return self::utc($moment)->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * Signature mask, YYYYMMDDhhmmss in UTC.
     */
    public static function signatureMask(DateTimeInterface $moment): string
    {
        return self::utc($moment)->format('YmdHis');
    }

    /**
     * Current instant truncated to milliseconds, the precision of the header field.
     */
    public static function now(): DateTimeImmutable
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $now->setTime(
            (int) $now->format('H'),
            (int) $now->format('i'),
            (int) $now->format('s'),
            intdiv((int) $now->format('u'), 1000) * 1000,
        );
    }

    public static function utc(DateTimeInterface $moment): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone('UTC'));
    }
}
