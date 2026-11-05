<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Xml;

/**
 * A complex type of the invoice data model that serializes itself as child elements.
 */
interface ModelElement
{
    public function writeXml(ElementWriter $writer): void;
}
