<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice;

use DateTimeImmutable;
use DateTimeInterface;
use IntrAgile\NavOnlineSzamla\Invoice\Model\AdditionalData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\DiscountData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\LineNatureIndicator;
use IntrAgile\NavOnlineSzamla\Invoice\Model\ProductCode;
use IntrAgile\NavOnlineSzamla\Invoice\Model\ProductCodeCategory;
use IntrAgile\NavOnlineSzamla\Invoice\Model\UnitOfMeasure;
use IntrAgile\NavOnlineSzamla\Invoice\Model\VatRate;
use IntrAgile\NavOnlineSzamla\Support\Decimal;

/**
 * One invoice line as entered by the caller; InvoiceBuilder computes its amounts.
 *
 * On a normal or aggregate invoice the unit price is net; on a simplified invoice it is
 * gross. Quantities are signed: a negative quantity reverses the line.
 */
final class LineItem
{
    public ?Decimal $quantity = null;

    public ?UnitOfMeasure $unit = null;

    public ?string $ownUnit = null;

    public ?Decimal $unitPrice = null;

    public ?VatRate $vat = null;

    public ?Decimal $netAmount = null;

    public ?Decimal $vatAmount = null;

    public ?Decimal $grossAmount = null;

    public ?Decimal $netAmountHuf = null;

    public ?Decimal $vatAmountHuf = null;

    public ?Decimal $grossAmountHuf = null;

    public ?DiscountData $discount = null;

    public ?DateTimeImmutable $deliveryDate = null;

    public ?Decimal $lineExchangeRate = null;

    public bool $advance = false;

    public ?string $advanceOriginalInvoice = null;

    public ?DateTimeImmutable $advancePaymentDate = null;

    public ?Decimal $advanceExchangeRate = null;

    public ?int $lineNumberReference = null;

    public ?bool $intermediatedService = null;

    public ?bool $depositIndicator = null;

    /** @var list<ProductCode> */
    public array $productCodes = [];

    /** @var list<AdditionalData> */
    public array $additionalData = [];

    private function __construct(public ?string $description, public ?LineNatureIndicator $nature) {}

    public static function of(?string $description, ?LineNatureIndicator $nature = null): self
    {
        return new self($description, $nature);
    }

    public static function product(string $description): self
    {
        return new self($description, LineNatureIndicator::Product);
    }

    public static function service(string $description): self
    {
        return new self($description, LineNatureIndicator::Service);
    }

    public static function other(string $description): self
    {
        return new self($description, LineNatureIndicator::Other);
    }

    /**
     * @param  string|null  $ownUnit  Free-text unit; required by NAV when $unit is OWN.
     */
    public function quantity(Decimal|int|string $quantity, UnitOfMeasure $unit, ?string $ownUnit = null): self
    {
        $this->quantity = Decimal::of($quantity);
        $this->unit = $unit;
        $this->ownUnit = $ownUnit;

        return $this;
    }

    /**
     * Unit price in the invoice currency: net on normal invoices, gross on simplified ones.
     */
    public function unitPrice(Decimal|int|string $unitPrice): self
    {
        $this->unitPrice = Decimal::of($unitPrice);

        return $this;
    }

    public function vat(VatRate $vat): self
    {
        $this->vat = $vat;

        return $this;
    }

    /**
     * Fixes the net amount instead of computing quantity × unit price.
     */
    public function netAmount(Decimal|int|string $amount): self
    {
        $this->netAmount = Decimal::of($amount);

        return $this;
    }

    /**
     * Fixes the VAT amount instead of computing net × rate.
     */
    public function vatAmount(Decimal|int|string $amount): self
    {
        $this->vatAmount = Decimal::of($amount);

        return $this;
    }

    /**
     * Fixes the gross amount of a simplified invoice line instead of computing quantity × unit price.
     */
    public function grossAmount(Decimal|int|string $amount): self
    {
        $this->grossAmount = Decimal::of($amount);

        return $this;
    }

    /**
     * Fixes the HUF amounts instead of converting with the exchange rate. Null keeps the
     * computed value.
     */
    public function hufAmounts(Decimal|int|string|null $net = null, Decimal|int|string|null $vat = null, Decimal|int|string|null $gross = null): self
    {
        $this->netAmountHuf = $net === null ? null : Decimal::of($net);
        $this->vatAmountHuf = $vat === null ? null : Decimal::of($vat);
        $this->grossAmountHuf = $gross === null ? null : Decimal::of($gross);

        return $this;
    }

    /**
     * Discount not included in the unit price: a total value, or a rate (0.1 = 10 %), that
     * reduces the computed net (or gross, on simplified invoices) amount of the line.
     */
    public function discount(?string $description = null, Decimal|int|string|null $value = null, Decimal|int|string|null $rate = null): self
    {
        $this->discount = new DiscountData(
            $description,
            $value === null ? null : Decimal::of($value),
            $rate === null ? null : Decimal::of($rate),
        );

        return $this;
    }

    /**
     * Delivery date of the line on an aggregate invoice, optionally with its own exchange rate.
     */
    public function deliveredOn(DateTimeInterface|string $date, Decimal|int|string|null $exchangeRate = null): self
    {
        $this->deliveryDate = InvoiceBuilder::date($date);
        $this->lineExchangeRate = $exchangeRate === null ? null : Decimal::of($exchangeRate);

        return $this;
    }

    /**
     * Marks the line as an advance payment.
     */
    public function asAdvance(): self
    {
        $this->advance = true;

        return $this;
    }

    /**
     * Marks the line as the deduction of an advance invoiced earlier (on a final invoice).
     * The advance exchange rate is reported for information; HUF amounts use the invoice rate.
     */
    public function settlesAdvance(string $advanceInvoiceNumber, DateTimeInterface|string $paymentDate, Decimal|int|string $exchangeRate = 1): self
    {
        $this->advance = true;
        $this->advanceOriginalInvoice = $advanceInvoiceNumber;
        $this->advancePaymentDate = InvoiceBuilder::date($paymentDate);
        $this->advanceExchangeRate = Decimal::of($exchangeRate);

        return $this;
    }

    public function productCode(ProductCodeCategory $category, string $value): self
    {
        $this->productCodes[] = $category === ProductCodeCategory::Own
            ? new ProductCode($category, productCodeOwnValue: $value)
            : new ProductCode($category, productCodeValue: $value);

        return $this;
    }

    /**
     * Number of the line in the invoice chain that a modifying line creates. Defaults to
     * the next free number after InvoiceBuilder::previousLineCount().
     */
    public function lineNumberReference(int $reference): self
    {
        $this->lineNumberReference = $reference;

        return $this;
    }

    public function intermediatedService(bool $value = true): self
    {
        $this->intermediatedService = $value;

        return $this;
    }

    public function deposit(bool $value = true): self
    {
        $this->depositIndicator = $value;

        return $this;
    }

    public function additionalData(string $name, string $description, string $value): self
    {
        $this->additionalData[] = new AdditionalData($name, $description, $value);

        return $this;
    }

    /**
     * Whether the line is expressed in natural units (quantity, unit and unit price).
     */
    public function hasNaturalUnits(): bool
    {
        return $this->quantity !== null && $this->unit !== null && $this->unitPrice !== null;
    }
}
