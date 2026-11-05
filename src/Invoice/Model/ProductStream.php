<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

/**
 * Product stream.
 *
 * Generated from ProductStreamType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
enum ProductStream: string
{
    /** Battery. */
    case Battery = 'BATTERY';

    /** Packaging products. */
    case Packaging = 'PACKAGING';

    /** Other petroleum product. */
    case OtherPetrol = 'OTHER_PETROL';

    /** The electric appliance, electronic equipment. */
    case Electronic = 'ELECTRONIC';

    /** Tire. */
    case Tire = 'TIRE';

    /** Commercial printing paper. */
    case Commercial = 'COMMERCIAL';

    /** Other plastic product. */
    case Plastic = 'PLASTIC';

    /** Other chemical product. */
    case OtherChemical = 'OTHER_CHEMICAL';

    /** Paper stationery. */
    case Paper = 'PAPER';
}
