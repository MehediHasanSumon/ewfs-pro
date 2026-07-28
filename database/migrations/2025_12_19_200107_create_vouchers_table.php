<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no', 100)->unique();
            $table->enum('voucher_type', ['payment', 'receipt', 'office_payment', 'contra', 'opening_balance']);
            $table->date('voucher_date');
            $table->time('voucher_time')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();
            $table->foreignId('voucher_category_id')->nullable()->constrained('voucher_categories')->restrictOnDelete();
            $table->foreignId('payment_sub_type_id')->nullable()->constrained('payment_sub_types')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->enum('status', ['draft', 'posted', 'reversed', 'void'])->default('draft');
            $table->string('external_reference', 150)->nullable();
            $table->text('description')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('vouchers')->restrictOnDelete();
            $table->timestamps();

            $table->index(['voucher_date', 'voucher_type', 'status', 'id'], 'voucher_date_type_idx');
            $table->index(['shift_id', 'voucher_date', 'status', 'id'], 'voucher_shift_date_idx');
            $table->index(['voucher_category_id', 'voucher_date', 'status'], 'voucher_category_date_idx');
            $table->index(['payment_sub_type_id', 'voucher_date', 'status'], 'voucher_subtype_date_idx');
        });

        Schema::create('voucher_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->enum('entry_side', ['debit', 'credit']);
            $table->decimal('amount', 24, 4);
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->unique(['voucher_id', 'line_no']);
            $table->index(['account_id', 'voucher_id'], 'vl_account_voucher_idx');
            $table->index(['customer_id', 'voucher_id'], 'vl_customer_voucher_idx');
            $table->index(['supplier_id', 'voucher_id'], 'vl_supplier_voucher_idx');
            $table->index(['employee_id', 'voucher_id'], 'vl_employee_voucher_idx');
        });

        Schema::create('voucher_payment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_line_id')->unique()->constrained('voucher_lines')->cascadeOnDelete();
            $table->enum('payment_method', ['cash', 'bank', 'cheque', 'mobile_bank', 'online']);
            $table->string('bank_type', 50)->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->string('branch_name', 150)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('cheque_number', 100)->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('mobile_bank_name', 100)->nullable();
            $table->string('mobile_number', 50)->nullable();
            $table->string('transaction_reference', 150)->nullable();
            $table->timestamps();

            $table->index(['payment_method', 'transaction_reference'], 'vpd_method_reference_idx');
            $table->index(['cheque_number', 'cheque_date'], 'vpd_cheque_date_idx');
        });

        Schema::create('sale_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers')->restrictOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->decimal('amount', 24, 4);
            $table->timestamps();

            $table->unique(['voucher_id', 'sale_id']);
            $table->index(['sale_id', 'voucher_id']);
        });

        Schema::create('credit_sale_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers')->restrictOnDelete();
            $table->foreignId('credit_sale_customer_id')->constrained('credit_sale_customers')->restrictOnDelete();
            $table->decimal('amount', 24, 4);
            $table->timestamps();

            $table->unique(['voucher_id', 'credit_sale_customer_id'], 'cspa_voucher_customer_uk');
            $table->index(['credit_sale_customer_id', 'voucher_id'], 'cspa_customer_voucher_idx');
        });

        Schema::create('purchase_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers')->restrictOnDelete();
            $table->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $table->decimal('amount', 24, 4);
            $table->timestamps();

            $table->unique(['voucher_id', 'purchase_id']);
            $table->index(['purchase_id', 'voucher_id']);
        });

        DB::statement('ALTER TABLE voucher_lines ADD CONSTRAINT voucher_line_amount_chk CHECK (amount > 0)');
        DB::statement('ALTER TABLE sale_payment_allocations ADD CONSTRAINT spa_amount_chk CHECK (amount > 0)');
        DB::statement('ALTER TABLE credit_sale_payment_allocations ADD CONSTRAINT cspa_amount_chk CHECK (amount > 0)');
        DB::statement('ALTER TABLE purchase_payment_allocations ADD CONSTRAINT ppa_amount_chk CHECK (amount > 0)');

        DB::unprepared("
            CREATE TRIGGER voucher_lines_before_insert
            BEFORE INSERT ON voucher_lines
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM vouchers WHERE id = NEW.voucher_id) <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Voucher lines can only be added to draft vouchers';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER voucher_lines_before_update
            BEFORE UPDATE ON voucher_lines
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM vouchers WHERE id = OLD.voucher_id) <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted voucher lines are immutable';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER voucher_lines_before_delete
            BEFORE DELETE ON voucher_lines
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM vouchers WHERE id = OLD.voucher_id) <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted voucher lines cannot be deleted';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER vouchers_before_update
            BEFORE UPDATE ON vouchers
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('posted', 'reversed') THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted vouchers are immutable';
                END IF;

                IF NEW.shift_id IS NOT NULL AND EXISTS (
                    SELECT 1 FROM shift_closings
                    WHERE shift_id = NEW.shift_id
                      AND business_date = NEW.voucher_date
                      AND status = 'posted'
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Vouchers are locked for the closed shift';
                END IF;

                IF NEW.status = 'posted' AND (
                    NEW.journal_entry_id IS NULL OR
                    (SELECT status FROM journal_entries WHERE id = NEW.journal_entry_id) <> 'posted'
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A voucher can only post with a posted journal entry';
                END IF;

                IF NEW.status = 'posted' THEN
                    SET NEW.posted_at = COALESCE(NEW.posted_at, CURRENT_TIMESTAMP);
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER vouchers_before_delete
            BEFORE DELETE ON vouchers
            FOR EACH ROW
            BEGIN
                IF OLD.status <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only draft vouchers can be deleted';
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS vouchers_before_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS vouchers_before_update');
        DB::unprepared('DROP TRIGGER IF EXISTS voucher_lines_before_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS voucher_lines_before_update');
        DB::unprepared('DROP TRIGGER IF EXISTS voucher_lines_before_insert');

        Schema::dropIfExists('purchase_payment_allocations');
        Schema::dropIfExists('credit_sale_payment_allocations');
        Schema::dropIfExists('sale_payment_allocations');
        Schema::dropIfExists('voucher_payment_details');
        Schema::dropIfExists('voucher_lines');
        Schema::dropIfExists('vouchers');
    }
};
