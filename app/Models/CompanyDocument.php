<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyDocument extends Model
{
    protected $fillable = [
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
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'sort_order' => 'integer',
            'status' => 'boolean',
        ];
    }

    public function companySetting(): BelongsTo
    {
        return $this->belongsTo(CompanySetting::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
