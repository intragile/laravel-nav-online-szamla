<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Jobs;

use DOMDocument;
use IntrAgile\NavOnlineSzamla\Enums\TransactionKind;
use IntrAgile\NavOnlineSzamla\Enums\TransactionState;
use IntrAgile\NavOnlineSzamla\Events\TransactionReconciled;
use IntrAgile\NavOnlineSzamla\Exceptions\NavException;
use IntrAgile\NavOnlineSzamla\Models\NavTransaction;
use IntrAgile\NavOnlineSzamla\Models\NavTransactionItem;
use IntrAgile\NavOnlineSzamla\NavClient;
use IntrAgile\NavOnlineSzamla\NavManager;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceNumberQuery;
use IntrAgile\NavOnlineSzamla\Responses\Transaction;

/**
 * Decides the outcome of a transaction whose response was lost.
 *
 * NAV records a transaction as soon as it accepts the request, so the transaction list of
 * the sending window is searched for a request carrying exactly the logged documents. When
 * one is found, polling resumes with its transaction ID. When none is found and no invoice
 * of the transaction is known to NAV either, in two checks "reconciliation.delay" apart,
 * the request never arrived and is resubmitted.
 */
class ReconcileTransaction extends TransactionJob
{
    /** Tolerance for the clock difference between this server and NAV. */
    private const CLOCK_SKEW_SECONDS = 300;

    public function handle(NavManager $nav): void
    {
        $transaction = $this->transaction();
        if ($transaction === null) {
            return;
        }

        if ($transaction->state === TransactionState::Sending) {
            $stale = ($transaction->sent_at ?? $transaction->updated_at)->lt(now()->subSeconds(self::sendingTimeout()));
            if (! $stale || ! $transaction->transition(TransactionState::Sending, TransactionState::Unconfirmed)) {
                return;
            }
        }

        if ($transaction->state !== TransactionState::Unconfirmed) {
            return;
        }

        $transaction->increment('reconciliation_attempts');
        $client = $nav->client($transaction->tenant);

        try {
            $transactionId = $this->find($transaction, $client);
            $known = $transactionId === null ? $this->knownInvoice($transaction, $client) : null;
        } catch (NavException $e) {
            if ($transaction->reconciliation_attempts >= self::setting('reconciliation.max_attempts', 5)) {
                $this->abandon($transaction, TransactionState::Unconfirmed, NavTransaction::ERROR_RECONCILIATION_FAILED, $e->getMessage());
            } else {
                self::schedule($transaction->id, self::setting('reconciliation.delay', 60) * $transaction->reconciliation_attempts);
            }

            return;
        }

        if ($transactionId !== null) {
            if ($transaction->transition(TransactionState::Unconfirmed, TransactionState::Processing, ['transaction_id' => $transactionId, 'error_code' => null, 'error_message' => null])) {
                event(new TransactionReconciled($transaction, true));
                PollTransactionStatus::schedule($transaction->id, PollTransactionStatus::delayFor(0));
            }

            return;
        }

        if ($known !== null) {
            $this->abandon($transaction, TransactionState::Unconfirmed, NavTransaction::ERROR_RECONCILIATION_CONFLICT, "NAV already has invoice {$known}, but no transaction carrying the logged data was found.");

            return;
        }

        // NAV may list a received request with a delay, so a single negative answer is not proof.
        if ($transaction->reconciliation_attempts < 2) {
            self::schedule($transaction->id, self::setting('reconciliation.delay', 60));

            return;
        }

        if (! config('nav-online-szamla.reconciliation.resubmit', true)
            || $transaction->submission_count >= self::setting('reconciliation.max_submissions', 3)) {
            $this->abandon($transaction, TransactionState::Unconfirmed, NavTransaction::ERROR_NOT_RECEIVED, 'NAV never received the request.');

            return;
        }

        if ($transaction->transition(TransactionState::Unconfirmed, TransactionState::Pending, ['reconciliation_attempts' => 0])) {
            event(new TransactionReconciled($transaction, false));
            SubmitTransaction::schedule($transaction->id);
        }
    }

    /**
     * The NAV transaction ID of the request carrying exactly the logged documents.
     */
    private function find(NavTransaction $transaction, NavClient $client): ?string
    {
        $sentAt = ($transaction->sent_at ?? $transaction->updated_at)->toImmutable();
        $from = $sentAt->subSeconds(self::CLOCK_SKEW_SECONDS);
        $to = now()->toImmutable()->min($sentAt->addHours(23));
        $items = $transaction->items;

        $candidates = $client->transactions($from, $to)->filter(
            static fn (Transaction $t): bool => $t->insertedBy === $client->credentials->login
                && $t->itemCount === $items->count()
                && $t->technicalAnnulment === ($transaction->kind === TransactionKind::Annulment),
        );

        foreach ($candidates as $candidate) {
            $status = $client->queryTransactionStatus($candidate->transactionId, returnOriginalRequest: true);
            if (count($status->results) !== $items->count()) {
                continue;
            }

            $matches = true;
            foreach ($status->results as $result) {
                $item = $items->firstWhere('position', $result->index);
                if (! $item instanceof NavTransactionItem || $result->originalRequest === null || ! self::samePayload($item, $result->originalRequest)) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return $candidate->transactionId;
            }
        }

        return null;
    }

    /**
     * The number of an invoice of the transaction that NAV already knows, if any.
     */
    private function knownInvoice(NavTransaction $transaction, NavClient $client): ?string
    {
        if ($transaction->kind !== TransactionKind::Invoice) {
            return null;
        }

        foreach ($transaction->items as $item) {
            if ($client->queryInvoiceCheck(InvoiceNumberQuery::outbound($item->invoice_number))) {
                return $item->invoice_number;
            }
        }

        return null;
    }

    private static function samePayload(NavTransactionItem $item, string $original): bool
    {
        if (hash_equals($item->payload_hash, hash('sha256', $original))) {
            return true;
        }

        $canonical = self::canonical($original);

        return $canonical !== null && $canonical === self::canonical($item->payload);
    }

    private static function canonical(string $xml): ?string
    {
        $document = new DOMDocument;
        $document->preserveWhiteSpace = false;
        if (! @$document->loadXML($xml, LIBXML_NONET)) {
            return null;
        }
        $canonical = $document->C14N();

        return $canonical === false ? null : $canonical;
    }
}
