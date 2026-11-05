<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

/**
 * Product fee summary type.
 *
 * Generated from ProductFeeOperationType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
enum ProductFeeOperation: string
{
    /** Refund. */
    case Refund = 'REFUND';

    /** Deposit. */
    case Deposit = 'DEPOSIT';
}
