<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

/**
 * Unit of measure type.
 *
 * Generated from UnitOfMeasureType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
enum UnitOfMeasure: string
{
    /** Piece. */
    case Piece = 'PIECE';

    /** Kilogram. */
    case Kilogram = 'KILOGRAM';

    /** Metric ton. */
    case Ton = 'TON';

    /** Kilowatt hour. */
    case Kwh = 'KWH';

    /** Day. */
    case Day = 'DAY';

    /** Hour. */
    case Hour = 'HOUR';

    /** Minute. */
    case Minute = 'MINUTE';

    /** Month. */
    case Month = 'MONTH';

    /** Liter. */
    case Liter = 'LITER';

    /** Kilometer. */
    case Kilometer = 'KILOMETER';

    /** Cubic meter. */
    case CubicMeter = 'CUBIC_METER';

    /** Meter. */
    case Meter = 'METER';

    /** Linear meter. */
    case LinearMeter = 'LINEAR_METER';

    /** Carton. */
    case Carton = 'CARTON';

    /** Pack. */
    case Pack = 'PACK';

    /** Own unit of measure. */
    case Own = 'OWN';
}
