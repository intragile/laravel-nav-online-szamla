<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Enums;

/**
 * Severity of a business validation message.
 */
enum BusinessResultCode: string
{
    case Error = 'ERROR';
    case Warn = 'WARN';
    case Info = 'INFO';
}
