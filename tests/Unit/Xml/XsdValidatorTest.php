<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Unit\Xml;

use IntrAgile\NavOnlineSzamla\Exceptions\XsdValidationException;
use IntrAgile\NavOnlineSzamla\Xml\Schema;
use IntrAgile\NavOnlineSzamla\Xml\XsdValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class XsdValidatorTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function officialInvoiceSamples(): iterable
    {
        foreach (glob(__DIR__.'/../../fixtures/nav-samples/*.xml') ?: [] as $file) {
            yield basename($file) => [$file];
        }
    }

    #[DataProvider('officialInvoiceSamples')]
    public function test_every_official_nav_invoice_sample_is_valid(string $file): void
    {
        XsdValidator::validate((string) file_get_contents($file), Schema::Data);
        $this->addToAssertionCount(1);
    }

    /** @return iterable<string, array{string}> */
    public static function officialApiSamples(): iterable
    {
        foreach (glob(__DIR__.'/../../fixtures/nav-api-samples/*.xml') ?: [] as $file) {
            yield basename($file) => [$file];
        }
    }

    #[DataProvider('officialApiSamples')]
    public function test_every_official_nav_api_request_sample_is_valid(string $file): void
    {
        XsdValidator::validate((string) file_get_contents($file), Schema::Api);
        $this->addToAssertionCount(1);
    }

    public function test_official_sample_count_is_complete(): void
    {
        $this->assertCount(30, iterator_to_array(self::officialInvoiceSamples()));
        $this->assertCount(11, iterator_to_array(self::officialApiSamples()));
    }

    public function test_invalid_invoice_is_rejected_with_readable_errors(): void
    {
        $xml = '<?xml version="1.0"?><InvoiceData xmlns="http://schemas.nav.gov.hu/OSA/3.0/data"><invoiceNumber>1</invoiceNumber></InvoiceData>';

        try {
            XsdValidator::validate($xml, Schema::Data);
            $this->fail('Expected an exception.');
        } catch (XsdValidationException $e) {
            $this->assertSame('invoiceData.xsd', $e->schema);
            $this->assertNotEmpty($e->errors);
            $this->assertStringContainsString('line ', $e->errors[0]);
        }
    }

    public function test_malformed_xml_is_rejected(): void
    {
        $this->expectException(XsdValidationException::class);
        XsdValidator::validate('<unclosed>', Schema::Api);
    }

    public function test_libxml_error_state_is_restored(): void
    {
        $before = libxml_use_internal_errors(false);
        try {
            XsdValidator::validate('<x/>', Schema::Api);
        } catch (XsdValidationException) {
        }
        $this->assertFalse(libxml_use_internal_errors($before));
    }
}
