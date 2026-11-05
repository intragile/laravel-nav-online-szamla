<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Jobs;

use IntrAgile\NavOnlineSzamla\Enums\TransactionKind;
use IntrAgile\NavOnlineSzamla\Enums\TransactionState;
use IntrAgile\NavOnlineSzamla\Events\TransactionRejected;
use IntrAgile\NavOnlineSzamla\Events\TransactionSubmitted;
use IntrAgile\NavOnlineSzamla\Events\TransactionUnconfirmed;
use IntrAgile\NavOnlineSzamla\Exceptions\ConfigurationException;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceValidationException;
use IntrAgile\NavOnlineSzamla\Exceptions\NavApiException;
use IntrAgile\NavOnlineSzamla\Exceptions\NavException;
use IntrAgile\NavOnlineSzamla\Exceptions\TokenDecodingException;
use IntrAgile\NavOnlineSzamla\Exceptions\TransportException;
use IntrAgile\NavOnlineSzamla\Exceptions\XsdValidationException;
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
    protected static function jobTimeout(): int
    {
        return self::maxSendSeconds();
    }

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

        $claim = $transaction->submission_count + 1;
        if (! $transaction->transition(TransactionState::Pending, TransactionState::Sending, [
            'sent_at' => now(),
            'submission_count' => $claim,
            'error_code' => null,
            'error_message' => null,
        ])) {
            return;
        }

        try {
            $transactionId = $this->send($transaction, $client);
        } catch (XsdValidationException|ConfigurationException|TokenDecodingException $e) {
            // Raised before the manage request was sent.
            $this->reject($transaction, $e, $claim);

            return;
        } catch (NavApiException|TransportException $e) {
            $this->handleFailure($transaction, $e, $claim);

            return;
        } catch (Throwable $e) {
            // Anything else may have happened after NAV received the request.
            $this->unconfirmed($transaction, $e, $claim);
            report($e);

            return;
        }

        if ($transaction->transition(TransactionState::Sending, TransactionState::Processing, ['transaction_id' => $transactionId], ['submission_count' => $claim])) {
            event(new TransactionSubmitted($transaction));
            PollTransactionStatus::schedule($transaction->id, PollTransactionStatus::delayFor(0));

            return;
        }

        // The claim expired while this worker was still sending: surface the orphaned NAV transaction.
        report(new NavException("NAV transaction {$transactionId} of local transaction {$transaction->id} completed after its claim expired; check it for a duplicate submission."));
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

        $this->reject($transaction, new InvoiceValidationException($violations), null, NavTransaction::ERROR_CLIENT_VALIDATION);

        return false;
    }

    private function handleFailure(NavTransaction $transaction, NavApiException|TransportException $e, int $claim): void
    {
        $retryable = $e instanceof TransportException || $e->isRetryable();

        if ($e->operation === 'tokenExchange') {
            // The submission itself was never sent, so this attempt does not count.
            if (! $retryable) {
                $this->reject($transaction, $e, $claim);

                return;
            }

            if ($transaction->transition(TransactionState::Sending, TransactionState::Pending, [
                'submission_count' => $claim - 1,
                'error_code' => $this->code($e),
                'error_message' => $e->getMessage(),
            ], ['submission_count' => $claim])) {
                self::schedule($transaction->id, self::setting('reconciliation.delay', 60));
            }

            return;
        }

        // Only an explicit, permanent NAV error proves the request was refused. A transport
        // failure, a transient error, or a response without a NAV error code (a proxy page, a
        // truncated body) leaves the outcome open.
        $refused = $e instanceof NavApiException && $e->errorCode !== null && ! $retryable;
        $refused ? $this->reject($transaction, $e, $claim) : $this->unconfirmed($transaction, $e, $claim);
    }

    private function unconfirmed(NavTransaction $transaction, Throwable $e, int $claim): void
    {
        if ($transaction->transition(TransactionState::Sending, TransactionState::Unconfirmed, [
            'error_code' => $this->code($e),
            'error_message' => $e->getMessage(),
        ], ['submission_count' => $claim])) {
            event(new TransactionUnconfirmed($transaction));
            ReconcileTransaction::schedule($transaction->id, self::setting('reconciliation.delay', 60));
        }
    }

    /**
     * @param  int|null  $claim  The claimed submission_count when sending; null before the claim.
     */
    private function reject(NavTransaction $transaction, Throwable $e, ?int $claim, ?string $code = null): void
    {
        $from = $claim === null ? TransactionState::Pending : TransactionState::Sending;
        if ($transaction->transition($from, TransactionState::Rejected, [
            'error_code' => $code ?? $this->code($e),
            'error_message' => $e->getMessage(),
            'finished_at' => now(),
        ], $claim === null ? [] : ['submission_count' => $claim])) {
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
