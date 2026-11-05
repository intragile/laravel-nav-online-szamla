<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DateTimeImmutable;
use DOMElement;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\Value;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Supply of new means of transport - section 89 § and 169 (o) of the VAT law.
 *
 * Generated from NewTransportMeanType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class NewTransportMean implements ModelElement
{
    /**
     * @param  string|null  $brand  Product / type.
     * @param  string|null  $serialNum  Chassis number / serial number / product number.
     * @param  string|null  $engineNum  Engine number.
     * @param  DateTimeImmutable|null  $firstEntryIntoService  First entry into service.
     * @param  Vehicle|null  $vehicle  Other data in relation to motorised land vehicle.
     * @param  Vessel|null  $vessel  Data of vessel.
     * @param  Aircraft|null  $aircraft  Aircraft.
     */
    public function __construct(
        public ?string $brand = null,
        public ?string $serialNum = null,
        public ?string $engineNum = null,
        public ?DateTimeImmutable $firstEntryIntoService = null,
        public ?Vehicle $vehicle = null,
        public ?Vessel $vessel = null,
        public ?Aircraft $aircraft = null,
    ) {
        if ((($this->vehicle !== null ? 1 : 0) + ($this->vessel !== null ? 1 : 0) + ($this->aircraft !== null ? 1 : 0)) !== 1) {
            throw new InvoiceModelException('NewTransportMean: Exactly one of vehicle, vessel, aircraft must be set.');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            brand: $reader->optional(Ns::DATA, 'brand', Value::string(...)),
            serialNum: $reader->optional(Ns::DATA, 'serialNum', Value::string(...)),
            engineNum: $reader->optional(Ns::DATA, 'engineNum', Value::string(...)),
            firstEntryIntoService: $reader->optional(Ns::DATA, 'firstEntryIntoService', Value::date(...)),
            vehicle: $reader->optional(Ns::DATA, 'vehicle', Vehicle::fromXml(...)),
            vessel: $reader->optional(Ns::DATA, 'vessel', Vessel::fromXml(...)),
            aircraft: $reader->optional(Ns::DATA, 'aircraft', Aircraft::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'brand', $this->brand);
        $writer->text(Ns::DATA, 'serialNum', $this->serialNum);
        $writer->text(Ns::DATA, 'engineNum', $this->engineNum);
        $writer->text(Ns::DATA, 'firstEntryIntoService', Value::fromDate($this->firstEntryIntoService));
        $writer->child(Ns::DATA, 'vehicle', $this->vehicle);
        $writer->child(Ns::DATA, 'vessel', $this->vessel);
        $writer->child(Ns::DATA, 'aircraft', $this->aircraft);
    }
}
