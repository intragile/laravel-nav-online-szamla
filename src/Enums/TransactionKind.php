<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Enums;

enum TransactionKind: string
{
    case Invoice = 'invoice';
    case Annulment = 'annulment';
}
