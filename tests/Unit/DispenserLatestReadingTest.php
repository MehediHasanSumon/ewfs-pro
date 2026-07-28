<?php

use App\Models\Dispenser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('dispensers', function (Blueprint $table) {
        $table->id();
        $table->string('dispenser_name');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('dispenser_readings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('dispenser_id');
        $table->decimal('start_reading', 24, 6);
        $table->decimal('end_reading', 24, 6);
        $table->decimal('unit_price', 24, 6);
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('dispenser_readings');
    Schema::dropIfExists('dispensers');
});

it('loads the latest dispenser reading with qualified selected columns', function () {
    $dispenserId = DB::table('dispensers')->insertGetId([
        'dispenser_name' => 'Pump 1',
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('dispenser_readings')->insert([
        [
            'dispenser_id' => $dispenserId,
            'start_reading' => 100,
            'end_reading' => 150,
            'unit_price' => 120,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'dispenser_id' => $dispenserId,
            'start_reading' => 150,
            'end_reading' => 200,
            'unit_price' => 125,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $dispenser = Dispenser::query()
        ->with([
            'latestReading' => fn ($query) => $query->select([
                'dispenser_readings.id',
                'dispenser_readings.dispenser_id',
                'dispenser_readings.start_reading',
                'dispenser_readings.end_reading',
                'dispenser_readings.unit_price',
            ]),
        ])
        ->findOrFail($dispenserId);

    expect((float) $dispenser->latestReading->end_reading)->toBe(200.0)
        ->and((float) $dispenser->latestReading->unit_price)->toBe(125.0);
});
