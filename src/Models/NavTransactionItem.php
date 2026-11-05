<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use IntrAgile\NavOnlineSzamla\Crypto\HashAlgorithm;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceOperation;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceStatus;
use IntrAgile\NavOnlineSzamla\Exceptions\TechnicalValidationMessage;
use IntrAgile\NavOnlineSzamla\Invoice\Validation\Violation;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceSubmission;
use IntrAgile\NavOnlineSzamla\Requests\TechnicalAnnulment;
use IntrAgile\NavOnlineSzamla\Responses\BusinessValidationMessage;
use IntrAgile\NavOnlineSzamla\Responses\ProcessingResult;

/**
 * One invoice or annulment of a logged transaction, with the exact XML that was sent.
 *
 * @property int $id
 * @property int $nav_transaction_id
 * @property int $position
 * @property string $operation
 * @property string $invoice_number
 * @property string $payload
 * @property string $payload_hash
 * @property string|null $electronic_invoice_hash
 * @property string|null $electronic_invoice_hash_algorithm
 * @property InvoiceStatus|null $status
 * @property list<array{code: string, message: string, path: string}>|null $violations
 * @property list<array{resultCode: string, errorCode: string|null, message: string|null}>|null $technical_messages
 * @property list<array{resultCode: string, errorCode: string|null, message: string|null, tag: string|null, value: string|null, line: int|null}>|null $business_messages
 * @property string|null $subject_type
 * @property int|string|null $subject_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read NavTransaction $transaction
 * @property-read Model|null $subject
 */
class NavTransactionItem extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        $table = config('nav-online-szamla.transactions.items_table');

        return is_string($table) && $table !== '' ? $table : 'nav_transaction_items';
    }

    public function getConnectionName(): ?string
    {
        $connection = config('nav-online-szamla.transactions.connection');

        return is_string($connection) ? $connection : parent::getConnectionName();
    }

    /**
     * @return BelongsTo<NavTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        /** @var class-string<NavTransaction> $model */
        $model = config('nav-online-szamla.transactions.model', NavTransaction::class);

        return $this->belongsTo($model, 'nav_transaction_id');
    }

    /**
     * The application model this document belongs to, e.g. an invoice.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function isAnnulment(): bool
    {
        return $this->operation === 'ANNUL';
    }

    public function isAccepted(): bool
    {
        return $this->status === InvoiceStatus::Done;
    }

    public function isRejected(): bool
    {
        return $this->status === InvoiceStatus::Aborted;
    }

    /**
     * The submission exactly as it was, or will be, sent.
     */
    public function toSubmission(): InvoiceSubmission
    {
        return new InvoiceSubmission(
            InvoiceOperation::from($this->operation),
            $this->payload,
            $this->electronic_invoice_hash,
            HashAlgorithm::tryFrom((string) $this->electronic_invoice_hash_algorithm) ?? HashAlgorithm::Sha3_512,
        );
    }

    public function toAnnulment(): TechnicalAnnulment
    {
        return TechnicalAnnulment::fromXml($this->payload);
    }

    /**
     * @param  list<Violation>  $violations
     */
    public function recordViolations(array $violations): void
    {
        $this->violations = array_map(static fn (Violation $v): array => ['code' => $v->code, 'message' => $v->message, 'path' => $v->path], $violations);
    }

    public function recordResult(ProcessingResult $result): void
    {
        $this->status = $result->status;
        $this->technical_messages = array_map(static fn (TechnicalValidationMessage $m): array => [
            'resultCode' => $m->resultCode,
            'errorCode' => $m->errorCode,
            'message' => $m->message,
        ], $result->technicalValidationMessages);
        $this->business_messages = array_map(static fn (BusinessValidationMessage $m): array => [
            'resultCode' => $m->resultCode->value,
            'errorCode' => $m->errorCode,
            'message' => $m->message,
            'tag' => $m->tag,
            'value' => $m->value,
            'line' => $m->line,
        ], $result->businessValidationMessages);
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'status' => InvoiceStatus::class,
            'violations' => 'array',
            'technical_messages' => 'array',
            'business_messages' => 'array',
        ];
    }
}
