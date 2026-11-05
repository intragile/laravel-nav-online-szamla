<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Enums;

/**
 * Channel through which the data reached NAV.
 */
enum Source: string
{
    case Web = 'WEB';
    case Xml = 'XML';
    case Mgm = 'MGM';
    case Opg = 'OPG';
    case Osz = 'OSZ';
}
