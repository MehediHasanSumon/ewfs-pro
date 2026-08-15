<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fund_transfers')) {
            return;
        }

        Schema::create('fund_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('transfer_no', 100)->unique();
            $table->date('transfer_date');
            $table->foreignId('from_account_id')
                ->constrained('accounts')
                ->restrictOnDelete();
            $table->foreignId('to_account_id')
                ->constrained('accounts')
                ->restrictOnDelete();
            $table->decimal('amount', 24, 4);
            $table->decimal('transfer_fee', 24, 4)->default(0.0000);
            $table->foreignId('fee_account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->unique()
                ->constrained('journal_entries')
                ->restrictOnDelete();
            $table->string('reference_no', 150)->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('posted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['transfer_date', 'status'], 'ft_date_status_idx');
            $table->index(['from_account_id', 'transfer_date'], 'ft_from_date_idx');
            $table->index(['to_account_id', 'transfer_date'], 'ft_to_date_idx');
            $table->index(['status', 'transfer_date'], 'ft_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_transfers');
    }
};
