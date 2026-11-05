<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Support;

interface RequestIdGenerator
{
    /**
     * Return an ID matching [+a-zA-Z0-9_]{1,30}, unique per taxpayer.
     */
    public function generate(): string;
}
