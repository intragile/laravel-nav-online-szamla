<?php

declare(strict_types=1);

namespace Intragile\NavOnlineSzamla\Support;

use Illuminate\Support\Str;

/**
 * 26 karakteres ULID: időrendben rendezhető (naplóban kereshető) és ütközésmentes.
 * A NAV a sikeres, az INVALID_REQUEST_SIGNATURE és a FORBIDDEN kérések azonosítóját sem
 * engedi újra felhasználni, ezért újrapróbáláskor is mindig új azonosító kell.
 */
final class UlidRequestIdGenerator implements RequestIdGenerator
{
    public function generate(): string
    {
        return strtoupper((string) Str::ulid());
    }
}
