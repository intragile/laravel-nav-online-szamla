<?php

declare(strict_types=1);

namespace Intragile\NavOnlineSzamla\Xml;

enum Schema: string
{
    case Api = 'invoiceApi.xsd';
    case Data = 'invoiceData.xsd';
    case Annulment = 'invoiceAnnulment.xsd';

    public function path(): string
    {
        return dirname(__DIR__, 2).'/resources/xsd/'.$this->value;
    }
}
