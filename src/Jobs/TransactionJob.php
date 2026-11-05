<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use IntrAgile\NavOnlineSzamla\Enums\TransactionState;
use IntrAgile\NavOnlineSzamla\Events\TransactionAbandoned;
use IntrAgile\NavOnlineSzamla\Models\NavTransaction;

/**
 * Base of the jobs that drive a logged transaction through its lifecycle.
 *
 * Every job re-reads the transaction and acts only when it is in the state the job expects,
 * so duplicate or retried jobs are harmless.
 */
abstract class TransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** Seconds the worker may run the job; null uses the worker's default. */
    public ?int $timeout = null;

    final public function __construct(public readonly int $transactionId)
    {
        $connection = config('nav-online-szamla.queue.connection');
        $queue = config('nav-online-szamla.queue.name');

        $this->onConnection(is_string($connection) && $connection !== '' ? $connection : null);
        $this->onQueue(is_string($queue) && $queue !== '' ? $queue : null);
        $this->timeout = static::jobTimeout();
    }

    /**
     * Seconds after which a transaction still marked as sending can no longer be in flight.
     *
     * Longer than the submission job's timeout, so the worker that claimed the transaction has
     * been stopped by then (given a queue worker that enforces timeouts).
     */
    public static function sendingTimeout(): int
    {
        return self::maxSendSeconds() + 30;
    }

    protected static function jobTimeout(): ?int
    {
        return null;
    }

    /**
     * Upper bound of one send: a token exchange with all its retries and backoff, then the
     * manage request itself, plus local processing.
     */
    protected static function maxSendSeconds(): int
    {
        $request = self::setting('http.timeout', 30) + self::setting('http.connect_timeout', 10);
        $retries = max(0, self::setting('http.retries', 2));
        $backoff = (int) ceil(self::setting('http.retry_delay_ms', 500) * $retries * ($retries + 1) / 2 / 1000);

        return ($retries + 1) * $request + $backoff + $request + 15;
    }

    /**
     * Queue the job for a transaction after the given number of seconds.
     *
     * The sync driver ignores delays, which would turn status polling into a burst of
     * queries; on that driver the delay is waited out before the job runs.
     */
    public static function schedule(int $transactionId, int $delay = 0): void
    {
        $job = new static($transactionId);

        if ($delay > 0 && self::runsSynchronously($job)) {
            sleep($delay);
            $delay = 0;
        }

        dispatch($delay > 0 ? $job->delay($delay) : $job);
    }

    protected function transaction(): ?NavTransaction
    {
        /** @var class-string<NavTransaction> $model */
        $model = config('nav-online-szamla.transactions.model', NavTransaction::class);

        return $model::query()->find($this->transactionId);
    }

    protected function abandon(NavTransaction $transaction, TransactionState $from, string $reason, string $message): void
    {
        if ($transaction->transition($from, TransactionState::Abandoned, [
            'error_code' => $reason,
            'error_message' => $message,
            'finished_at' => now(),
        ])) {
            event(new TransactionAbandoned($transaction, $reason));
        }
    }

    private static function runsSynchronously(self $job): bool
    {
        $connection = $job->connection ?? config('queue.default');

        return is_string($connection) && config("queue.connections.{$connection}.driver") === 'sync';
    }

    protected static function setting(string $key, int $default): int
    {
        $value = config('nav-online-szamla.'.$key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
