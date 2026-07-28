<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->string('invoice_no', 100)->unique();
            $table->string('supplier_invoice_no', 150)->nullable();
            $table->string('memo_no', 150)->nullable();
            $table->date('purchase_date');
            $table->time('purchase_time')->nullable();
            $table->decimal('subtotal', 24, 4);
            $table->decimal('discount_total', 24, 4)->default(0);
            $table->decimal('tax_total', 24, 4)->default(0);
            $table->decimal('grand_total', 24, 4);
            $table->enum('status', ['draft', 'posted', 'partially_paid', 'paid', 'void'])->default('draft');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'purchase_date', 'status', 'id'], 'purchase_supplier_date_idx');
            $table->index(['purchase_date', 'status', 'id'], 'purchase_date_status_idx');
            $table->index(['shift_id', 'purchase_date', 'status'], 'purchase_shift_date_idx');
            $table->index(['supplier_invoice_no', 'supplier_id'], 'purchase_supplier_invoice_idx');
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('product_code_snapshot', 64)->nullable();
            $table->string('product_name_snapshot', 150);
            $table->string('unit_name_snapshot', 100);
            $table->decimal('quantity', 24, 6);
            $table->decimal('unit_cost', 24, 6);
            $table->decimal('discount_amount', 24, 4)->default(0);
            $table->decimal('tax_amount', 24, 4)->default(0);
            $table->decimal('line_total', 24, 4);
            $table->timestamps();

            $table->unique(['purchase_id', 'line_no']);
            $table->index(['product_id', 'purchase_id'], 'purchase_item_product_idx');
        });

        DB::statement('ALTER TABLE purchases ADD CONSTRAINT purchase_totals_chk CHECK (subtotal >= 0 AND discount_total >= 0 AND tax_total >= 0 AND grand_total >= 0)');
        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT purchase_item_values_chk CHECK (quantity > 0 AND unit_cost >= 0 AND discount_amount >= 0 AND tax_amount >= 0 AND line_total >= 0)');

        DB::unprepared("
            CREATE TRIGGER purchase_items_before_insert
            BEFORE INSERT ON purchase_items
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM purchases WHERE id = NEW.purchase_id) <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Purchase items can only be added to draft purchases';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER purchase_items_before_update
            BEFORE UPDATE ON purchase_items
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM purchases WHERE id = OLD.purchase_id) <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted purchase items are immutable';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER purchase_items_before_delete
            BEFORE DELETE ON purchase_items
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM purchases WHERE id = OLD.purchase_id) <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted purchase items cannot be deleted';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER purchases_before_update
            BEFORE UPDATE ON purchases
            FOR EACH ROW
            BEGIN
                IF OLD.status <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted purchases are immutable';
                END IF;

                IF NEW.status = 'posted' AND (
                    NEW.journal_entry_id IS NULL OR
                    (SELECT status FROM journal_entries WHERE id = NEW.journal_entry_id) <> 'posted'
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A purchase can only post with a posted journal entry';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER purchases_before_delete
            BEFORE DELETE ON purchases
            FOR EACH ROW
            BEGIN
                IF OLD.status <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only draft purchases can be deleted';
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS purchases_before_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS purchases_before_update');
        DB::unprepared('DROP TRIGGER IF EXISTS purchase_items_before_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS purchase_items_before_update');
        DB::unprepared('DROP TRIGGER IF EXISTS purchase_items_before_insert');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
