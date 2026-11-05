<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

/**
 * Indication of the nature of the supply of goods or services on a given line.
 *
 * Generated from LineNatureIndicatorType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
enum LineNatureIndicator: string
{
    /** Supply of goods. */
    case Product = 'PRODUCT';

    /** Supply of services. */
    case Service = 'SERVICE';

    /** Other, non-qualifiable. */
    case Other = 'OTHER';
}
