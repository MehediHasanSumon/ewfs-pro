@php
    $logoPath = null;
    if (!empty($companySetting?->company_logo)) {
        $relative = ltrim($companySetting->company_logo, '/');
        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, 8);
        }
        $fullLogo = public_path('storage/' . $relative);
        if (file_exists($fullLogo)) {
            $logoPath = $fullLogo;
        } elseif (file_exists(storage_path('app/public/' . $relative))) {
            $logoPath = storage_path('app/public/' . $relative);
        }
    }
@endphp
<div class="header">
    <div class="logo">
        @if($logoPath)
            <img src="{{ $logoPath }}" alt="Company Logo">
        @endif
    </div>
    <div class="company-info">
        @if($companySetting)
            <h2 style="font-size: 22px; margin: 0 0 6px 0; font-weight: bold; color: #000;">{{ $companySetting->company_name ?? 'East West Filling Station' }}</h2>
            @if($companySetting->company_address)
                <p style="margin: 3px 0; font-size: 11px; color: #333;">{{ $companySetting->company_address }}</p>
            @endif
            @php
                $contacts = [];
                if (!empty($companySetting->company_email)) {
                    $contacts[] = $companySetting->company_email;
                }
                if (!empty($companySetting->company_mobile)) {
                    $contacts[] = $companySetting->company_mobile;
                }
                if (!empty($companySetting->company_phone)) {
                    $contacts[] = 'Phone: ' . $companySetting->company_phone;
                }
                if (!empty($companySetting->fax)) {
                    $contacts[] = 'Fax: ' . $companySetting->fax;
                }
            @endphp
            @if(count($contacts) > 0)
                <p style="margin: 3px 0; font-size: 11px; color: #333;">{{ implode(' | ', $contacts) }}</p>
            @endif
        @else
            <h2 style="font-size: 22px; margin: 0 0 6px 0; font-weight: bold; color: #000;">East West Filling Station</h2>
            <p style="margin: 3px 0; font-size: 11px; color: #333;">Dhaka, Bangladesh</p>
            <p style="margin: 3px 0; font-size: 11px; color: #333;">mehedihassan2992001@gmail.com | 01750542923</p>
        @endif
    </div>
</div>

<div class="header-divider" style="border-bottom: 3px double #000; margin: 10px 0 16px 0; width: 100%; clear: both;"></div>

@if(!empty($title) || !empty($titleSlot) || !empty($subtitle))
<div class="title-section">
    @if(!empty($titleSlot))
        {!! $titleSlot !!}
    @elseif(!empty($title))
        <div class="title-box">{{ $title }}</div>
    @endif
    @if(!empty($subtitle))
        <p style="margin-top: 8px; font-size: 12px; color: #333;">{!! $subtitle !!}</p>
    @endif
</div>
@endif
