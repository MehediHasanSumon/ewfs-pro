<?php

namespace App\Services;

use App\Models\CompanySetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CompanySettingService
{
    public function create(array $attributes, ?UploadedFile $logo): CompanySetting
    {
        $storedLogo = $this->storeLogo($logo);

        try {
            return DB::transaction(function () use ($attributes, $storedLogo): CompanySetting {
                if ($storedLogo !== null) {
                    $attributes['company_logo'] = $storedLogo;
                }

                return CompanySetting::create($attributes);
            });
        } catch (Throwable $exception) {
            $this->deleteLogo($storedLogo);
            throw $exception;
        }
    }

    public function update(
        CompanySetting $companySetting,
        array $attributes,
        ?UploadedFile $logo
    ): CompanySetting {
        $storedLogo = $this->storeLogo($logo);
        $previousLogo = $companySetting->company_logo;

        try {
            DB::transaction(function () use ($companySetting, $attributes, $storedLogo): void {
                if ($storedLogo !== null) {
                    $attributes['company_logo'] = $storedLogo;
                }

                $companySetting->update($attributes);
            });
        } catch (Throwable $exception) {
            $this->deleteLogo($storedLogo);
            throw $exception;
        }

        if ($storedLogo !== null) {
            $this->deleteLogo($previousLogo);
        }

        return $companySetting->refresh();
    }

    public function delete(CompanySetting $companySetting): void
    {
        $logo = $companySetting->company_logo;
        $documentPaths = $companySetting->documents()->pluck('file_path')->all();

        DB::transaction(fn () => $companySetting->delete());
        $this->deleteLogo($logo);
        $this->deleteDocumentFiles($documentPaths);
    }

    public function deleteMany(array $ids): int
    {
        /** @var Collection<int, CompanySetting> $settings */
        $settings = CompanySetting::query()
            ->with('documents:id,company_setting_id,file_path')
            ->whereKey($ids)
            ->get();

        DB::transaction(function () use ($settings): void {
            CompanySetting::query()->whereKey($settings->modelKeys())->delete();
        });

        $settings->each(function (CompanySetting $setting): void {
            $this->deleteLogo($setting->company_logo);
            $this->deleteDocumentFiles(
                $setting->documents->pluck('file_path')->all()
            );
        });

        return $settings->count();
    }

    private function storeLogo(?UploadedFile $logo): ?string
    {
        if ($logo === null) {
            return null;
        }

        $path = $logo->store('images/company', 'public');

        return $path === false ? null : '/'.$path;
    }

    private function deleteLogo(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('public')->delete(ltrim($path, '/'));
        }
    }

    private function deleteDocumentFiles(array $paths): void
    {
        $paths = array_values(array_filter(array_map(
            fn (?string $path): ?string => $path ? ltrim($path, '/') : null,
            $paths
        )));

        if ($paths !== []) {
            Storage::disk(config('erp.company_documents.disk', 'private'))
                ->delete($paths);
        }
    }
}
