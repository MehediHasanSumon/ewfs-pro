<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>White Sale POS Receipt - {{ $whiteSale->invoice_no }}</title>
    <style>
        @page {
            margin: 4mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.3;
            color: #000;
            margin: 0;
            padding: 0;
            width: 100%;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        
        .logo {
            max-height: 45px;
            margin: 0 auto 4px auto;
            display: block;
        }
        .company-name {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 2px 0;
        }
        .company-address {
            font-size: 9px;
            margin: 1px 0;
        }
        .company-contact {
            font-size: 9px;
            margin: 1px 0;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
        .receipt-title {
            font-size: 11px;
            font-weight: bold;
            margin: 4px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
        }
        .info-table td {
            font-size: 9.5px;
            padding: 1px 0;
            vertical-align: top;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
        }
        .items-table th {
            font-size: 9.5px;
            font-weight: bold;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 4px 0;
        }
        .items-table td {
            font-size: 9.5px;
            padding: 3px 0;
            vertical-align: top;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
        }
        .totals-table td {
            font-size: 9.5px;
            padding: 2px 0;
        }
        .grand-total {
            font-size: 12px;
            font-weight: bold;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 4px 0;
        }
        .words {
            font-size: 8.5px;
            font-style: italic;
            margin: 4px 0;
        }
        .signature-table {
            width: 100%;
            margin-top: 25px;
            border-collapse: collapse;
        }
        .signature-table td {
            border-top: 1px dotted #000;
            text-align: center;
            font-size: 8.5px;
            padding-top: 3px;
            width: 50%;
        }
        .footer-message {
            font-size: 9px;
            margin-top: 8px;
            text-align: center;
        }
    </style>
</head>
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
    if (!$logoPath && file_exists(public_path('images/logo.jpg'))) {
        $logoPath = public_path('images/logo.jpg');
    }
@endphp
<body>
    <table style="width: 100%; border: none; margin: 0 0 2px 0; border-collapse: collapse;">
        <tr>
            <td style="width: 38px; text-align: left; vertical-align: middle; padding: 0;">
                @if($logoPath)
                    <img src="{{ $logoPath }}" style="max-height: 38px; max-width: 38px; display: block;" alt="Logo">
                @endif
            </td>
            <td style="text-align: center; vertical-align: middle; padding: 0 2px;">
                <div class="company-name" style="font-size: 11px; font-weight: bold; text-transform: uppercase; margin: 0; line-height: 1.2;">
                    {{ $companySetting->company_name ?? 'East West Filling Station' }}
                </div>
                <div class="company-address" style="font-size: 8px; margin: 1px 0; line-height: 1.2;">
                    {{ $companySetting->company_address ?? ($companySetting->address ?? 'Dhour Baribad, Turag, Dhaka.') }}
                </div>
                @php
                    $contactList = [];
                    if (!empty($companySetting?->company_phone ?? $companySetting?->phone)) {
                        $contactList[] = 'Tel: ' . ($companySetting?->company_phone ?? $companySetting?->phone);
                    }
                    if (!empty($companySetting?->company_mobile ?? $companySetting?->mobile)) {
                        $contactList[] = 'Mob: ' . ($companySetting?->company_mobile ?? $companySetting?->mobile);
                    }
                @endphp
                @if(count($contactList) > 0)
                    <div class="company-contact" style="font-size: 7.5px; margin: 1px 0;">
                        {{ implode(' | ', $contactList) }}
                    </div>
                @endif
            </td>
            <td style="width: 38px; padding: 0;"></td>
        </tr>
    </table>

    <div class="divider"></div>
    <div class="receipt-title text-center">White Sale Receipt (POS)</div>

    <table class="info-table">
        <tr>
            <td style="width: 55%;"><strong>Invoice:</strong> {{ $whiteSale->invoice_no }}</td>
            <td class="text-right" style="width: 45%;"><strong>Date:</strong> {{ $whiteSale->sale_date ? \Carbon\Carbon::parse($whiteSale->sale_date)->format('d/m/Y') : date('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>Shift:</strong> {{ $whiteSale->shift?->name ?? 'N/A' }}</td>
            <td class="text-right"><strong>Time:</strong> {{ $whiteSale->sale_time ?? ($whiteSale->created_at ? $whiteSale->created_at->format('h:i A') : date('h:i A')) }}</td>
        </tr>
        @if($whiteSale->company_name_snapshot || $whiteSale->company_name)
        <tr>
            <td colspan="2"><strong>Company:</strong> {{ $whiteSale->company_name_snapshot ?: $whiteSale->company_name }}</td>
        </tr>
        @endif
        @if($whiteSale->customer_mobile_snapshot || $whiteSale->mobile_no)
        <tr>
            <td colspan="2"><strong>Mobile:</strong> {{ $whiteSale->customer_mobile_snapshot ?: $whiteSale->mobile_no }}</td>
        </tr>
        @endif
        @if($whiteSale->proprietor_name)
        <tr>
            <td colspan="2"><strong>Proprietor:</strong> {{ $whiteSale->proprietor_name }}</td>
        </tr>
        @endif
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th class="text-left" style="width: 45%;">Item</th>
                <th class="text-right" style="width: 15%;">Qty</th>
                <th class="text-right" style="width: 20%;">Rate</th>
                <th class="text-right" style="width: 20%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @php
                $products = $whiteSale->products ?? $whiteSale->items ?? [];
            @endphp
            @foreach($products as $prod)
                @php
                    $prodName = $prod->product?->product_name ?? ($prod->product_name_snapshot ?? 'Item');
                    $qty = (float) ($prod->quantity ?? 0);
                    $price = (float) ($prod->sales_price ?? ($prod->purchase_price ?? ($prod->unit_price ?? 0)));
                    $amt = (float) ($prod->amount ?? ($prod->line_total ?? ($qty * $price)));
                @endphp
                <tr>
                    <td class="text-left">{{ $prodName }}</td>
                    <td class="text-right">{{ number_format($qty, 2) }}</td>
                    <td class="text-right">{{ number_format($price, 2) }}</td>
                    <td class="text-right">{{ number_format($amt, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr class="grand-total">
            <td class="text-left bold">TOTAL AMOUNT:</td>
            <td class="text-right bold">Tk {{ number_format((float) ($whiteSale->grand_total ?: $whiteSale->total_amount), 2) }}</td>
        </tr>
        <tr>
            <td class="text-left">Sale Type:</td>
            <td class="text-right bold">White Sale</td>
        </tr>
    </table>

    <div class="words">
        In words: {{ \App\Helpers\NumberToWordsHelper::convert((int) floor((float) ($whiteSale->grand_total ?: $whiteSale->total_amount))) }}
    </div>

    @if($whiteSale->remarks)
        <div style="font-size: 8.5px; margin: 3px 0;"><strong>Remarks:</strong> {{ $whiteSale->remarks }}</div>
    @endif

    <table class="signature-table">
        <tr>
            <td>Customer Signature</td>
            <td>Authorized Signature</td>
        </tr>
    </table>

    <div class="divider"></div>

    <div class="footer-message">
        <strong>Thank You! Please Visit Again.</strong><br>
        <span style="font-size: 8px; color: #444;">Printed: {{ date('d/m/Y h:i A') }}</span>
    </div>
</body>
</html>
