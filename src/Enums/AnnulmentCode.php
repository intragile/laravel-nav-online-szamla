<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Enums;

/**
 * Reason code of a technical annulment.
 */
enum AnnulmentCode: string
{
    case ErraticData = 'ERRATIC_DATA';
    case ErraticInvoiceNumber = 'ERRATIC_INVOICE_NUMBER';
    case ErraticInvoiceIssueDate = 'ERRATIC_INVOICE_ISSUE_DATE';
    case ErraticElectronicHashValue = 'ERRATIC_ELECTRONIC_HASH_VALUE';
}
