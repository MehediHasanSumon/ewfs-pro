<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyDocumentRequest extends FormRequest
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxFileSize = (int) config('erp.company_documents.max_file_kb', 10240);
        $fileRules = [
            'file',
            'mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES),
            'max:'.$maxFileSize,
        ];

        $rules = [
            'document_name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'boolean'],
        ];

        if ($this->route('companyDocument')) {
            return [
                ...$rules,
                'file' => ['nullable', ...$fileRules],
            ];
        }

        return [
            ...$rules,
            'files' => ['required', 'array', 'min:1', 'max:50'],
            'files.*' => ['required', ...$fileRules],
        ];
    }

    public function attributesForPersistence(): array
    {
        return $this->safe()->except(['file', 'files']);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('document_name')) {
            $this->merge([
                'document_name' => trim((string) $this->input('document_name')),
            ]);
        }

        if (! $this->has('status')) {
            $this->merge(['status' => true]);
        }
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Select at least one company document.',
            'files.*.mimetypes' => 'Each document must be a JPG, JPEG, PNG, WEBP, or PDF file.',
            'file.mimetypes' => 'The replacement must be a JPG, JPEG, PNG, WEBP, or PDF file.',
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }
}
