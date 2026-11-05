<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

/**
 * Field type for inputting margin-scheme taxation.
 *
 * Generated from MarginSchemeType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
enum MarginScheme: string
{
    /** Travel agencies. */
    case TravelAgency = 'TRAVEL_AGENCY';

    /** Second-hand goods. */
    case SecondHand = 'SECOND_HAND';

    /** Works of art. */
    case Artwork = 'ARTWORK';

    /** Collector’s items and antiques. */
    case Antiques = 'ANTIQUES';
}
