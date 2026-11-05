<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Console;

use Illuminate\Console\Command;
use IntrAgile\NavOnlineSzamla\Enums\TransactionState;
use IntrAgile\NavOnlineSzamla\Jobs\PollTransactionStatus;
use IntrAgile\NavOnlineSzamla\Jobs\ReconcileTransaction;
use IntrAgile\NavOnlineSzamla\Jobs\SubmitTransaction;
use IntrAgile\NavOnlineSzamla\Jobs\TransactionJob;
use IntrAgile\NavOnlineSzamla\Models\NavTransaction;

/**
 * Re-dispatches the job of every open transaction that has been idle for too long, e.g.
 * after a failed dispatch, a worker killed on its last attempt or a cleared queue.
 *
 * Safe to run at any time: the jobs act only on the state they expect. Schedule it, for
 * example every ten minutes.
 */
final class ResumeCommand extends Command
{
    protected $signature = 'nav:resume {--idle=10 : Minutes without progress after which a transaction is resumed}';

    protected $description = 'Resume NAV transactions whose queued job was lost';

    public function handle(): int
    {
        $idle = $this->option('idle');
        $cutoff = now()->subMinutes(is_numeric($idle) ? max(1, (int) $idle) : 10);
        $sendingCutoff = now()->subSeconds(TransactionJob::sendingTimeout());

        /** @var class-string<NavTransaction> $model */
        $model = config('nav-online-szamla.transactions.model', NavTransaction::class);

        $resumed = 0;
        $model::query()->open()->where('updated_at', '<', $cutoff)->orderBy('id')->each(function (NavTransaction $transaction) use (&$resumed, $sendingCutoff): void {
            $job = match ($transaction->state) {
                TransactionState::Pending => SubmitTransaction::class,
                TransactionState::Sending => ($transaction->sent_at ?? $transaction->updated_at)->lt($sendingCutoff) ? ReconcileTransaction::class : null,
                TransactionState::Unconfirmed => ReconcileTransaction::class,
                TransactionState::Processing => PollTransactionStatus::class,
                default => null,
            };

            if ($job !== null) {
                $job::schedule($transaction->id);
                $resumed++;
            }
        });

        $this->components->info("Resumed {$resumed} NAV transaction(s).");

        return self::SUCCESS;
    }
}
