<?php

use App\Http\Requests\CompanyDocumentRequest;
use App\Http\Resources\CompanyDocumentResource;
use App\Models\CompanyDocument;
use App\Models\CompanySetting;
use App\Services\CompanyDocumentService;
use App\Services\CompanySettingService;
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
        $table->string('company_logo')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('company_documents', function (Blueprint $table) {
        $table->id();
        $table->foreignId('company_setting_id');
        $table->string('document_name');
        $table->string('document_type', 20);
        $table->date('start_date')->nullable();
        $table->date('end_date')->nullable();
        $table->string('file_path');
        $table->unsignedInteger('sort_order')->default(0);
        $table->text('remarks')->nullable();
        $table->boolean('status')->default(true);
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('updated_by')->nullable();
        $table->timestamps();
    });

    Storage::fake('public');
    Storage::fake('private');
    config()->set('erp.company_documents.disk', 'private');
    config()->set('erp.company_documents.directory', 'company-documents');
    config()->set('erp.company_documents.max_file_kb', 10240);
});

function companyDocumentSetting(): CompanySetting
{
    return CompanySetting::query()->create([
        'company_name' => 'Example Company',
        'status' => true,
    ]);
}

function companyDocumentPng(string $name = 'license.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        )
    );
}

function companyDocumentPdf(string $name = 'certificate.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"
    );
}

it('stores mixed uploads as normalized records using detected mime types', function () {
    $documents = (new CompanyDocumentService)->createMany(
        companyDocumentSetting(),
        [
            'document_name' => 'Registration Documents',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'remarks' => 'Annual registration set.',
            'status' => true,
        ],
        [
            companyDocumentPng(),
            companyDocumentPng('registration.webp'),
            companyDocumentPdf(),
            companyDocumentPdf('tax-certificate.pdf'),
        ],
        7
    );

    expect($documents)->toHaveCount(4)
        ->and($documents->pluck('document_type')->all())
        ->toBe(['image', 'image', 'pdf', 'pdf'])
        ->and(CompanyDocument::query()->count())->toBe(4)
        ->and($documents->pluck('document_name')->unique()->all())
        ->toBe(['Registration Documents'])
        ->and($documents->pluck('created_by')->unique()->all())->toBe([7]);

    foreach ($documents as $document) {
        expect($document->file_path)
            ->toStartWith('company-documents/1/')
            ->not->toStartWith('/');
        Storage::disk('private')->assertExists($document->file_path);
    }
});

it('updates metadata without replacing the existing file', function () {
    $service = new CompanyDocumentService;
    $document = $service->createMany(
        companyDocumentSetting(),
        [
            'document_name' => 'Trade License',
            'start_date' => null,
            'end_date' => null,
            'remarks' => null,
            'status' => true,
        ],
        [companyDocumentPng()],
        1
    )->firstOrFail();
    $originalPath = $document->file_path;

    $updated = $service->update(
        $document,
        [
            'document_name' => 'Updated Trade License',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'remarks' => 'Renewed.',
            'status' => false,
        ],
        null,
        2
    );

    expect($updated->document_name)->toBe('Updated Trade License')
        ->and($updated->file_path)->toBe($originalPath)
        ->and($updated->document_type)->toBe('image')
        ->and($updated->status)->toBeFalse()
        ->and($updated->updated_by)->toBe(2);
    Storage::disk('private')->assertExists($originalPath);
});

it('replaces a document file and removes the previous stored file', function () {
    $service = new CompanyDocumentService;
    $document = $service->createMany(
        companyDocumentSetting(),
        [
            'document_name' => 'Certificate',
            'start_date' => null,
            'end_date' => null,
            'remarks' => null,
            'status' => true,
        ],
        [companyDocumentPng()],
        1
    )->firstOrFail();
    $oldPath = $document->file_path;

    $updated = $service->update(
        $document,
        [
            'document_name' => 'Certificate',
            'start_date' => null,
            'end_date' => null,
            'remarks' => null,
            'status' => true,
        ],
        companyDocumentPdf('replacement.pdf'),
        2
    );

    expect($updated->document_type)->toBe('pdf')
        ->and($updated->file_path)->not->toBe($oldPath);
    Storage::disk('private')->assertMissing($oldPath);
    Storage::disk('private')->assertExists($updated->file_path);
});

it('deletes both the normalized record and its stored file', function () {
    $service = new CompanyDocumentService;
    $document = $service->createMany(
        companyDocumentSetting(),
        [
            'document_name' => 'Tax Document',
            'start_date' => null,
            'end_date' => null,
            'remarks' => null,
            'status' => true,
        ],
        [companyDocumentPdf()],
        1
    )->firstOrFail();
    $path = $document->file_path;

    $service->delete($document);

    expect(CompanyDocument::query()->whereKey($document->id)->exists())
        ->toBeFalse();
    Storage::disk('private')->assertMissing($path);
});

it('cleans company document files when the parent company setting is deleted', function () {
    $companySetting = companyDocumentSetting();
    Storage::disk('public')->put('images/company/logo.png', 'logo');
    Storage::disk('private')->put('company-documents/1/license.pdf', 'pdf');
    $companySetting->update(['company_logo' => '/images/company/logo.png']);
    $companySetting->documents()->create([
        'document_name' => 'License',
        'document_type' => 'pdf',
        'file_path' => 'company-documents/1/license.pdf',
        'sort_order' => 1,
        'status' => true,
    ]);

    (new CompanySettingService)->delete($companySetting);

    expect(CompanySetting::query()->whereKey($companySetting->id)->exists())
        ->toBeFalse();
    Storage::disk('public')->assertMissing('images/company/logo.png');
    Storage::disk('private')->assertMissing('company-documents/1/license.pdf');
});

it('validates supported mime types and chronological document dates', function () {
    $request = CompanyDocumentRequest::create('/documents', 'POST');
    $request->setContainer(app());
    $validator = Validator::make([
        'document_name' => 'Invalid Document',
        'start_date' => '2026-12-31',
        'end_date' => '2026-01-01',
        'status' => true,
        'files' => [
            UploadedFile::fake()->createWithContent(
                'payload.txt',
                'not a supported document'
            ),
        ],
    ], $request->rules(), $request->messages());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('end_date'))->toBeTrue()
        ->and($validator->errors()->has('files.0'))->toBeTrue();
});

it('returns an authenticated viewer route without exposing a storage path', function () {
    $document = CompanyDocument::query()->create([
        'company_setting_id' => companyDocumentSetting()->id,
        'document_name' => 'Trade License',
        'document_type' => 'pdf',
        'file_path' => 'company-documents/1/license.pdf',
        'sort_order' => 1,
        'status' => true,
    ]);
    $resource = (new CompanyDocumentResource($document))->resolve();

    expect($resource['file_url'])
        ->toBe("/company-settings/1/documents/{$document->id}/file")
        ->and($resource)->not->toHaveKey('file_path')
        ->and($resource['document_type'])->toBe('pdf');
});
