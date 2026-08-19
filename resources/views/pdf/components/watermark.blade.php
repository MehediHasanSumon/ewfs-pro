@php
    $watermarkPath = null;
    if (!empty($companySetting?->pdf_watermark_image)) {
        $relative = ltrim($companySetting->pdf_watermark_image, '/');
        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, 8);
        }
        $fullPath = public_path('storage/' . $relative);
        if (file_exists($fullPath)) {
            $watermarkPath = $fullPath;
        } elseif (file_exists(storage_path('app/public/' . $relative))) {
            $watermarkPath = storage_path('app/public/' . $relative);
        }
    }
@endphp
@if($watermarkPath)
<div class="pdf-watermark" style="position: fixed; top: 30%; left: 15%; width: 70%; text-align: center; opacity: 0.18; z-index: -1000;">
    <img src="{{ $watermarkPath }}" style="max-width: 380px; max-height: 380px; width: auto; height: auto;" alt="Watermark">
</div>
@endif
