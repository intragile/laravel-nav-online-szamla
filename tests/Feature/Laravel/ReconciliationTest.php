<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Feature\Laravel;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use IntrAgile\NavOnlineSzamla\Enums\TransactionState;
use IntrAgile\NavOnlineSzamla\Events\TransactionAbandoned;
use IntrAgile\NavOnlineSzamla\Events\TransactionReconciled;
use IntrAgile\NavOnlineSzamla\Facades\Nav;
use IntrAgile\NavOnlineSzamla\Jobs\PollTransactionStatus;
use IntrAgile\NavOnlineSzamla\Jobs\ReconcileTransaction;
use IntrAgile\NavOnlineSzamla\Jobs\SubmitTransaction;
use IntrAgile\NavOnlineSzamla\Models\NavTransaction;
use IntrAgile\NavOnlineSzamla\Tests\Support\InvoiceFactory;

final class ReconciliationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Event::fake([TransactionReconciled::class, TransactionAbandoned::class]);
    }

    private function unconfirmed(): NavTransaction
    {
        $transaction = Nav::submit(InvoiceFactory::domestic()->line(InvoiceFactory::product()));
        $transaction->transition(TransactionState::Pending, TransactionState::Unconfirmed, ['sent_at' => now()->subMinute(), 'submission_count' => 1]);

        return $transaction;
    }

    private function reconcile(NavTransaction $transaction): void
    {
        $this->app->call([new ReconcileTransaction($transaction->id), 'handle']);
    }

    public function test_finds_the_transaction_by_its_original_request_and_resumes_polling(): void
    {
        $transaction = $this->unconfirmed();
        $payload = $transaction->items()->sole()->payload;
        Http::fake([
            self::URL.'/queryTransactionList' => Http::response($this->transactionListResponse([
                ['id' => 'OTHERUSER', 'login' => 'someoneelse1'],
                ['id' => 'TWOITEMS', 'items' => 2],
                ['id' => 'ANNULMENT', 'annulment' => true],
                ['id' => 'DIFFERENT'],
                ['id' => 'OURS'],
            ])),
            self::URL.'/queryTransactionStatus' => Http::sequence()
                ->push($this->statusResponse([['DONE', '<InvoiceData>other</InvoiceData>']]))
                ->push($this->statusResponse([['RECEIVED', $payload]])),
        ]);

        $this->reconcile($transaction);

        $transaction->refresh();
        $this->assertSame(TransactionState::Processing, $transaction->state);
        $this->assertSame('OURS', $transaction->transaction_id);
        $this->assertNull($transaction->error_code);
        Event::assertDispatched(TransactionReconciled::class, fn (TransactionReconciled $e) => $e->found);
        Bus::assertDispatched(PollTransactionStatus::class);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/queryTransactionStatus') && str_contains($r->body(), 'returnOriginalRequest>true<'));
        Http::assertSentCount(3);
    }

    public function test_matches_a_reformatted_original_request(): void
    {
        $transaction = $this->unconfirmed();
        $reformatted = str_replace('><', ">\n  <", $transaction->items()->sole()->payload);
        Http::fake([
            self::URL.'/queryTransactionList' => Http::response($this->transactionListResponse([['id' => 'OURS']])),
            self::URL.'/queryTransactionStatus' => Http::response($this->statusResponse([['DONE', $reformatted]])),
        ]);

        $this->reconcile($transaction);

        $this->assertSame('OURS', $transaction->refresh()->transaction_id);
    }

    public function test_queries_the_window_around_the_send_time(): void
    {
        $transaction = $this->unconfirmed();
        Http::fake([
            self::URL.'/queryTransactionList' => Http::response($this->emptyTransactionListResponse()),
            self::URL.'/queryInvoiceCheck' => Http::response($this->invoiceCheckResponse(false)),
        ]);

        $this->reconcile($transaction);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/queryTransactionList')
            && str_contains($r->body(), '<dateTimeFrom>2026-11-05T11:54:00.000Z</dateTimeFrom>')
            && str_contains($r->body(), '<dateTimeTo>2026-11-05T12:00:00.000Z</dateTimeTo>'));
    }

    public function test_resubmits_when_nav_never_received_the_request(): void
    {
        $transaction = $this->unconfirmed();
        Http::fake([
            self::URL.'/queryTransactionList' => Http::response($this->emptyTransactionListResponse()),
            self::URL.'/queryInvoiceCheck' => Http::response($this->invoiceCheckResponse(false)),
        ]);

        $this->reconcile($transaction);

        $this->assertSame(TransactionState::Pending, $transaction->refresh()->state);
        Event::assertDispatched(TransactionReconciled::class, fn (TransactionReconciled $e) => ! $e->found);
        Bus::assertDispatchedTimes(SubmitTransaction::class, 2);
    }

    public function test_abandons_instead_of_resubmitting_when_disabled(): void
    {
        config(['nav-online-szamla.reconciliation.resubmit' => false]);
        $transaction = $this->unconfirmed();
        Http::fake([
            self::URL.'/queryTransactionList' => Http::response($this->emptyTransactionListResponse()),
            self::URL.'/queryInvoiceCheck' => Http::response($this->invoiceCheckResponse(false)),
        ]);

        $this->reconcile($transaction);

        $this->assertSame(TransactionState::Abandoned, $transaction->refresh()->state);
        $this->assertSame(NavTransaction::ERROR_NOT_RECEIVED, $transaction->error_code);
        Event::assertDispatched(TransactionAbandoned::class);
        Bus::assertDispatchedTimes(SubmitTransaction::class, 1);
    }

    public function test_abandons_when_the_submission_limit_is_reached(): void
    {
        config(['nav-online-szamla.reconciliation.max_submissions' => 1]);
        $transaction = $this->unconfirmed();
        Http::fake([
            self::URL.'/queryTransactionList' => Http::response($this->emptyTransactionListResponse()),
            self::URL.'/queryInvoiceCheck' => Http::response($this->invoiceCheckResponse(false)),
        ]);

        $this->reconcile($transaction);

        $this->assertSame(NavTransaction::ERROR_NOT_RECEIVED, $transaction->refresh()->error_code);
    }

    public function test_abandons_when_nav_knows_the_invoice_but_not_the_transaction(): void
    {
        $transaction = $this->unconfirmed();
        Http::fake([
            self::URL.'/queryTransactionList' => Http::response($this->emptyTransactionListResponse()),
            self::URL.'/queryInvoiceCheck' => Http::response($this->invoiceCheckResponse(true)),
        ]);

        $this->reconcile($transaction);

        $this->assertSame(TransactionState::Abandoned, $transaction->refresh()->state);
        $this->assertSame(NavTransaction::ERROR_RECONCILIATION_CONFLICT, $transaction->error_code);
        Bus::assertDispatchedTimes(SubmitTransaction::class, 1);
    }

    public function test_retries_failing_queries_with_growing_delay_then_gives_up(): void
    {
        config(['nav-online-szamla.reconciliation.max_attempts' => 2]);
        $transaction = $this->unconfirmed();
        Http::fake([self::URL.'/queryTransactionList' => Http::failedConnection()]);

        $this->reconcile($transaction);
        $this->assertSame(TransactionState::Unconfirmed, $transaction->refresh()->state);
        Bus::assertDispatched(ReconcileTransaction::class, fn (ReconcileTransaction $job) => $job->delay === 60);

        $this->reconcile($transaction);
        $this->assertSame(TransactionState::Abandoned, $transaction->refresh()->state);
        $this->assertSame(NavTransaction::ERROR_RECONCILIATION_FAILED, $transaction->error_code);
    }

    public function test_takes_over_a_transaction_whose_worker_died_while_sending(): void
    {
        $transaction = Nav::submit(InvoiceFactory::domestic()->line(InvoiceFactory::product()));
        $transaction->transition(TransactionState::Pending, TransactionState::Sending, ['sent_at' => now(), 'submission_count' => 1]);
        $this->travel(71)->seconds();
        Http::fake([
            self::URL.'/queryTransactionList' => Http::response($this->emptyTransactionListResponse()),
            self::URL.'/queryInvoiceCheck' => Http::response($this->invoiceCheckResponse(false)),
        ]);

        $this->reconcile($transaction);

        $this->assertSame(TransactionState::Pending, $transaction->refresh()->state);
        Bus::assertDispatched(SubmitTransaction::class);
    }

    public function test_leaves_a_transaction_that_is_still_being_sent_alone(): void
    {
        $transaction = Nav::submit(InvoiceFactory::domestic()->line(InvoiceFactory::product()));
        $transaction->transition(TransactionState::Pending, TransactionState::Sending, ['sent_at' => now()]);
        Http::fake();

        $this->reconcile($transaction);

        $this->assertSame(TransactionState::Sending, $transaction->refresh()->state);
        Http::assertNothingSent();
    }

    public function test_ignores_transactions_that_are_not_unconfirmed(): void
    {
        $transaction = Nav::submit(InvoiceFactory::domestic()->line(InvoiceFactory::product()));
        Http::fake();

        $this->reconcile($transaction);

        $this->assertSame(TransactionState::Pending, $transaction->refresh()->state);
        Http::assertNothingSent();
    }
}
