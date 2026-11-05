<?php

declare(strict_types=1);

namespace Intragile\NavOnlineSzamla\Support;

interface RequestIdGenerator
{
    /** Pattern: [+a-zA-Z0-9_]{1,30}, adózónként egyedi. */
    public function generate(): string;
}
