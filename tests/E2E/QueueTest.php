<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\E2E;

use Illuminate\Support\Facades\Bus;
use IntrAgile\NavOnlineSzamla\Enums\TransactionState;
use IntrAgile\NavOnlineSzamla\Facades\Nav;
use IntrAgile\NavOnlineSzamla\Invoice\LineItem;
use IntrAgile\NavOnlineSzamla\Invoice\Model\UnitOfMeasure;
use IntrAgile\NavOnlineSzamla\Invoice\Vat;
use IntrAgile\NavOnlineSzamla\Jobs\PollTransactionStatus;
use IntrAgile\NavOnlineSzamla\Jobs\ReconcileTransaction;
use IntrAgile\NavOnlineSzamla\Jobs\SubmitTransaction;
use PHPUnit\Framework\Attributes\Group;

/**
 * The queued pipeline and reconciliation against the test environment.
 */
#[Group('e2e')]
final class QueueTest extends E2ETestCase
{
    public function test_connection_command(): void
    {
        $this->artisan('nav:test-connection')->expectsOutputToContain('The NAV connection works.')->assertSuccessful();
    }

    public function test_queued_submission_is_accepted_and_a_lost_response_is_reconciled(): void
    {
        Bus::fake();
        $transaction = Nav::submit($this->invoice(self::invoiceNumber('-Q'))
            ->line(LineItem::product('Queue teszt')->quantity(2, UnitOfMeasure::Piece)->unitPrice('1000')->vat(Vat::rate(27))));

        $this->app->call([new SubmitTransaction($transaction->id), 'handle']);
        $transaction->refresh();
        $this->assertSame(TransactionState::Processing, $transaction->state, (string) $transaction->error_message);
        $transactionId = $transaction->transaction_id;

        for ($i = 0; $i < 30 && $transaction->state === TransactionState::Processing; $i++) {
            sleep(2);
            $this->app->call([new PollTransactionStatus($transaction->id), 'handle']);
            $transaction->refresh();
        }
        $this->assertTrue($transaction->isAccepted());

        // Simulate a lost manageInvoice response: reconciliation must find the same transaction.
        $transaction->forceFill(['state' => TransactionState::Unconfirmed, 'transaction_id' => null])->save();
        $this->app->call([new ReconcileTransaction($transaction->id), 'handle']);

        $transaction->refresh();
        $this->assertSame(TransactionState::Processing, $transaction->state);
        $this->assertSame($transactionId, $transaction->transaction_id);
    }
}
