<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Unit\Invoice;

use IntrAgile\NavOnlineSzamla\Enums\InvoiceAppearance;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceCategory;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceOperation;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceValidationException;
use IntrAgile\NavOnlineSzamla\Invoice\BatchModification;
use IntrAgile\NavOnlineSzamla\Invoice\InvoiceBuilder;
use IntrAgile\NavOnlineSzamla\Invoice\LineItem;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\LineOperation;
use IntrAgile\NavOnlineSzamla\Invoice\Model\ProductCodeCategory;
use IntrAgile\NavOnlineSzamla\Invoice\Model\UnitOfMeasure;
use IntrAgile\NavOnlineSzamla\Invoice\Party;
use IntrAgile\NavOnlineSzamla\Invoice\Vat;
use IntrAgile\NavOnlineSzamla\Tests\Support\InvoiceFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InvoiceBuilderTest extends TestCase
{
    public function test_domestic_invoice_amounts_and_summary(): void
    {
        $data = InvoiceFactory::domestic()
            ->line(InvoiceFactory::product('2', '1000', 27))
            ->line(LineItem::service('Szállítás')->quantity(1, UnitOfMeasure::Piece)->unitPrice('500')->vat(Vat::rate(5)))
            ->line(InvoiceFactory::product('1', '333.33', 27))
            ->build();

        $invoice = $data->invoiceMain->invoice;
        $this->assertNotNull($invoice);
        $lines = $invoice->invoiceLines->line ?? [];
        $this->assertCount(3, $lines);
        $this->assertSame([1, 2, 3], array_map(static fn ($l): int => $l->lineNumber, $lines));
        $this->assertTrue($lines[0]->lineExpressionIndicator);
        $this->assertSame('2000.00', $lines[0]->lineAmountsNormal?->lineNetAmountData->lineNetAmount->value);
        $this->assertSame('540.00', $lines[0]->lineAmountsNormal?->lineVatData?->lineVatAmount->value);
        $this->assertSame('2540.00', $lines[0]->lineAmountsNormal?->lineGrossAmountData?->lineGrossAmountNormal->value);
        $this->assertSame('1000', $lines[0]->unitPriceHUF?->value);
        // 333.33 × 0.27 = 89.9991 → 90.00
        $this->assertSame('90.00', $lines[2]->lineAmountsNormal?->lineVatData?->lineVatAmount->value);

        $summary = $invoice->invoiceSummary->summaryNormal;
        $this->assertNotNull($summary);
        $this->assertCount(2, $summary->summaryByVatRate);
        $this->assertSame('2333.33', $summary->summaryByVatRate[0]->vatRateNetData->vatRateNetAmount->value);
        $this->assertSame('630.00', $summary->summaryByVatRate[0]->vatRateVatData->vatRateVatAmount->value);
        $this->assertSame('2833.33', $summary->invoiceNetAmount->value);
        $this->assertSame('655.00', $summary->invoiceVatAmount->value);
        $this->assertSame('3488.33', $invoice->invoiceSummary->summaryGrossData?->invoiceGrossAmount->value);
        $this->assertSame('3488.33', $invoice->invoiceSummary->summaryGrossData?->invoiceGrossAmountHUF->value);
    }

    public function test_rounds_half_away_from_zero(): void
    {
        // 3 × 33.335 = 100.005 → 100.01; VAT 27.0027 → 27.00. Negative line mirrors it.
        $data = InvoiceFactory::domestic()
            ->line(InvoiceFactory::product('3', '33.335'))
            ->line(InvoiceFactory::product('-3', '33.335'))
            ->build();

        $lines = $data->invoiceMain->invoice?->invoiceLines->line ?? [];
        $this->assertSame('100.01', $lines[0]->lineAmountsNormal?->lineNetAmountData->lineNetAmount->value);
        $this->assertSame('27.00', $lines[0]->lineAmountsNormal?->lineVatData?->lineVatAmount->value);
        $this->assertSame('-100.01', $lines[1]->lineAmountsNormal?->lineNetAmountData->lineNetAmount->value);
        $this->assertSame('-27.00', $lines[1]->lineAmountsNormal?->lineVatData?->lineVatAmount->value);
        $this->assertTrue($data->invoiceMain->invoice?->invoiceSummary->summaryGrossData?->invoiceGrossAmount->isZero());
    }

    public function test_whole_forint_amounts(): void
    {
        $data = InvoiceFactory::domestic()->amountScale(0)->line(InvoiceFactory::product('1', '999'))->build();

        $line = $data->invoiceMain->invoice?->invoiceLines->line[0];
        $this->assertSame('999', $line?->lineAmountsNormal?->lineNetAmountData->lineNetAmount->value);
        $this->assertSame('270', $line?->lineAmountsNormal?->lineVatData?->lineVatAmount->value);
    }

    public function test_foreign_currency_converts_to_huf_per_line(): void
    {
        $data = InvoiceFactory::domestic()
            ->currency('eur', '392.17')
            ->line(InvoiceFactory::product('3', '12.99'))
            ->build();

        $invoice = $data->invoiceMain->invoice;
        $this->assertSame('EUR', $invoice?->invoiceHead->invoiceDetail->currencyCode);
        $amounts = $invoice?->invoiceLines?->line[0]->lineAmountsNormal;
        $this->assertSame('38.97', $amounts?->lineNetAmountData->lineNetAmount->value);
        // 38.97 × 392.17 = 15282.8649 → 15282.86; VAT 10.52 × 392.17 = 4125.6284 → 4125.63
        $this->assertSame('15282.86', $amounts?->lineNetAmountData->lineNetAmountHUF->value);
        $this->assertSame('10.52', $amounts?->lineVatData?->lineVatAmount->value);
        $this->assertSame('4125.63', $amounts?->lineVatData?->lineVatAmountHUF->value);
        $this->assertSame('19408.49', $amounts?->lineGrossAmountData?->lineGrossAmountNormalHUF->value);
        $this->assertSame('5094.29', $invoice?->invoiceLines?->line[0]->unitPriceHUF?->value);
    }

    public function test_simplified_invoice_uses_vat_content_and_gross_amounts(): void
    {
        $data = InvoiceFactory::domestic()
            ->simplified()
            ->customer(Party::privatePerson())
            ->line(InvoiceFactory::product('2', '1270', 27))
            ->line(InvoiceFactory::product('1', '105', 5))
            ->build();

        $invoice = $data->invoiceMain->invoice;
        $this->assertSame(InvoiceCategory::Simplified, $invoice?->invoiceHead->invoiceDetail->invoiceCategory);
        $line = $invoice?->invoiceLines?->line[0];
        $this->assertNull($line?->lineAmountsNormal);
        $this->assertSame('0.2126', $line?->lineAmountsSimplified?->lineVatRate->vatContent?->value);
        $this->assertSame('2540.00', $line?->lineAmountsSimplified?->lineGrossAmountSimplified->value);
        $this->assertCount(2, $invoice->invoiceSummary->summarySimplified ?? []);
        $this->assertSame('0.0476', $invoice?->invoiceSummary->summarySimplified[1]->vatRate->vatContent?->value);
        $this->assertSame('2645.00', $invoice?->invoiceSummary->summaryGrossData?->invoiceGrossAmount->value);
        $this->assertNull($invoice?->invoiceSummary->summaryNormal);
    }

    public function test_aggregate_invoice_converts_each_line_at_its_own_rate(): void
    {
        $data = InvoiceFactory::domestic()
            ->aggregate()
            ->currency('EUR', 390)
            ->line(InvoiceFactory::product('1', '100')->deliveredOn('2026-10-01', 380))
            ->line(InvoiceFactory::product('1', '100')->deliveredOn('2026-10-15', 400))
            ->build();

        $lines = $data->invoiceMain->invoice?->invoiceLines->line ?? [];
        $this->assertSame('38000.00', $lines[0]->lineAmountsNormal?->lineNetAmountData->lineNetAmountHUF->value);
        $this->assertSame('40000.00', $lines[1]->lineAmountsNormal?->lineNetAmountData->lineNetAmountHUF->value);
        $this->assertSame('2026-10-15', $lines[1]->aggregateInvoiceLineData?->lineDeliveryDate->format('Y-m-d'));
        $this->assertSame('78000.00', $data->invoiceMain->invoice?->invoiceSummary->summaryNormal?->invoiceNetAmountHUF->value);
    }

    public function test_exempt_and_reverse_charge_lines_carry_zero_vat(): void
    {
        $data = InvoiceFactory::domestic()
            ->line(InvoiceFactory::product()->vat(Vat::exempt('AAM', 'Alanyi adómentes')))
            ->line(InvoiceFactory::product()->vat(Vat::domesticReverseCharge()))
            ->build();

        $summary = $data->invoiceMain->invoice?->invoiceSummary->summaryNormal;
        $this->assertCount(2, $summary->summaryByVatRate ?? []);
        $this->assertTrue($summary?->invoiceVatAmount->isZero());
        $this->assertSame('4000.00', $summary?->invoiceNetAmount->value);
    }

    public function test_discount_reduces_the_net_amount(): void
    {
        $data = InvoiceFactory::domestic()
            ->line(InvoiceFactory::product('10', '100')->discount('Akció', rate: '0.1'))
            ->line(InvoiceFactory::product('10', '100')->discount('Árengedmény', value: '150'))
            ->build();

        $lines = $data->invoiceMain->invoice?->invoiceLines->line ?? [];
        $this->assertSame('900.00', $lines[0]->lineAmountsNormal?->lineNetAmountData->lineNetAmount->value);
        $this->assertSame('850.00', $lines[1]->lineAmountsNormal?->lineNetAmountData->lineNetAmount->value);
    }

    public function test_line_without_natural_units_uses_a_fixed_amount(): void
    {
        $data = InvoiceFactory::domestic()
            ->line(LineItem::service('Tanácsadás november')->netAmount('150000')->vat(Vat::rate(27))->productCode(ProductCodeCategory::Szj, '7022'))
            ->build();

        $line = $data->invoiceMain->invoice?->invoiceLines->line[0];
        $this->assertFalse($line?->lineExpressionIndicator);
        $this->assertSame('40500.00', $line?->lineAmountsNormal?->lineVatData?->lineVatAmount->value);
        $this->assertSame('7022', $line?->productCodes?->productCode[0]->productCodeValue);
    }

    public function test_final_invoice_settles_advance_at_the_invoice_rate(): void
    {
        $data = InvoiceFactory::domestic()
            ->currency('EUR', 343)
            ->line(InvoiceFactory::product('1', '16000'))
            ->line(LineItem::product('Előleg beszámítása')->netAmount('-7000')->vat(Vat::rate(27))->settlesAdvance('ESZ-01', '2026-10-02', 340))
            ->build();

        $line = $data->invoiceMain->invoice?->invoiceLines->line[1];
        $this->assertSame('-2401000.00', $line?->lineAmountsNormal?->lineNetAmountData->lineNetAmountHUF->value);
        $this->assertSame('340', $line?->advanceData?->advancePaymentData?->advanceExchangeRate->value);
    }

    public function test_modification_references_lines_after_the_existing_ones(): void
    {
        $builder = InvoiceBuilder::modify('INV-1-M1', 'INV-1', 1)
            ->issueDate('2026-11-06')
            ->appearance(InvoiceAppearance::Electronic)
            ->supplier(InvoiceFactory::domestic()->buildInvoice()->invoiceHead->supplierInfo)
            ->previousLineCount(3)
            ->line(InvoiceFactory::product('-1', '1000'), InvoiceFactory::product('1', '900'));

        $data = $builder->build();
        $lines = $data->invoiceMain->invoice?->invoiceLines->line ?? [];
        $this->assertSame(InvoiceOperation::Modify, $builder->operation());
        $this->assertSame([4, 5], array_map(static fn ($l): ?int => $l->lineModificationReference?->lineNumberReference, $lines));
        $this->assertSame(LineOperation::Create, $lines[0]->lineModificationReference?->lineOperation);
        $this->assertSame('INV-1', $data->invoiceMain->invoice?->invoiceReference?->originalInvoiceNumber);
    }

    public function test_modification_requires_line_numbering_base(): void
    {
        $this->expectException(InvoiceModelException::class);
        $this->expectExceptionMessage('previousLineCount');

        InvoiceBuilder::modify('INV-1-M1', 'INV-1', 1)
            ->issueDate('2026-11-06')
            ->appearance(InvoiceAppearance::Electronic)
            ->supplier(InvoiceFactory::domestic()->buildInvoice()->invoiceHead->supplierInfo)
            ->line(InvoiceFactory::product())
            ->build();
    }

    public function test_modification_without_lines_reports_a_zero_27_percent_summary(): void
    {
        $data = InvoiceBuilder::modify('INV-1-M1', 'INV-1', 1)
            ->issueDate('2026-11-06')
            ->appearance(InvoiceAppearance::Electronic)
            ->supplier(InvoiceFactory::domestic()->buildInvoice()->invoiceHead->supplierInfo)
            ->build();

        $summary = $data->invoiceMain->invoice?->invoiceSummary->summaryNormal;
        $this->assertNull($data->invoiceMain->invoice?->invoiceLines);
        $this->assertSame('0.27', $summary?->summaryByVatRate[0]->vatRate->vatPercentage?->value);
        $this->assertTrue($summary?->invoiceNetAmount->isZero());
    }

    public function test_storno_of_reverses_every_amount_exactly(): void
    {
        $original = InvoiceFactory::domestic()
            ->currency('EUR', '392.17')
            ->line(InvoiceFactory::product('3', '12.99'))
            ->line(InvoiceFactory::product('1', '7.5', 5)->discount(value: '0.5'))
            ->build();

        $storno = InvoiceBuilder::stornoOf($original, 'INV-1-S', 1, '2026-11-07');
        $data = $storno->build();

        $this->assertSame(InvoiceOperation::Storno, $storno->operation());
        $a = $original->invoiceMain->invoice;
        $b = $data->invoiceMain->invoice;
        $this->assertSame('EUR', $b?->invoiceHead->invoiceDetail->currencyCode);
        $this->assertSame([3, 4], array_map(static fn ($l): ?int => $l->lineModificationReference?->lineNumberReference, $b->invoiceLines->line ?? []));
        foreach ($a->invoiceLines->line ?? [] as $i => $line) {
            $reversed = $b?->invoiceLines?->line[$i];
            $this->assertTrue($line->lineAmountsNormal?->lineNetAmountData->lineNetAmountHUF->add($reversed?->lineAmountsNormal?->lineNetAmountData->lineNetAmountHUF ?? '0')->isZero());
            $this->assertTrue($line->quantity?->add($reversed->quantity ?? '0')->isZero());
        }
        $this->assertTrue($a?->invoiceSummary->summaryGrossData?->invoiceGrossAmountHUF->add($b?->invoiceSummary->summaryGrossData?->invoiceGrossAmountHUF ?? '0')->isZero());
        $this->assertSame('-15282.86', $b?->invoiceSummary->summaryNormal?->summaryByVatRate[0]->vatRateNetData->vatRateNetAmountHUF->value);
    }

    public function test_build_runs_the_business_validator(): void
    {
        try {
            InvoiceFactory::domestic()->customer(Party::otherCustomer('X', Party::address('HU', '1011', 'Budapest', 'Fő utca 1.')))
                ->line(InvoiceFactory::product()->vat(Vat::domesticReverseCharge()))
                ->build();
            $this->fail('Expected a validation error.');
        } catch (InvoiceValidationException $e) {
            $this->assertContains('INCORRECT_LINE_DATA_VAT_DOMESTIC_REVERSE_CHARGE', $e->codes());
            $this->assertContains('DOMESTIC_TAXNUMBER_EXPECTED_REVERSE_CHARGE', $e->codes());
        }
    }

    public function test_build_requires_appearance(): void
    {
        $this->expectException(InvoiceModelException::class);
        $this->expectExceptionMessage('appearance');

        InvoiceBuilder::create('X')->issueDate('2026-11-05')
            ->supplier(InvoiceFactory::domestic()->buildInvoice()->invoiceHead->supplierInfo)
            ->line(InvoiceFactory::product())
            ->build();
    }

    public function test_submission_carries_operation_and_round_trippable_xml(): void
    {
        $submission = InvoiceFactory::domestic()->line(InvoiceFactory::product())->submission();

        $this->assertSame(InvoiceOperation::Create, $submission->operation);
        $this->assertSame('INV-1', InvoiceData::fromXmlString($submission->invoiceXml)->invoiceNumber);
    }

    public function test_batch_modification(): void
    {
        $supplier = InvoiceFactory::domestic()->buildInvoice()->invoiceHead->supplierInfo;
        $modify = static fn (string $original): InvoiceBuilder => InvoiceBuilder::modify('BATCH-1', $original, 1)
            ->appearance(InvoiceAppearance::Electronic)
            ->supplier($supplier)
            ->previousLineCount(1)
            ->line(InvoiceFactory::product('-1', '100'));

        $batch = BatchModification::of('BATCH-1', '2026-11-06')->add($modify('INV-1'))->add($modify('INV-2'));
        $data = $batch->build();

        $this->assertSame(InvoiceOperation::Modify, $batch->operation());
        $this->assertSame([1, 2], array_map(static fn ($b): int => $b->batchIndex, $data->invoiceMain->batchInvoice));
        $this->assertSame('INV-2', $data->invoiceMain->batchInvoice[1]->invoice->invoiceReference?->originalInvoiceNumber);
    }

    public function test_party_parses_tax_numbers(): void
    {
        $this->assertSame(['12345678', '2', '41'], array_values((array) Party::taxNumber('12345678-2-41')));
        $this->assertSame(['12345678', '2', '41'], array_values((array) Party::taxNumber('12345678241')));
        $this->assertSame(['12345678', null, null], array_values((array) Party::taxNumber('12345678')));

        $this->expectException(InvalidArgumentException::class);
        Party::taxNumber('1234-5678');
    }

    public function test_vat_factories(): void
    {
        $this->assertSame('0.27', Vat::rate(27)->vatPercentage?->value);
        $this->assertSame('0.27', Vat::rate('0.27')->vatPercentage?->value);
        $this->assertSame('0.1525', Vat::contentForRate(18)->vatContent?->value);
        $this->assertSame('exemption:AAM', Vat::key(Vat::exempt('AAM', 'a')));
        $this->assertSame(Vat::key(Vat::exempt('AAM', 'a')), Vat::key(Vat::exempt('AAM', 'b')));

        $this->expectException(InvalidArgumentException::class);
        Vat::contentForRate(12);
    }
}
