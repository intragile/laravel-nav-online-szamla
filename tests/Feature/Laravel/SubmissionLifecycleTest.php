<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Feature\Laravel;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use IntrAgile\NavOnlineSzamla\Enums\AnnulmentCode;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceStatus;
use IntrAgile\NavOnlineSzamla\Enums\TransactionKind;
use IntrAgile\NavOnlineSzamla\Enums\TransactionState;
use IntrAgile\NavOnlineSzamla\Events\InvoiceAccepted;
use IntrAgile\NavOnlineSzamla\Events\InvoiceRejected;
use IntrAgile\NavOnlineSzamla\Events\TransactionAbandoned;
use IntrAgile\NavOnlineSzamla\Events\TransactionFinished;
use IntrAgile\NavOnlineSzamla\Events\TransactionRejected;
use IntrAgile\NavOnlineSzamla\Events\TransactionSubmitted;
use IntrAgile\NavOnlineSzamla\Events\TransactionUnconfirmed;
use IntrAgile\NavOnlineSzamla\Facades\Nav;
use IntrAgile\NavOnlineSzamla\Invoice\Validation\InvoiceValidator;
use IntrAgile\NavOnlineSzamla\Jobs\PollTransactionStatus;
use IntrAgile\NavOnlineSzamla\Jobs\ReconcileTransaction;
use IntrAgile\NavOnlineSzamla\Jobs\SubmitTransaction;
use IntrAgile\NavOnlineSzamla\Jobs\TransactionJob;
use IntrAgile\NavOnlineSzamla\Models\NavTransaction;
use IntrAgile\NavOnlineSzamla\Requests\TechnicalAnnulment;
use IntrAgile\NavOnlineSzamla\Tests\Support\InvoiceFactory;

final class SubmissionLifecycleTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Event::fake([
            TransactionSubmitted::class, TransactionRejected::class, TransactionUnconfirmed::class,
            TransactionFinished::class, TransactionAbandoned::class, InvoiceAccepted::class, InvoiceRejected::class,
        ]);
    }

    private function handle(TransactionJob $job): void
    {
        $this->app->call([$job, 'handle']);
    }

    private function queueInvoice(string $number = 'INV-1'): NavTransaction
    {
        return Nav::submit(InvoiceFactory::domestic($number)->line(InvoiceFactory::product()));
    }

    public function test_submit_logs_the_exact_payload_and_queues_the_submission(): void
    {
        $builder = InvoiceFactory::domestic('INV-42')->line(InvoiceFactory::product());
        $transaction = Nav::submit($builder);

        $this->assertSame(TransactionState::Pending, $transaction->state);
        $this->assertSame(TransactionKind::Invoice, $transaction->kind);
        $this->assertSame('12345678', $transaction->tax_number);
        $this->assertNull($transaction->tenant);

        $item = $transaction->items()->sole();
        $this->assertSame('INV-42', $item->invoice_number);
        $this->assertSame('CREATE', $item->operation);
        $this->assertSame(1, $item->position);
        $this->assertSame($builder->submission()->invoiceXml, $item->payload);
        $this->assertSame(hash('sha256', $item->payload), $item->payload_hash);

        Bus::assertDispatched(SubmitTransaction::class, fn (SubmitTransaction $job) => $job->transactionId === $transaction->id);
        Http::assertNothingSent();
    }

    public function test_submit_links_the_subject_and_records_the_tenant(): void
    {
        $subject = NavTransaction::query()->create(['kind' => TransactionKind::Invoice, 'tax_number' => '00000000', 'state' => TransactionState::Finished]);

        $transaction = Nav::submit(InvoiceFactory::domestic()->line(InvoiceFactory::product()), $subject, 'acme');

        $this->assertSame('acme', $transaction->tenant);
        $this->assertSame('87654321', $transaction->tax_number);
        $this->assertTrue($transaction->items()->sole()->subject?->is($subject));
    }

    public function test_happy_path_from_submission_to_accepted_invoice(): void
    {
        $transaction = $this->queueInvoice();
        Http::fake([
            self::URL.'/tokenExchange' => Http::response($this->tokenResponse()),
            self::URL.'/manageInvoice' => Http::response($this->manageResponse('TX0000000001')),
            self::URL.'/queryTransactionStatus' => Http::sequence()
                ->push($this->statusResponse([['PROCESSING']]))
                ->push($this->statusResponse([['DONE']])),
        ]);

        $this->handle(new SubmitTransaction($transaction->id));

        $transaction->refresh();
        $this->assertSame(TransactionState::Processing, $transaction->state);
        $this->assertSame('TX0000000001', $transaction->transaction_id);
        $this->assertSame(1, $transaction->submission_count);
        $this->assertNotNull($transaction->sent_at);
        Event::assertDispatched(TransactionSubmitted::class);
        Bus::assertDispatched(PollTransactionStatus::class, fn (PollTransactionStatus $job) => $job->delay === 5);

        $this->handle(new PollTransactionStatus($transaction->id));
        $this->assertSame(TransactionState::Processing, $transaction->refresh()->state);
        $this->assertSame(InvoiceStatus::Processing, $transaction->items()->sole()->status);
        Bus::assertDispatched(PollTransactionStatus::class, fn (PollTransactionStatus $job) => $job->delay === 10);

        $this->handle(new PollTransactionStatus($transaction->id));
        $transaction->refresh();
        $this->assertSame(TransactionState::Finished, $transaction->state);
        $this->assertNotNull($transaction->finished_at);
        $this->assertTrue($transaction->isAccepted());
        $this->assertSame(InvoiceStatus::Done, $transaction->items()->sole()->status);
        Event::assertDispatched(InvoiceAccepted::class, fn (InvoiceAccepted $e) => $e->item->invoice_number === 'INV-1');
        Event::assertDispatched(TransactionFinished::class, 1);
        Event::assertNotDispatched(InvoiceRejected::class);
    }

    public function test_aborted_invoice_fires_rejection_with_the_nav_messages(): void
    {
        $transaction = $this->queueInvoice();
        $transaction->transition(TransactionState::Pending, TransactionState::Processing, ['transaction_id' => 'TX1']);
        Http::fake([self::URL.'/queryTransactionStatus' => Http::response($this->statusResponse([['ABORTED']]))]);

        $this->handle(new PollTransactionStatus($transaction->id));

        $item = $transaction->items()->sole();
        $this->assertTrue($item->isRejected());
        $this->assertSame('INVOICE_NUMBER_NOT_UNIQUE', $item->business_messages[0]['errorCode'] ?? null);
        $this->assertFalse($transaction->refresh()->isAccepted());
        Event::assertDispatched(InvoiceRejected::class);
    }

    public function test_a_finished_transaction_is_reported_only_once(): void
    {
        $transaction = $this->queueInvoice();
        $transaction->transition(TransactionState::Pending, TransactionState::Processing, ['transaction_id' => 'TX1']);
        Http::fake([self::URL.'/queryTransactionStatus' => Http::response($this->statusResponse([['DONE']]))]);

        $this->handle(new PollTransactionStatus($transaction->id));
        $this->handle(new PollTransactionStatus($transaction->id));

        Event::assertDispatched(TransactionFinished::class, 1);
        Http::assertSentCount(1);
    }

    public function test_client_side_validation_rejects_without_contacting_nav(): void
    {
        $transaction = Nav::submit(InvoiceFactory::domestic('INV-9', '2028-01-01')->line(InvoiceFactory::product())->validateWith(null));
        Http::fake();

        $this->handle(new SubmitTransaction($transaction->id));

        $transaction->refresh();
        $this->assertSame(TransactionState::Rejected, $transaction->state);
        $this->assertSame(NavTransaction::ERROR_CLIENT_VALIDATION, $transaction->error_code);
        $this->assertContains('INVOICE_ISSUE_DATE_LATE', array_column($transaction->items()->sole()->violations ?? [], 'code'));
        Event::assertDispatched(TransactionRejected::class, fn (TransactionRejected $e) => $e->transaction->is($transaction));
        $this->assertStringContainsString('INVOICE_ISSUE_DATE_LATE', (string) $transaction->error_message);
        Http::assertNothingSent();
    }

    public function test_validation_codes_can_be_skipped_by_configuration(): void
    {
        config(['nav-online-szamla.validation.skip' => ['INVOICE_ISSUE_DATE_LATE']]);
        $this->app->forgetInstance(InvoiceValidator::class);
        $transaction = Nav::submit(InvoiceFactory::domestic('INV-9', '2028-01-01')->line(InvoiceFactory::product())->validateWith(null));
        Http::fake([
            self::URL.'/tokenExchange' => Http::response($this->tokenResponse()),
            self::URL.'/manageInvoice' => Http::response($this->manageResponse('TX2')),
        ]);

        $this->handle(new SubmitTransaction($transaction->id));

        $this->assertSame(TransactionState::Processing, $transaction->refresh()->state);
    }

    public function test_validation_uses_the_authenticated_tax_number(): void
    {
        $transaction = Nav::submit(InvoiceFactory::domestic()->line(InvoiceFactory::product()), tenant: 'acme');
        Http::fake();

        $this->handle(new SubmitTransaction($transaction->id));

        $this->assertContains('SUPPLIER_TAX_NUMBER_MISMATCH', array_column($transaction->items()->sole()->violations ?? [], 'code'));
        Http::assertNothingSent();
    }

    public function test_nav_error_on_submission_rejects_the_transaction(): void
    {
        $transaction = $this->queueInvoice();
        Http::fake([
            self::URL.'/tokenExchange' => Http::response($this->tokenResponse()),
            self::URL.'/manageInvoice' => Http::response($this->errorResponse('INVALID_REQUEST', 'bad'), 400),
        ]);

        $this->handle(new SubmitTransaction($transaction->id));

        $transaction->refresh();
        $this->assertSame(TransactionState::Rejected, $transaction->state);
        $this->assertSame('INVALID_REQUEST', $transaction->error_code);
        Event::assertDispatched(TransactionRejected::class);
        Bus::assertNotDispatched(ReconcileTransaction::class);
    }

    public function test_timeout_on_submission_leads_to_reconciliation_instead_of_resending(): void
    {
        $transaction = $this->queueInvoice();
        Http::fake([
            self::URL.'/tokenExchange' => Http::response($this->tokenResponse()),
            self::URL.'/manageInvoice' => Http::failedConnection(),
        ]);

        $this->handle(new SubmitTransaction($transaction->id));

        $transaction->refresh();
        $this->assertSame(TransactionState::Unconfirmed, $transaction->state);
        $this->assertSame(NavTransaction::ERROR_TRANSPORT, $transaction->error_code);
        Event::assertDispatched(TransactionUnconfirmed::class);
        Bus::assertDispatched(ReconcileTransaction::class, fn (ReconcileTransaction $job) => $job->delay === 60);
        Http::assertSentCount(2);
    }

    public function test_transient_nav_error_on_submission_is_treated_as_unconfirmed(): void
    {
        $transaction = $this->queueInvoice();
        Http::fake([
            self::URL.'/tokenExchange' => Http::response($this->tokenResponse()),
            self::URL.'/manageInvoice' => Http::response($this->errorResponse('OPERATION_FAILED'), 500),
        ]);

        $this->handle(new SubmitTransaction($transaction->id));

        $this->assertSame(TransactionState::Unconfirmed, $transaction->refresh()->state);
        Bus::assertDispatched(ReconcileTransaction::class);
    }

    public function test_transient_token_exchange_failure_requeues_without_reconciliation(): void
    {
        $transaction = $this->queueInvoice();
        Http::fake([self::URL.'/tokenExchange' => Http::failedConnection()]);

        $this->handle(new SubmitTransaction($transaction->id));

        $transaction->refresh();
        $this->assertSame(TransactionState::Pending, $transaction->state);
        $this->assertSame(NavTransaction::ERROR_TRANSPORT, $transaction->error_code);
        Bus::assertDispatched(SubmitTransaction::class, fn (SubmitTransaction $job) => $job->delay === 60);
        Bus::assertNotDispatched(ReconcileTransaction::class);
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/manageInvoice'));
    }

    public function test_token_exchange_failures_do_not_count_as_submissions(): void
    {
        $transaction = $this->queueInvoice();
        Http::fake([self::URL.'/tokenExchange' => Http::failedConnection()]);

        $this->handle(new SubmitTransaction($transaction->id));

        $this->assertSame(0, $transaction->refresh()->submission_count);
    }

    public function test_rejected_credentials_reject_the_transaction(): void
    {
        $transaction = $this->queueInvoice();
        Http::fake([self::URL.'/tokenExchange' => Http::response($this->errorResponse('INVALID_SECURITY_USER'), 401)]);

        $this->handle(new SubmitTransaction($transaction->id));

        $this->assertSame(TransactionState::Rejected, $transaction->refresh()->state);
        $this->assertSame('INVALID_SECURITY_USER', $transaction->error_code);
    }

    public function test_a_transaction_is_never_sent_twice(): void
    {
        $transaction = $this->queueInvoice();
        Http::fake([
            self::URL.'/tokenExchange' => Http::response($this->tokenResponse()),
            self::URL.'/manageInvoice' => Http::response($this->manageResponse('TX1')),
        ]);

        $this->handle(new SubmitTransaction($transaction->id));
        $this->handle(new SubmitTransaction($transaction->id));

        Http::assertSentCount(2);
        Event::assertDispatched(TransactionSubmitted::class, 1);
    }

    public function test_a_job_finding_the_transaction_mid_send_hands_it_to_reconciliation(): void
    {
        $transaction = $this->queueInvoice();
        $transaction->transition(TransactionState::Pending, TransactionState::Sending, ['sent_at' => now()]);
        Http::fake();

        $this->handle(new SubmitTransaction($transaction->id));

        Http::assertNothingSent();
        Bus::assertDispatched(ReconcileTransaction::class, fn (ReconcileTransaction $job) => $job->delay === 125);
        $this->assertSame(TransactionState::Sending, $transaction->refresh()->state);
    }

    public function test_annulments_are_sent_with_manage_annulment(): void
    {
        $transaction = Nav::annul(new TechnicalAnnulment('INV-1', AnnulmentCode::ErraticData, 'Wrong data'));
        Http::fake([
            self::URL.'/tokenExchange' => Http::response($this->tokenResponse()),
            self::URL.'/manageAnnulment' => Http::response($this->manageResponse('TXA', 'ManageAnnulmentResponse')),
        ]);

        $this->handle(new SubmitTransaction($transaction->id));

        $transaction->refresh();
        $this->assertSame(TransactionKind::Annulment, $transaction->kind);
        $this->assertSame(TransactionState::Processing, $transaction->state);
        $item = $transaction->items()->sole();
        $this->assertTrue($item->isAnnulment());
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/manageAnnulment')
            && str_contains($r->body(), base64_encode($item->payload)));
    }

    public function test_polling_backs_off_and_gives_up_after_the_configured_attempts(): void
    {
        config(['nav-online-szamla.polling.max_attempts' => 2]);
        $transaction = $this->queueInvoice();
        $transaction->transition(TransactionState::Pending, TransactionState::Processing, ['transaction_id' => 'TX1']);
        Http::fake([self::URL.'/queryTransactionStatus' => Http::response($this->statusResponse([['RECEIVED']]))]);

        $this->handle(new PollTransactionStatus($transaction->id));
        $this->assertSame(TransactionState::Processing, $transaction->refresh()->state);

        $this->handle(new PollTransactionStatus($transaction->id));
        $transaction->refresh();
        $this->assertSame(TransactionState::Abandoned, $transaction->state);
        $this->assertSame(NavTransaction::ERROR_POLLING_EXHAUSTED, $transaction->error_code);
        Event::assertDispatched(TransactionAbandoned::class, fn (TransactionAbandoned $e) => $e->reason === NavTransaction::ERROR_POLLING_EXHAUSTED);
    }

    public function test_failed_status_queries_are_retried_later(): void
    {
        $transaction = $this->queueInvoice();
        $transaction->transition(TransactionState::Pending, TransactionState::Processing, ['transaction_id' => 'TX1']);
        Http::fake([self::URL.'/queryTransactionStatus' => Http::failedConnection()]);

        $this->handle(new PollTransactionStatus($transaction->id));

        $this->assertSame(TransactionState::Processing, $transaction->refresh()->state);
        Bus::assertDispatched(PollTransactionStatus::class, fn (PollTransactionStatus $job) => $job->delay === 10);
    }

    public function test_polling_delays_repeat_the_last_value(): void
    {
        config(['nav-online-szamla.polling.delays' => [1, 2]]);

        $this->assertSame(1, PollTransactionStatus::delayFor(0));
        $this->assertSame(2, PollTransactionStatus::delayFor(1));
        $this->assertSame(2, PollTransactionStatus::delayFor(10));
    }

    public function test_jobs_use_the_configured_queue(): void
    {
        config(['nav-online-szamla.queue.connection' => 'redis', 'nav-online-szamla.queue.name' => 'nav']);

        $job = new SubmitTransaction(1);

        $this->assertSame('redis', $job->connection);
        $this->assertSame('nav', $job->queue);
    }

    public function test_delays_are_waited_out_on_the_sync_driver(): void
    {
        config(['queue.default' => 'sync']);
        $started = microtime(true);

        PollTransactionStatus::schedule(1, 1);

        $this->assertGreaterThanOrEqual(1.0, microtime(true) - $started);
        Bus::assertDispatched(PollTransactionStatus::class, fn (PollTransactionStatus $job) => $job->delay === null);
    }

    public function test_delays_are_passed_to_asynchronous_queues(): void
    {
        $started = microtime(true);

        PollTransactionStatus::schedule(1, 30);

        $this->assertLessThan(1.0, microtime(true) - $started);
        Bus::assertDispatched(PollTransactionStatus::class, fn (PollTransactionStatus $job) => $job->delay === 30);
    }

    public function test_the_submission_job_timeout_covers_a_complete_send(): void
    {
        $this->assertSame(95, (new SubmitTransaction(1))->timeout);
        $this->assertNull((new PollTransactionStatus(1))->timeout);
        $this->assertGreaterThan(95, SubmitTransaction::sendingTimeout());
    }

    public function test_a_response_without_nav_error_code_is_unconfirmed_not_rejected(): void
    {
        $transaction = $this->queueInvoice();
        Http::fake([
            self::URL.'/tokenExchange' => Http::response($this->tokenResponse()),
            self::URL.'/manageInvoice' => Http::response('<html>Bad gateway page</html>', 403),
        ]);

        $this->handle(new SubmitTransaction($transaction->id));

        $this->assertSame(TransactionState::Unconfirmed, $transaction->refresh()->state);
        Bus::assertDispatched(ReconcileTransaction::class);
    }

    public function test_an_accepted_request_with_an_unreadable_response_is_unconfirmed(): void
    {
        $transaction = $this->queueInvoice();
        Http::fake([
            self::URL.'/tokenExchange' => Http::response($this->tokenResponse()),
            self::URL.'/manageInvoice' => Http::response($this->okResponse('ManageInvoiceResponse', '')),
        ]);

        $this->handle(new SubmitTransaction($transaction->id));

        $this->assertSame(TransactionState::Unconfirmed, $transaction->refresh()->state);
    }

    public function test_a_worker_whose_claim_expired_cannot_overwrite_a_newer_attempt(): void
    {
        $transaction = $this->queueInvoice();
        $transaction->transition(TransactionState::Pending, TransactionState::Sending, ['submission_count' => 2]);

        $this->assertFalse($transaction->transition(TransactionState::Sending, TransactionState::Processing, ['transaction_id' => 'OLD'], ['submission_count' => 1]));
        $this->assertNull($transaction->refresh()->transaction_id);
        $this->assertTrue($transaction->transition(TransactionState::Sending, TransactionState::Processing, ['transaction_id' => 'NEW'], ['submission_count' => 2]));
    }

    public function test_transaction_finished_fires_even_without_matching_items(): void
    {
        $transaction = $this->queueInvoice();
        $transaction->transition(TransactionState::Pending, TransactionState::Processing, ['transaction_id' => 'TX1']);
        Http::fake([self::URL.'/queryTransactionStatus' => Http::response($this->statusResponse([['DONE'], ['DONE']]))]);
        $transaction->items()->update(['position' => 9]);

        $this->handle(new PollTransactionStatus($transaction->id));

        Event::assertDispatched(TransactionFinished::class, 1);
    }
}
