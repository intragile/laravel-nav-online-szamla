<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Unit\Invoice;

use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceData;
use IntrAgile\NavOnlineSzamla\Tests\Support\CanonicalXml;
use IntrAgile\NavOnlineSzamla\Xml\Schema;
use IntrAgile\NavOnlineSzamla\Xml\XsdValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModelRoundTripTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function samples(): iterable
    {
        $files = glob(dirname(__DIR__, 2).'/fixtures/nav-samples/*.xml') ?: [];
        self::assertCount(30, $files);

        foreach ($files as $file) {
            yield basename($file) => [$file];
        }
    }

    #[DataProvider('samples')]
    public function test_official_sample_survives_xml_to_model_to_xml(string $file): void
    {
        $original = (string) file_get_contents($file);

        $written = InvoiceData::fromXmlString($original)->toXml();

        XsdValidator::validate($written, Schema::Data);
        $this->assertSame(CanonicalXml::of($original), CanonicalXml::of($written));
    }

    #[DataProvider('samples')]
    public function test_written_xml_is_stable(string $file): void
    {
        $once = InvoiceData::fromXmlString((string) file_get_contents($file))->toXml();

        $this->assertSame($once, InvoiceData::fromXmlString($once)->toXml());
    }
}
