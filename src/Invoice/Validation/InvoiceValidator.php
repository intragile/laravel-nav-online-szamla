<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Validation;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntrAgile\NavOnlineSzamla\Crypto\HashAlgorithm;
use IntrAgile\NavOnlineSzamla\Crypto\Signer;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceAppearance;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceCategory;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceOperation;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceValidationException;
use IntrAgile\NavOnlineSzamla\Invoice\Model\CustomerInfo;
use IntrAgile\NavOnlineSzamla\Invoice\Model\CustomerVatStatus;
use IntrAgile\NavOnlineSzamla\Invoice\Model\Invoice;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\Line;
use IntrAgile\NavOnlineSzamla\Invoice\Model\LineOperation;
use IntrAgile\NavOnlineSzamla\Invoice\Model\UnitOfMeasure;
use IntrAgile\NavOnlineSzamla\Invoice\Model\VatRate;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceSubmission;
use IntrAgile\NavOnlineSzamla\Support\Decimal;
use IntrAgile\NavOnlineSzamla\Support\Payload;

/**
 * Client-side check of the blocking (ERROR) business validations NAV applies to invoice
 * data, using the error codes of the interface specification (section 3.3.2).
 *
 * Only rules decidable from the submitted data are checked. Rules that need NAV's records
 * (uniqueness, references to earlier invoices, technical user permissions, legal
 * predecessor relationships) are left to NAV. Any rule can be skipped by its code, e.g.
 * when NAV changes a rule before this package is updated.
 */
final class InvoiceValidator
{
    private const EXEMPTION_CASES = ['AAM', 'TAM', 'KBAET', 'KBAUK', 'EAM', 'NAM'];

    private const OUT_OF_SCOPE_CASES = ['ATK', 'EUFAD37', 'EUFADE', 'EUE', 'HO'];

    private const AMOUNT_MISMATCH_CASES = ['REFUNDABLE_VAT', 'NONREFUNDABLE_VAT'];

    private const NORMAL_RATES = ['0.05', '0.07', '0.12', '0.18', '0.27'];

    /** Rates only valid for modifications or for supplies before 2013-01-01. */
    private const LEGACY_NORMAL_RATES = ['0.2', '0.25'];

    private const SIMPLIFIED_CONTENTS = ['0.0476', '0.1525', '0.2126'];

    private const LEGACY_SIMPLIFIED_CONTENTS = ['0.1667', '0.2'];

    private const AMOUNT_MISMATCH_RATES = ['0.27', '0.18', '0.05', '0.2126', '0.1525', '0.0476'];

    /**
     * Exchange rate limits NAV monitors (INCORRECT_HEAD_DATA_EXCHANGERATE_EXTREME). NAV may
     * change them without notice.
     */
    private const EXCHANGE_RATE_LIMITS = [
        'EUR' => [250, 600], 'USD' => [200, 600], 'GBP' => [250, 720], 'CHF' => [200, 600],
        'BGN' => [120, 360], 'RON' => [50, 180], 'PLN' => [50, 180], 'SEK' => [20, 72],
        'NOK' => [20, 72], 'CZK' => [5, 48], 'ILS' => [40, 240], 'DKK' => [20, 120], 'BAM' => [100, 360],
    ];

    private const PREDECESSOR_CODES = ['A10000_JOGELOD_POTLAS', 'A10000_JOGELOD_ADOSZAM', 'A10000_JOGELOD_IDOSZAK'];

    /** @var array<string, true> */
    private array $skipped = [];

    /** @var list<Violation> */
    private array $violations = [];

    private InvoiceOperation $operation;

    private DateTimeImmutable $now;

    public function __construct(string ...$skip)
    {
        $this->skipped = array_fill_keys($skip, true);
    }

    /**
     * A validator that additionally skips the given error codes.
     */
    public function skip(string ...$codes): self
    {
        return new self(...array_keys($this->skipped), ...$codes);
    }

    /**
     * @param  string|null  $authenticatedTaxNumber  Tax number of the technical user; enables SUPPLIER_TAX_NUMBER_MISMATCH.
     * @return list<Violation>
     */
    public function validate(
        InvoiceData $data,
        InvoiceOperation $operation,
        ?DateTimeInterface $now = null,
        ?string $authenticatedTaxNumber = null,
    ): array {
        $this->violations = [];
        $this->operation = $operation;
        $this->now = DateTimeImmutable::createFromInterface($now ?? new DateTimeImmutable)->setTimezone(new DateTimeZone('Europe/Budapest'));

        $this->check('INVALID_INVOICE_NUMBER', self::hasSurroundingWhitespace($data->invoiceNumber), 'The invoice number must not begin or end with whitespace.', 'invoiceNumber');

        $invoices = $data->invoiceMain->invoice !== null
            ? ['invoiceMain/invoice' => $data->invoiceMain->invoice]
            : [];
        foreach ($data->invoiceMain->batchInvoice as $i => $batch) {
            $invoices[sprintf('invoiceMain/batchInvoice[%d]/invoice', $i + 1)] = $batch->invoice;
        }

        foreach ($invoices as $path => $invoice) {
            $this->invoice($data, $invoice, $path, $authenticatedTaxNumber);
        }

        if (count($invoices) > 1) {
            $this->batch(array_values($invoices));
        }

        $this->completeness($data, array_values($invoices));

        return $this->violations;
    }

    /**
     * Validates a submission, including the electronic invoice hash rules.
     *
     * @return list<Violation>
     */
    public function validateSubmission(InvoiceSubmission $submission, ?DateTimeInterface $now = null, ?string $authenticatedTaxNumber = null): array
    {
        $data = InvoiceData::fromXmlString($submission->invoiceXml);
        $this->validate($data, $submission->operation, $now, $authenticatedTaxNumber);

        if ($data->completenessIndicator) {
            $hash = $submission->electronicInvoiceHash;
            $this->check('ELECTRONIC_INVOICE_HASH_EXPECTED', $hash === null, 'An electronic invoice (completenessIndicator = true) requires electronicInvoiceHash.', 'electronicInvoiceHash');
            $this->check('INVALID_INVOICE_HASH_CRYPTO', $submission->electronicInvoiceHashAlgorithm !== HashAlgorithm::Sha3_512, 'The electronic invoice hash of an electronic invoice must be SHA3-512.', 'electronicInvoiceHash');
            $expected = Signer::electronicInvoiceHash(Payload::encode($submission->invoiceXml, false), $submission->electronicInvoiceHashAlgorithm);
            $this->check('INVALID_INVOICE_HASH', $hash !== null && strtoupper($hash) !== $expected, 'electronicInvoiceHash does not match the hash of the BASE64 encoded invoice data.', 'electronicInvoiceHash');
        } elseif ($submission->electronicInvoiceHash !== null) {
            $this->check('INVALID_INVOICE_HASH_CRYPTO', ! in_array($submission->electronicInvoiceHashAlgorithm, [HashAlgorithm::Sha256, HashAlgorithm::Sha3_512], true), 'The electronic invoice hash must be SHA-256 or SHA3-512.', 'electronicInvoiceHash');
        }

        return $this->violations;
    }

    /**
     * @throws InvoiceValidationException
     */
    public function assertValid(InvoiceData $data, InvoiceOperation $operation, ?DateTimeInterface $now = null, ?string $authenticatedTaxNumber = null): void
    {
        $violations = $this->validate($data, $operation, $now, $authenticatedTaxNumber);
        if ($violations !== []) {
            throw new InvoiceValidationException($violations);
        }
    }

    /**
     * @throws InvoiceValidationException
     */
    public function assertValidSubmission(InvoiceSubmission $submission, ?DateTimeInterface $now = null, ?string $authenticatedTaxNumber = null): void
    {
        $violations = $this->validateSubmission($submission, $now, $authenticatedTaxNumber);
        if ($violations !== []) {
            throw new InvoiceValidationException($violations);
        }
    }

    private function invoice(InvoiceData $data, Invoice $invoice, string $path, ?string $authenticatedTaxNumber): void
    {
        $head = $invoice->invoiceHead;
        $detail = $head->invoiceDetail;
        $category = $detail->invoiceCategory;
        $customer = $head->customerInfo;
        $create = $this->operation === InvoiceOperation::Create;
        $modifyOrStorno = ! $create;
        $createOrModify = $this->operation !== InvoiceOperation::Storno;
        $lines = $invoice->invoiceLines->line ?? [];
        $predecessorCodes = array_values(array_filter(
            array_map(static fn ($d): string => $d->dataName, $detail->additionalInvoiceData),
            static fn (string $name): bool => in_array($name, self::PREDECESSOR_CODES, true),
        ));

        if ($authenticatedTaxNumber !== null) {
            $this->check(
                'SUPPLIER_TAX_NUMBER_MISMATCH',
                $head->supplierInfo->supplierTaxNumber->taxpayerId !== substr((string) preg_replace('/\D/', '', $authenticatedTaxNumber), 0, 8)
                    && ! in_array('A10000_JOGELOD_POTLAS', $predecessorCodes, true),
                'The supplier tax number differs from the tax number of the technical user.',
                $path.'/invoiceHead/supplierInfo/supplierTaxNumber',
            );
        }

        // Invoice reference and line modification data.
        $this->check('INVOICE_REFERENCE_EXPECTED', $modifyOrStorno && $invoice->invoiceReference === null, 'A modifying or cancelling invoice requires invoiceReference.', $path.'/invoiceReference');
        $this->check('INVOICE_REFERENCE_NOT_EXPECTED', $create && $invoice->invoiceReference !== null, 'An original invoice must not contain invoiceReference.', $path.'/invoiceReference');
        if ($invoice->invoiceReference !== null) {
            $reference = $invoice->invoiceReference;
            $this->check('INVALID_ORIGINAL_INVOICE_NUMBER', $modifyOrStorno && self::hasSurroundingWhitespace($reference->originalInvoiceNumber), 'The original invoice number must not begin or end with whitespace.', $path.'/invoiceReference/originalInvoiceNumber');
            $this->check('INCORRECT_HEAD_DATA_MOD_REF_INVOICE_NUMBER', $this->operation === InvoiceOperation::Modify && $reference->originalInvoiceNumber === $data->invoiceNumber && ! in_array('A10000_JOGELOD_POTLAS', $predecessorCodes, true), 'The modifying invoice number must differ from the original invoice number.', $path.'/invoiceReference/originalInvoiceNumber');
            $this->check('INCONSISTENT_MODIFICATION_DATA_MODIFICATIONINDEX_UNREAL', $this->operation === InvoiceOperation::Modify && $reference->modificationIndex > 1000, 'modificationIndex must not exceed 1000.', $path.'/invoiceReference/modificationIndex');
        }

        // Customer.
        $this->check('CUSTOMER_INFO_MISSING', $create && $customer === null, 'An original invoice requires customerInfo.', $path.'/invoiceHead/customerInfo');
        if ($customer !== null) {
            $this->customer($customer, $path.'/invoiceHead/customerInfo');
        }

        // Dates and currency.
        $this->check('INVOICE_DELIVERY_DATE_LATE', $detail->invoiceDeliveryDate > $data->invoiceIssueDate->modify('+5 years'), 'The delivery date must not be later than the issue date plus 5 years.', $path.'/invoiceHead/invoiceDetail/invoiceDeliveryDate');
        $this->check('INVOICE_ISSUE_DATE_LATE', $data->invoiceIssueDate->format('Y-m-d') > $this->now->modify('+1 year')->format('Y-m-d'), 'The issue date must not be later than the submission date plus 1 year.', 'invoiceIssueDate');
        $this->check('INCORRECT_DATE_INVOICE_DELIVERY_TO_FROM', $createOrModify && $detail->invoiceDeliveryPeriodStart !== null && $detail->invoiceDeliveryPeriodEnd !== null && $detail->invoiceDeliveryPeriodEnd < $detail->invoiceDeliveryPeriodStart, 'The delivery period must not end before it starts.', $path.'/invoiceHead/invoiceDetail/invoiceDeliveryPeriodEnd');
        $this->check('INCORRECT_HEAD_DATA_CURRENCY_CODE_HUF', $createOrModify && $detail->currencyCode === 'HUF' && ! $detail->exchangeRate->equals(1), 'The exchange rate of a HUF invoice must be 1.', $path.'/invoiceHead/invoiceDetail/exchangeRate');
        if (isset(self::EXCHANGE_RATE_LIMITS[$detail->currencyCode])) {
            [$min, $max] = self::EXCHANGE_RATE_LIMITS[$detail->currencyCode];
            $this->check('INCORRECT_HEAD_DATA_EXCHANGERATE_EXTREME', $createOrModify && ($detail->exchangeRate->compare($min) < 0 || $detail->exchangeRate->compare($max) > 0), sprintf('The %s exchange rate must be between %d and %d.', $detail->currencyCode, $min, $max), $path.'/invoiceHead/invoiceDetail/exchangeRate');
        }

        // Legal predecessor codes.
        $this->check('INVALID_PREDECESSOR_OPERATION', count($predecessorCodes) > 1, 'Only one legal predecessor code may appear in additionalInvoiceData.', $path.'/invoiceHead/invoiceDetail/additionalInvoiceData');
        $this->check('INVALID_PREDECESSOR_OPERATION', $create && in_array('A10000_JOGELOD_ADOSZAM', $predecessorCodes, true), 'A reference to a legal predecessor invoice is only allowed on modifying or cancelling invoices.', $path.'/invoiceHead/invoiceDetail/additionalInvoiceData');

        // Lines.
        $this->check('INVOICE_LINE_MISSING', $this->operation !== InvoiceOperation::Modify && $lines === [], 'The invoice contains no lines.', $path.'/invoiceLines');
        $expected = 1;
        foreach ($lines as $line) {
            if ($line->lineNumber !== $expected) {
                $this->check('LINE_NUMBER_NOT_SEQUENTIAL', true, sprintf('Line numbers must be continuous from 1; expected %d, got %d.', $expected, $line->lineNumber), $path.'/invoiceLines');
                break;
            }
            $expected++;
        }

        $references = [];
        $reverseCharge = false;
        foreach ($lines as $i => $line) {
            $linePath = sprintf('%s/invoiceLines/line[%d]', $path, $i + 1);
            $this->line($line, $linePath, $category, $customer, $detail->invoiceDeliveryDate);
            $reverseCharge = $reverseCharge || self::lineVatRate($line)?->vatDomesticReverseCharge === true;

            $reference = $line->lineModificationReference;
            $this->check('LINE_MODIFICATION_EXPECTED', $modifyOrStorno && $reference === null, 'Lines of a modifying or cancelling invoice require lineModificationReference.', $linePath.'/lineModificationReference');
            $this->check('LINE_MODIFICATION_NOT_EXPECTED', $create && $reference !== null, 'Lines of an original invoice must not contain lineModificationReference.', $linePath.'/lineModificationReference');
            if ($reference !== null && $modifyOrStorno) {
                $this->check('LINE_NUMBER_REFERENCE_NOT_UNIQUE', isset($references[$reference->lineNumberReference]), sprintf('lineNumberReference %d occurs more than once.', $reference->lineNumberReference), $linePath.'/lineModificationReference/lineNumberReference');
                $references[$reference->lineNumberReference] = true;
                $this->check('INVALID_LINE_OPERATION', $reference->lineOperation === LineOperation::Modify, 'lineOperation must be CREATE on modifying and cancelling invoices.', $linePath.'/lineModificationReference/lineOperation');
            }
        }

        $this->check('DOMESTIC_TAXNUMBER_EXPECTED_REVERSE_CHARGE', $createOrModify && $reverseCharge && $customer !== null && $customer->customerVatData?->customerTaxNumber === null, 'Domestic reverse charge lines require the Hungarian tax number of the customer.', $path.'/invoiceHead/customerInfo/customerVatData');

        $this->summary($invoice, $path, $category, $detail->invoiceDeliveryDate);
    }

    private function customer(CustomerInfo $customer, string $path): void
    {
        $private = $customer->customerVatStatus === CustomerVatStatus::PrivatePerson;
        $this->check('CUSTOMER_DATA_NOT_EXPECTED', $private && ($customer->customerVatData !== null || $customer->customerName !== null || $customer->customerAddress !== null), 'A private person customer must not have tax data, name or address.', $path);
        $this->check('CUSTOMER_DATA_EXPECTED', ! $private && ($customer->customerName === null || $customer->customerAddress === null), 'A customer that is not a private person requires name and address.', $path);

        if ($customer->customerVatStatus === CustomerVatStatus::Domestic) {
            $vat = $customer->customerVatData;
            $this->check('MISSING_CUSTOMER_DOMESTIC_TAXNUMBER', $vat?->customerTaxNumber === null, 'A domestic VAT subject customer requires a Hungarian tax number.', $path.'/customerVatData');
            $this->check('CUSTOMER_COMMUNITY_TAXNUMBER_NOT_EXPECTED', $vat?->communityVatNumber !== null, 'A domestic VAT subject customer must not have a community VAT number.', $path.'/customerVatData/communityVatNumber');
            $this->check('CUSTOMER_THIRD_STATE_TAXNUMBER_NOT_EXPECTED', $vat?->thirdStateTaxId !== null, 'A domestic VAT subject customer must not have a third state tax id.', $path.'/customerVatData/thirdStateTaxId');
        }
    }

    private function line(Line $line, string $path, InvoiceCategory $category, ?CustomerInfo $customer, DateTimeImmutable $invoiceDeliveryDate): void
    {
        $createOrModify = $this->operation !== InvoiceOperation::Storno;
        $normalOrAggregate = $category !== InvoiceCategory::Simplified;
        $status = $customer?->customerVatStatus;

        if ($line->lineExpressionIndicator) {
            $this->check('MANDATORY_LINE_CONTENT_MISSING', $line->lineDescription === null || $line->quantity === null || $line->unitOfMeasure === null || $line->unitPrice === null, 'A line in natural units requires description, quantity, unit of measure and unit price.', $path);
        } else {
            $this->check('MANDATORY_LINE_CONTENT_MISSING', $line->lineDescription === null, 'A line requires a description.', $path);
        }
        $this->check('INCORRECT_LINE_DATA_UOM_INCOMPLETE', $createOrModify && $line->unitOfMeasure === UnitOfMeasure::Own && $line->unitOfMeasureOwn === null, 'unitOfMeasure OWN requires unitOfMeasureOwn.', $path.'/unitOfMeasureOwn');
        $this->check('INCORRECT_LINE_DATA_AGGREGATE_INV_LINE_DATA_MANDATORY', $createOrModify && $category === InvoiceCategory::Aggregate && $line->aggregateInvoiceLineData === null, 'Lines of an aggregate invoice require aggregateInvoiceLineData.', $path.'/aggregateInvoiceLineData');

        $this->check('LINE_SUMMARY_TYPE_MISMATCH_LINE_SIMPLIFIED', $normalOrAggregate && $line->lineAmountsSimplified !== null, 'A normal or aggregate invoice must not contain simplified line amounts.', $path.'/lineAmountsSimplified');
        $this->check('LINE_SUMMARY_TYPE_MISMATCH_LINE_NORMAL', ! $normalOrAggregate && $line->lineAmountsNormal !== null, 'A simplified invoice must not contain normal line amounts.', $path.'/lineAmountsNormal');

        $deliveryDate = $line->aggregateInvoiceLineData->lineDeliveryDate ?? $invoiceDeliveryDate;

        if ($line->lineAmountsNormal !== null) {
            $rate = $line->lineAmountsNormal->lineVatRate;
            $ratePath = $path.'/lineAmountsNormal/lineVatRate';
            $this->check('INVALID_LINE_VAT_RATE_NORMAL', $rate->vatContent !== null, 'Normal line amounts must not use vatContent.', $ratePath.'/vatContent');
            $this->vatRate($rate, $ratePath, 'LINE', $deliveryDate, false);

            $vat = $line->lineAmountsNormal->lineVatData;
            $hasVat = $vat !== null && (! $vat->lineVatAmount->isZero() || ! $vat->lineVatAmountHUF->isZero());
            if ($normalOrAggregate && $createOrModify) {
                $this->check('INCORRECT_LINE_DATA_VAT_EXEMPTION_NORMAL', $rate->vatExemption !== null && $hasVat, 'A tax exempt line must not carry VAT.', $path.'/lineAmountsNormal/lineVatData');
                $this->check('INCORRECT_LINE_DATA_VAT_OUT_OF_SCOPE_NORMAL', $rate->vatOutOfScope !== null && $hasVat, 'A line outside the scope of VAT must not carry VAT.', $path.'/lineAmountsNormal/lineVatData');
            }
        }

        if ($line->lineAmountsSimplified !== null) {
            $rate = $line->lineAmountsSimplified->lineVatRate;
            $ratePath = $path.'/lineAmountsSimplified/lineVatRate';
            $this->check('INVALID_LINE_VAT_RATE_SIMPLIFIED', $rate->vatPercentage !== null, 'Simplified line amounts must not use vatPercentage.', $ratePath.'/vatPercentage');
            $this->vatRate($rate, $ratePath, 'LINE', $deliveryDate, true);
        }

        $rate = self::lineVatRate($line);
        if ($rate !== null && $createOrModify && $status !== null) {
            if ($normalOrAggregate) {
                $this->check('INCORRECT_LINE_DATA_VAT_STATUS_VAT_DATA_MISMATCH_KBAET', $rate->vatExemption?->case === 'KBAET' && $status !== CustomerVatStatus::Other, 'KBAET requires a customer with VAT status OTHER.', $path);
                $this->check('INCORRECT_LINE_DATA_VAT_STATUS_VAT_DATA_MISMATCH_KBAUK', $rate->vatExemption?->case === 'KBAUK' && $status === CustomerVatStatus::Domestic, 'KBAUK requires a customer with VAT status OTHER or PRIVATE_PERSON.', $path);
                $this->check('INCORRECT_LINE_DATA_VAT_STATUS_VAT_DATA_MISMATCH_EUFAD37', $rate->vatOutOfScope?->case === 'EUFAD37' && $status !== CustomerVatStatus::Other, 'EUFAD37 requires a customer with VAT status OTHER.', $path);
                $this->check('INCORRECT_LINE_DATA_VAT_STATUS_VAT_DATA_MISMATCH_EUFADE', $rate->vatOutOfScope?->case === 'EUFADE' && $status !== CustomerVatStatus::Other, 'EUFADE requires a customer with VAT status OTHER.', $path);
            }
            $this->check('INCORRECT_LINE_DATA_VAT_DOMESTIC_REVERSE_CHARGE', $rate->vatDomesticReverseCharge === true && $status !== CustomerVatStatus::Domestic, 'Domestic reverse charge requires a domestic VAT subject customer.', $path);
        }
    }

    private function summary(Invoice $invoice, string $path, InvoiceCategory $category, DateTimeImmutable $deliveryDate): void
    {
        $summary = $invoice->invoiceSummary;
        $normalOrAggregate = $category !== InvoiceCategory::Simplified;
        $createOrModify = $this->operation !== InvoiceOperation::Storno;

        $this->check('LINE_SUMMARY_TYPE_MISMATCH_SUMMARY_SIMPLIFIED', $normalOrAggregate && $summary->summarySimplified !== [], 'A normal or aggregate invoice must not contain a simplified summary.', $path.'/invoiceSummary/summarySimplified');
        $this->check('LINE_SUMMARY_TYPE_MISMATCH_SUMMARY_NORMAL', ! $normalOrAggregate && $summary->summaryNormal !== null, 'A simplified invoice must not contain a normal summary.', $path.'/invoiceSummary/summaryNormal');

        foreach ($summary->summaryNormal->summaryByVatRate ?? [] as $i => $byRate) {
            $ratePath = sprintf('%s/invoiceSummary/summaryNormal/summaryByVatRate[%d]/vatRate', $path, $i + 1);
            $this->check('INVALID_SUMMARY_VAT_RATE_NORMAL', $byRate->vatRate->vatContent !== null, 'A normal summary must not use vatContent.', $ratePath.'/vatContent');
            $this->vatRate($byRate->vatRate, $ratePath, 'SUMMARY', $deliveryDate, false);

            $hasVat = ! $byRate->vatRateVatData->vatRateVatAmount->isZero() || ! $byRate->vatRateVatData->vatRateVatAmountHUF->isZero();
            if ($normalOrAggregate && $createOrModify) {
                $this->check('INCORRECT_SUMMARY_DATA_VAT_OUT_OF_SCOPE_NORMAL', $byRate->vatRate->vatOutOfScope !== null && $hasVat, 'A summary outside the scope of VAT must not carry VAT.', dirname($ratePath).'/vatRateVatData');
                $this->check('INCORRECT_SUMMARY_DATA_VAT_EXEMPTION_NORMAL', $byRate->vatRate->vatExemption !== null && $hasVat, 'A tax exempt summary must not carry VAT.', dirname($ratePath).'/vatRateVatData');
            }
        }

        foreach ($summary->summarySimplified as $i => $simplified) {
            $ratePath = sprintf('%s/invoiceSummary/summarySimplified[%d]/vatRate', $path, $i + 1);
            $this->check('INVALID_SUMMARY_VAT_RATE_SIMPLIFIED', $simplified->vatRate->vatPercentage !== null, 'A simplified summary must not use vatPercentage.', $ratePath.'/vatPercentage');
            $this->vatRate($simplified->vatRate, $ratePath, 'SUMMARY', $deliveryDate, true);
        }
    }

    /**
     * @param  'LINE'|'SUMMARY'  $level
     */
    private function vatRate(VatRate $rate, string $path, string $level, DateTimeImmutable $deliveryDate, bool $simplified): void
    {
        $modifying = $this->operation !== InvoiceOperation::Create;
        $zeroAllowed = $deliveryDate->format('Y-m-d') >= '2024-01-01';
        $legacyAllowed = $modifying || $deliveryDate->format('Y-m-d') < '2013-01-01';

        $value = $simplified ? $rate->vatContent : $rate->vatPercentage;
        if ($value !== null) {
            $allowed = $simplified ? self::SIMPLIFIED_CONTENTS : self::NORMAL_RATES;
            if ($legacyAllowed) {
                $allowed = [...$allowed, ...($simplified ? self::LEGACY_SIMPLIFIED_CONTENTS : self::LEGACY_NORMAL_RATES)];
            }
            $valid = ($zeroAllowed && $value->isZero()) || self::inSet($value, $allowed);
            $this->check('INVALID_VAT_DATA', ! $valid, sprintf('VAT %s %s is not accepted for this invoice.', $simplified ? 'content' : 'rate', $value), $path);
        }

        // UNKNOWN is accepted for modifications of invoices without (3.0) history; NAV decides that.
        $unknown = $modifying ? ['UNKNOWN'] : [];
        if ($rate->vatExemption !== null) {
            $this->check("INVALID_{$level}_VAT_EXEMPTION_CODE", ! in_array($rate->vatExemption->case, [...self::EXEMPTION_CASES, ...$unknown], true), sprintf('"%s" is not a valid tax exemption code.', $rate->vatExemption->case), $path.'/vatExemption/case');
        }
        if ($rate->vatOutOfScope !== null) {
            $this->check("INVALID_{$level}_VAT_OUT_OF_SCOPE_CODE", ! in_array($rate->vatOutOfScope->case, [...self::OUT_OF_SCOPE_CASES, ...$unknown], true), sprintf('"%s" is not a valid out of scope code.', $rate->vatOutOfScope->case), $path.'/vatOutOfScope/case');
        }
        if ($rate->vatAmountMismatch !== null) {
            $mismatch = $rate->vatAmountMismatch;
            $this->check("INVALID_{$level}_VAT_AMOUNT_MISMATCH_CODE", ! in_array($mismatch->case, [...self::AMOUNT_MISMATCH_CASES, ...$unknown], true), sprintf('"%s" is not a valid VAT amount mismatch code.', $mismatch->case), $path.'/vatAmountMismatch/case');
            $this->check("INVALID_{$level}_VAT_AMOUNT_MISMATCH_VAT_RATE", ! (($zeroAllowed && $mismatch->vatRate->isZero()) || self::inSet($mismatch->vatRate, self::AMOUNT_MISMATCH_RATES)), sprintf('VAT rate %s is not accepted for a VAT amount mismatch.', $mismatch->vatRate), $path.'/vatAmountMismatch/vatRate');
        }
    }

    /**
     * @param  list<Invoice>  $invoices
     */
    private function batch(array $invoices): void
    {
        $customers = array_filter(array_map(
            static fn (Invoice $i): ?string => $i->invoiceHead->customerInfo?->customerVatData?->customerTaxNumber?->taxpayerId,
            $invoices,
        ));
        $this->check('CUSTOMER_NOT_IDENTICAL', count(array_unique($customers)) > 1, 'All invoices of a batch modification must have the same customer tax number.', 'invoiceMain/batchInvoice');

        $suppliers = array_map(static fn (Invoice $i): string => $i->invoiceHead->supplierInfo->supplierTaxNumber->taxpayerId, $invoices);
        $this->check('SUPPLIER_NOT_IDENTICAL', count(array_unique($suppliers)) > 1, 'All invoices of a batch modification must have the same supplier tax number.', 'invoiceMain/batchInvoice');

        $predecessors = array_map(static function (Invoice $i): ?string {
            foreach ($i->invoiceHead->invoiceDetail->additionalInvoiceData as $data) {
                if ($data->dataName === 'A10000_JOGELOD_ADOSZAM') {
                    return $data->dataValue;
                }
            }

            return null;
        }, $invoices);
        $this->check('PREDECESSOR_REFERENCE_NOT_IDENTICAL', count(array_unique(array_map(static fn (?string $v): string => (string) $v, $predecessors))) > 1, 'Either all or none of the batch invoices must reference the same legal predecessor.', 'invoiceMain/batchInvoice');
    }

    /**
     * @param  list<Invoice>  $invoices
     */
    private function completeness(InvoiceData $data, array $invoices): void
    {
        if (! $data->completenessIndicator) {
            return;
        }

        $this->check('INVOICE_COMPLETENESS_NOT_ALLOWED', true, 'NAV does not accept data reports as electronic invoices yet (completenessIndicator = true).', 'completenessIndicator');
        foreach ($invoices as $invoice) {
            $this->check('INVOICE_APPEARANCE_MISMATCH', $invoice->invoiceHead->invoiceDetail->invoiceAppearance !== InvoiceAppearance::Electronic, 'An electronic invoice must have invoiceAppearance ELECTRONIC.', 'completenessIndicator');
            $this->check('INVOICE_COMPLETENESS_MERGED_ITEM_INDICATOR_MISMATCH', $invoice->invoiceLines?->mergedItemIndicator === true, 'An invoice with merged items cannot be an electronic invoice.', 'completenessIndicator');
            $this->check('INVOICE_COMPLETENESS_PRIVATE_PERSON_INDICATOR_MISMATCH', $invoice->invoiceHead->customerInfo?->customerVatStatus === CustomerVatStatus::PrivatePerson, 'An invoice to a private person cannot be an electronic invoice.', 'completenessIndicator');
        }
    }

    private function check(string $code, bool $failed, string $message, string $path): void
    {
        if ($failed && ! isset($this->skipped[$code])) {
            $this->violations[] = new Violation($code, $message, $path);
        }
    }

    private static function lineVatRate(Line $line): ?VatRate
    {
        return $line->lineAmountsNormal->lineVatRate ?? $line->lineAmountsSimplified?->lineVatRate;
    }

    /**
     * @param  list<string>  $set
     */
    private static function inSet(Decimal $value, array $set): bool
    {
        foreach ($set as $candidate) {
            if ($value->equals($candidate)) {
                return true;
            }
        }

        return false;
    }

    private static function hasSurroundingWhitespace(string $value): bool
    {
        return $value !== '' && (trim($value, " \n\r\t") !== $value);
    }
}
