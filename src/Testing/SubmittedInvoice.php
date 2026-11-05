<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Testing;

use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceData;
use IntrAgile\NavOnlineSzamla\Models\NavTransaction;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceSubmission;

/**
 * An invoice handed to a faked NAV, either queued with Nav::submit() or sent with manageInvoice().
 */
final class SubmittedInvoice
{
    private ?InvoiceData $invoice = null;

    public function __construct(
        public readonly InvoiceSubmission $submission,
        public readonly ?string $tenant,
        public readonly ?NavTransaction $transaction = null,
    ) {}

    public function invoice(): InvoiceData
    {
        return $this->invoice ??= InvoiceData::fromXmlString($this->submission->invoiceXml);
    }

    public function invoiceNumber(): string
    {
        return $this->invoice()->invoiceNumber;
    }
}
