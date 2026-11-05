<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice;

use DateTimeInterface;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceOperation;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;
use IntrAgile\NavOnlineSzamla\Invoice\Model\BatchInvoice;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceMain;
use IntrAgile\NavOnlineSzamla\Invoice\Validation\InvoiceValidator;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceSubmission;
use IntrAgile\NavOnlineSzamla\Xml\Schema;
use IntrAgile\NavOnlineSzamla\Xml\XsdValidator;

/**
 * One modifying document that modifies or cancels several invoices (batchInvoice).
 */
final class BatchModification
{
    /** @var list<InvoiceBuilder> */
    private array $invoices = [];

    private ?InvoiceValidator $validator;

    private function __construct(private readonly string $invoiceNumber, private readonly DateTimeInterface|string $issueDate)
    {
        $this->validator = new InvoiceValidator;
    }

    public static function of(string $invoiceNumber, DateTimeInterface|string $issueDate): self
    {
        return new self($invoiceNumber, $issueDate);
    }

    /**
     * Adds the modification of one invoice, created with InvoiceBuilder::modify() or storno().
     */
    public function add(InvoiceBuilder $modification): self
    {
        if ($modification->operation() === InvoiceOperation::Create) {
            throw new InvoiceModelException('A batch can only contain modifying or cancelling invoices.');
        }
        $this->invoices[] = $modification->issueDate($this->issueDate);

        return $this;
    }

    public function validateWith(?InvoiceValidator $validator): self
    {
        $this->validator = $validator;

        return $this;
    }

    public function operation(): InvoiceOperation
    {
        $operations = array_unique(array_map(static fn (InvoiceBuilder $b): string => $b->operation()->value, $this->invoices));
        if (count($operations) !== 1) {
            throw new InvoiceModelException('All invoices of a batch must use the same operation.');
        }

        return InvoiceOperation::from($operations[array_key_first($operations)]);
    }

    public function build(?DateTimeInterface $now = null): InvoiceData
    {
        if (count($this->invoices) < 2) {
            throw new InvoiceModelException('A batch modification needs at least two invoices.');
        }

        $data = new InvoiceData(
            invoiceNumber: $this->invoiceNumber,
            invoiceIssueDate: InvoiceBuilder::date($this->issueDate),
            completenessIndicator: false,
            invoiceMain: new InvoiceMain(batchInvoice: array_map(
                static fn (InvoiceBuilder $b, int $i): BatchInvoice => new BatchInvoice($i + 1, $b->buildInvoice()),
                $this->invoices,
                array_keys($this->invoices),
            )),
        );

        XsdValidator::validate($data->toXml(), Schema::Data);
        $this->validator?->assertValid($data, $this->operation(), $now);

        return $data;
    }

    public function submission(?DateTimeInterface $now = null): InvoiceSubmission
    {
        return InvoiceSubmission::of($this->operation(), $this->build($now));
    }
}
