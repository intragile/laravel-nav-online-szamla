<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice;

use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;
use IntrAgile\NavOnlineSzamla\Invoice\Model\Line;

/**
 * @internal Turns a reported line into a line item that cancels it exactly.
 */
final class ReversedLine
{
    public static function of(Line $line): LineItem
    {
        $item = LineItem::of($line->lineDescription, $line->lineNatureIndicator);
        $item->unit = $line->unitOfMeasure;
        $item->ownUnit = $line->unitOfMeasureOwn;
        $item->quantity = $line->quantity?->negate();
        $item->unitPrice = $line->unitPrice;
        $item->discount = $line->lineDiscountData;
        $item->intermediatedService = $line->intermediatedService;
        $item->depositIndicator = $line->depositIndicator;
        $item->productCodes = $line->productCodes->productCode ?? [];
        $item->additionalData = $line->additionalLineData;

        if ($line->aggregateInvoiceLineData !== null) {
            $item->deliveryDate = $line->aggregateInvoiceLineData->lineDeliveryDate;
            $item->lineExchangeRate = $line->aggregateInvoiceLineData->lineExchangeRate;
        }

        if ($line->advanceData?->advanceIndicator === true) {
            $item->advance = true;
            $payment = $line->advanceData->advancePaymentData;
            if ($payment !== null) {
                $item->advanceOriginalInvoice = $payment->advanceOriginalInvoice;
                $item->advancePaymentDate = $payment->advancePaymentDate;
                $item->advanceExchangeRate = $payment->advanceExchangeRate;
            }
        }

        if ($line->lineAmountsNormal !== null) {
            $amounts = $line->lineAmountsNormal;
            $item->vat = $amounts->lineVatRate;
            $item->netAmount = $amounts->lineNetAmountData->lineNetAmount->negate();
            $item->netAmountHuf = $amounts->lineNetAmountData->lineNetAmountHUF->negate();
            $item->vatAmount = $amounts->lineVatData?->lineVatAmount->negate();
            $item->vatAmountHuf = $amounts->lineVatData?->lineVatAmountHUF->negate();
        } elseif ($line->lineAmountsSimplified !== null) {
            $amounts = $line->lineAmountsSimplified;
            $item->vat = $amounts->lineVatRate;
            $item->grossAmount = $amounts->lineGrossAmountSimplified->negate();
            $item->grossAmountHuf = $amounts->lineGrossAmountSimplifiedHUF->negate();
        } else {
            throw new InvoiceModelException(sprintf('Line %d has no amounts to reverse.', $line->lineNumber));
        }

        return $item;
    }
}
