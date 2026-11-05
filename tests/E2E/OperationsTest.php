<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\E2E;

use DateTimeImmutable;
use IntrAgile\NavOnlineSzamla\Enums\AnnulmentCode;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceAppearance;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceOperation;
use IntrAgile\NavOnlineSzamla\Enums\PaymentMethod;
use IntrAgile\NavOnlineSzamla\Exceptions\NavApiException;
use IntrAgile\NavOnlineSzamla\Facades\Nav;
use IntrAgile\NavOnlineSzamla\Invoice\InvoiceBuilder;
use IntrAgile\NavOnlineSzamla\Invoice\LineItem;
use IntrAgile\NavOnlineSzamla\Invoice\Model\UnitOfMeasure;
use IntrAgile\NavOnlineSzamla\Invoice\Party;
use IntrAgile\NavOnlineSzamla\Invoice\Vat;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceChainQuery;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceDigestQuery;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceNumberQuery;
use IntrAgile\NavOnlineSzamla\Requests\TechnicalAnnulment;
use IntrAgile\NavOnlineSzamla\Responses\InvoiceDigest;
use IntrAgile\NavOnlineSzamla\Responses\Transaction;
use PHPUnit\Framework\Attributes\Group;

/**
 * Every NAV operation, executed against the test environment.
 */
#[Group('e2e')]
final class OperationsTest extends E2ETestCase
{
    public function test_token_exchange_and_taxpayer_queries(): void
    {
        $token = Nav::tokenExchange();
        $this->assertNotSame('', $token->token);
        $this->assertTrue($token->isValidAt(new DateTimeImmutable));

        $self = Nav::queryTaxpayer(Nav::client()->credentials->taxNumber);
        $this->assertNotNull($self);
        $this->assertSame(Nav::client()->credentials->taxNumber, $self->taxpayerId);

        $this->assertNull(Nav::queryTaxpayer('00000001'));
    }

    public function test_invalid_credentials_are_reported_as_authentication_error(): void
    {
        config(['nav-online-szamla.credentials.password' => 'definitely-wrong']);
        $this->app->forgetScopedInstances();

        try {
            Nav::tokenExchange();
            $this->fail('Expected NAV to reject the credentials.');
        } catch (NavApiException $e) {
            $this->assertTrue($e->isAuthenticationError(), (string) $e->errorCode);
            $this->assertFalse($e->isRetryable());
        }
    }

    public function test_create_then_every_query_finds_the_invoice(): void
    {
        $number = self::invoiceNumber();
        $submission = $this->invoice($number)
            ->line(LineItem::product('Hűtött házi sertés')->quantity('1500.00', UnitOfMeasure::Kilogram)->unitPrice('400.00')->vat(Vat::rate(5)))
            ->line(LineItem::product('Érlelt szalámi')->quantity('16', UnitOfMeasure::Kilogram)->unitPrice('3000')->vat(Vat::rate(27))->discount('Árengedmény', rate: '0.1'))
            ->line(LineItem::service('Kiszállítás')->netAmount('12500')->vat(Vat::rate(27)))
            ->submission();
        $since = new DateTimeImmutable('-1 minute');

        $transactionId = Nav::manageInvoice($submission);
        $result = $this->waitFor($transactionId, returnOriginalRequest: true);

        $this->assertAccepted($result);
        $this->assertSame($submission->invoiceXml, $result->originalRequest, 'NAV returns the submitted XML unchanged; reconciliation relies on it.');

        $this->assertTrue(Nav::queryInvoiceCheck(InvoiceNumberQuery::outbound($number)));
        $this->assertFalse(Nav::queryInvoiceCheck(InvoiceNumberQuery::outbound('NO-SUCH-'.uniqid())));

        $data = Nav::queryInvoiceData(InvoiceNumberQuery::outbound($number));
        $this->assertNotNull($data);
        $this->assertSame($transactionId, $data->auditData->transactionId);
        $this->assertSame($number, $data->invoice()->invoiceNumber);
        $this->assertNull(Nav::queryInvoiceData(InvoiceNumberQuery::outbound('NO-SUCH-'.uniqid())));

        $digests = Nav::invoiceDigests(InvoiceDigestQuery::insertedBetween($since, new DateTimeImmutable('+1 minute')));
        $this->assertTrue($digests->contains(fn (InvoiceDigest $d) => $d->invoiceNumber === $number));

        $chain = Nav::queryInvoiceChainDigest(new InvoiceChainQuery($number));
        $this->assertSame(InvoiceOperation::Create, $chain->items[0]->operation ?? null);

        $transactions = Nav::transactions($since, new DateTimeImmutable);
        $this->assertTrue($transactions->contains(fn (Transaction $t) => $t->transactionId === $transactionId));
    }

    public function test_builder_variants_are_accepted(): void
    {
        $this->assertAccepted($this->submitAndWait(
            $this->invoice(self::invoiceNumber('-EUR'))->currency('EUR', '392.17')
                ->line(LineItem::product('Termék EUR')->quantity(3, UnitOfMeasure::Piece)->unitPrice('12.99')->vat(Vat::rate(27))),
        ));

        $this->assertAccepted($this->submitAndWait(
            $this->invoice(self::invoiceNumber('-S'))->simplified()->customer(Party::privatePerson())->payment(PaymentMethod::Cash)
                ->line(LineItem::product('Kenyér')->quantity(2, UnitOfMeasure::Piece)->unitPrice('650')->vat(Vat::rate(5)))
                ->line(LineItem::product('Ajándéktárgy')->quantity(1, UnitOfMeasure::Piece)->unitPrice('2540')->vat(Vat::rate(27))),
        ));
    }

    public function test_storno_and_modification_of_accepted_invoices(): void
    {
        $number = self::invoiceNumber();
        $this->assertAccepted($this->submitAndWait(
            $this->invoice($number)->line(LineItem::product('Termék')->quantity(2, UnitOfMeasure::Piece)->unitPrice('1000')->vat(Vat::rate(27))),
        ));

        $modification = InvoiceBuilder::modify($number.'-M', $number, 1)
            ->issueDate(new DateTimeImmutable('today'))->appearance(InvoiceAppearance::Electronic)->supplier($this->supplier())->customer($this->customer())->previousLineCount(1)
            ->line(LineItem::product('Termék, árkorrekció')->quantity(2, UnitOfMeasure::Piece)->unitPrice('-100')->vat(Vat::rate(27)));
        $this->assertAccepted($this->submitAndWait($modification));

        $original = Nav::queryInvoiceData(InvoiceNumberQuery::outbound($number))?->invoice();
        $this->assertNotNull($original);
        $this->assertAccepted($this->submitAndWait(InvoiceBuilder::stornoOf($original, $number.'-S', 2, new DateTimeImmutable('today'), previousLineCount: 2)));
    }

    public function test_technical_annulment_is_received(): void
    {
        $number = self::invoiceNumber();
        $this->assertAccepted($this->submitAndWait(
            $this->invoice($number)->line(LineItem::product('Termék')->quantity(1, UnitOfMeasure::Piece)->unitPrice('1000')->vat(Vat::rate(27))),
        ));

        $transactionId = Nav::manageAnnulment(new TechnicalAnnulment($number, AnnulmentCode::ErraticData, 'Automated E2E test invoice'));

        // Annulments wait for approval on the web interface, so only acceptance of the request is checked.
        $this->assertAccepted($this->waitFor($transactionId));
    }
}
