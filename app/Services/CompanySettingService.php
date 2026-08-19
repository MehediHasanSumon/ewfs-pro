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
    public function create(
        array $attributes,
        ?UploadedFile $logo,
        ?UploadedFile $watermark = null
    ): CompanySetting {
        $storedLogo = $this->storeLogo($logo);
        $storedWatermark = $this->storeWatermark($watermark);

        try {
            return DB::transaction(function () use ($attributes, $storedLogo, $storedWatermark): CompanySetting {
                if ($storedLogo !== null) {
                    $attributes['company_logo'] = $storedLogo;
                }
                if ($storedWatermark !== null) {
                    $attributes['pdf_watermark_image'] = $storedWatermark;
                }

                return CompanySetting::create($attributes);
            });
        } catch (Throwable $exception) {
            $this->deleteLogo($storedLogo);
            $this->deleteWatermark($storedWatermark);
            throw $exception;
        }
    }

    public function update(
        CompanySetting $companySetting,
        array $attributes,
        ?UploadedFile $logo,
        ?UploadedFile $watermark = null,
        bool $removeWatermark = false
    ): CompanySetting {
        $storedLogo = $this->storeLogo($logo);
        $storedWatermark = $this->storeWatermark($watermark);
        $previousLogo = $companySetting->company_logo;
        $previousWatermark = $companySetting->pdf_watermark_image;

        try {
            DB::transaction(function () use ($companySetting, $attributes, $storedLogo, $storedWatermark, $removeWatermark): void {
                if ($storedLogo !== null) {
                    $attributes['company_logo'] = $storedLogo;
                }
                if ($storedWatermark !== null) {
                    $attributes['pdf_watermark_image'] = $storedWatermark;
                } elseif ($removeWatermark) {
                    $attributes['pdf_watermark_image'] = null;
                }

                $companySetting->update($attributes);
            });
        } catch (Throwable $exception) {
            $this->deleteLogo($storedLogo);
            $this->deleteWatermark($storedWatermark);
            throw $exception;
        }

        if ($storedLogo !== null) {
            $this->deleteLogo($previousLogo);
        }
        if ($storedWatermark !== null || ($removeWatermark && $previousWatermark)) {
            $this->deleteWatermark($previousWatermark);
        }

        return $companySetting->refresh();
    }

    public function delete(CompanySetting $companySetting): void
    {
        $logo = $companySetting->company_logo;
        $watermark = $companySetting->pdf_watermark_image;
        $documentPaths = $companySetting->documents()->pluck('file_path')->all();

        DB::transaction(fn () => $companySetting->delete());
        $this->deleteLogo($logo);
        $this->deleteWatermark($watermark);
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
            $this->deleteWatermark($setting->pdf_watermark_image);
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

    private function storeWatermark(?UploadedFile $watermark): ?string
    {
        if ($watermark === null) {
            return null;
        }

        $path = $watermark->store('images/company', 'public');

        return $path === false ? null : '/'.$path;
    }

    private function deleteWatermark(?string $path): void
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
