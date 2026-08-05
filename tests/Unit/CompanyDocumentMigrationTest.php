<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

it('creates the normalized company documents helper table', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
    });

    Schema::create('company_settings', function (Blueprint $table) {
        $table->id();
    });

    $migration = require database_path(
        'migrations/2026_08_05_000018_create_company_documents_table.php'
    );
    $migration->up();

    expect(Schema::hasTable('company_documents'))->toBeTrue()
        ->and(Schema::hasColumns('company_documents', [
            'id',
            'company_setting_id',
            'document_name',
            'document_type',
            'start_date',
            'end_date',
            'file_path',
            'sort_order',
            'remarks',
            'status',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});
