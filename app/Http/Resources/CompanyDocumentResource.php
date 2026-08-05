<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_setting_id' => $this->company_setting_id,
            'document_name' => $this->document_name,
            'document_type' => $this->document_type,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'file_url' => $this->fileUrl(),
            'file_name' => basename((string) $this->file_path),
            'sort_order' => (int) $this->sort_order,
            'remarks' => $this->remarks,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at?->format('Y-m-d'),
            'updated_at' => $this->updated_at?->format('Y-m-d'),
        ];
    }

    private function fileUrl(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return route('company-settings.documents.file', [
            'companySetting' => $this->company_setting_id,
            'companyDocument' => $this->id,
        ], false);
    }
}
