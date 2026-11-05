<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Jobs;

use IntrAgile\NavOnlineSzamla\Enums\TransactionKind;
use IntrAgile\NavOnlineSzamla\Enums\TransactionState;
use IntrAgile\NavOnlineSzamla\Events\TransactionRejected;
use IntrAgile\NavOnlineSzamla\Events\TransactionSubmitted;
use IntrAgile\NavOnlineSzamla\Events\TransactionUnconfirmed;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceValidationException;
use IntrAgile\NavOnlineSzamla\Exceptions\NavApiException;
use IntrAgile\NavOnlineSzamla\Exceptions\NavException;
use IntrAgile\NavOnlineSzamla\Exceptions\TransportException;
use IntrAgile\NavOnlineSzamla\Invoice\Validation\InvoiceValidator;
use IntrAgile\NavOnlineSzamla\Models\NavTransaction;
use IntrAgile\NavOnlineSzamla\Models\NavTransactionItem;
use IntrAgile\NavOnlineSzamla\NavClient;
use IntrAgile\NavOnlineSzamla\NavManager;
use ReflectionClass;
use Throwable;

/**
 * Sends a pending transaction to NAV exactly once.
 *
 * The transaction is claimed atomically before sending. When the outcome is unknown (a
 * timeout, a lost worker, a NAV error that does not prove rejection) the transaction is
 * handed to reconciliation instead of being sent again.
 */
class SubmitTransaction extends TransactionJob
{
    public function handle(NavManager $nav, InvoiceValidator $validator): void
    {
        $transaction = $this->transaction();
        if ($transaction === null) {
            return;
        }

        if ($transaction->state === TransactionState::Sending) {
            // Another worker is sending it, or died while doing so: let reconciliation decide.
            ReconcileTransaction::schedule($transaction->id, self::sendingTimeout());

            return;
        }

        if ($transaction->state !== TransactionState::Pending) {
            return;
        }

        $client = $nav->client($transaction->tenant);

        if ($transaction->kind === TransactionKind::Invoice && config('nav-online-szamla.validation.enabled', true) && ! $this->validate($transaction, $client, $validator)) {
            return;
        }

        if (! $transaction->transition(TransactionState::Pending, TransactionState::Sending, [
            'sent_at' => now(),
            'submission_count' => $transaction->submission_count + 1,
            'error_code' => null,
            'error_message' => null,
        ])) {
            return;
        }

        try {
            $transactionId = $this->send($transaction, $client);
        } catch (NavApiException|TransportException $e) {
            $this->handleFailure($transaction, $e);

            return;
        } catch (NavException $e) {
            // Raised while preparing the request (e.g. XSD validation): nothing was sent.
            $this->reject($transaction, TransactionState::Sending, $e);

            return;
        } catch (Throwable $e) {
            $this->unconfirmed($transaction, $e);
            report($e);

            return;
        }

        if ($transaction->transition(TransactionState::Sending, TransactionState::Processing, ['transaction_id' => $transactionId])) {
            event(new TransactionSubmitted($transaction));
            PollTransactionStatus::schedule($transaction->id, PollTransactionStatus::delayFor(0));
        }
    }

    private function send(NavTransaction $transaction, NavClient $client): string
    {
        $items = $transaction->items;

        return $transaction->kind === TransactionKind::Invoice
            ? $client->manageInvoice(array_values($items->map(static fn (NavTransactionItem $i) => $i->toSubmission())->all()))
            : $client->manageAnnulment(array_values($items->map(static fn (NavTransactionItem $i) => $i->toAnnulment())->all()));
    }

    private function validate(NavTransaction $transaction, NavClient $client, InvoiceValidator $validator): bool
    {
        $violations = [];
        foreach ($transaction->items as $item) {
            $found = $validator->validateSubmission($item->toSubmission(), now(), $client->credentials->taxNumber);
            if ($found !== []) {
                $item->recordViolations($found);
                $item->save();
                array_push($violations, ...$found);
            }
        }

        if ($violations === []) {
            return true;
        }

        $this->reject($transaction, TransactionState::Pending, new InvoiceValidationException($violations), NavTransaction::ERROR_CLIENT_VALIDATION);

        return false;
    }

    private function handleFailure(NavTransaction $transaction, NavApiException|TransportException $e): void
    {
        $retryable = $e instanceof TransportException || $e->isRetryable();

        if ($e->operation === 'tokenExchange') {
            // The submission itself was never sent.
            if (! $retryable) {
                $this->reject($transaction, TransactionState::Sending, $e);

                return;
            }

            if ($transaction->submission_count >= self::setting('reconciliation.max_submissions', 3)) {
                $this->abandon($transaction, TransactionState::Sending, NavTransaction::ERROR_SUBMISSION_LIMIT, $e->getMessage());

                return;
            }

            if ($transaction->transition(TransactionState::Sending, TransactionState::Pending, ['error_code' => $this->code($e), 'error_message' => $e->getMessage()])) {
                self::schedule($transaction->id, self::setting('reconciliation.delay', 60));
            }

            return;
        }

        // A transport failure or a transient NAV error leaves the outcome open.
        $retryable ? $this->unconfirmed($transaction, $e) : $this->reject($transaction, TransactionState::Sending, $e);
    }

    private function unconfirmed(NavTransaction $transaction, Throwable $e): void
    {
        if ($transaction->transition(TransactionState::Sending, TransactionState::Unconfirmed, [
            'error_code' => $this->code($e),
            'error_message' => $e->getMessage(),
        ])) {
            event(new TransactionUnconfirmed($transaction));
            ReconcileTransaction::schedule($transaction->id, self::setting('reconciliation.delay', 60));
        }
    }

    private function reject(NavTransaction $transaction, TransactionState $from, Throwable $e, ?string $code = null): void
    {
        if ($transaction->transition($from, TransactionState::Rejected, [
            'error_code' => $code ?? $this->code($e),
            'error_message' => $e->getMessage(),
            'finished_at' => now(),
        ])) {
            event(new TransactionRejected($transaction));
        }
    }

    private function code(Throwable $e): string
    {
        return match (true) {
            $e instanceof NavApiException => $e->errorCode ?? 'HTTP_'.$e->httpStatus,
            $e instanceof TransportException => NavTransaction::ERROR_TRANSPORT,
            default => (new ReflectionClass($e))->getShortName(),
        };
    }
}
