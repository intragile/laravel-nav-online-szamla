<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Feature\Laravel;

use IntrAgile\NavOnlineSzamla\Enums\InvoiceAppearance;
use IntrAgile\NavOnlineSzamla\Enums\PaymentMethod;
use IntrAgile\NavOnlineSzamla\Enums\TransactionState;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceValidationException;
use IntrAgile\NavOnlineSzamla\Facades\Nav;
use IntrAgile\NavOnlineSzamla\Invoice\InvoiceBuilder;
use IntrAgile\NavOnlineSzamla\Invoice\LineItem;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\UnitOfMeasure;
use IntrAgile\NavOnlineSzamla\Invoice\Party;
use IntrAgile\NavOnlineSzamla\Invoice\Validation\InvoiceValidator;
use IntrAgile\NavOnlineSzamla\Invoice\Vat;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceNumberQuery;

/**
 * The code examples of README.md and README.hu.md, kept runnable.
 */
final class DocumentationExamplesTest extends IntegrationTestCase
{
    private function readmeInvoice(): InvoiceBuilder
    {
        return InvoiceBuilder::create('INV-2026-0001')
            ->issueDate('2026-11-05')
            ->deliveryDate('2026-11-05')
            ->payment(PaymentMethod::Transfer, '2026-11-20')
            ->appearance(InvoiceAppearance::Electronic)
            ->supplier(Party::supplier(
                '12345678-2-41',
                'Eladó Kft.',
                Party::address('HU', '1011', 'Budapest', 'Fő utca 1.'),
                '11111111-22222222-33333333',
            ))
            ->customer(Party::domesticCustomer(
                '87654321-2-13',
                'Vevő Kft.',
                Party::address('HU', '2000', 'Szentendre', 'Kossuth tér 2.'),
            ))
            ->line(LineItem::product('Laptop')->quantity(2, UnitOfMeasure::Piece)->unitPrice('250000')->vat(Vat::rate(27)))
            ->line(LineItem::product('Könyv')->quantity(3, UnitOfMeasure::Piece)->unitPrice('4990')->vat(Vat::rate(5)))
            ->line(LineItem::service('Kiszállítás')->netAmount('2500')->vat(Vat::rate(27)));
    }

    public function test_builder_computes_the_summary(): void
    {
        $invoice = $this->readmeInvoice()->build(now());

        $summary = $invoice->invoiceMain->invoice?->invoiceSummary->summaryNormal;
        $this->assertSame('517470.00', (string) $summary?->invoiceNetAmount);
        $this->assertSame('136423.50', (string) $summary?->invoiceVatAmount);
    }

    public function test_queued_submission(): void
    {
        config(['queue.default' => 'sync']);
        Nav::fake();

        $transaction = Nav::submit($this->readmeInvoice());

        $this->assertSame(TransactionState::Finished, $transaction->refresh()->state);
        $this->assertTrue($transaction->isAccepted());
    }

    public function test_direct_submission_and_storno(): void
    {
        Nav::fake();

        $transactionId = Nav::manageInvoice($this->readmeInvoice()->submission());
        $status = Nav::queryTransactionStatus($transactionId);
        $this->assertTrue($status->isFinished());

        $original = Nav::queryInvoiceData(InvoiceNumberQuery::outbound('INV-2026-0001'))?->invoice();
        $this->assertInstanceOf(InvoiceData::class, $original);

        $storno = InvoiceBuilder::stornoOf($original, 'INV-2026-0001-S', modificationIndex: 1, issueDate: '2026-11-05');
        Nav::submit($storno);

        Nav::assertInvoiceSubmitted('INV-2026-0001-S');
        Nav::assertInvoiceSubmitted(fn (InvoiceData $invoice) => $invoice->invoiceMain->invoice?->invoiceReference?->originalInvoiceNumber === 'INV-2026-0001');
    }

    public function test_validation_and_skipping_rules(): void
    {
        try {
            $this->readmeInvoice()->issueDate('2028-01-01')->build(now());
            $this->fail('Expected a validation error.');
        } catch (InvoiceValidationException $e) {
            $this->assertSame(['INVOICE_ISSUE_DATE_LATE'], $e->codes());
            $this->assertSame('invoiceIssueDate', $e->violations[0]->path);
        }

        $invoice = $this->readmeInvoice()
            ->validateWith((new InvoiceValidator)->skip('MISSING_CUSTOMER_DOMESTIC_TAXNUMBER'))
            ->build(now());
        $this->assertSame('INV-2026-0001', $invoice->invoiceNumber);

        $violations = (new InvoiceValidator)->validateSubmission($this->readmeInvoice()->submission(), now(), authenticatedTaxNumber: '99999999');
        $this->assertSame(['SUPPLIER_TAX_NUMBER_MISMATCH'], array_map(fn ($v) => $v->code, $violations));
    }

    public function test_taxpayer_lookup(): void
    {
        Nav::fake()->taxpayer('87654321', 'Vevő Kft.');

        $taxpayer = Nav::queryTaxpayer('87654321-2-13');

        $this->assertSame('Vevő Kft.', $taxpayer?->name);
    }

    public function test_testing_section(): void
    {
        Nav::fake()
            ->taxpayer('87654321', 'Vevő Kft.')
            ->rejectInvoice('INV-13', 'INVOICE_NUMBER_NOT_UNIQUE');

        Nav::queryTaxpayer('87654321');
        Nav::manageInvoice($this->readmeInvoice()->submission());

        Nav::assertInvoiceSubmitted('INV-2026-0001');
        Nav::assertInvoiceSubmitted(fn (InvoiceData $invoice) => $invoice->invoiceMain->invoice?->invoiceHead->customerInfo?->customerName === 'Vevő Kft.');
        Nav::assertSubmittedCount(1);
        Nav::assertTaxpayerQueried('87654321');
    }
}
