<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use IntrAgile\NavOnlineSzamla\Enums\TransactionKind;
use IntrAgile\NavOnlineSzamla\Enums\TransactionState;

/**
 * One manageInvoice or manageAnnulment request in the local transaction log.
 *
 * @property int $id
 * @property TransactionKind $kind
 * @property string|null $tenant
 * @property string $tax_number
 * @property TransactionState $state
 * @property string|null $transaction_id
 * @property int $submission_count
 * @property int $poll_attempts
 * @property int $reconciliation_attempts
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $sent_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, NavTransactionItem> $items
 */
class NavTransaction extends Model
{
    /** Client-side validation found blocking NAV errors; see the items' violations. */
    public const ERROR_CLIENT_VALIDATION = 'CLIENT_VALIDATION';

    /** The request did not reach NAV or the response was lost. */
    public const ERROR_TRANSPORT = 'TRANSPORT';

    /** NAV did not finish processing within the configured polling attempts. */
    public const ERROR_POLLING_EXHAUSTED = 'POLLING_EXHAUSTED';

    /** Reconciliation proved that NAV never received the request, and resubmission is disabled or exhausted. */
    public const ERROR_NOT_RECEIVED = 'NOT_RECEIVED';

    /** NAV knows an invoice of the transaction but no matching transaction was found. */
    public const ERROR_RECONCILIATION_CONFLICT = 'RECONCILIATION_CONFLICT';

    /** The reconciliation queries kept failing. */
    public const ERROR_RECONCILIATION_FAILED = 'RECONCILIATION_FAILED';

    protected $guarded = ['id'];

    protected $attributes = [
        'submission_count' => 0,
        'poll_attempts' => 0,
        'reconciliation_attempts' => 0,
    ];

    public function getTable(): string
    {
        $table = config('nav-online-szamla.transactions.table');

        return is_string($table) && $table !== '' ? $table : 'nav_transactions';
    }

    public function getConnectionName(): ?string
    {
        $connection = config('nav-online-szamla.transactions.connection');

        return is_string($connection) ? $connection : parent::getConnectionName();
    }

    /**
     * @return HasMany<NavTransactionItem, $this>
     */
    public function items(): HasMany
    {
        /** @var class-string<NavTransactionItem> $model */
        $model = config('nav-online-szamla.transactions.item_model', NavTransactionItem::class);

        return $this->hasMany($model, 'nav_transaction_id')->orderBy('position');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('state', array_map(
            static fn (TransactionState $s): string => $s->value,
            array_filter(TransactionState::cases(), static fn (TransactionState $s): bool => $s->isFinal()),
        ));
    }

    /**
     * Atomically move the transaction from one state to another.
     *
     * Only one worker can win the transition, which guards against the same transaction
     * being sent twice by duplicate or retried jobs.
     *
     * @param  array<string, mixed>  $attributes  Further columns to update together with the state.
     * @param  array<string, int|string>  $conditions  Further column values the row must still have, e.g. the claimed submission_count.
     */
    public function transition(TransactionState $from, TransactionState $to, array $attributes = [], array $conditions = []): bool
    {
        $attributes['state'] = $to;
        $this->fill($attributes);
        $dirty = $this->getDirty();
        $updatedAt = $this->getUpdatedAtColumn();
        if ($updatedAt !== null) {
            $dirty[$updatedAt] = $this->freshTimestampString();
        }

        $updated = static::query()
            ->whereKey($this->getKey())
            ->where('state', $from->value)
            ->where($conditions)
            ->update($dirty) === 1;

        $updated ? $this->syncOriginal() : $this->refresh();

        return $updated;
    }

    public function isFinished(): bool
    {
        return $this->state === TransactionState::Finished;
    }

    /**
     * Whether NAV accepted every document of a finished transaction.
     */
    public function isAccepted(): bool
    {
        return $this->isFinished() && $this->items->every(static fn (NavTransactionItem $item): bool => $item->isAccepted());
    }

    protected function casts(): array
    {
        return [
            'kind' => TransactionKind::class,
            'state' => TransactionState::class,
            'submission_count' => 'integer',
            'poll_attempts' => 'integer',
            'reconciliation_attempts' => 'integer',
            'sent_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
