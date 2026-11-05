<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

/**
 * Customers status type by VAT.
 *
 * Generated from CustomerVatStatusType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
enum CustomerVatStatus: string
{
    /** Domestic VAT subject. */
    case Domestic = 'DOMESTIC';

    /** Other (domestic non-VAT subject, non-natural person, foreign VAT subject and foreign non-VAT subject, non-natural person). */
    case Other = 'OTHER';

    /** Non-VAT subject (domestic or foreign) natural person. */
    case PrivatePerson = 'PRIVATE_PERSON';
}
