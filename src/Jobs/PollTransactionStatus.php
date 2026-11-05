<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Jobs;

use IntrAgile\NavOnlineSzamla\Enums\TransactionState;
use IntrAgile\NavOnlineSzamla\Events\InvoiceAccepted;
use IntrAgile\NavOnlineSzamla\Events\InvoiceRejected;
use IntrAgile\NavOnlineSzamla\Events\TransactionFinished;
use IntrAgile\NavOnlineSzamla\Exceptions\NavException;
use IntrAgile\NavOnlineSzamla\Models\NavTransaction;
use IntrAgile\NavOnlineSzamla\NavManager;
use IntrAgile\NavOnlineSzamla\Responses\ProcessingResult;

/**
 * Queries the status of a processing transaction until NAV finishes it, backing off
 * according to "polling.delays".
 */
class PollTransactionStatus extends TransactionJob
{
    /**
     * Seconds to wait before the given (zero-based) polling attempt.
     */
    public static function delayFor(int $attempt): int
    {
        $delays = array_values(array_filter((array) config('nav-online-szamla.polling.delays', [5]), 'is_numeric'));
        if ($delays === []) {
            return 5;
        }

        return (int) $delays[min($attempt, count($delays) - 1)];
    }

    public function handle(NavManager $nav): void
    {
        $transaction = $this->transaction();
        $transactionId = $transaction?->transaction_id;
        if ($transaction === null || $transactionId === null || $transaction->state !== TransactionState::Processing) {
            return;
        }

        $transaction->increment('poll_attempts');

        try {
            $status = $nav->client($transaction->tenant)->queryTransactionStatus($transactionId);
        } catch (NavException $e) {
            $this->scheduleNext($transaction->id, $transaction->poll_attempts, $e->getMessage());

            return;
        }

        $items = $transaction->items->keyBy('position');
        $results = [];
        $won = false;

        $finished = $status->isFinished();
        $transaction->getConnection()->transaction(function () use ($transaction, $status, $items, $finished, &$results, &$won): void {
            if ($finished && ! $won = $transaction->transition(TransactionState::Processing, TransactionState::Finished, ['finished_at' => now()])) {
                return;
            }

            foreach ($status->results as $result) {
                $item = $items->get($result->index);
                if ($item !== null) {
                    $item->recordResult($result);
                    $item->save();
                    $results[] = [$item, $result];
                }
            }
        });

        if (! $finished) {
            $this->scheduleNext($transaction->id, $transaction->poll_attempts);

            return;
        }

        if (! $won) {
            return;
        }

        // Fired after the commit: listeners that must not miss an event should be queued.
        foreach ($results as [$item, $result]) {
            /** @var ProcessingResult $result */
            event($result->isAccepted() ? new InvoiceAccepted($item, $result) : new InvoiceRejected($item, $result));
        }
        event(new TransactionFinished($transaction, $status));
    }

    private function scheduleNext(int $id, int $attempts, ?string $error = null): void
    {
        if ($attempts >= self::setting('polling.max_attempts', 30)) {
            $transaction = $this->transaction();
            if ($transaction !== null) {
                $this->abandon(
                    $transaction,
                    TransactionState::Processing,
                    NavTransaction::ERROR_POLLING_EXHAUSTED,
                    $error ?? "NAV did not finish processing after {$attempts} status queries.",
                );
            }

            return;
        }

        self::schedule($id, self::delayFor($attempts));
    }
}
