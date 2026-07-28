<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $fillable = [
        'document_type',
        'prefix',
        'fiscal_year',
        'next_number',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'next_number' => 'integer',
            'version' => 'integer',
        ];
    }
}
