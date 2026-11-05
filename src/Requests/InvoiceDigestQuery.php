<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Requests;

use DateTimeInterface;
use DOMElement;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceAppearance;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceCategory;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceDirection;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceOperation;
use IntrAgile\NavOnlineSzamla\Enums\PaymentMethod;
use IntrAgile\NavOnlineSzamla\Enums\QueryOperator;
use IntrAgile\NavOnlineSzamla\Enums\Source;
use IntrAgile\NavOnlineSzamla\Exceptions\NavException;
use IntrAgile\NavOnlineSzamla\Support\Timestamp;
use IntrAgile\NavOnlineSzamla\Xml\RequestDocument;

/**
 * Search parameters of queryInvoiceDigest.
 *
 * Start from one of the mandatory criteria (issue date, insertion date or original
 * invoice number), then narrow it with the fluent filters.
 */
final class InvoiceDigestQuery
{
    private const RELATIONAL = ['invoiceDelivery', 'paymentDate', 'invoiceNetAmount', 'invoiceNetAmountHUF', 'invoiceVatAmount', 'invoiceVatAmountHUF'];

    /** @var array<string, string> */
    private array $additional = [];

    /** @var array<string, list<array{QueryOperator, string}>> */
    private array $relational = [];

    /** @var array{transactionId: string, index: ?int, operation: ?InvoiceOperation}|null */
    private ?array $transaction = null;

    /**
     * @param  array{string, string|array{string, string}}  $mandatory
     */
    private function __construct(public readonly InvoiceDirection $direction, private readonly array $mandatory) {}

    /**
     * Invoices issued between two dates (inclusive).
     */
    public static function issuedBetween(DateTimeInterface $from, DateTimeInterface $to, InvoiceDirection $direction = InvoiceDirection::Outbound): self
    {
        return new self($direction, ['invoiceIssueDate', [$from->format('Y-m-d'), $to->format('Y-m-d')]]);
    }

    /**
     * Invoices that reached NAV between two instants.
     */
    public static function insertedBetween(DateTimeInterface $from, DateTimeInterface $to, InvoiceDirection $direction = InvoiceDirection::Outbound): self
    {
        return new self($direction, ['insDate', [Timestamp::header($from), Timestamp::header($to)]]);
    }

    /**
     * Modification documents that refer to the given original invoice number.
     */
    public static function modificationsOf(string $originalInvoiceNumber, InvoiceDirection $direction = InvoiceDirection::Outbound): self
    {
        return new self($direction, ['originalInvoiceNumber', $originalInvoiceNumber]);
    }

    /**
     * Tax number (8 digits) of the partner: the customer for outbound, the supplier for inbound queries.
     */
    public function taxNumber(string $taxNumber): self
    {
        return $this->with('taxNumber', $taxNumber);
    }

    public function groupMemberTaxNumber(string $taxNumber): self
    {
        return $this->with('groupMemberTaxNumber', $taxNumber);
    }

    /**
     * Partner name prefix (at least 5 characters).
     */
    public function name(string $name): self
    {
        return $this->with('name', $name);
    }

    public function category(InvoiceCategory $category): self
    {
        return $this->with('invoiceCategory', $category->value);
    }

    public function paymentMethod(PaymentMethod $method): self
    {
        return $this->with('paymentMethod', $method->value);
    }

    public function appearance(InvoiceAppearance $appearance): self
    {
        return $this->with('invoiceAppearance', $appearance->value);
    }

    public function source(Source $source): self
    {
        return $this->with('source', $source->value);
    }

    public function currency(string $currency): self
    {
        return $this->with('currency', strtoupper($currency));
    }

    public function deliveryDate(QueryOperator $operator, DateTimeInterface $date): self
    {
        return $this->relation('invoiceDelivery', $operator, $date->format('Y-m-d'));
    }

    public function paymentDate(QueryOperator $operator, DateTimeInterface $date): self
    {
        return $this->relation('paymentDate', $operator, $date->format('Y-m-d'));
    }

    public function netAmount(QueryOperator $operator, string|int|float $amount, bool $huf = false): self
    {
        return $this->relation($huf ? 'invoiceNetAmountHUF' : 'invoiceNetAmount', $operator, (string) $amount);
    }

    public function vatAmount(QueryOperator $operator, string|int|float $amount, bool $huf = false): self
    {
        return $this->relation($huf ? 'invoiceVatAmountHUF' : 'invoiceVatAmount', $operator, (string) $amount);
    }

    public function transaction(string $transactionId, ?int $index = null, ?InvoiceOperation $operation = null): self
    {
        $clone = clone $this;
        $clone->transaction = ['transactionId' => $transactionId, 'index' => $index, 'operation' => $operation];

        return $clone;
    }

    /**
     * @internal
     */
    public function appendTo(RequestDocument $request, DOMElement $parent): void
    {
        $params = $request->add($parent, 'invoiceQueryParams');

        $mandatory = $request->add($params, 'mandatoryQueryParams');
        [$name, $value] = $this->mandatory;
        if (is_array($value)) {
            $range = $request->add($mandatory, $name);
            $request->add($range, $name === 'insDate' ? 'dateTimeFrom' : 'dateFrom', $value[0]);
            $request->add($range, $name === 'insDate' ? 'dateTimeTo' : 'dateTo', $value[1]);
        } else {
            $request->add($mandatory, $name, $value);
        }

        if ($this->additional !== []) {
            $additional = $request->add($params, 'additionalQueryParams');
            foreach (['taxNumber', 'groupMemberTaxNumber', 'name', 'invoiceCategory', 'paymentMethod', 'invoiceAppearance', 'source', 'currency'] as $key) {
                $request->addOptional($additional, $key, $this->additional[$key] ?? null);
            }
        }

        if ($this->relational !== []) {
            $relational = $request->add($params, 'relationalQueryParams');
            foreach (self::RELATIONAL as $key) {
                foreach ($this->relational[$key] ?? [] as [$operator, $value]) {
                    $node = $request->add($relational, $key);
                    $request->add($node, 'queryOperator', $operator->value);
                    $request->add($node, 'queryValue', $value);
                }
            }
        }

        if ($this->transaction !== null) {
            $transaction = $request->add($params, 'transactionQueryParams');
            $request->add($transaction, 'transactionId', $this->transaction['transactionId']);
            $request->addOptional($transaction, 'index', $this->transaction['index'] === null ? null : (string) $this->transaction['index']);
            $request->addOptional($transaction, 'invoiceOperation', $this->transaction['operation']?->value);
        }
    }

    private function with(string $key, string $value): self
    {
        $clone = clone $this;
        $clone->additional[$key] = $value;

        return $clone;
    }

    private function relation(string $key, QueryOperator $operator, string $value): self
    {
        if (count($this->relational[$key] ?? []) >= 2) {
            throw new NavException("At most two conditions are allowed for {$key}.");
        }

        $clone = clone $this;
        $clone->relational[$key][] = [$operator, $value];

        return $clone;
    }
}
