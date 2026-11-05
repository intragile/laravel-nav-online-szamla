<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Feature\Laravel;

use DateTimeImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use IntrAgile\NavOnlineSzamla\Enums\AnnulmentCode;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceStatus;
use IntrAgile\NavOnlineSzamla\Enums\TransactionState;
use IntrAgile\NavOnlineSzamla\Events\InvoiceAccepted;
use IntrAgile\NavOnlineSzamla\Events\InvoiceRejected;
use IntrAgile\NavOnlineSzamla\Events\TransactionReconciled;
use IntrAgile\NavOnlineSzamla\Exceptions\NavApiException;
use IntrAgile\NavOnlineSzamla\Exceptions\XsdValidationException;
use IntrAgile\NavOnlineSzamla\Facades\Nav;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceData;
use IntrAgile\NavOnlineSzamla\NavClient;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceDigestQuery;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceNumberQuery;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceSubmission;
use IntrAgile\NavOnlineSzamla\Requests\TechnicalAnnulment;
use IntrAgile\NavOnlineSzamla\Responses\Page;
use IntrAgile\NavOnlineSzamla\Testing\FakeNavClient;
use IntrAgile\NavOnlineSzamla\Testing\NavFake;
use IntrAgile\NavOnlineSzamla\Testing\RecordedCall;
use IntrAgile\NavOnlineSzamla\Testing\SubmittedInvoice;
use IntrAgile\NavOnlineSzamla\Tests\Support\InvoiceFactory;
use LogicException;
use PHPUnit\Framework\ExpectationFailedException;

final class NavFakeTest extends IntegrationTestCase
{
    private function invoice(string $number = 'INV-1'): InvoiceSubmission
    {
        return InvoiceFactory::domestic($number)->line(InvoiceFactory::product())->submission();
    }

    public function test_fake_replaces_the_manager_and_the_client_binding(): void
    {
        $fake = Nav::fake();

        $this->assertInstanceOf(NavFake::class, $fake);
        $this->assertInstanceOf(FakeNavClient::class, $this->app->make(NavClient::class));
        $this->assertInstanceOf(FakeNavClient::class, Nav::client('acme'));
    }

    public function test_direct_submission_is_recorded_and_stored(): void
    {
        Http::fake();
        Nav::fake();

        $transactionId = Nav::manageInvoice($this->invoice());

        Nav::assertInvoiceSubmitted('INV-1');
        Nav::assertInvoiceSubmitted(fn (InvoiceData $invoice, SubmittedInvoice $s) => $invoice->invoiceNumber === 'INV-1' && $s->tenant === null);
        Nav::assertInvoiceNotSubmitted('INV-2');
        Nav::assertSubmittedCount(1);
        Nav::assertSent('manageInvoice');

        $status = Nav::queryTransactionStatus($transactionId);
        $this->assertTrue($status->isFinished());
        $this->assertSame(InvoiceStatus::Done, $status->results[0]->status);
        $this->assertNull($status->results[0]->originalRequest);
        $this->assertTrue(Nav::queryInvoiceCheck(InvoiceNumberQuery::outbound('INV-1')));
        $this->assertFalse(Nav::queryInvoiceCheck(InvoiceNumberQuery::outbound('INV-2')));
        $this->assertSame('INV-1', Nav::queryInvoiceData(InvoiceNumberQuery::outbound('INV-1'))?->invoice()->invoiceNumber);
        Http::assertNothingSent();
    }

    public function test_queued_submission_runs_the_whole_lifecycle_without_delays(): void
    {
        config(['queue.default' => 'sync']);
        Event::fake([InvoiceAccepted::class]);
        Nav::fake();

        $started = microtime(true);
        $transaction = Nav::submit(InvoiceFactory::domestic()->line(InvoiceFactory::product()));

        $transaction->refresh();
        $this->assertSame(TransactionState::Finished, $transaction->state);
        $this->assertTrue($transaction->isAccepted());
        $this->assertStringStartsWith('FAKE', (string) $transaction->transaction_id);
        $this->assertLessThan(2.0, microtime(true) - $started);
        Event::assertDispatched(InvoiceAccepted::class);
        Nav::assertInvoiceSubmitted('INV-1');
        Nav::assertSubmittedCount(1);
    }

    public function test_queued_submission_without_a_worker_is_still_recorded(): void
    {
        Nav::fake();

        $transaction = Nav::submit(InvoiceFactory::domestic()->line(InvoiceFactory::product()));

        $this->assertSame(TransactionState::Pending, $transaction->state);
        Nav::assertInvoiceSubmitted(fn (InvoiceData $i, SubmittedInvoice $s) => $s->transaction?->is($transaction) === true);
        Nav::assertNothingSent();
    }

    public function test_rejected_invoices_are_aborted_by_the_fake_nav(): void
    {
        config(['queue.default' => 'sync']);
        Event::fake([InvoiceRejected::class]);
        Nav::fake()->rejectInvoice('INV-1', 'INVOICE_LINE_MISSING', 'No lines.');

        $transaction = Nav::submit(InvoiceFactory::domestic()->line(InvoiceFactory::product()));

        $item = $transaction->items()->sole();
        $this->assertTrue($item->isRejected());
        $this->assertSame('INVOICE_LINE_MISSING', $item->business_messages[0]['errorCode'] ?? null);
        Event::assertDispatched(InvoiceRejected::class);
    }

    public function test_warnings_and_duplicate_invoice_numbers(): void
    {
        Nav::fake()->warnInvoice('INV-1', 'INCORRECT_HEAD_DATA_CUSTOMER_TAX_NUMBER');

        $first = Nav::queryTransactionStatus(Nav::manageInvoice($this->invoice()));
        $second = Nav::queryTransactionStatus(Nav::manageInvoice($this->invoice()));

        $this->assertTrue($first->results[0]->isAccepted());
        $this->assertSame('INCORRECT_HEAD_DATA_CUSTOMER_TAX_NUMBER', $first->results[0]->warnings()[0]->errorCode);
        $this->assertTrue($second->results[0]->isRejected());
        $this->assertSame('INVOICE_NUMBER_NOT_UNIQUE', $second->results[0]->errors()[0]->errorCode);
    }

    public function test_a_lost_response_is_reconciled_and_resubmitted_through_the_fake(): void
    {
        config(['queue.default' => 'sync']);
        Event::fake([TransactionReconciled::class]);
        Nav::fake()->failNext('manageInvoice', NavFake::connectionFailure());

        $transaction = Nav::submit(InvoiceFactory::domestic()->line(InvoiceFactory::product()));

        $transaction->refresh();
        $this->assertSame(TransactionState::Finished, $transaction->state);
        $this->assertSame(2, $transaction->submission_count);
        Event::assertDispatched(TransactionReconciled::class, fn (TransactionReconciled $e) => ! $e->found);
        Nav::assertSentCount('manageInvoice', 2);
        Nav::assertSent('queryTransactionList');
        Nav::assertSubmittedCount(1);
    }

    public function test_failures_are_thrown_once(): void
    {
        Nav::fake()->failNext('queryTaxpayer', NavFake::apiError('INVALID_SECURITY_USER', 'queryTaxpayer'));

        try {
            Nav::queryTaxpayer('12345678');
            $this->fail('Expected a NAV error.');
        } catch (NavApiException $e) {
            $this->assertTrue($e->isAuthenticationError());
        }

        $this->assertNull(Nav::queryTaxpayer('12345678'));
    }

    public function test_taxpayer_lookups(): void
    {
        Nav::fake()
            ->taxpayer('12345678-2-41', 'Acme Kft.')
            ->taxpayer('99999999', null);

        $taxpayer = Nav::queryTaxpayer('12345678');

        $this->assertSame('Acme Kft.', $taxpayer?->name);
        $this->assertSame('12345678-2-41', $taxpayer->taxNumber());
        $this->assertNotNull($taxpayer->headquarters());
        $this->assertNull(Nav::queryTaxpayer('99999999'));
        $this->assertNull(Nav::queryTaxpayer('11111111'));
        Nav::assertTaxpayerQueried('12345678-2-41');
        Nav::assertTaxpayerQueried();
        Nav::assertSentCount('queryTaxpayer', 3);
    }

    public function test_custom_responses(): void
    {
        Nav::fake()->respond('queryInvoiceDigest', fn (InvoiceDigestQuery $query, int $page): Page => new Page($page, 3, []));

        $page = Nav::queryInvoiceDigest(InvoiceDigestQuery::issuedBetween(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-05')), 2);

        $this->assertSame(2, $page->currentPage);
        $this->assertTrue($page->hasMorePages());
        Nav::assertSent('queryInvoiceDigest', fn (RecordedCall $call) => $call->arguments[1] === 2);
    }

    public function test_custom_responses_must_have_the_operation_type(): void
    {
        Nav::fake()->respond('queryInvoiceCheck', fn () => 'yes');

        $this->expectException(LogicException::class);

        Nav::queryInvoiceCheck(InvoiceNumberQuery::outbound('INV-1'));
    }

    public function test_tenants_are_kept_apart(): void
    {
        $fake = Nav::fake();

        Nav::client('acme')->manageInvoice($this->invoice('ACME-1'));

        $this->assertSame('87654321', Nav::client('acme')->credentials->taxNumber);
        Nav::assertInvoiceSubmitted('ACME-1', 'acme');
        Nav::assertInvoiceNotSubmitted('ACME-1', 'other');
        $this->assertFalse(Nav::queryInvoiceCheck(InvoiceNumberQuery::outbound('ACME-1')));
        $this->assertTrue(Nav::client('acme')->queryInvoiceCheck(InvoiceNumberQuery::outbound('ACME-1')));
        $this->assertCount(1, $fake->recorded('manageInvoice', fn (RecordedCall $c) => $c->tenant === 'acme'));
    }

    public function test_works_without_configured_credentials(): void
    {
        config(['nav-online-szamla.credentials' => [], 'nav-online-szamla.software.id' => null]);

        $fake = Nav::fake();
        $this->assertSame('12345678', Nav::client()->credentials->taxNumber);
        $this->assertSame('HU00000000-FAKE000', Nav::client()->software->id);

        $fake->withTaxNumber('11111111-2-42', 'shop');
        $this->assertSame('11111111', Nav::client('shop')->credentials->taxNumber);
    }

    public function test_annulments(): void
    {
        Nav::fake();

        Nav::manageAnnulment(new TechnicalAnnulment('INV-1', AnnulmentCode::ErraticData, 'Wrong'));

        Nav::assertAnnulmentSubmitted('INV-1');
        Nav::assertAnnulmentSubmitted(fn (TechnicalAnnulment $a) => $a->code === AnnulmentCode::ErraticData);
        Nav::assertInvoiceNotSubmitted();
    }

    public function test_outgoing_documents_are_still_validated_against_the_xsd(): void
    {
        Nav::fake();

        $this->expectException(XsdValidationException::class);

        Nav::manageInvoice(InvoiceSubmission::create('<InvoiceData xmlns="http://schemas.nav.gov.hu/OSA/3.0/data"/>'));
    }

    public function test_raw_calls_are_not_supported(): void
    {
        Nav::fake();

        $this->expectException(LogicException::class);

        Nav::client()->call('queryTaxpayer', 'QueryTaxpayerRequest');
    }

    public function test_nothing_assertions(): void
    {
        Nav::fake();

        Nav::assertNothingSent();
        Nav::assertNothingSubmitted();
        Nav::assertSubmittedCount(0);
        Nav::assertNotSent('manageInvoice');
    }

    public function test_failed_assertions_report_the_invoice_number(): void
    {
        Nav::fake();

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('The expected invoice was not submitted to NAV: INV-404.');

        Nav::assertInvoiceSubmitted('INV-404');
    }

    public function test_assert_nothing_submitted_fails_after_a_submission(): void
    {
        Nav::fake();
        Nav::manageInvoice($this->invoice());

        $this->expectException(ExpectationFailedException::class);

        Nav::assertNothingSubmitted();
    }
}
