<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice;

use IntrAgile\NavOnlineSzamla\Invoice\Model\Address;
use IntrAgile\NavOnlineSzamla\Invoice\Model\CustomerInfo;
use IntrAgile\NavOnlineSzamla\Invoice\Model\CustomerTaxNumber;
use IntrAgile\NavOnlineSzamla\Invoice\Model\CustomerVatData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\CustomerVatStatus;
use IntrAgile\NavOnlineSzamla\Invoice\Model\DetailedAddress;
use IntrAgile\NavOnlineSzamla\Invoice\Model\SimpleAddress;
use IntrAgile\NavOnlineSzamla\Invoice\Model\SupplierInfo;
use IntrAgile\NavOnlineSzamla\Invoice\Model\TaxNumber;
use InvalidArgumentException;

/**
 * Factories for supplier, customer and address data.
 */
final class Party
{
    /**
     * Parses a Hungarian tax number: "12345678", "12345678-2-41" or "12345678241".
     */
    public static function taxNumber(string $taxNumber): TaxNumber
    {
        if (preg_match('/^(\d{8})(?:-?(\d)-?(\d{2}))?$/', trim($taxNumber), $m) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a Hungarian tax number.', $taxNumber));
        }

        return new TaxNumber($m[1], $m[2] ?? null, $m[3] ?? null);
    }

    public static function address(string $countryCode, string $postalCode, string $city, string $addressLine, ?string $region = null): Address
    {
        return new Address(simpleAddress: new SimpleAddress($countryCode, $postalCode, $city, $addressLine, $region));
    }

    public static function detailedAddress(
        string $countryCode,
        string $postalCode,
        string $city,
        string $streetName,
        string $publicPlaceCategory,
        ?string $number = null,
        ?string $building = null,
        ?string $staircase = null,
        ?string $floor = null,
        ?string $door = null,
        ?string $lotNumber = null,
        ?string $region = null,
    ): Address {
        return new Address(detailedAddress: new DetailedAddress(
            countryCode: $countryCode,
            postalCode: $postalCode,
            city: $city,
            streetName: $streetName,
            publicPlaceCategory: $publicPlaceCategory,
            region: $region,
            number: $number,
            building: $building,
            staircase: $staircase,
            floor: $floor,
            door: $door,
            lotNumber: $lotNumber,
        ));
    }

    /**
     * @param  string|null  $groupMemberTaxNumber  Tax number of the VAT group member issuing the invoice.
     */
    public static function supplier(
        string $taxNumber,
        string $name,
        Address $address,
        ?string $bankAccountNumber = null,
        ?string $communityVatNumber = null,
        ?string $groupMemberTaxNumber = null,
        ?bool $individualExemption = null,
        ?string $exciseLicenceNumber = null,
    ): SupplierInfo {
        return new SupplierInfo(
            supplierTaxNumber: self::taxNumber($taxNumber),
            supplierName: $name,
            supplierAddress: $address,
            groupMemberTaxNumber: $groupMemberTaxNumber === null ? null : self::taxNumber($groupMemberTaxNumber),
            communityVatNumber: $communityVatNumber,
            supplierBankAccountNumber: $bankAccountNumber,
            individualExemption: $individualExemption,
            exciseLicenceNum: $exciseLicenceNumber,
        );
    }

    /**
     * Customer registered for VAT in Hungary.
     */
    public static function domesticCustomer(string $taxNumber, string $name, Address $address, ?string $bankAccountNumber = null, ?string $groupMemberTaxNumber = null): CustomerInfo
    {
        $tax = self::taxNumber($taxNumber);

        return new CustomerInfo(
            customerVatStatus: CustomerVatStatus::Domestic,
            customerVatData: new CustomerVatData(customerTaxNumber: new CustomerTaxNumber(
                taxpayerId: $tax->taxpayerId,
                vatCode: $tax->vatCode,
                countyCode: $tax->countyCode,
                groupMemberTaxNumber: $groupMemberTaxNumber === null ? null : self::taxNumber($groupMemberTaxNumber),
            )),
            customerName: $name,
            customerAddress: $address,
            customerBankAccountNumber: $bankAccountNumber,
        );
    }

    /**
     * Customer with a VAT number of another EU member state, e.g. "ATU12345678".
     */
    public static function euCustomer(string $communityVatNumber, string $name, Address $address, ?string $bankAccountNumber = null): CustomerInfo
    {
        return new CustomerInfo(
            customerVatStatus: CustomerVatStatus::Other,
            customerVatData: new CustomerVatData(communityVatNumber: $communityVatNumber),
            customerName: $name,
            customerAddress: $address,
            customerBankAccountNumber: $bankAccountNumber,
        );
    }

    /**
     * Customer with a tax number of a country outside the EU.
     */
    public static function thirdStateCustomer(string $taxId, string $name, Address $address, ?string $bankAccountNumber = null): CustomerInfo
    {
        return new CustomerInfo(
            customerVatStatus: CustomerVatStatus::Other,
            customerVatData: new CustomerVatData(thirdStateTaxId: $taxId),
            customerName: $name,
            customerAddress: $address,
            customerBankAccountNumber: $bankAccountNumber,
        );
    }

    /**
     * Non-private customer without a tax number (e.g. a foreign company or a domestic association).
     */
    public static function otherCustomer(string $name, Address $address, ?string $bankAccountNumber = null): CustomerInfo
    {
        return new CustomerInfo(
            customerVatStatus: CustomerVatStatus::Other,
            customerName: $name,
            customerAddress: $address,
            customerBankAccountNumber: $bankAccountNumber,
        );
    }

    /**
     * Private individual. NAV does not accept name, address or tax data for private persons.
     */
    public static function privatePerson(): CustomerInfo
    {
        return new CustomerInfo(customerVatStatus: CustomerVatStatus::PrivatePerson);
    }
}
