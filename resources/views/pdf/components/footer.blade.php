@php
    $footerContacts = [];
    if (!empty($companySetting?->company_address)) {
        $footerContacts[] = trim($companySetting->company_address);
    }
    if (!empty($companySetting?->fax)) {
        $footerContacts[] = 'Fax: ' . trim($companySetting->fax);
    }
    if (!empty($companySetting?->company_mobile)) {
        $footerContacts[] = 'Cell: ' . trim($companySetting->company_mobile);
    }
    if (!empty($companySetting?->company_phone)) {
        $footerContacts[] = 'Phone: ' . trim($companySetting->company_phone);
    }
    if (!empty($companySetting?->company_email)) {
        $footerContacts[] = trim($companySetting->company_email);
    }
    $contactLine = implode(', ', $footerContacts);
@endphp
<div class="footer" style="position: fixed; bottom: 0; left: 0; right: 0; width: 100%; border-top: 1px solid #000; padding-top: 5px; text-align: center; font-size: 9px; color: #333; line-height: 1.3;">
    @if(!empty($contactLine))
        <div style="text-align: center; margin-bottom: 2px; font-size: 9px; color: #222;">
            {{ $contactLine }}
        </div>
    @endif
    @if(isset($leftText) || isset($rightText) || isset($totalRecords) || ($showGeneratedAt ?? true))
        <div style="font-size: 8px; color: #666; margin-top: 2px;">
            <table style="width: 100%; border: none; margin: 0; padding: 0; background: none;">
                <tr style="background: none !important;">
                    <td style="border: none !important; text-align: left; padding: 0; font-size: 8px; color: #666;">
                        @if(isset($leftText))
                            {{ $leftText }}
                        @elseif($showGeneratedAt ?? true)
                            Generated on: {{ now()->format('Y-m-d H:i:s') }}
                        @endif
                    </td>
                    <td style="border: none !important; text-align: right; padding: 0; font-size: 8px; color: #666;">
                        @if(isset($rightText))
                            {{ $rightText }}
                        @elseif(isset($totalRecords))
                            Total Records: {{ $totalRecords }}
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    @endif
</div>
