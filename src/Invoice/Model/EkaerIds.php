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
 * EKAER ID-s.
 *
 * Generated from EkaerIdsType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class EkaerIds implements ModelElement
{
    /**
     * @param  list<string>  $ekaerId  EKAER numbers; EKAER stands for Electronic Trade and Transport Control System.
     */
    public function __construct(
        public array $ekaerId,
    ) {
        if (count($this->ekaerId) < 1) {
            throw new InvoiceModelException('EkaerIds: ekaerId requires at least 1 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            ekaerId: $reader->many(Ns::DATA, 'ekaerId', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->texts(Ns::DATA, 'ekaerId', $this->ekaerId);
    }
}
