<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Support;

use Illuminate\Support\Str;

/**
 * Time-ordered, collision-free request IDs (26-character ULIDs).
 */
final class UlidRequestIdGenerator implements RequestIdGenerator
{
    public function generate(): string
    {
        return strtoupper((string) Str::ulid());
    }
}
