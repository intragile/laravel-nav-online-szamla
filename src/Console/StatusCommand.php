<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Console;

use Illuminate\Console\Command;
use IntrAgile\NavOnlineSzamla\Exceptions\NavException;
use IntrAgile\NavOnlineSzamla\Models\NavTransaction;
use IntrAgile\NavOnlineSzamla\Models\NavTransactionItem;
use IntrAgile\NavOnlineSzamla\NavManager;
use IntrAgile\NavOnlineSzamla\Responses\BusinessValidationMessage;
use IntrAgile\NavOnlineSzamla\Responses\TransactionStatus;

final class StatusCommand extends Command
{
    protected $signature = 'nav:status
        {transaction? : Local transaction log ID, or a NAV transaction ID}
        {--tenant= : Tenant of a NAV transaction ID; the default taxpayer when omitted}
        {--limit=50 : Maximum number of open transactions to list}';

    protected $description = 'List open NAV transactions, or show the status of one transaction';

    public function handle(NavManager $nav): int
    {
        $argument = $this->argument('transaction');

        if (! is_string($argument) || $argument === '') {
            return $this->listOpen();
        }

        $local = ctype_digit($argument) ? $this->model()::query()->find((int) $argument) : null;
        if ($local === null && ctype_digit($argument)) {
            $this->components->error("No local NAV transaction with ID {$argument}.");

            return self::FAILURE;
        }

        if ($local !== null) {
            $this->showLocal($local);
        }

        $transactionId = $local !== null ? $local->transaction_id : $argument;
        if ($transactionId === null) {
            return self::SUCCESS;
        }

        $tenant = $local !== null ? $local->tenant : $this->option('tenant');

        try {
            $status = $nav->client(is_string($tenant) && $tenant !== '' ? $tenant : null)->queryTransactionStatus($transactionId);
        } catch (NavException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->showRemote($status);

        return self::SUCCESS;
    }

    private function listOpen(): int
    {
        $limit = $this->option('limit');
        $transactions = $this->model()::query()->open()->withCount('items')->oldest()->limit(is_numeric($limit) ? (int) $limit : 50)->get();

        if ($transactions->isEmpty()) {
            $this->components->info('There are no open NAV transactions.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Kind', 'Tenant', 'State', 'NAV transaction', 'Documents', 'Sent at', 'Error'],
            $transactions->map(static fn (NavTransaction $t): array => [
                $t->id,
                $t->kind->value,
                $t->tenant ?? '-',
                $t->state->value,
                $t->transaction_id ?? '-',
                $t->getAttribute('items_count'),
                $t->sent_at?->format('Y-m-d H:i:s') ?? '-',
                $t->error_code ?? '',
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function showLocal(NavTransaction $transaction): void
    {
        $this->components->twoColumnDetail('Local ID', (string) $transaction->id);
        $this->components->twoColumnDetail('Kind', $transaction->kind->value);
        $this->components->twoColumnDetail('Tenant', $transaction->tenant ?? '(default)');
        $this->components->twoColumnDetail('State', $transaction->state->value);
        $this->components->twoColumnDetail('NAV transaction', $transaction->transaction_id ?? '-');
        $this->components->twoColumnDetail('Send attempts', (string) $transaction->submission_count);
        if ($transaction->error_code !== null) {
            $this->components->twoColumnDetail('Error', $transaction->error_code.': '.$transaction->error_message);
        }

        $this->table(
            ['#', 'Operation', 'Invoice number', 'Status', 'Messages'],
            $transaction->items->map(static fn (NavTransactionItem $i): array => [
                $i->position,
                $i->operation,
                $i->invoice_number,
                $i->status->value ?? '-',
                implode("\n", array_merge(
                    array_map(static fn (array $v): string => $v['code'].': '.$v['message'], $i->violations ?? []),
                    array_map(static fn (array $m): string => $m['resultCode'].' '.$m['errorCode'].': '.$m['message'], $i->business_messages ?? []),
                )),
            ])->all(),
        );
    }

    private function showRemote(TransactionStatus $status): void
    {
        $this->components->info("NAV status of {$status->transactionId}".($status->isFinished() ? ' (finished)' : ' (in progress)'));

        $this->table(
            ['Index', 'Batch index', 'Status', 'Business messages'],
            array_map(static fn ($r): array => [
                $r->index,
                $r->batchIndex ?? '-',
                $r->status->value,
                implode("\n", array_map(
                    static fn (BusinessValidationMessage $m): string => $m->resultCode->value.' '.$m->errorCode.': '.$m->message,
                    $r->businessValidationMessages,
                )),
            ], $status->results),
        );
    }

    /**
     * @return class-string<NavTransaction>
     */
    private function model(): string
    {
        /** @var class-string<NavTransaction> */
        return config('nav-online-szamla.transactions.model', NavTransaction::class);
    }
}
