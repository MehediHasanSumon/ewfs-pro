<?php

namespace App\Services;

use App\Models\CompanyDocument;
use App\Models\CompanySetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CompanyDocumentService
{
    private const MIME_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'image',
        'image/png' => 'image',
        'image/webp' => 'image',
    ];

    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, CompanyDocument>
     */
    public function createMany(
        CompanySetting $companySetting,
        array $attributes,
        array $files,
        ?int $userId
    ): Collection {
        $storedFiles = [];

        try {
            foreach ($files as $file) {
                $storedFiles[] = $this->storeFile($companySetting, $file);
            }
        } catch (Throwable $exception) {
            $this->deleteFiles(array_column($storedFiles, 'path'));
            throw $exception;
        }

        try {
            return DB::transaction(function () use (
                $companySetting,
                $attributes,
                $storedFiles,
                $userId
            ): Collection {
                $nextSortOrder = ((int) $companySetting->documents()->max('sort_order')) + 1;

                return collect($storedFiles)->map(
                    function (array $storedFile, int $index) use (
                        $companySetting,
                        $attributes,
                        $nextSortOrder,
                        $userId
                    ): CompanyDocument {
                        return $companySetting->documents()->create([
                            ...$attributes,
                            'document_type' => $storedFile['type'],
                            'file_path' => $storedFile['path'],
                            'sort_order' => $nextSortOrder + $index,
                            'created_by' => $userId,
                            'updated_by' => $userId,
                        ]);
                    }
                );
            }, 3);
        } catch (Throwable $exception) {
            $this->deleteFiles(array_column($storedFiles, 'path'));
            throw $exception;
        }
    }

    public function update(
        CompanyDocument $document,
        array $attributes,
        ?UploadedFile $file,
        ?int $userId
    ): CompanyDocument {
        $storedFile = $file
            ? $this->storeFile($document->companySetting, $file)
            : null;
        $previousPath = $document->file_path;

        try {
            DB::transaction(function () use (
                $document,
                $attributes,
                $storedFile,
                $userId
            ): void {
                $document->update([
                    ...$attributes,
                    ...($storedFile ? [
                        'document_type' => $storedFile['type'],
                        'file_path' => $storedFile['path'],
                    ] : []),
                    'updated_by' => $userId,
                ]);
            }, 3);
        } catch (Throwable $exception) {
            if ($storedFile) {
                $this->deleteFiles([$storedFile['path']]);
            }

            throw $exception;
        }

        if ($storedFile) {
            $this->deleteFiles([$previousPath]);
        }

        return $document->refresh();
    }

    public function delete(CompanyDocument $document): void
    {
        $path = $document->file_path;

        DB::transaction(fn () => $document->delete(), 3);
        $this->deleteFiles([$path]);
    }

    /**
     * @return array{path: string, type: string}
     */
    private function storeFile(
        CompanySetting $companySetting,
        UploadedFile $file
    ): array {
        $mimeType = (string) $file->getMimeType();
        $documentType = self::MIME_TYPES[$mimeType] ?? null;

        if ($documentType === null) {
            throw ValidationException::withMessages([
                'files' => 'The selected file type is not supported.',
            ]);
        }

        $directory = trim(
            (string) config('erp.company_documents.directory', 'company-documents'),
            '/'
        ).'/'.$companySetting->getKey();
        $path = $file->store($directory, $this->disk());

        if ($path === false) {
            throw new RuntimeException('Unable to store the company document.');
        }

        return [
            'path' => $path,
            'type' => $documentType,
        ];
    }

    /**
     * @param  array<int, string|null>  $paths
     */
    private function deleteFiles(array $paths): void
    {
        $paths = array_values(array_filter(array_map(
            fn (?string $path): ?string => $path ? ltrim($path, '/') : null,
            $paths
        )));

        if ($paths !== []) {
            Storage::disk($this->disk())->delete($paths);
        }
    }

    private function disk(): string
    {
        return (string) config('erp.company_documents.disk', 'private');
    }
}
