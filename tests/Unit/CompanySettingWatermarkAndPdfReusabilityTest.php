<?php

use App\Http\Requests\CompanySettingRequest;
use App\Models\CompanySetting;
use App\Services\CompanySettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('company_settings', function (Blueprint $table) {
        $table->id();
        $table->string('company_name');
        $table->string('company_address')->nullable();
        $table->string('company_mobile')->nullable();
        $table->string('company_email')->nullable();
        $table->string('currency', 3)->default('BDT');
        $table->string('company_logo')->nullable();
        $table->string('pdf_watermark_image')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('company_documents', function (Blueprint $table) {
        $table->id();
        $table->foreignId('company_setting_id');
        $table->string('file_path');
        $table->unsignedInteger('sort_order')->default(0);
        $table->timestamps();
    });

    Storage::fake('public');
});

function fakePngFile(string $name = 'watermark.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        )
    );
}

test('it handles watermark creation, replacement, removal, and cleanup in CompanySettingService', function () {
    $service = app(CompanySettingService::class);
    $watermark = fakePngFile('watermark.png');

    $setting = $service->create([
        'company_name' => 'East West Filling Station',
        'currency' => 'BDT',
        'status' => true,
    ], null, $watermark);

    expect($setting->pdf_watermark_image)->not->toBeNull();
    Storage::disk('public')->assertExists(ltrim($setting->pdf_watermark_image, '/'));

    // Replace watermark
    $newWatermark = fakePngFile('watermark_v2.png');
    $oldPath = $setting->pdf_watermark_image;

    $updated = $service->update($setting, [
        'company_name' => 'East West Filling Station Updated',
        'currency' => 'BDT',
        'status' => true,
    ], null, $newWatermark);

    expect($updated->pdf_watermark_image)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing(ltrim($oldPath, '/'));
    Storage::disk('public')->assertExists(ltrim($updated->pdf_watermark_image, '/'));

    // Remove watermark
    $currentPath = $updated->pdf_watermark_image;
    $removed = $service->update($updated, [
        'company_name' => 'East West Filling Station Updated',
        'currency' => 'BDT',
        'status' => true,
    ], null, null, true);

    expect($removed->pdf_watermark_image)->toBeNull();
    Storage::disk('public')->assertMissing(ltrim($currentPath, '/'));

    // Re-add and delete company setting
    $finalWatermark = fakePngFile('watermark_final.png');
    $withWatermark = $service->update($removed, [
        'company_name' => 'East West Filling Station Updated',
        'currency' => 'BDT',
        'status' => true,
    ], null, $finalWatermark);

    $finalPath = $withWatermark->pdf_watermark_image;
    Storage::disk('public')->assertExists(ltrim($finalPath, '/'));

    $service->delete($withWatermark);
    Storage::disk('public')->assertMissing(ltrim($finalPath, '/'));
});

test('it validates watermark file format and file size in request', function () {
    $rules = (new CompanySettingRequest())->rules();

    // Valid watermark image
    $validImage = fakePngFile('watermark.png');
    $validator = Validator::make([
        'company_name' => 'Test Company',
        'pdf_watermark_image' => $validImage,
    ], $rules);
    expect($validator->passes())->toBeTrue();

    // Invalid non-image file
    $textDoc = UploadedFile::fake()->create('document.txt', 100);
    $invalidValidator = Validator::make([
        'company_name' => 'Test Company',
        'pdf_watermark_image' => $textDoc,
    ], $rules);
    expect($invalidValidator->fails())->toBeTrue()
        ->and($invalidValidator->errors()->has('pdf_watermark_image'))->toBeTrue();
});

test('it renders PDF with reusable watermark and header components', function () {
    $service = app(CompanySettingService::class);
    $watermark = fakePngFile('watermark_test.png');

    $setting = $service->create([
        'company_name' => 'East West Test Station',
        'company_address' => 'Dhaka, Bangladesh',
        'company_mobile' => '01700000000',
        'company_email' => 'test@station.com',
        'currency' => 'BDT',
        'status' => true,
    ], null, $watermark);

    $pdf = Pdf::loadView('pdf.accounts', [
        'accounts' => [],
        'companySetting' => $setting,
    ]);
    $output = $pdf->output();

    expect(strlen($output))->toBeGreaterThan(1000);
});
