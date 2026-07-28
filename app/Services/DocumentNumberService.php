<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function next(
        string $documentType,
        string $prefix,
        CarbonInterface|string|null $date = null,
        int $padding = 6
    ): string {
        $year = $date
            ? (int) Carbon::parse($date)->format('Y')
            : (int) now()->format('Y');

        DB::table('document_sequences')->insertOrIgnore([
            'document_type' => $documentType,
            'prefix' => $prefix,
            'fiscal_year' => $year,
            'next_number' => 1,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DocumentSequence::query()
            ->where('document_type', $documentType)
            ->where('fiscal_year', $year)
            ->lockForUpdate()
            ->firstOrFail();

        $number = $sequence->next_number;

        $sequence->update([
            'next_number' => $number + 1,
            'version' => $sequence->version + 1,
        ]);

        return $prefix.str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
    }
}
