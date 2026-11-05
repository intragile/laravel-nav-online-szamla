<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Unit\Invoice;

use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;
use IntrAgile\NavOnlineSzamla\Invoice\Model\Address;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\SimpleAddress;
use IntrAgile\NavOnlineSzamla\Invoice\Model\Summary;
use IntrAgile\NavOnlineSzamla\Invoice\Model\VatRate;
use IntrAgile\NavOnlineSzamla\Support\Decimal;
use PHPUnit\Framework\TestCase;

final class ModelMappingTest extends TestCase
{
    private static function sample(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/fixtures/nav-samples/Belfoldi termekertekesites.xml');
    }

    public function test_maps_typed_values(): void
    {
        $invoice = InvoiceData::fromXmlString(self::sample());

        $this->assertNotNull($invoice->invoiceMain->invoice);
        $head = $invoice->invoiceMain->invoice->invoiceHead;
        $this->assertSame('99999999', $head->supplierInfo->supplierTaxNumber->taxpayerId);
        $this->assertFalse($invoice->completenessIndicator);
        $this->assertNotEmpty($invoice->invoiceMain->invoice->invoiceLines?->line);
    }

    public function test_required_choice_rejects_no_member(): void
    {
        $this->expectException(InvoiceModelException::class);
        $this->expectExceptionMessage('Exactly one of vatPercentage');
        new VatRate;
    }

    public function test_required_choice_rejects_two_members(): void
    {
        $this->expectException(InvoiceModelException::class);
        new VatRate(vatPercentage: Decimal::of('0.27'), noVatCharge: true);
    }

    public function test_repeated_choice_member_counts_as_set_when_not_empty(): void
    {
        $this->expectException(InvoiceModelException::class);
        new Summary;
    }

    public function test_choice_member_accepts_single_value(): void
    {
        $address = new Address(simpleAddress: new SimpleAddress(countryCode: 'HU', postalCode: '1234', city: 'Budapest', additionalAddressDetail: 'Fő utca 1.'));

        $this->assertNull($address->detailedAddress);
    }

    public function test_unknown_element_is_an_error_not_silently_dropped(): void
    {
        $xml = str_replace('<invoiceNumber>', '<unexpected>x</unexpected><invoiceNumber>', self::sample());

        $this->expectException(InvoiceModelException::class);
        $this->expectExceptionMessage('unexpected');
        InvoiceData::fromXmlString($xml);
    }

    public function test_missing_required_element_is_reported(): void
    {
        $xml = (string) preg_replace('#<invoiceIssueDate>[^<]*</invoiceIssueDate>#', '', self::sample());

        $this->expectException(InvoiceModelException::class);
        $this->expectExceptionMessage('invoiceIssueDate');
        InvoiceData::fromXmlString($xml);
    }

    public function test_wrong_root_element_is_rejected(): void
    {
        $this->expectException(InvoiceModelException::class);
        InvoiceData::fromXmlString('<Other xmlns="urn:x"/>');
    }
}
