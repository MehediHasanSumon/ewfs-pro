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
<style>
    @page {
        margin-top: 15px !important;
    }
    body {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }
    .header {
        padding: 0 !important;
        margin: 0 !important;
        margin-top: 0 !important;
    }
</style>
<div class="header" style="position: relative; width: 100%; padding: 0 !important; margin: 0 !important; margin-bottom: 0 !important;">
    <table style="width: 100%; border: none; margin: 0; padding: 0; background: none;">
        <tr style="background: none !important;">
            <td style="width: 100px; border: none !important; vertical-align: middle; padding: 0; text-align: left;">
                @if($logoPath)
                    <img src="{{ $logoPath }}" alt="Company Logo" style="max-height: 70px; max-width: 100px; display: block;">
                @endif
            </td>
            <td style="border: none !important; text-align: center; vertical-align: middle; padding: 0;">
                @if($companySetting)
                    <h2 style="font-size: 28px; font-weight: bold; color: #008000; margin: 0 0 2px 0; line-height: 1.15;">
                        {{ $companySetting->company_name ?? 'East West Filling Station' }}
                    </h2>
                    <div style="margin-top: -3px;">
                        @if($companySetting->company_address)
                            <p style="margin: 1px 0; font-size: 11px; color: #333; line-height: 1.3;">{{ $companySetting->company_address }}</p>
                        @endif
                        @php
                            $headerContacts = [];
                            if (!empty($companySetting->company_email)) {
                                $headerContacts[] = $companySetting->company_email;
                            }
                            if (!empty($companySetting->company_mobile)) {
                                $headerContacts[] = $companySetting->company_mobile;
                            }
                            if (!empty($companySetting->company_phone)) {
                                $headerContacts[] = 'Phone: ' . $companySetting->company_phone;
                            }
                            if (!empty($companySetting->fax)) {
                                $headerContacts[] = 'Fax: ' . $companySetting->fax;
                            }
                        @endphp
                        @if(count($headerContacts) > 0)
                            <p style="margin: 1px 0; font-size: 11px; color: #333; line-height: 1.3;">{{ implode(' | ', $headerContacts) }}</p>
                        @endif
                    </div>
                @else
                    <h2 style="font-size: 24px; font-weight: bold; color: #000; margin: 0 0 2px 0; line-height: 1.15;">East West Filling Station</h2>
                    <div style="margin-top: -3px;">
                        <p style="margin: 1px 0; font-size: 11px; color: #333; line-height: 1.3;">Dhaka, Bangladesh</p>
                        <p style="margin: 1px 0; font-size: 11px; color: #333; line-height: 1.3;">mehedihassan2992001@gmail.com | 01750542923</p>
                    </div>
                @endif
            </td>
            <td style="width: 100px; border: none !important; padding: 0;"></td>
        </tr>
    </table>
</div>

<div class="header-divider" style="border-bottom: 3px double #000; margin: 6px 0 14px 0; width: 100%; clear: both;"></div>

@if(!empty($title) || !empty($titleSlot) || !empty($subtitle))
<div class="title-section">
    @if(!empty($titleSlot))
        {!! $titleSlot !!}
    @elseif(!empty($title))
        <div class="title-box">{{ $title }}</div>
    @endif
    @if(!empty($subtitle))
        <p style="margin-top: 6px; font-size: 12px; color: #333;">{!! $subtitle !!}</p>
    @endif
</div>
@endif
