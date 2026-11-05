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
 * Marking the goal of modification of the line (in the case of data supply about changes/updates only).
 *
 * Generated from LineModificationReferenceType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class LineModificationReference implements ModelElement
{
    /**
     * @param  int  $lineNumberReference  Line number of the original invoice, which the modification occurs with. In case of create operation the tag shall contain the new line number, as a sequential increment of the the existing lines set.
     * @param  LineOperation  $lineOperation  Line modification type.
     */
    public function __construct(
        public int $lineNumberReference,
        public LineOperation $lineOperation,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            lineNumberReference: $reader->one(Ns::DATA, 'lineNumberReference', Value::int(...)),
            lineOperation: $reader->one(Ns::DATA, 'lineOperation', static fn (DOMElement $e): LineOperation => LineOperation::from(Value::string($e))),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'lineNumberReference', (string) $this->lineNumberReference);
        $writer->text(Ns::DATA, 'lineOperation', $this->lineOperation->value);
    }
}
