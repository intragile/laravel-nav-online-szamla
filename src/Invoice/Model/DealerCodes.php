<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\Value;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Dealer codes.
 *
 * Generated from DealerCodesType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class DealerCodes implements ModelElement
{
    /**
     * @param  list<string>  $dealerCode  Dealer code.
     */
    public function __construct(
        public array $dealerCode,
    ) {
        if (count($this->dealerCode) < 1) {
            throw new InvoiceModelException('DealerCodes: dealerCode requires at least 1 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            dealerCode: $reader->many(Ns::DATA, 'dealerCode', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->texts(Ns::DATA, 'dealerCode', $this->dealerCode);
    }
}
