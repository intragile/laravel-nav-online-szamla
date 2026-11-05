<?php

declare(strict_types=1);

namespace Intragile\NavOnlineSzamla\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * A NAV időbélyeg-formátumai. A `timestamp` tag és a requestSignature maszkja UGYANABBÓL a
 * pillanatból kell készüljön, ezért mindkettőt ez az osztály állítja elő, mindig UTC-ben.
 */
final class Timestamp
{
    /** A kérés `timestamp` tagja, pl. 2017-12-30T18:25:45.000Z */
    public static function header(DateTimeInterface $moment): string
    {
        return self::utc($moment)->format('Y-m-d\TH:i:s.v\Z');
    }

    /** A requestSignature-be kerülő maszk: YYYYMMDDhhmmss, UTC. */
    public static function signatureMask(DateTimeInterface $moment): string
    {
        return self::utc($moment)->format('YmdHis');
    }

    /** A jelen pillanat, ezredmásodpercre csonkolva (a tag pontossága). */
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
