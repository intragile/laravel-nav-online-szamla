<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Unit\Invoice;

use IntrAgile\NavOnlineSzamla\Enums\InvoiceCategory;
use IntrAgile\NavOnlineSzamla\Invoice\InvoiceBuilder;
use IntrAgile\NavOnlineSzamla\Invoice\LineItem;
use IntrAgile\NavOnlineSzamla\Invoice\Model\Invoice;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\Line;
use IntrAgile\NavOnlineSzamla\Invoice\Vat;
use IntrAgile\NavOnlineSzamla\Support\Decimal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Rebuilds every official sample from the inputs a user would enter (quantity, unit
 * price, VAT classification, exchange rates) and compares each computed amount with the
 * amounts NAV published in the sample.
 */
final class SampleRecalculationTest extends TestCase
{
    /**
     * Values in NAV's samples that contradict the sample's own arithmetic.
     */
    private const SAMPLE_ERRORS = [
        // Summary row: net 600000.00 + VAT 0 is stated as gross 500000.00; one HUF gross has an extra zero.
        'Belfoldi ertekesites tobb AFA tipus.xml' => ['rate[2] gross', 'rate[2] grossHUF', 'rate[5] grossHUF', 'gross total', 'gross total HUF'],
        // The 27 % lines sum to VAT 1304640.00, which the invoice VAT total also implies.
        'Gyujtoszamla 1.xml' => ['rate[1] vat', 'rate[1] vatHUF'],
        // Line 3: net 120.00 + VAT 0.00 is stated as gross 120.40.
        'Harmadik orszagbeli devizas szamla.xml' => ['line 3 gross', 'gross total'],
        'Tagorszagi devizas szamla.xml' => ['line 3 gross', 'gross total'],
        // Lines and the VAT rate summary give VAT 280800.00; the invoice total says 280000.00.
        'Termekdijas szamla.xml' => ['vat total', 'vat total HUF'],
        // The only line has gross 8000.00; the invoice total says 8000.40.
        'Uj kozlekedesi eszkoz export.xml' => ['gross total'],
    ];

    /**
     * Samples using lineOperation MODIFY without amounts, which NAV rejects since
     * application version 3.21 (INVALID_LINE_OPERATION).
     */
    private const OBSOLETE = ['Teteladatok modositasa.xml'];

    /**
     * @return iterable<string, array{string}>
     */
    public static function samples(): iterable
    {
        foreach (glob(dirname(__DIR__, 2).'/fixtures/nav-samples/*.xml') ?: [] as $file) {
            yield basename($file) => [$file];
        }
    }

    #[DataProvider('samples')]
    public function test_builder_reproduces_the_amounts_of_the_official_sample(string $file): void
    {
        if (in_array(basename($file), self::OBSOLETE, true)) {
            $this->markTestSkipped('Uses a line operation NAV no longer accepts.');
        }

        $data = InvoiceData::fromXmlString((string) file_get_contents($file));
        $invoices = $data->invoiceMain->invoice !== null
            ? [$data->invoiceMain->invoice]
            : array_map(static fn ($b): Invoice => $b->invoice, $data->invoiceMain->batchInvoice);

        $mismatches = [];
        foreach ($invoices as $original) {
            $rebuilt = self::rebuild($data, $original);
            $mismatches = [...$mismatches, ...self::compare($original, $rebuilt)];
        }

        $known = self::SAMPLE_ERRORS[basename($file)] ?? [];
        $unexpected = array_values(array_filter($mismatches, static fn (string $m): bool => ! in_array(explode(':', $m)[0], $known, true)));
        $this->assertSame([], $unexpected);

        // Every documented sample error must still show up, so the list cannot go stale.
        $reported = array_map(static fn (string $m): string => explode(':', $m)[0], $mismatches);
        $this->assertSame([], array_values(array_diff($known, $reported)));
    }

    private static function rebuild(InvoiceData $data, Invoice $original): Invoice
    {
        $detail = $original->invoiceHead->invoiceDetail;
        $reference = $original->invoiceReference;
        $builder = $reference === null
            ? InvoiceBuilder::create($data->invoiceNumber)
            : InvoiceBuilder::modify($data->invoiceNumber, $reference->originalInvoiceNumber, $reference->modificationIndex, $reference->modifyWithoutMaster);

        $builder->issueDate($data->invoiceIssueDate)
            ->deliveryDate($detail->invoiceDeliveryDate)
            ->category($detail->invoiceCategory)
            ->currency($detail->currencyCode, $detail->exchangeRate)
            ->appearance($detail->invoiceAppearance)
            ->supplier($original->invoiceHead->supplierInfo)
            ->amountScale(self::scaleOf($original))
            ->validateWith(null);

        foreach ($original->invoiceLines->line ?? [] as $line) {
            $builder->line(self::input($line, $detail->invoiceCategory));
        }

        return $builder->buildInvoice();
    }

    /**
     * Samples that round VAT to whole forints were issued with amount scale 0.
     */
    private static function scaleOf(Invoice $invoice): int
    {
        foreach ($invoice->invoiceLines->line ?? [] as $line) {
            $vat = $line->lineAmountsNormal?->lineVatData?->lineVatAmount;
            if ($vat !== null && ! $vat->equals($vat->round(0))) {
                return 2;
            }
        }

        return $invoice->invoiceHead->invoiceDetail->currencyCode === 'HUF' ? 0 : 2;
    }

    /**
     * Only what a user would type in; amounts are fixed only for lines without a unit price.
     */
    private static function input(Line $line, InvoiceCategory $category): LineItem
    {
        $item = LineItem::of($line->lineDescription, $line->lineNatureIndicator);
        $item->quantity = $line->quantity;
        $item->unit = $line->unitOfMeasure;
        $item->ownUnit = $line->unitOfMeasureOwn;
        $item->unitPrice = $line->unitPrice;
        $item->discount = $line->lineDiscountData;
        $item->vat = $line->lineAmountsNormal->lineVatRate ?? $line->lineAmountsSimplified?->lineVatRate;
        if ($line->lineModificationReference !== null) {
            $item->lineNumberReference($line->lineModificationReference->lineNumberReference);
        }
        if ($line->aggregateInvoiceLineData !== null) {
            $item->deliveredOn($line->aggregateInvoiceLineData->lineDeliveryDate, $line->aggregateInvoiceLineData->lineExchangeRate);
        }
        $payment = $line->advanceData?->advancePaymentData;
        if ($payment !== null) {
            $item->settlesAdvance($payment->advanceOriginalInvoice, $payment->advancePaymentDate, $payment->advanceExchangeRate);
        } elseif ($line->advanceData?->advanceIndicator === true) {
            $item->asAdvance();
        }

        if ($line->quantity === null || $line->unitPrice === null) {
            if ($category === InvoiceCategory::Simplified) {
                $item->grossAmount = $line->lineAmountsSimplified?->lineGrossAmountSimplified;
            } else {
                $item->netAmount = $line->lineAmountsNormal?->lineNetAmountData->lineNetAmount;
            }
        }

        return $item;
    }

    /**
     * @return list<string>
     */
    private static function compare(Invoice $expected, Invoice $actual): array
    {
        $out = [];
        $check = static function (string $what, ?Decimal $want, ?Decimal $got) use (&$out): void {
            if ($want !== null && ($got === null || ! $want->equals($got))) {
                $out[] = sprintf('%s: expected %s, got %s', $what, $want->value, $got?->value ?? 'null');
            }
        };

        foreach ($expected->invoiceLines->line ?? [] as $i => $want) {
            $got = $actual->invoiceLines?->line[$i] ?? null;
            $n = 'line '.$want->lineNumber;
            $check("$n unitPriceHUF", $want->unitPriceHUF, $got?->unitPriceHUF);
            if ($want->lineAmountsNormal !== null) {
                $w = $want->lineAmountsNormal;
                $g = $got?->lineAmountsNormal;
                $check("$n net", $w->lineNetAmountData->lineNetAmount, $g?->lineNetAmountData->lineNetAmount);
                $check("$n netHUF", $w->lineNetAmountData->lineNetAmountHUF, $g?->lineNetAmountData->lineNetAmountHUF);
                $check("$n vat", $w->lineVatData?->lineVatAmount, $g?->lineVatData?->lineVatAmount);
                $check("$n vatHUF", $w->lineVatData?->lineVatAmountHUF, $g?->lineVatData?->lineVatAmountHUF);
                $check("$n gross", $w->lineGrossAmountData?->lineGrossAmountNormal, $g?->lineGrossAmountData?->lineGrossAmountNormal);
                $check("$n grossHUF", $w->lineGrossAmountData?->lineGrossAmountNormalHUF, $g?->lineGrossAmountData?->lineGrossAmountNormalHUF);
            }
            if ($want->lineAmountsSimplified !== null) {
                $check("$n gross", $want->lineAmountsSimplified->lineGrossAmountSimplified, $got?->lineAmountsSimplified?->lineGrossAmountSimplified);
                $check("$n grossHUF", $want->lineAmountsSimplified->lineGrossAmountSimplifiedHUF, $got?->lineAmountsSimplified?->lineGrossAmountSimplifiedHUF);
            }
        }

        $w = $expected->invoiceSummary;
        $g = $actual->invoiceSummary;
        if ($w->summaryNormal !== null) {
            // NAV does not prescribe the order of VAT rate summaries; match them by classification.
            $byKey = [];
            foreach ($g->summaryNormal->summaryByVatRate ?? [] as $candidate) {
                $byKey[Vat::key($candidate->vatRate)] = $candidate;
            }
            foreach ($w->summaryNormal->summaryByVatRate as $i => $rate) {
                $other = $byKey[Vat::key($rate->vatRate)] ?? null;
                $check("rate[$i] net", $rate->vatRateNetData->vatRateNetAmount, $other?->vatRateNetData->vatRateNetAmount);
                $check("rate[$i] netHUF", $rate->vatRateNetData->vatRateNetAmountHUF, $other?->vatRateNetData->vatRateNetAmountHUF);
                $check("rate[$i] vat", $rate->vatRateVatData->vatRateVatAmount, $other?->vatRateVatData->vatRateVatAmount);
                $check("rate[$i] vatHUF", $rate->vatRateVatData->vatRateVatAmountHUF, $other?->vatRateVatData->vatRateVatAmountHUF);
                $check("rate[$i] gross", $rate->vatRateGrossData?->vatRateGrossAmount, $other?->vatRateGrossData?->vatRateGrossAmount);
                $check("rate[$i] grossHUF", $rate->vatRateGrossData?->vatRateGrossAmountHUF, $other?->vatRateGrossData?->vatRateGrossAmountHUF);
            }
            $check('net total', $w->summaryNormal->invoiceNetAmount, $g->summaryNormal?->invoiceNetAmount);
            $check('net total HUF', $w->summaryNormal->invoiceNetAmountHUF, $g->summaryNormal?->invoiceNetAmountHUF);
            $check('vat total', $w->summaryNormal->invoiceVatAmount, $g->summaryNormal?->invoiceVatAmount);
            $check('vat total HUF', $w->summaryNormal->invoiceVatAmountHUF, $g->summaryNormal?->invoiceVatAmountHUF);
        }
        foreach ($w->summarySimplified as $i => $rate) {
            $other = $g->summarySimplified[$i] ?? null;
            $check("simplified[$i] gross", $rate->vatContentGrossAmount, $other?->vatContentGrossAmount);
            $check("simplified[$i] grossHUF", $rate->vatContentGrossAmountHUF, $other?->vatContentGrossAmountHUF);
        }
        $check('gross total', $w->summaryGrossData?->invoiceGrossAmount, $g->summaryGrossData?->invoiceGrossAmount);
        $check('gross total HUF', $w->summaryGrossData?->invoiceGrossAmountHUF, $g->summaryGrossData?->invoiceGrossAmountHUF);

        return $out;
    }
}
