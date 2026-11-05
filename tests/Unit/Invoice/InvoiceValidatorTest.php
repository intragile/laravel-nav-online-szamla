<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Unit\Invoice;

use Closure;
use DateTimeImmutable;
use IntrAgile\NavOnlineSzamla\Crypto\HashAlgorithm;
use IntrAgile\NavOnlineSzamla\Crypto\Signer;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceAppearance;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceOperation;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceValidationException;
use IntrAgile\NavOnlineSzamla\Invoice\BatchModification;
use IntrAgile\NavOnlineSzamla\Invoice\InvoiceBuilder;
use IntrAgile\NavOnlineSzamla\Invoice\LineItem;
use IntrAgile\NavOnlineSzamla\Invoice\Model\Address;
use IntrAgile\NavOnlineSzamla\Invoice\Model\CustomerInfo;
use IntrAgile\NavOnlineSzamla\Invoice\Model\CustomerVatData;
use IntrAgile\NavOnlineSzamla\Invoice\Model\CustomerVatStatus;
use IntrAgile\NavOnlineSzamla\Invoice\Model\UnitOfMeasure;
use IntrAgile\NavOnlineSzamla\Invoice\Party;
use IntrAgile\NavOnlineSzamla\Invoice\Validation\InvoiceValidator;
use IntrAgile\NavOnlineSzamla\Invoice\Validation\Violation;
use IntrAgile\NavOnlineSzamla\Invoice\Vat;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceSubmission;
use IntrAgile\NavOnlineSzamla\Support\Payload;
use IntrAgile\NavOnlineSzamla\Tests\Support\InvoiceFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InvoiceValidatorTest extends TestCase
{
    private const NOW = '2026-11-05';

    /**
     * @return list<string>
     */
    private static function codes(InvoiceBuilder $builder, ?InvoiceOperation $as = null, ?string $authenticated = null): array
    {
        $data = $builder->validateWith(null)->build();

        return array_map(
            static fn (Violation $v): string => $v->code,
            (new InvoiceValidator)->validate($data, $as ?? $builder->operation(), new DateTimeImmutable(self::NOW), $authenticated),
        );
    }

    private static function address(): Address
    {
        return Party::address('HU', '1011', 'Budapest', 'Fő utca 1.');
    }

    private static function modification(string $number = 'INV-1-M', string $original = 'INV-1', int $index = 1): InvoiceBuilder
    {
        return InvoiceBuilder::modify($number, $original, $index)
            ->issueDate(self::NOW)
            ->appearance(InvoiceAppearance::Electronic)
            ->supplier(InvoiceFactory::domestic()->buildInvoice()->invoiceHead->supplierInfo)
            ->previousLineCount(1);
    }

    public function test_a_regular_invoice_has_no_violations(): void
    {
        $this->assertSame([], self::codes(InvoiceFactory::domestic()->line(InvoiceFactory::product())));
    }

    /**
     * @return iterable<string, array{string, Closure(): InvoiceBuilder}>
     */
    public static function violations(): iterable
    {
        $d = static fn (): InvoiceBuilder => InvoiceFactory::domestic()->line(InvoiceFactory::product());
        $line = static fn (LineItem $item): InvoiceBuilder => InvoiceFactory::domestic()->line($item);

        yield 'invoice number whitespace' => ['INVALID_INVOICE_NUMBER', static fn () => InvoiceFactory::domestic(' INV-1')->line(InvoiceFactory::product())];
        yield 'no lines on create' => ['INVOICE_LINE_MISSING', static fn () => InvoiceFactory::domestic()];
        yield 'private person with name' => ['CUSTOMER_DATA_NOT_EXPECTED', static fn () => $d()->customer(new CustomerInfo(CustomerVatStatus::PrivatePerson, customerName: 'Kiss Anna'))];
        yield 'company without address' => ['CUSTOMER_DATA_EXPECTED', static fn () => $d()->customer(new CustomerInfo(CustomerVatStatus::Other, customerName: 'X GmbH'))];
        yield 'domestic customer without tax number' => ['MISSING_CUSTOMER_DOMESTIC_TAXNUMBER', static fn () => $d()->customer(new CustomerInfo(CustomerVatStatus::Domestic, customerName: 'Vevő', customerAddress: self::address()))];
        yield 'domestic customer with EU number' => ['CUSTOMER_COMMUNITY_TAXNUMBER_NOT_EXPECTED', static fn () => $d()->customer(new CustomerInfo(CustomerVatStatus::Domestic, new CustomerVatData(communityVatNumber: 'ATU12345678'), 'Vevő', self::address()))];
        yield 'domestic customer with third state id' => ['CUSTOMER_THIRD_STATE_TAXNUMBER_NOT_EXPECTED', static fn () => $d()->customer(new CustomerInfo(CustomerVatStatus::Domestic, new CustomerVatData(thirdStateTaxId: 'US-1'), 'Vevő', self::address()))];
        yield 'unknown VAT rate' => ['INVALID_VAT_DATA', static fn () => $line(InvoiceFactory::product(rate: '0.15'))];
        yield 'legacy 25 % on a new invoice' => ['INVALID_VAT_DATA', static fn () => $line(InvoiceFactory::product(rate: '0.25'))];
        yield '0 % before 2024' => ['INVALID_VAT_DATA', static fn () => $line(InvoiceFactory::product(rate: '0'))->deliveryDate('2023-12-31')];
        yield 'vat content on normal invoice' => ['INVALID_LINE_VAT_RATE_NORMAL', static fn () => $line(InvoiceFactory::product()->vat(Vat::content('0.2126')))];
        yield 'vat content in normal summary' => ['INVALID_SUMMARY_VAT_RATE_NORMAL', static fn () => $line(InvoiceFactory::product()->vat(Vat::content('0.2126')))];
        yield 'unknown exemption code' => ['INVALID_LINE_VAT_EXEMPTION_CODE', static fn () => $line(InvoiceFactory::product()->vat(Vat::exempt('XYZ', 'x')))];
        yield 'unknown exemption code in summary' => ['INVALID_SUMMARY_VAT_EXEMPTION_CODE', static fn () => $line(InvoiceFactory::product()->vat(Vat::exempt('XYZ', 'x')))];
        yield 'UNKNOWN exemption on create' => ['INVALID_LINE_VAT_EXEMPTION_CODE', static fn () => $line(InvoiceFactory::product()->vat(Vat::exempt('UNKNOWN', 'x')))];
        yield 'unknown out of scope code' => ['INVALID_LINE_VAT_OUT_OF_SCOPE_CODE', static fn () => $line(InvoiceFactory::product()->vat(Vat::outOfScope('XYZ', 'x')))];
        yield 'unknown mismatch code' => ['INVALID_LINE_VAT_AMOUNT_MISMATCH_CODE', static fn () => $line(InvoiceFactory::product()->vat(Vat::amountMismatch(27, 'XYZ')))];
        yield 'mismatch with unknown rate' => ['INVALID_LINE_VAT_AMOUNT_MISMATCH_VAT_RATE', static fn () => $line(InvoiceFactory::product()->vat(Vat::amountMismatch(12, 'REFUNDABLE_VAT')))];
        yield 'KBAET to domestic customer' => ['INCORRECT_LINE_DATA_VAT_STATUS_VAT_DATA_MISMATCH_KBAET', static fn () => $line(InvoiceFactory::product()->vat(Vat::exempt('KBAET', 'x')))];
        yield 'KBAUK to domestic customer' => ['INCORRECT_LINE_DATA_VAT_STATUS_VAT_DATA_MISMATCH_KBAUK', static fn () => $line(InvoiceFactory::product()->vat(Vat::exempt('KBAUK', 'x')))];
        yield 'EUFAD37 to domestic customer' => ['INCORRECT_LINE_DATA_VAT_STATUS_VAT_DATA_MISMATCH_EUFAD37', static fn () => $line(InvoiceFactory::product()->vat(Vat::outOfScope('EUFAD37', 'x')))];
        yield 'EUFADE to domestic customer' => ['INCORRECT_LINE_DATA_VAT_STATUS_VAT_DATA_MISMATCH_EUFADE', static fn () => $line(InvoiceFactory::product()->vat(Vat::outOfScope('EUFADE', 'x')))];
        yield 'reverse charge to foreign customer' => ['INCORRECT_LINE_DATA_VAT_DOMESTIC_REVERSE_CHARGE', static fn () => $line(InvoiceFactory::product()->vat(Vat::domesticReverseCharge()))->customer(Party::euCustomer('ATU12345678', 'X GmbH', self::address()))];
        yield 'reverse charge without tax number' => ['DOMESTIC_TAXNUMBER_EXPECTED_REVERSE_CHARGE', static fn () => $line(InvoiceFactory::product()->vat(Vat::domesticReverseCharge()))->customer(Party::euCustomer('ATU12345678', 'X GmbH', self::address()))];
        yield 'exempt line with VAT' => ['INCORRECT_LINE_DATA_VAT_EXEMPTION_NORMAL', static fn () => $line(InvoiceFactory::product()->vat(Vat::exempt('AAM', 'x'))->vatAmount('10'))];
        yield 'exempt summary with VAT' => ['INCORRECT_SUMMARY_DATA_VAT_EXEMPTION_NORMAL', static fn () => $line(InvoiceFactory::product()->vat(Vat::exempt('AAM', 'x'))->vatAmount('10'))];
        yield 'out of scope line with VAT' => ['INCORRECT_LINE_DATA_VAT_OUT_OF_SCOPE_NORMAL', static fn () => $line(InvoiceFactory::product()->vat(Vat::outOfScope('ATK', 'x'))->vatAmount('10'))];
        yield 'out of scope summary with VAT' => ['INCORRECT_SUMMARY_DATA_VAT_OUT_OF_SCOPE_NORMAL', static fn () => $line(InvoiceFactory::product()->vat(Vat::outOfScope('ATK', 'x'))->vatAmount('10'))];
        yield 'own unit without name' => ['INCORRECT_LINE_DATA_UOM_INCOMPLETE', static fn () => $line(LineItem::product('Kábel')->quantity(3, UnitOfMeasure::Own)->unitPrice(10)->vat(Vat::rate(27)))];
        yield 'line without description' => ['MANDATORY_LINE_CONTENT_MISSING', static fn () => $line(LineItem::of(null)->netAmount(10)->vat(Vat::rate(27)))];
        yield 'aggregate line without delivery data' => ['INCORRECT_LINE_DATA_AGGREGATE_INV_LINE_DATA_MANDATORY', static fn () => $d()->aggregate()];
        yield 'HUF with rate 2' => ['INCORRECT_HEAD_DATA_CURRENCY_CODE_HUF', static fn () => $d()->currency('HUF', 2)];
        yield 'extreme EUR rate' => ['INCORRECT_HEAD_DATA_EXCHANGERATE_EXTREME', static fn () => $d()->currency('EUR', 100)];
        yield 'reversed delivery period' => ['INCORRECT_DATE_INVOICE_DELIVERY_TO_FROM', static fn () => $d()->deliveryPeriod('2026-10-31', '2026-10-01')];
        yield 'delivery 6 years after issue' => ['INVOICE_DELIVERY_DATE_LATE', static fn () => $d()->deliveryDate('2032-11-06')];
        yield 'issue date 2 years ahead' => ['INVOICE_ISSUE_DATE_LATE', static fn () => $d()->issueDate('2028-11-06')];
        yield 'predecessor reference on create' => ['INVALID_PREDECESSOR_OPERATION', static fn () => $d()->additionalData('A10000_JOGELOD_ADOSZAM', 'Jogelőd', '11111111')];
        yield 'two predecessor codes' => ['INVALID_PREDECESSOR_OPERATION', static fn () => self::modification()->line(InvoiceFactory::product())->additionalData('A10000_JOGELOD_ADOSZAM', 'a', '1')->additionalData('A10000_JOGELOD_IDOSZAK', 'b', '2')];
        yield 'modification of itself' => ['INCORRECT_HEAD_DATA_MOD_REF_INVOICE_NUMBER', static fn () => self::modification('INV-1', 'INV-1')->line(InvoiceFactory::product())];
        yield 'modification index above 1000' => ['INCONSISTENT_MODIFICATION_DATA_MODIFICATIONINDEX_UNREAL', static fn () => self::modification(index: 1001)->line(InvoiceFactory::product())];
        yield 'original number whitespace' => ['INVALID_ORIGINAL_INVOICE_NUMBER', static fn () => self::modification(original: 'INV-1 ')->line(InvoiceFactory::product())];
        yield 'duplicate line reference' => ['LINE_NUMBER_REFERENCE_NOT_UNIQUE', static fn () => self::modification()->line(InvoiceFactory::product()->lineNumberReference(5), InvoiceFactory::product()->lineNumberReference(5))];
        yield 'completeness not allowed' => ['INVOICE_COMPLETENESS_NOT_ALLOWED', static fn () => $d()->completeElectronicInvoice()];
        yield 'completeness on paper' => ['INVOICE_APPEARANCE_MISMATCH', static fn () => $d()->completeElectronicInvoice()->appearance(InvoiceAppearance::Paper)];
        yield 'completeness to private person' => ['INVOICE_COMPLETENESS_PRIVATE_PERSON_INDICATOR_MISMATCH', static fn () => $d()->completeElectronicInvoice()->customer(Party::privatePerson())];
    }

    /**
     * @param  Closure(): InvoiceBuilder  $builder
     */
    #[DataProvider('violations')]
    public function test_detects_blocking_rule(string $code, Closure $builder): void
    {
        $this->assertContains($code, self::codes($builder()));
    }

    public function test_create_data_submitted_as_modification(): void
    {
        $codes = self::codes(InvoiceFactory::domestic()->line(InvoiceFactory::product()), InvoiceOperation::Modify);

        $this->assertContains('INVOICE_REFERENCE_EXPECTED', $codes);
        $this->assertContains('LINE_MODIFICATION_EXPECTED', $codes);
    }

    public function test_modification_data_submitted_as_create(): void
    {
        $codes = self::codes(self::modification()->line(InvoiceFactory::product()), InvoiceOperation::Create);

        $this->assertContains('INVOICE_REFERENCE_NOT_EXPECTED', $codes);
        $this->assertContains('LINE_MODIFICATION_NOT_EXPECTED', $codes);
        $this->assertContains('CUSTOMER_INFO_MISSING', $codes);
    }

    public function test_zero_rate_and_legacy_rates_where_allowed(): void
    {
        $this->assertSame([], self::codes(InvoiceFactory::domestic()->line(InvoiceFactory::product(rate: '0'))));
        $this->assertSame([], self::codes(self::modification()->line(InvoiceFactory::product(rate: '0.25'))));
        $this->assertSame([], self::codes(self::modification()->line(InvoiceFactory::product()->vat(Vat::exempt('UNKNOWN', 'x')))));
    }

    public function test_supplier_must_match_the_technical_user(): void
    {
        $builder = InvoiceFactory::domestic()->line(InvoiceFactory::product());

        $this->assertContains('SUPPLIER_TAX_NUMBER_MISMATCH', self::codes($builder, authenticated: '11111111-2-41'));
        $this->assertSame([], self::codes(InvoiceFactory::domestic()->line(InvoiceFactory::product()), authenticated: '12345678'));
    }

    public function test_rules_can_be_skipped(): void
    {
        $data = InvoiceFactory::domestic()->line(InvoiceFactory::product())->completeElectronicInvoice()->validateWith(null)->build();
        $validator = (new InvoiceValidator)->skip('INVOICE_COMPLETENESS_NOT_ALLOWED');

        $this->assertSame([], $validator->validate($data, InvoiceOperation::Create, new DateTimeImmutable(self::NOW)));
    }

    public function test_assert_valid_throws_with_all_violations(): void
    {
        $data = InvoiceFactory::domestic(' X ')->validateWith(null)->build();

        try {
            (new InvoiceValidator)->assertValid($data, InvoiceOperation::Create, new DateTimeImmutable(self::NOW));
            $this->fail('Expected a validation exception.');
        } catch (InvoiceValidationException $e) {
            $this->assertSame(['INVALID_INVOICE_NUMBER', 'INVOICE_LINE_MISSING'], $e->codes());
            $this->assertStringContainsString('invoiceMain/invoice/invoiceLines', $e->getMessage());
        }
    }

    public function test_electronic_invoice_hash_rules(): void
    {
        $data = InvoiceFactory::domestic()->line(InvoiceFactory::product())->completeElectronicInvoice()->validateWith(null)->build();
        $validator = (new InvoiceValidator)->skip('INVOICE_COMPLETENESS_NOT_ALLOWED');
        $xml = $data->toXml();
        $codes = static fn (InvoiceSubmission $s): array => array_map(static fn (Violation $v): string => $v->code, $validator->validateSubmission($s, new DateTimeImmutable(self::NOW)));

        $this->assertSame(['ELECTRONIC_INVOICE_HASH_EXPECTED'], $codes(new InvoiceSubmission(InvoiceOperation::Create, $xml)));
        $this->assertSame(['INVALID_INVOICE_HASH'], $codes(new InvoiceSubmission(InvoiceOperation::Create, $xml, str_repeat('A', 128))));
        $this->assertSame(['INVALID_INVOICE_HASH_CRYPTO', 'INVALID_INVOICE_HASH'], $codes(new InvoiceSubmission(InvoiceOperation::Create, $xml, 'ab', HashAlgorithm::Sha256)));

        $hash = Signer::electronicInvoiceHash(Payload::encode($xml, false));
        $this->assertSame([], $codes(new InvoiceSubmission(InvoiceOperation::Create, $xml, $hash)));
    }

    public function test_batch_rules(): void
    {
        $other = Party::supplier('22222222-2-41', 'Másik Kft.', self::address());
        $first = self::modification('B-1', 'INV-1')->line(InvoiceFactory::product())->customer(Party::domesticCustomer('87654321-2-13', 'Vevő', self::address()));
        $second = self::modification('B-1', 'INV-2')->line(InvoiceFactory::product())->supplier($other)->customer(Party::domesticCustomer('99999999-2-13', 'Más vevő', self::address()));

        $data = BatchModification::of('B-1', self::NOW)->add($first)->add($second)->validateWith(null)->build();
        $codes = array_map(static fn (Violation $v): string => $v->code, (new InvoiceValidator)->validate($data, InvoiceOperation::Modify, new DateTimeImmutable(self::NOW)));

        $this->assertContains('CUSTOMER_NOT_IDENTICAL', $codes);
        $this->assertContains('SUPPLIER_NOT_IDENTICAL', $codes);
    }
}
