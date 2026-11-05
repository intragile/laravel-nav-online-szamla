<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

/**
 * Invoice line modification type.
 *
 * Generated from LineOperationType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
enum LineOperation: string
{
    case Create = 'CREATE';

    case Modify = 'MODIFY';
}
