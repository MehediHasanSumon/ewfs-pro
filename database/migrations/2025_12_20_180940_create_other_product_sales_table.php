<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_no', 100)->unique();
            $table->foreignId('account_id')->unique()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('counterparty_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->enum('loan_type', ['borrowed', 'lent']);
            $table->date('start_date');
            $table->date('maturity_date')->nullable();
            $table->decimal('principal_amount', 24, 4);
            $table->decimal('interest_rate', 9, 6)->default(0);
            $table->enum('interest_method', ['flat', 'reducing', 'none'])->default('none');
            $table->enum('status', ['draft', 'active', 'closed', 'written_off', 'void'])->default('draft');
            $table->foreignId('opening_journal_entry_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['loan_type', 'status', 'start_date', 'id'], 'loan_type_status_date_idx');
            $table->index(['counterparty_account_id', 'status'], 'loan_counterparty_status_idx');
        });

        Schema::create('loan_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->unsignedInteger('installment_no');
            $table->date('due_date');
            $table->decimal('principal_due', 24, 4)->default(0);
            $table->decimal('interest_due', 24, 4)->default(0);
            $table->enum('status', ['pending', 'partial', 'paid', 'waived'])->default('pending');
            $table->timestamps();

            $table->unique(['loan_id', 'installment_no']);
            $table->index(['due_date', 'status', 'loan_id'], 'loan_schedule_due_idx');
        });

        Schema::create('loan_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers')->restrictOnDelete();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->foreignId('loan_schedule_id')->nullable()->constrained('loan_schedules')->restrictOnDelete();
            $table->decimal('principal_amount', 24, 4)->default(0);
            $table->decimal('interest_amount', 24, 4)->default(0);
            $table->decimal('fee_amount', 24, 4)->default(0);
            $table->timestamps();

            $table->unique(['voucher_id', 'loan_id', 'loan_schedule_id'], 'lpa_voucher_loan_schedule_uk');
            $table->index(['loan_id', 'voucher_id'], 'lpa_loan_voucher_idx');
        });

        DB::statement('ALTER TABLE loans ADD CONSTRAINT loan_values_chk CHECK (principal_amount > 0 AND interest_rate >= 0)');
        DB::statement('ALTER TABLE loan_schedules ADD CONSTRAINT loan_schedule_values_chk CHECK (principal_due >= 0 AND interest_due >= 0)');
        DB::statement('ALTER TABLE loan_payment_allocations ADD CONSTRAINT loan_payment_values_chk CHECK (principal_amount >= 0 AND interest_amount >= 0 AND fee_amount >= 0 AND (principal_amount + interest_amount + fee_amount) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_payment_allocations');
        Schema::dropIfExists('loan_schedules');
        Schema::dropIfExists('loans');
    }
};
