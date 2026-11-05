<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Xml;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Enums\AnnulmentVerificationStatus;
use IntrAgile\NavOnlineSzamla\Enums\BusinessResultCode;
use IntrAgile\NavOnlineSzamla\Enums\Incorporation;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceAppearance;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceCategory;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceOperation;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceStatus;
use IntrAgile\NavOnlineSzamla\Enums\PaymentMethod;
use IntrAgile\NavOnlineSzamla\Enums\RequestStatus;
use IntrAgile\NavOnlineSzamla\Enums\Source;
use IntrAgile\NavOnlineSzamla\Enums\TaxpayerAddressType;
use IntrAgile\NavOnlineSzamla\Exceptions\NavException;
use IntrAgile\NavOnlineSzamla\Exceptions\TechnicalValidationMessage;
use IntrAgile\NavOnlineSzamla\Responses\AuditData;
use IntrAgile\NavOnlineSzamla\Responses\BusinessValidationMessage;
use IntrAgile\NavOnlineSzamla\Responses\InvoiceChainElement;
use IntrAgile\NavOnlineSzamla\Responses\InvoiceDataResult;
use IntrAgile\NavOnlineSzamla\Responses\InvoiceDigest;
use IntrAgile\NavOnlineSzamla\Responses\Page;
use IntrAgile\NavOnlineSzamla\Responses\ProcessingResult;
use IntrAgile\NavOnlineSzamla\Responses\Taxpayer;
use IntrAgile\NavOnlineSzamla\Responses\TaxpayerAddress;
use IntrAgile\NavOnlineSzamla\Responses\Transaction;
use IntrAgile\NavOnlineSzamla\Responses\TransactionStatus;
use IntrAgile\NavOnlineSzamla\Support\Payload;

/**
 * Maps NAV response documents to value objects.
 *
 * @internal
 */
final class ResponseMapper
{
    public static function transactionStatus(ResponseDocument $r, string $transactionId): TransactionStatus
    {
        $list = $r->element('/*/api:processingResults');
        if ($list === null) {
            return new TransactionStatus($transactionId, []);
        }

        $results = array_map(static function (DOMElement $e) use ($r): ProcessingResult {
            $compressed = $r->bool('api:compressedContentIndicator', $e) ?? false;
            $original = $r->string('api:originalRequest', $e);

            return new ProcessingResult(
                index: (int) $r->requireString('api:index', $e),
                batchIndex: $r->int('api:batchIndex', $e),
                status: InvoiceStatus::from($r->requireString('api:invoiceStatus', $e)),
                technicalValidationMessages: array_map(
                    static fn (DOMElement $m): TechnicalValidationMessage => new TechnicalValidationMessage(
                        $r->requireString('api:validationResultCode', $m),
                        $r->string('api:validationErrorCode', $m),
                        $r->string('api:message', $m),
                    ),
                    $r->elements('api:technicalValidationMessages', $e),
                ),
                businessValidationMessages: array_map(
                    static fn (DOMElement $m): BusinessValidationMessage => new BusinessValidationMessage(
                        BusinessResultCode::from($r->requireString('api:validationResultCode', $m)),
                        $r->string('api:validationErrorCode', $m),
                        $r->string('api:message', $m),
                        $r->string('api:pointer/api:tag', $m),
                        $r->string('api:pointer/api:value', $m),
                        $r->int('api:pointer/api:line', $m),
                        $r->string('api:pointer/api:originalInvoiceNumber', $m),
                    ),
                    $r->elements('api:businessValidationMessages', $e),
                ),
                compressedContent: $compressed,
                originalRequest: $original === null ? null : Payload::decode($original, $compressed),
            );
        }, $r->elements('api:processingResult', $list));

        $annulmentStatus = $r->string('api:annulmentData/api:annulmentVerificationStatus', $list);

        return new TransactionStatus(
            $transactionId,
            $results,
            $r->string('api:originalRequestVersion', $list),
            $annulmentStatus === null ? null : AnnulmentVerificationStatus::from($annulmentStatus),
            $r->date('api:annulmentData/api:annulmentDecisionDate', $list),
            $r->string('api:annulmentData/api:annulmentDecisionUser', $list),
        );
    }

    public static function invoiceData(ResponseDocument $r): ?InvoiceDataResult
    {
        $result = $r->element('/*/api:invoiceDataResult');
        if ($result === null) {
            return null;
        }

        $compressed = $r->bool('api:compressedContentIndicator', $result) ?? false;
        $audit = $r->element('api:auditData', $result) ?? $result;

        return new InvoiceDataResult(
            Payload::decode($r->requireString('api:invoiceData', $result), $compressed),
            new AuditData(
                new \DateTimeImmutable($r->requireString('api:insdate', $audit)),
                $r->requireString('api:insCusUser', $audit),
                Source::from($r->requireString('api:source', $audit)),
                $r->string('api:transactionId', $audit),
                $r->int('api:index', $audit),
                $r->int('api:batchIndex', $audit),
                $r->requireString('api:originalRequestVersion', $audit),
            ),
            $compressed,
            $r->string('api:electronicInvoiceHash', $result),
        );
    }

    /**
     * @return Page<InvoiceDigest>
     */
    public static function invoiceDigestPage(ResponseDocument $r): Page
    {
        $result = $r->element('/*/api:invoiceDigestResult') ?? throw new NavException('Missing invoiceDigestResult.');

        $date = static fn (string $path, DOMElement $e): ?\DateTimeImmutable => $r->date($path, $e);

        return new Page(
            (int) $r->requireString('api:currentPage', $result),
            (int) $r->requireString('api:availablePage', $result),
            array_map(static fn (DOMElement $e): InvoiceDigest => new InvoiceDigest(
                invoiceNumber: $r->requireString('api:invoiceNumber', $e),
                batchIndex: $r->int('api:batchIndex', $e),
                operation: InvoiceOperation::from($r->requireString('api:invoiceOperation', $e)),
                category: InvoiceCategory::from($r->requireString('api:invoiceCategory', $e)),
                issueDate: new \DateTimeImmutable($r->requireString('api:invoiceIssueDate', $e)),
                supplierTaxNumber: $r->requireString('api:supplierTaxNumber', $e),
                supplierGroupMemberTaxNumber: $r->string('api:supplierGroupMemberTaxNumber', $e),
                supplierName: $r->requireString('api:supplierName', $e),
                customerTaxNumber: $r->string('api:customerTaxNumber', $e),
                customerGroupMemberTaxNumber: $r->string('api:customerGroupMemberTaxNumber', $e),
                customerName: $r->string('api:customerName', $e),
                paymentMethod: PaymentMethod::tryFrom((string) $r->string('api:paymentMethod', $e)),
                paymentDate: $date('api:paymentDate', $e),
                appearance: InvoiceAppearance::tryFrom((string) $r->string('api:invoiceAppearance', $e)),
                source: Source::tryFrom((string) $r->string('api:source', $e)),
                deliveryDate: $date('api:invoiceDeliveryDate', $e),
                currency: $r->string('api:currency', $e),
                netAmount: $r->string('api:invoiceNetAmount', $e),
                netAmountHuf: $r->string('api:invoiceNetAmountHUF', $e),
                vatAmount: $r->string('api:invoiceVatAmount', $e),
                vatAmountHuf: $r->string('api:invoiceVatAmountHUF', $e),
                transactionId: $r->string('api:transactionId', $e),
                index: $r->int('api:index', $e),
                originalInvoiceNumber: $r->string('api:originalInvoiceNumber', $e),
                modificationIndex: $r->int('api:modificationIndex', $e),
                insertedAt: new \DateTimeImmutable($r->requireString('api:insDate', $e)),
                completenessIndicator: $r->bool('api:completenessIndicator', $e),
            ), $r->elements('api:invoiceDigest', $result)),
        );
    }

    /**
     * @return Page<InvoiceChainElement>
     */
    public static function invoiceChainPage(ResponseDocument $r): Page
    {
        $result = $r->element('/*/api:invoiceChainDigestResult') ?? throw new NavException('Missing invoiceChainDigestResult.');

        return new Page(
            (int) $r->requireString('api:currentPage', $result),
            (int) $r->requireString('api:availablePage', $result),
            array_map(static fn (DOMElement $e): InvoiceChainElement => new InvoiceChainElement(
                invoiceNumber: $r->requireString('api:invoiceChainDigest/api:invoiceNumber', $e),
                batchIndex: $r->int('api:invoiceChainDigest/api:batchIndex', $e),
                operation: InvoiceOperation::from($r->requireString('api:invoiceChainDigest/api:invoiceOperation', $e)),
                supplierTaxNumber: $r->requireString('api:invoiceChainDigest/api:supplierTaxNumber', $e),
                customerTaxNumber: $r->string('api:invoiceChainDigest/api:customerTaxNumber', $e),
                insertedAt: new \DateTimeImmutable($r->requireString('api:invoiceChainDigest/api:insDate', $e)),
                originalRequestVersion: $r->requireString('api:invoiceChainDigest/api:originalRequestVersion', $e),
                maxLineNumber: $r->int('api:invoiceLines/api:maxLineNumber', $e),
                newCreatedLines: array_map(static fn (DOMElement $l): array => [
                    'start' => (int) $r->requireString('api:lineNumberIntervalStart', $l),
                    'end' => (int) $r->requireString('api:lineNumberIntervalEnd', $l),
                ], $r->elements('api:invoiceLines/api:newCreatedLines', $e)),
                originalInvoiceNumber: $r->string('api:invoiceReferenceData/api:originalInvoiceNumber', $e),
                modifyWithoutMaster: $r->bool('api:invoiceReferenceData/api:modifyWithoutMaster', $e),
                modificationTimestamp: $r->date('api:invoiceReferenceData/api:modificationTimestamp', $e),
                modificationIndex: $r->int('api:invoiceReferenceData/api:modificationIndex', $e),
            ), $r->elements('api:invoiceChainElement', $result)),
        );
    }

    /**
     * @return Page<Transaction>
     */
    public static function transactionPage(ResponseDocument $r): Page
    {
        $result = $r->element('/*/api:transactionListResult') ?? throw new NavException('Missing transactionListResult.');

        return new Page(
            (int) $r->requireString('api:currentPage', $result),
            (int) $r->requireString('api:availablePage', $result),
            array_map(static fn (DOMElement $e): Transaction => new Transaction(
                transactionId: $r->requireString('api:transactionId', $e),
                insertedAt: new \DateTimeImmutable($r->requireString('api:insDate', $e)),
                insertedBy: $r->requireString('api:insCusUser', $e),
                source: Source::from($r->requireString('api:source', $e)),
                status: RequestStatus::from($r->requireString('api:requestStatus', $e)),
                technicalAnnulment: $r->bool('api:technicalAnnulment', $e) ?? false,
                originalRequestVersion: $r->requireString('api:originalRequestVersion', $e),
                itemCount: (int) $r->requireString('api:itemCount', $e),
            ), $r->elements('api:transaction', $result)),
        );
    }

    public static function taxpayer(ResponseDocument $r): ?Taxpayer
    {
        $data = $r->element('/*/api:taxpayerData');
        if ($data === null) {
            return null;
        }

        return new Taxpayer(
            valid: $r->bool('/*/api:taxpayerValidity') ?? false,
            infoDate: $r->date('/*/api:infoDate'),
            name: $r->requireString('api:taxpayerName', $data),
            shortName: $r->string('api:taxpayerShortName', $data),
            taxpayerId: $r->requireString('api:taxNumberDetail/base:taxpayerId', $data),
            vatCode: $r->string('api:taxNumberDetail/base:vatCode', $data),
            countyCode: $r->string('api:taxNumberDetail/base:countyCode', $data),
            incorporation: Incorporation::from($r->requireString('api:incorporation', $data)),
            vatGroupMembership: $r->string('api:vatGroupMembership', $data),
            addresses: array_map(static fn (DOMElement $item): TaxpayerAddress => new TaxpayerAddress(
                TaxpayerAddressType::from($r->requireString('api:taxpayerAddressType', $item)),
                $r->requireString('api:taxpayerAddress/base:countryCode', $item),
                $r->string('api:taxpayerAddress/base:region', $item),
                $r->requireString('api:taxpayerAddress/base:postalCode', $item),
                $r->requireString('api:taxpayerAddress/base:city', $item),
                $r->requireString('api:taxpayerAddress/base:streetName', $item),
                $r->requireString('api:taxpayerAddress/base:publicPlaceCategory', $item),
                $r->string('api:taxpayerAddress/base:number', $item),
                $r->string('api:taxpayerAddress/base:building', $item),
                $r->string('api:taxpayerAddress/base:staircase', $item),
                $r->string('api:taxpayerAddress/base:floor', $item),
                $r->string('api:taxpayerAddress/base:door', $item),
                $r->string('api:taxpayerAddress/base:lotNumber', $item),
            ), $r->elements('api:taxpayerAddressList/api:taxpayerAddressItem', $data)),
        );
    }
}
