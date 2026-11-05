<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        $connection = config('nav-online-szamla.transactions.connection');

        return is_string($connection) ? $connection : null;
    }

    public function up(): void
    {
        $transactions = $this->table('table', 'nav_transactions');

        Schema::create($transactions, function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 16);
            $table->string('tenant')->nullable()->index();
            $table->char('tax_number', 8);
            $table->string('state', 16)->index();
            $table->string('transaction_id', 30)->nullable()->index();
            $table->unsignedTinyInteger('submission_count')->default(0);
            $table->unsignedSmallInteger('poll_attempts')->default(0);
            $table->unsignedSmallInteger('reconciliation_attempts')->default(0);
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at', 3)->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create($this->table('items_table', 'nav_transaction_items'), function (Blueprint $table) use ($transactions): void {
            $table->id();
            $table->foreignId('nav_transaction_id')->constrained($transactions)->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('operation', 16);
            $table->string('invoice_number', 50)->index();
            $table->longText('payload');
            $table->char('payload_hash', 64);
            $table->string('electronic_invoice_hash', 512)->nullable();
            $table->string('electronic_invoice_hash_algorithm', 16)->nullable();
            $table->string('status', 16)->nullable();
            $table->json('violations')->nullable();
            $table->json('technical_messages')->nullable();
            $table->json('business_messages')->nullable();
            $table->nullableMorphs('subject');
            $table->timestamps();

            $table->unique(['nav_transaction_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('items_table', 'nav_transaction_items'));
        Schema::dropIfExists($this->table('table', 'nav_transactions'));
    }

    private function table(string $key, string $default): string
    {
        $name = config('nav-online-szamla.transactions.'.$key);

        return is_string($name) && $name !== '' ? $name : $default;
    }
};
