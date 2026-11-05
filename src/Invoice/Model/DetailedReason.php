<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\Value;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Detailed justification of exemption.
 *
 * Generated from DetailedReasonType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class DetailedReason implements ModelElement
{
    /**
     * @param  string  $case  Case notation with code.
     * @param  string  $reason  Case notation with text.
     */
    public function __construct(
        public string $case,
        public string $reason,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            case: $reader->one(Ns::DATA, 'case', Value::string(...)),
            reason: $reader->one(Ns::DATA, 'reason', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'case', $this->case);
        $writer->text(Ns::DATA, 'reason', $this->reason);
    }
}
