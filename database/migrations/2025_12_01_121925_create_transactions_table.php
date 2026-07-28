<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->enum('status', ['open', 'closing', 'closed'])->default('open');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['starts_on', 'ends_on']);
            $table->index(['status', 'starts_on', 'ends_on']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_period_id')->nullable()->constrained('accounting_periods')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();
            $table->string('entry_no', 100)->unique();
            $table->date('business_date');
            $table->timestamp('occurred_at');
            $table->string('event_type', 64);
            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference_no', 150)->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->string('idempotency_key', 150)->unique();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['business_date', 'status', 'id'], 'je_date_status_id_idx');
            $table->index(['shift_id', 'business_date', 'status'], 'je_shift_date_status_idx');
            $table->index(['source_type', 'source_id'], 'je_source_idx');
            $table->index(['event_type', 'business_date', 'id'], 'je_event_date_id_idx');
            $table->index(['accounting_period_id', 'status'], 'je_period_status_idx');
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('debit_amount', 24, 4)->default(0);
            $table->decimal('credit_amount', 24, 4)->default(0);
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('payment_method', 32)->nullable();
            $table->string('description', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['journal_entry_id', 'line_no']);
            $table->index(['account_id', 'journal_entry_id'], 'jl_account_entry_idx');
            $table->index(['customer_id', 'journal_entry_id'], 'jl_customer_entry_idx');
            $table->index(['supplier_id', 'journal_entry_id'], 'jl_supplier_entry_idx');
            $table->index(['employee_id', 'journal_entry_id'], 'jl_employee_entry_idx');
            $table->index(['product_id', 'journal_entry_id'], 'jl_product_entry_idx');
        });

        Schema::create('party_opening_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->enum('balance_type', ['receivable', 'payable', 'customer_deposit', 'employee_advance', 'loan']);
            $table->date('effective_date');
            $table->decimal('amount', 24, 4);
            $table->foreignId('journal_entry_id')->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->enum('status', ['posted', 'reversed'])->default('posted');
            $table->timestamps();

            $table->unique(['customer_id', 'balance_type'], 'pob_customer_type_uk');
            $table->unique(['supplier_id', 'balance_type'], 'pob_supplier_type_uk');
            $table->unique(['employee_id', 'balance_type'], 'pob_employee_type_uk');
            $table->index(['effective_date', 'balance_type']);
        });

        Schema::create('account_daily_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->date('balance_date');
            $table->decimal('opening_balance', 24, 4)->default(0);
            $table->decimal('debit_total', 24, 4)->default(0);
            $table->decimal('credit_total', 24, 4)->default(0);
            $table->decimal('closing_balance', 24, 4)->default(0);
            $table->unsignedBigInteger('last_journal_line_id')->nullable();
            $table->timestamp('refreshed_at')->nullable();

            $table->unique(['account_id', 'balance_date']);
            $table->index(['balance_date', 'account_id'], 'adb_date_account_idx');
        });

        Schema::create('party_daily_balances', function (Blueprint $table) {
            $table->id();
            $table->enum('party_type', ['customer', 'supplier', 'employee']);
            $table->unsignedBigInteger('party_id');
            $table->date('balance_date');
            $table->decimal('opening_balance', 24, 4)->default(0);
            $table->decimal('debit_total', 24, 4)->default(0);
            $table->decimal('credit_total', 24, 4)->default(0);
            $table->decimal('closing_balance', 24, 4)->default(0);
            $table->unsignedBigInteger('last_journal_line_id')->nullable();
            $table->timestamp('refreshed_at')->nullable();

            $table->unique(['party_type', 'party_id', 'balance_date'], 'pdb_party_date_uk');
            $table->index(['balance_date', 'party_type', 'party_id'], 'pdb_date_party_idx');
        });

        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT jl_one_sided_amount_chk CHECK ((debit_amount > 0 AND credit_amount = 0) OR (credit_amount > 0 AND debit_amount = 0))');
        DB::statement('ALTER TABLE party_opening_balances ADD CONSTRAINT pob_positive_amount_chk CHECK (amount > 0)');
        DB::statement('ALTER TABLE party_opening_balances ADD CONSTRAINT pob_single_party_chk CHECK (((customer_id IS NOT NULL) + (supplier_id IS NOT NULL) + (employee_id IS NOT NULL)) = 1)');

        DB::unprepared("
            CREATE TRIGGER journal_lines_before_insert
            BEFORE INSERT ON journal_lines
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM journal_entries WHERE id = NEW.journal_entry_id) <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal lines can only be added to draft entries';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER journal_lines_before_update
            BEFORE UPDATE ON journal_lines
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM journal_entries WHERE id = OLD.journal_entry_id) <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted journal lines are immutable';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER journal_lines_before_delete
            BEFORE DELETE ON journal_lines
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM journal_entries WHERE id = OLD.journal_entry_id) <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted journal lines cannot be deleted';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER journal_entries_before_post
            BEFORE UPDATE ON journal_entries
            FOR EACH ROW
            BEGIN
                DECLARE debit_total DECIMAL(24,4);
                DECLARE credit_total DECIMAL(24,4);
                DECLARE line_count BIGINT;

                IF OLD.status = 'posted' AND NEW.status = 'posted' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted journal entries are immutable';
                END IF;

                IF NEW.status = 'posted' AND OLD.status = 'draft' THEN
                    SELECT COALESCE(SUM(debit_amount), 0), COALESCE(SUM(credit_amount), 0), COUNT(*)
                    INTO debit_total, credit_total, line_count
                    FROM journal_lines
                    WHERE journal_entry_id = NEW.id;

                    IF line_count < 2 OR debit_total <= 0 OR debit_total <> credit_total THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal entry must contain balanced debit and credit lines';
                    END IF;

                    SET NEW.posted_at = COALESCE(NEW.posted_at, CURRENT_TIMESTAMP);
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS journal_entries_before_post');
        DB::unprepared('DROP TRIGGER IF EXISTS journal_lines_before_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS journal_lines_before_update');
        DB::unprepared('DROP TRIGGER IF EXISTS journal_lines_before_insert');

        Schema::dropIfExists('party_daily_balances');
        Schema::dropIfExists('account_daily_balances');
        Schema::dropIfExists('party_opening_balances');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_periods');
    }
};
