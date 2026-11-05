<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceAppearance;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceCategory;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceOperation;
use IntrAgile\NavOnlineSzamla\Enums\PaymentMethod;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceValidationException;
use IntrAgile\NavOnlineSzamla\Exceptions\XsdValidationException;
use IntrAgile\NavOnlineSzamla\Invoice\Model\AdditionalData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\AdvanceData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\AdvancePaymentData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\AggregateInvoiceLineData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\ConventionalInvoiceInfo;
use IntrAgile\NavOnlineSzamla\Invoice\Model\CustomerInfo;
use IntrAgile\NavOnlineSzamla\Invoice\Model\FiscalRepresentative;
use IntrAgile\NavOnlineSzamla\Invoice\Model\Invoice;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceDetail;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceHead;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceMain;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceReference;
use IntrAgile\NavOnlineSzamla\Invoice\Model\Line;
use IntrAgile\NavOnlineSzamla\Invoice\Model\LineAmountsNormal;
use IntrAgile\NavOnlineSzamla\Invoice\Model\LineAmountsSimplified;
use IntrAgile\NavOnlineSzamla\Invoice\Model\LineGrossAmountData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\LineModificationReference;
use IntrAgile\NavOnlineSzamla\Invoice\Model\LineNetAmountData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\LineOperation;
use IntrAgile\NavOnlineSzamla\Invoice\Model\Lines;
use IntrAgile\NavOnlineSzamla\Invoice\Model\LineVatData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\ProductCodes;
use IntrAgile\NavOnlineSzamla\Invoice\Model\Summary;
use IntrAgile\NavOnlineSzamla\Invoice\Model\SummaryByVatRate;
use IntrAgile\NavOnlineSzamla\Invoice\Model\SummaryGrossData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\SummaryNormal;
use IntrAgile\NavOnlineSzamla\Invoice\Model\SummarySimplified;
use IntrAgile\NavOnlineSzamla\Invoice\Model\SupplierInfo;
use IntrAgile\NavOnlineSzamla\Invoice\Model\VatRateGrossData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\VatRateNetData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\VatRateVatData;
use IntrAgile\NavOnlineSzamla\Invoice\Validation\InvoiceValidator;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceSubmission;
use IntrAgile\NavOnlineSzamla\Support\Decimal;
use IntrAgile\NavOnlineSzamla\Xml\Schema;
use IntrAgile\NavOnlineSzamla\Xml\XsdValidator;

/**
 * Fluent builder for invoice data with computed line amounts, VAT summaries, HUF
 * conversion and rounding.
 *
 * Amounts are computed per line and summed, rounded half away from zero to the amount
 * scale (2 by default). HUF amounts are the invoice currency amounts multiplied by the
 * exchange rate of the line: the invoice rate, the line rate of an aggregate invoice, or
 * the rate of the advance a line settles.
 */
final class InvoiceBuilder
{
    private ?DateTimeImmutable $issueDate = null;

    private ?DateTimeImmutable $deliveryDate = null;

    private ?DateTimeImmutable $deliveryPeriodStart = null;

    private ?DateTimeImmutable $deliveryPeriodEnd = null;

    private ?DateTimeImmutable $accountingDeliveryDate = null;

    private ?DateTimeImmutable $paymentDate = null;

    private ?PaymentMethod $paymentMethod = null;

    private ?InvoiceAppearance $appearance = null;

    private InvoiceCategory $category = InvoiceCategory::Normal;

    private string $currency = 'HUF';

    private Decimal $exchangeRate;

    private ?SupplierInfo $supplier = null;

    private ?CustomerInfo $customer = null;

    private ?FiscalRepresentative $fiscalRepresentative = null;

    private ?bool $periodicalSettlement = null;

    private ?bool $smallBusiness = null;

    private ?bool $utilitySettlement = null;

    private ?bool $selfBilling = null;

    private ?bool $cashAccounting = null;

    private ?ConventionalInvoiceInfo $conventionalInfo = null;

    /** @var list<AdditionalData> */
    private array $additionalData = [];

    /** @var list<LineItem> */
    private array $lines = [];

    private ?int $previousLineCount = null;

    private int $scale = 2;

    private bool $completeness = false;

    private ?InvoiceValidator $validator;

    private function __construct(
        private readonly InvoiceOperation $operation,
        private readonly string $invoiceNumber,
        private readonly ?InvoiceReference $reference = null,
    ) {
        $this->exchangeRate = Decimal::of(1);
        $this->validator = new InvoiceValidator;
    }

    public static function create(string $invoiceNumber): self
    {
        return new self(InvoiceOperation::Create, $invoiceNumber);
    }

    /**
     * A modifying invoice. Its lines are added to the invoice chain after the lines
     * already reported; set their count with previousLineCount().
     *
     * @param  bool  $withoutMaster  The original invoice was not reported to NAV.
     */
    public static function modify(string $invoiceNumber, string $originalInvoiceNumber, int $modificationIndex, bool $withoutMaster = false): self
    {
        return new self(InvoiceOperation::Modify, $invoiceNumber, new InvoiceReference($originalInvoiceNumber, $withoutMaster, $modificationIndex));
    }

    /**
     * A cancelling invoice. See stornoOf() to reverse every line of an original invoice.
     */
    public static function storno(string $invoiceNumber, string $originalInvoiceNumber, int $modificationIndex, bool $withoutMaster = false): self
    {
        return new self(InvoiceOperation::Storno, $invoiceNumber, new InvoiceReference($originalInvoiceNumber, $withoutMaster, $modificationIndex));
    }

    /**
     * A cancelling invoice that reverses every line of the given original invoice with
     * exactly negated amounts, keeping its parties and invoice details.
     *
     * @param  int|null  $previousLineCount  Lines already in the invoice chain; defaults to the line count of $original.
     */
    public static function stornoOf(
        InvoiceData $original,
        string $invoiceNumber,
        int $modificationIndex,
        DateTimeInterface|string $issueDate,
        ?int $previousLineCount = null,
    ): self {
        $invoice = $original->invoiceMain->invoice
            ?? throw new InvoiceModelException('A batch modification cannot be reversed as a single invoice.');
        $head = $invoice->invoiceHead;
        $detail = $head->invoiceDetail;
        $lines = $invoice->invoiceLines->line ?? [];

        $builder = self::storno($invoiceNumber, $original->invoiceNumber, $modificationIndex)
            ->issueDate($issueDate)
            ->deliveryDate($detail->invoiceDeliveryDate)
            ->category($detail->invoiceCategory)
            ->currency($detail->currencyCode, $detail->exchangeRate)
            ->appearance($detail->invoiceAppearance)
            ->supplier($head->supplierInfo)
            ->previousLineCount($previousLineCount ?? count($lines));
        $builder->customer = $head->customerInfo;
        $builder->fiscalRepresentative = $head->fiscalRepresentativeInfo;
        $builder->paymentMethod = $detail->paymentMethod;
        $builder->paymentDate = $detail->paymentDate;
        $builder->deliveryPeriodStart = $detail->invoiceDeliveryPeriodStart;
        $builder->deliveryPeriodEnd = $detail->invoiceDeliveryPeriodEnd;
        $builder->accountingDeliveryDate = $detail->invoiceAccountingDeliveryDate;
        $builder->periodicalSettlement = $detail->periodicalSettlement;
        $builder->smallBusiness = $detail->smallBusinessIndicator;
        $builder->utilitySettlement = $detail->utilitySettlementIndicator;
        $builder->selfBilling = $detail->selfBillingIndicator;
        $builder->cashAccounting = $detail->cashAccountingIndicator;
        $builder->conventionalInfo = $detail->conventionalInvoiceInfo;
        $builder->additionalData = $detail->additionalInvoiceData;

        foreach ($lines as $line) {
            $builder->line(ReversedLine::of($line));
        }

        return $builder;
    }

    public function issueDate(DateTimeInterface|string $date): self
    {
        $this->issueDate = self::date($date);

        return $this;
    }

    /**
     * Date of supply. Defaults to the issue date.
     */
    public function deliveryDate(DateTimeInterface|string $date): self
    {
        $this->deliveryDate = self::date($date);

        return $this;
    }

    /**
     * Supply period of a periodic settlement.
     */
    public function deliveryPeriod(DateTimeInterface|string $start, DateTimeInterface|string $end): self
    {
        $this->deliveryPeriodStart = self::date($start);
        $this->deliveryPeriodEnd = self::date($end);

        return $this;
    }

    public function accountingDeliveryDate(DateTimeInterface|string $date): self
    {
        $this->accountingDeliveryDate = self::date($date);

        return $this;
    }

    public function payment(PaymentMethod $method, DateTimeInterface|string|null $dueDate = null): self
    {
        $this->paymentMethod = $method;
        $this->paymentDate = $dueDate === null ? null : self::date($dueDate);

        return $this;
    }

    public function appearance(InvoiceAppearance $appearance): self
    {
        $this->appearance = $appearance;

        return $this;
    }

    public function category(InvoiceCategory $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function simplified(): self
    {
        return $this->category(InvoiceCategory::Simplified);
    }

    public function aggregate(): self
    {
        return $this->category(InvoiceCategory::Aggregate);
    }

    /**
     * @param  Decimal|int|string  $exchangeRate  HUF value of one unit of the currency.
     */
    public function currency(string $currencyCode, Decimal|int|string $exchangeRate = 1): self
    {
        $this->currency = strtoupper($currencyCode);
        $this->exchangeRate = Decimal::of($exchangeRate);

        return $this;
    }

    public function supplier(SupplierInfo $supplier): self
    {
        $this->supplier = $supplier;

        return $this;
    }

    public function customer(CustomerInfo $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function fiscalRepresentative(FiscalRepresentative $representative): self
    {
        $this->fiscalRepresentative = $representative;

        return $this;
    }

    public function periodicalSettlement(bool $value = true): self
    {
        $this->periodicalSettlement = $value;

        return $this;
    }

    /**
     * The supplier is subject to the small business tax regime.
     */
    public function smallBusiness(bool $value = true): self
    {
        $this->smallBusiness = $value;

        return $this;
    }

    public function utilitySettlement(bool $value = true): self
    {
        $this->utilitySettlement = $value;

        return $this;
    }

    public function selfBilling(bool $value = true): self
    {
        $this->selfBilling = $value;

        return $this;
    }

    /**
     * The supplier applies cash accounting.
     */
    public function cashAccounting(bool $value = true): self
    {
        $this->cashAccounting = $value;

        return $this;
    }

    /**
     * Order, delivery note, contract and similar reference numbers.
     */
    public function conventionalInfo(ConventionalInvoiceInfo $info): self
    {
        $this->conventionalInfo = $info;

        return $this;
    }

    public function additionalData(string $name, string $description, string $value): self
    {
        $this->additionalData[] = new AdditionalData($name, $description, $value);

        return $this;
    }

    public function line(LineItem ...$lines): self
    {
        array_push($this->lines, ...$lines);

        return $this;
    }

    /**
     * Number of lines already reported in the invoice chain. Lines of a modifying or
     * cancelling invoice are referenced from this number plus one.
     */
    public function previousLineCount(int $count): self
    {
        $this->previousLineCount = $count;

        return $this;
    }

    /**
     * Fraction digits of computed amounts in the invoice currency: 0, 1 or 2 (default).
     */
    public function amountScale(int $scale): self
    {
        if ($scale < 0 || $scale > 2) {
            throw new \InvalidArgumentException('NAV accepts amounts with at most 2 fraction digits.');
        }
        $this->scale = $scale;

        return $this;
    }

    /**
     * Declares the data report to be the electronic invoice itself.
     */
    public function completeElectronicInvoice(bool $value = true): self
    {
        $this->completeness = $value;

        return $this;
    }

    /**
     * Replaces the validator used by build(); pass null to skip business validation.
     */
    public function validateWith(?InvoiceValidator $validator): self
    {
        $this->validator = $validator;

        return $this;
    }

    public function operation(): InvoiceOperation
    {
        return $this->operation;
    }

    /**
     * Builds the invoice data, checks it against the XSD and the business validator.
     *
     * @throws InvoiceValidationException
     * @throws XsdValidationException
     */
    public function build(?DateTimeInterface $now = null): InvoiceData
    {
        $data = new InvoiceData(
            invoiceNumber: $this->invoiceNumber,
            invoiceIssueDate: $this->issueDate ?? throw new InvoiceModelException('The issue date is required.'),
            completenessIndicator: $this->completeness,
            invoiceMain: new InvoiceMain(invoice: $this->buildInvoice()),
        );

        XsdValidator::validate($data->toXml(), Schema::Data);
        $this->validator?->assertValid($data, $this->operation, $now);

        return $data;
    }

    /**
     * Builds the data and wraps it in a submission for NavClient::manageInvoice().
     */
    public function submission(?DateTimeInterface $now = null): InvoiceSubmission
    {
        return InvoiceSubmission::of($this->operation, $this->build($now));
    }

    /**
     * Builds only the invoice node, e.g. to add it to a batch modification.
     */
    public function buildInvoice(): Invoice
    {
        $issueDate = $this->issueDate ?? throw new InvoiceModelException('The issue date is required.');
        $deliveryDate = $this->deliveryDate ?? $issueDate;

        $computed = [];
        foreach ($this->lines as $i => $item) {
            $computed[] = $this->computeLine($item, $i + 1);
        }

        return new Invoice(
            invoiceHead: new InvoiceHead(
                supplierInfo: $this->supplier ?? throw new InvoiceModelException('The supplier is required.'),
                invoiceDetail: new InvoiceDetail(
                    invoiceCategory: $this->category,
                    invoiceDeliveryDate: $deliveryDate,
                    currencyCode: $this->currency,
                    exchangeRate: $this->exchangeRate,
                    invoiceAppearance: $this->appearance ?? throw new InvoiceModelException('The invoice appearance (paper, electronic, EDI) is required.'),
                    invoiceDeliveryPeriodStart: $this->deliveryPeriodStart,
                    invoiceDeliveryPeriodEnd: $this->deliveryPeriodEnd,
                    invoiceAccountingDeliveryDate: $this->accountingDeliveryDate,
                    periodicalSettlement: $this->periodicalSettlement,
                    smallBusinessIndicator: $this->smallBusiness,
                    utilitySettlementIndicator: $this->utilitySettlement,
                    selfBillingIndicator: $this->selfBilling,
                    paymentMethod: $this->paymentMethod,
                    paymentDate: $this->paymentDate,
                    cashAccountingIndicator: $this->cashAccounting,
                    conventionalInvoiceInfo: $this->conventionalInfo,
                    additionalInvoiceData: $this->additionalData,
                ),
                customerInfo: $this->customer,
                fiscalRepresentativeInfo: $this->fiscalRepresentative,
            ),
            invoiceSummary: $this->summary($computed),
            invoiceReference: $this->reference,
            invoiceLines: $computed === [] ? null : new Lines(false, array_map(static fn (ComputedLine $c): Line => $c->line, $computed)),
        );
    }

    /**
     * @internal
     */
    public static function date(DateTimeInterface|string $date): DateTimeImmutable
    {
        $date = is_string($date) ? new DateTimeImmutable($date, new DateTimeZone('Europe/Budapest')) : DateTimeImmutable::createFromInterface($date);

        // Invoice dates are calendar dates; keep the local date regardless of the time zone.
        return new DateTimeImmutable($date->format('Y-m-d'), new DateTimeZone('UTC'));
    }

    private function computeLine(LineItem $item, int $number): ComputedLine
    {
        $simplified = $this->category === InvoiceCategory::Simplified;
        $vat = $item->vat ?? throw new InvoiceModelException(sprintf('Line %d has no VAT classification.', $number));
        if ($simplified && $vat->vatPercentage !== null) {
            $vat = Vat::contentForRate($vat->vatPercentage);
        }

        $hufRate = $item->lineExchangeRate ?? $this->exchangeRate;
        $huf = fn (Decimal $amount, ?Decimal $fixed): Decimal => $fixed ?? $this->toHuf($amount, $hufRate);

        $normal = null;
        $simplifiedAmounts = null;
        $net = $vatAmount = $gross = Decimal::zero();
        $netHuf = $vatHuf = $grossHuf = Decimal::zero();

        if ($simplified) {
            $gross = $item->grossAmount ?? $this->multiply($item, $number);
            $grossHuf = $huf($gross, $item->grossAmountHuf);
            $simplifiedAmounts = new LineAmountsSimplified($vat, $gross, $grossHuf);
        } else {
            $net = $item->netAmount ?? $this->multiply($item, $number);
            $netHuf = $huf($net, $item->netAmountHuf);
            // VAT data is reported with zero amounts when no VAT is charged, as in NAV's samples.
            $rate = Vat::chargedRate($vat) ?? Decimal::zero();
            $vatAmount = $item->vatAmount ?? $net->mul($rate)->toScale($this->scale);
            $vatHuf = $huf($vatAmount, $item->vatAmountHuf);
            $vatData = new LineVatData($vatAmount, $vatHuf);
            $gross = $net->add($vatAmount);
            $grossHuf = $netHuf->add($vatHuf);
            $normal = new LineAmountsNormal(
                lineNetAmountData: new LineNetAmountData($net, $netHuf),
                lineVatRate: $vat,
                lineVatData: $vatData,
                lineGrossAmountData: new LineGrossAmountData($gross, $grossHuf),
            );
        }

        $reference = null;
        if ($this->operation !== InvoiceOperation::Create) {
            $base = $this->previousLineCount;
            if ($item->lineNumberReference === null && $base === null) {
                throw new InvoiceModelException('Modifying and cancelling invoices need previousLineCount() or a lineNumberReference on every line.');
            }
            $reference = new LineModificationReference($item->lineNumberReference ?? (int) $base + $number, LineOperation::Create);
        }

        $line = new Line(
            lineNumber: $number,
            lineExpressionIndicator: $item->hasNaturalUnits(),
            lineModificationReference: $reference,
            advanceData: $item->advance ? new AdvanceData(
                true,
                $item->advanceOriginalInvoice === null ? null : new AdvancePaymentData(
                    $item->advanceOriginalInvoice,
                    $item->advancePaymentDate ?? throw new InvoiceModelException(sprintf('Line %d settles an advance without a payment date.', $number)),
                    $item->advanceExchangeRate ?? Decimal::of(1),
                ),
            ) : null,
            productCodes: $item->productCodes === [] ? null : new ProductCodes($item->productCodes),
            lineNatureIndicator: $item->nature,
            lineDescription: $item->description,
            quantity: $item->quantity,
            unitOfMeasure: $item->unit,
            unitOfMeasureOwn: $item->ownUnit,
            unitPrice: $item->unitPrice,
            unitPriceHUF: $item->unitPrice === null ? null : $this->toHuf($item->unitPrice, $hufRate),
            lineDiscountData: $item->discount,
            lineAmountsNormal: $normal,
            lineAmountsSimplified: $simplifiedAmounts,
            intermediatedService: $item->intermediatedService,
            aggregateInvoiceLineData: $item->deliveryDate === null ? null : new AggregateInvoiceLineData($item->deliveryDate, $item->lineExchangeRate),
            depositIndicator: $item->depositIndicator,
            additionalLineData: $item->additionalData,
        );

        return new ComputedLine($line, $vat, $net, $netHuf, $vatAmount, $vatHuf, $gross, $grossHuf);
    }

    private function multiply(LineItem $item, int $number): Decimal
    {
        if ($item->quantity === null || $item->unitPrice === null) {
            throw new InvoiceModelException(sprintf('Line %d needs either an amount or a quantity and unit price.', $number));
        }

        $amount = $item->quantity->mul($item->unitPrice);
        $discount = $item->discount;
        if ($discount?->discountValue !== null) {
            $amount = $amount->sub($discount->discountValue);
        } elseif ($discount?->discountRate !== null) {
            $amount = $amount->sub($amount->mul($discount->discountRate));
        }

        return $amount->toScale($this->scale);
    }

    private function toHuf(Decimal $amount, Decimal $rate): Decimal
    {
        return $this->currency === 'HUF' && $rate->equals(1)
            ? $amount
            : $amount->mul($rate)->toScale(2);
    }

    /**
     * @param  list<ComputedLine>  $lines
     */
    private function summary(array $lines): Summary
    {
        $simplified = $this->category === InvoiceCategory::Simplified;
        $zero = Decimal::zero();

        // A modification without lines still needs one summary row: 27 % (normal) or 0 (simplified) with zero amounts.
        if ($lines === []) {
            return $simplified
                ? new Summary(summarySimplified: [new SummarySimplified(Vat::content('0'), $zero, $zero)], summaryGrossData: new SummaryGrossData($zero, $zero))
                : new Summary(
                    summaryNormal: new SummaryNormal(
                        [new SummaryByVatRate(Vat::rate('0.27'), new VatRateNetData($zero, $zero), new VatRateVatData($zero, $zero), new VatRateGrossData($zero, $zero))],
                        $zero, $zero, $zero, $zero,
                    ),
                    summaryGrossData: new SummaryGrossData($zero, $zero),
                );
        }

        /** @var array<string, non-empty-list<ComputedLine>> $groups */
        $groups = [];
        foreach ($lines as $line) {
            $groups[Vat::key($line->classification)][] = $line;
        }

        $sum = self::sum(...);
        $net = static fn (ComputedLine $l): Decimal => $l->net;
        $netHuf = static fn (ComputedLine $l): Decimal => $l->netHuf;
        $vat = static fn (ComputedLine $l): Decimal => $l->vat;
        $vatHuf = static fn (ComputedLine $l): Decimal => $l->vatHuf;
        $gross = static fn (ComputedLine $l): Decimal => $l->gross;
        $grossHuf = static fn (ComputedLine $l): Decimal => $l->grossHuf;

        $grossData = new SummaryGrossData($sum($lines, $gross), $sum($lines, $grossHuf));

        if ($simplified) {
            return new Summary(
                summarySimplified: array_values(array_map(static fn (array $group): SummarySimplified => new SummarySimplified(
                    $group[0]->classification,
                    $sum($group, $gross),
                    $sum($group, $grossHuf),
                ), $groups)),
                summaryGrossData: $grossData,
            );
        }

        return new Summary(
            summaryNormal: new SummaryNormal(
                summaryByVatRate: array_values(array_map(static fn (array $group): SummaryByVatRate => new SummaryByVatRate(
                    vatRate: $group[0]->classification,
                    vatRateNetData: new VatRateNetData($sum($group, $net), $sum($group, $netHuf)),
                    vatRateVatData: new VatRateVatData($sum($group, $vat), $sum($group, $vatHuf)),
                    vatRateGrossData: new VatRateGrossData($sum($group, $gross), $sum($group, $grossHuf)),
                ), $groups)),
                invoiceNetAmount: $sum($lines, $net),
                invoiceNetAmountHUF: $sum($lines, $netHuf),
                invoiceVatAmount: $sum($lines, $vat),
                invoiceVatAmountHUF: $sum($lines, $vatHuf),
            ),
            summaryGrossData: $grossData,
        );
    }

    /**
     * @param  list<ComputedLine>  $lines
     * @param  Closure(ComputedLine): Decimal  $pick
     */
    private static function sum(array $lines, Closure $pick): Decimal
    {
        $total = Decimal::zero();
        foreach ($lines as $line) {
            $total = $total->add($pick($line));
        }

        return $total;
    }
}
