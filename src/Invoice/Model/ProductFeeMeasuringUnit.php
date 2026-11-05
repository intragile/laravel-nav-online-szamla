<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

/**
 * Unit of the rate type.
 *
 * Generated from ProductFeeMeasuringUnitType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
enum ProductFeeMeasuringUnit: string
{
    /** Piece. */
    case Darab = 'DARAB';

    /** Kilogram. */
    case Kg = 'KG';
}
