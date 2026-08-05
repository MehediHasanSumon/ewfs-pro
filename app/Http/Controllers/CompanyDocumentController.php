<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyDocumentRequest;
use App\Http\Resources\CompanyDocumentResource;
use App\Models\CompanyDocument;
use App\Models\CompanySetting;
use App\Services\CompanyDocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyDocumentController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly CompanyDocumentService $documents
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:company-document-view', only: ['index', 'show', 'file']),
            new Middleware('permission:company-document-create', only: ['store']),
            new Middleware('permission:company-document-update', only: ['update']),
            new Middleware('permission:company-document-delete', only: ['destroy']),
        ];
    }

    public function index(Request $request, CompanySetting $companySetting)
    {
        $perPage = max(1, min($request->integer('per_page', 10), 100));
        $documents = $this->filteredQuery($request, $companySetting)
            ->paginate($perPage)
            ->withQueryString();

        $documents->through(
            fn (CompanyDocument $document) => (new CompanyDocumentResource(
                $document
            ))->resolve($request)
        );

        return Inertia::render('CompanyDocuments/Index', [
            'companySetting' => $companySetting->only(['id', 'company_name']),
            'companyDocuments' => $documents,
            'viewerDocuments' => CompanyDocumentResource::collection(
                $companySetting->documents()
                    ->select([
                        'id',
                        'company_setting_id',
                        'document_name',
                        'document_type',
                        'file_path',
                        'sort_order',
                        'status',
                        'created_at',
                        'updated_at',
                    ])
                    ->get()
            )->resolve($request),
            'filters' => $request->only([
                'search',
                'document_type',
                'status',
                'start_date',
                'end_date',
                'per_page',
            ]),
            'upload' => [
                'max_file_kb' => (int) config(
                    'erp.company_documents.max_file_kb',
                    10240
                ),
            ],
        ]);
    }

    public function store(
        CompanyDocumentRequest $request,
        CompanySetting $companySetting
    ) {
        $created = $this->documents->createMany(
            $companySetting,
            $request->attributesForPersistence(),
            $request->file('files', []),
            $request->user()?->getKey()
        );

        return back()->with(
            'success',
            $created->count() === 1
                ? 'Company document created successfully.'
                : "{$created->count()} company documents created successfully."
        );
    }

    public function show(
        CompanySetting $companySetting,
        CompanyDocument $companyDocument
    ): CompanyDocumentResource {
        $this->ensureOwnership($companySetting, $companyDocument);

        return new CompanyDocumentResource($companyDocument);
    }

    public function file(
        CompanySetting $companySetting,
        CompanyDocument $companyDocument
    ): StreamedResponse {
        $this->ensureOwnership($companySetting, $companyDocument);

        $disk = Storage::disk(
            config('erp.company_documents.disk', 'private')
        );
        $path = ltrim($companyDocument->file_path, '/');
        abort_unless($disk->exists($path), 404);

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $name = Str::slug($companyDocument->document_name) ?: 'document';
        $fileName = $extension === '' ? $name : "{$name}.{$extension}";

        return $disk->response(
            $path,
            $fileName,
            [
                'Cache-Control' => 'private, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline'
        );
    }

    public function update(
        CompanyDocumentRequest $request,
        CompanySetting $companySetting,
        CompanyDocument $companyDocument
    ) {
        $this->ensureOwnership($companySetting, $companyDocument);

        $this->documents->update(
            $companyDocument,
            $request->attributesForPersistence(),
            $request->file('file'),
            $request->user()?->getKey()
        );

        return back()->with('success', 'Company document updated successfully.');
    }

    public function destroy(
        CompanySetting $companySetting,
        CompanyDocument $companyDocument
    ) {
        $this->ensureOwnership($companySetting, $companyDocument);
        $this->documents->delete($companyDocument);

        return back()->with('success', 'Company document deleted successfully.');
    }

    private function filteredQuery(
        Request $request,
        CompanySetting $companySetting
    ): Builder {
        $query = $companySetting->documents()->getQuery();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('document_name', 'like', "%{$search}%")
                    ->orWhere('document_type', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%");

                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $search) === 1) {
                    $builder->orWhereDate('start_date', $search)
                        ->orWhereDate('end_date', $search);
                }
            });
        }

        if (
            $request->filled('document_type')
            && $request->input('document_type') !== 'all'
        ) {
            $query->where('document_type', $request->input('document_type'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('start_date', '>=', $request->date('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('end_date', '<=', $request->date('end_date'));
        }

        return $query
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function ensureOwnership(
        CompanySetting $companySetting,
        CompanyDocument $companyDocument
    ): void {
        abort_unless(
            $companyDocument->company_setting_id === $companySetting->getKey(),
            404
        );
    }
}
