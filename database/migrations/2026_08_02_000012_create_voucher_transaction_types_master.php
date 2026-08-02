<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('payment_sub_types')
            && ! Schema::hasTable('voucher_transaction_types')
        ) {
            Schema::rename('payment_sub_types', 'voucher_transaction_types');
        }

        $createdTable = ! Schema::hasTable('voucher_transaction_types');

        if ($createdTable) {
            Schema::create('voucher_transaction_types', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('voucher_category_id')
                    ->constrained('voucher_categories')
                    ->restrictOnDelete();
                $table->string('code', 64);
                $table->string('name', 150);
                $table->string('voucher_type', 20);
                $table->string('report_bucket_code', 100)->nullable();
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('status')->default(true);
                $table->boolean('is_system')->default(false);
                $table->timestamps();

                $table->unique(
                    ['voucher_category_id', 'code'],
                    'voucher_transaction_types_category_code_unique'
                );
                $table->unique(
                    ['voucher_category_id', 'name'],
                    'voucher_transaction_types_category_name_unique'
                );
                $table->index(['voucher_type', 'status', 'sort_order'], 'vtt_type_status_sort_idx');
                $table->index(['status', 'sort_order'], 'vtt_status_sort_idx');
            });
        }

        if (! $createdTable && Schema::hasColumn('voucher_transaction_types', 'code')) {
            $this->prepareCategoryScopedCodeUniqueness();
        }

        if (! $createdTable && (
            Schema::hasColumn('voucher_transaction_types', 'type')
            && ! Schema::hasColumn('voucher_transaction_types', 'voucher_type')
        )) {
            Schema::table('voucher_transaction_types', function (Blueprint $table): void {
                $table->renameColumn('type', 'voucher_type');
            });
        }

        if (! $createdTable) {
            Schema::table('voucher_transaction_types', function (Blueprint $table): void {
                if (! Schema::hasColumn('voucher_transaction_types', 'description')) {
                    $table->text('description')->nullable()->after('report_bucket_code');
                }

                if (! Schema::hasColumn('voucher_transaction_types', 'sort_order')) {
                    $table->unsignedSmallInteger('sort_order')->default(0)->after('description');
                }

                if (! Schema::hasColumn('voucher_transaction_types', 'is_system')) {
                    $table->boolean('is_system')->default(false)->after('status');
                }
            });

            $this->ensureMasterIndexes();
        }

        if (
            Schema::hasTable('vouchers')
            && Schema::hasColumn('vouchers', 'payment_sub_type_id')
            && ! Schema::hasColumn('vouchers', 'voucher_transaction_type_id')
        ) {
            Schema::table('vouchers', function (Blueprint $table): void {
                $table->renameColumn(
                    'payment_sub_type_id',
                    'voucher_transaction_type_id'
                );
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('vouchers')
            && Schema::hasColumn('vouchers', 'voucher_transaction_type_id')
            && ! Schema::hasColumn('vouchers', 'payment_sub_type_id')
        ) {
            Schema::table('vouchers', function (Blueprint $table): void {
                $table->renameColumn(
                    'voucher_transaction_type_id',
                    'payment_sub_type_id'
                );
            });
        }

        if (
            Schema::hasTable('voucher_transaction_types')
            && Schema::hasColumn('voucher_transaction_types', 'voucher_type')
            && ! Schema::hasColumn('voucher_transaction_types', 'type')
        ) {
            Schema::table('voucher_transaction_types', function (Blueprint $table): void {
                $table->renameColumn('voucher_type', 'type');
            });
        }

        if (
            Schema::hasTable('voucher_transaction_types')
            && ! Schema::hasTable('payment_sub_types')
        ) {
            Schema::rename('voucher_transaction_types', 'payment_sub_types');
        }
    }

    private function prepareCategoryScopedCodeUniqueness(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $indexes = DB::select("SHOW INDEX FROM voucher_transaction_types WHERE Column_name = 'code' AND Non_unique = 0");

            foreach ($indexes as $index) {
                if ($index->Key_name !== 'PRIMARY') {
                    DB::statement(
                        'ALTER TABLE voucher_transaction_types DROP INDEX `'
                        .str_replace('`', '``', $index->Key_name).'`'
                    );
                }
            }

            return;
        }

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('voucher_transaction_types')");

            foreach ($indexes as $index) {
                if ((int) $index->unique !== 1) {
                    continue;
                }

                $columns = DB::select("PRAGMA index_info('{$index->name}')");

                if (count($columns) === 1 && $columns[0]->name === 'code') {
                    if (str_starts_with($index->name, 'sqlite_autoindex')) {
                        $this->rebuildLegacySqliteTable();

                        return;
                    }

                    DB::statement('DROP INDEX "'.str_replace('"', '""', $index->name).'"');
                }
            }
        }
    }

    private function ensureMasterIndexes(): void
    {
        if (! Schema::hasIndex(
            'voucher_transaction_types',
            ['voucher_category_id', 'code'],
            'unique'
        )) {
            Schema::table('voucher_transaction_types', function (Blueprint $table): void {
                $table->unique(
                    ['voucher_category_id', 'code'],
                    'voucher_transaction_types_category_code_unique'
                );
            });
        }

        if (! Schema::hasIndex(
            'voucher_transaction_types',
            ['voucher_type', 'status', 'sort_order']
        )) {
            Schema::table('voucher_transaction_types', function (Blueprint $table): void {
                $table->index(
                    ['voucher_type', 'status', 'sort_order'],
                    'vtt_type_status_sort_idx'
                );
            });
        }

        if (! Schema::hasIndex(
            'voucher_transaction_types',
            ['status', 'sort_order']
        )) {
            Schema::table('voucher_transaction_types', function (Blueprint $table): void {
                $table->index(
                    ['status', 'sort_order'],
                    'vtt_status_sort_idx'
                );
            });
        }
    }

    private function rebuildLegacySqliteTable(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            Schema::create('voucher_transaction_types_upgrade', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('voucher_category_id')
                    ->constrained('voucher_categories')
                    ->restrictOnDelete();
                $table->string('code', 64);
                $table->string('name', 150);
                $table->string('voucher_type', 20);
                $table->string('report_bucket_code', 100)->nullable();
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('status')->default(true);
                $table->boolean('is_system')->default(false);
                $table->timestamps();
                $table->unique(
                    ['voucher_category_id', 'name'],
                    'voucher_transaction_types_category_name_unique'
                );
            });

            DB::statement(
                'INSERT INTO voucher_transaction_types_upgrade (
                    id,
                    voucher_category_id,
                    code,
                    name,
                    voucher_type,
                    report_bucket_code,
                    description,
                    sort_order,
                    status,
                    is_system,
                    created_at,
                    updated_at
                )
                SELECT
                    id,
                    voucher_category_id,
                    code,
                    name,
                    type,
                    report_bucket_code,
                    NULL,
                    0,
                    status,
                    0,
                    created_at,
                    updated_at
                FROM voucher_transaction_types'
            );

            Schema::drop('voucher_transaction_types');
            Schema::rename(
                'voucher_transaction_types_upgrade',
                'voucher_transaction_types'
            );
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
