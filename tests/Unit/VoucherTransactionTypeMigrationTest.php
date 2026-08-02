<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

it('upgrades legacy payment sub types without changing voucher references', function () {
    Schema::create('voucher_categories', function (Blueprint $table): void {
        $table->id();
        $table->string('code', 64)->unique();
        $table->string('name', 150);
        $table->timestamps();
    });

    Schema::create('payment_sub_types', function (Blueprint $table): void {
        $table->id();
        $table->string('code', 64)->unique();
        $table->string('name', 150);
        $table->foreignId('voucher_category_id')
            ->constrained('voucher_categories')
            ->restrictOnDelete();
        $table->string('type', 20);
        $table->string('report_bucket_code', 100)->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
        $table->unique(['voucher_category_id', 'name']);
    });

    Schema::create('vouchers', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('payment_sub_type_id')
            ->nullable()
            ->constrained('payment_sub_types')
            ->restrictOnDelete();
        $table->timestamps();
    });

    DB::table('voucher_categories')->insert([
        [
            'id' => 1,
            'code' => 'VC001',
            'name' => 'Customer',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 2,
            'code' => 'VC003',
            'name' => 'Supplier',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
    DB::table('payment_sub_types')->insert([
        'id' => 25,
        'voucher_category_id' => 2,
        'code' => '1019',
        'name' => 'Advance Payment',
        'type' => 'payment',
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('vouchers')->insert([
        'id' => 41,
        'payment_sub_type_id' => 25,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path(
        'migrations/2026_08_02_000012_create_voucher_transaction_types_master.php'
    );
    $migration->up();

    expect(Schema::hasTable('payment_sub_types'))->toBeFalse()
        ->and(Schema::hasTable('voucher_transaction_types'))->toBeTrue()
        ->and(Schema::hasColumn('vouchers', 'payment_sub_type_id'))->toBeFalse()
        ->and(Schema::hasColumn('vouchers', 'voucher_transaction_type_id'))->toBeTrue()
        ->and(DB::table('voucher_transaction_types')->where('id', 25)->value('voucher_type'))
        ->toBe('payment')
        ->and(DB::table('vouchers')->where('id', 41)->value('voucher_transaction_type_id'))
        ->toBe(25);

    DB::table('voucher_transaction_types')->insert([
        'voucher_category_id' => 1,
        'code' => '1019',
        'name' => 'Advance Return',
        'voucher_type' => 'payment',
        'sort_order' => 1,
        'status' => true,
        'is_system' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('voucher_transaction_types')->where('code', '1019')->count())
        ->toBe(2);

    $migration->down();
    $migration->up();

    expect(Schema::hasTable('voucher_transaction_types'))->toBeTrue()
        ->and(Schema::hasColumn('vouchers', 'voucher_transaction_type_id'))->toBeTrue()
        ->and(DB::table('vouchers')->where('id', 41)->value('voucher_transaction_type_id'))
        ->toBe(25)
        ->and(DB::table('voucher_transaction_types')->where('code', '1019')->count())
        ->toBe(2);
});
