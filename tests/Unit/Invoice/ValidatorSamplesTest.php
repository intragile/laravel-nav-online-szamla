<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Unit\Invoice;

use DateTimeImmutable;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceOperation;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceData;
use IntrAgile\NavOnlineSzamla\Invoice\Validation\InvoiceValidator;
use IntrAgile\NavOnlineSzamla\Invoice\Validation\Violation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidatorSamplesTest extends TestCase
{
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
    public function test_official_samples_pass_the_blocking_rules(string $file): void
    {
        $data = InvoiceData::fromXmlString((string) file_get_contents($file));
        $first = $data->invoiceMain->invoice ?? $data->invoiceMain->batchInvoice[0]->invoice;
        $operation = $first->invoiceReference === null ? InvoiceOperation::Create : InvoiceOperation::Modify;

        $codes = array_map(
            static fn (Violation $v): string => $v->code.' '.$v->path,
            (new InvoiceValidator)->validate($data, $operation, new DateTimeImmutable('2021-06-01')),
        );

        $expected = basename($file) === 'Teteladatok modositasa.xml'
            ? ['INVALID_LINE_OPERATION invoiceMain/invoice/invoiceLines/line[1]/lineModificationReference/lineOperation']
            : [];
        $this->assertSame($expected, $codes);
    }
}
