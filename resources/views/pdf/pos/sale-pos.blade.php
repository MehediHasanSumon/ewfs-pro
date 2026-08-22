<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>POS Receipt - {{ $sale->invoice_no }}</title>
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
        .double-divider {
            border-top: 1px double #000;
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
        .footer-message {
            font-size: 9px;
            margin-top: 8px;
            text-align: center;
        }
        .signature-area {
            width: 100%;
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            display: inline-block;
            width: 48%;
            text-align: center;
            border-top: 1px dotted #000;
            font-size: 8.5px;
            padding-top: 3px;
        }
    </style>
</head>
<body>
    <div class="text-center">
        @if(file_exists(public_path('images/logo.jpg')))
            <img src="{{ public_path('images/logo.jpg') }}" class="logo" alt="Logo">
        @endif
        <div class="company-name">{{ $companySetting->company_name ?? 'East West Filling Station' }}</div>
        <div class="company-address">{{ $companySetting->address ?? 'Dhour Baribad, Turag, Dhaka.' }}</div>
        <div class="company-contact">
            @if(!empty($companySetting->phone)) Phone: {{ $companySetting->phone }} @endif
            @if(!empty($companySetting->mobile)) | Mob: {{ $companySetting->mobile }} @endif
        </div>
        <div class="divider"></div>
        <div class="receipt-title">POS Sales Receipt</div>
    </div>

    <table class="info-table">
        <tr>
            <td style="width: 55%;"><strong>Invoice:</strong> {{ $sale->invoice_no }}</td>
            <td class="text-right" style="width: 45%;"><strong>Date:</strong> {{ $sale->sale_date->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>Shift:</strong> {{ $sale->shift?->name ?? 'N/A' }}</td>
            <td class="text-right"><strong>Time:</strong> {{ $sale->created_at ? $sale->created_at->format('h:i A') : date('h:i A') }}</td>
        </tr>
        @if($sale->customer_name_snapshot || $sale->customer)
        <tr>
            <td colspan="2"><strong>Customer:</strong> {{ $sale->customer_name_snapshot ?: ($sale->customer ?: 'Walk-in Customer') }}</td>
        </tr>
        @endif
        @if($sale->customer_mobile_snapshot)
        <tr>
            <td colspan="2"><strong>Mobile:</strong> {{ $sale->customer_mobile_snapshot }}</td>
        </tr>
        @endif
        @if($sale->vehicle_number_snapshot || $sale->vehicle_no)
        <tr>
            <td colspan="2"><strong>Vehicle:</strong> {{ $sale->vehicle_number_snapshot ?: $sale->vehicle_no }}</td>
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
            @foreach($sale->items as $item)
                <tr>
                    <td class="text-left">
                        {{ $item->product_name_snapshot ?: ($item->product?->product_name ?? 'Item') }}
                    </td>
                    <td class="text-right">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        @if((float) $sale->discount_amount > 0)
        <tr>
            <td class="text-left">Subtotal:</td>
            <td class="text-right">{{ number_format((float) $sale->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td class="text-left">Discount:</td>
            <td class="text-right">-{{ number_format((float) $sale->discount_amount, 2) }}</td>
        </tr>
        @endif
        <tr class="grand-total">
            <td class="text-left bold">TOTAL AMOUNT:</td>
            <td class="text-right bold">Tk {{ number_format((float) $sale->grand_total, 2) }}</td>
        </tr>
        <tr>
            <td class="text-left">Payment Mode:</td>
            <td class="text-right" style="text-transform: capitalize;">
                {{ str($sale->paymentDetail?->payment_method ?? $sale->transaction?->payment_method ?? 'cash')->replace('_', ' ')->title() }}
            </td>
        </tr>
        <tr>
            <td class="text-left">Paid Amount:</td>
            <td class="text-right">Tk {{ number_format((float) $sale->paid_amount, 2) }}</td>
        </tr>
        @if((float) $sale->due_amount > 0)
        <tr>
            <td class="text-left bold">Due Amount:</td>
            <td class="text-right bold">Tk {{ number_format((float) $sale->due_amount, 2) }}</td>
        </tr>
        @endif
    </table>

    <div class="words">
        In words: {{ \App\Helpers\NumberToWordsHelper::convert((int) floor((float) $sale->grand_total)) }}
    </div>

    @if($sale->remarks)
        <div style="font-size: 8.5px; margin: 3px 0;"><strong>Remarks:</strong> {{ $sale->remarks }}</div>
    @endif

    <div class="divider"></div>

    <div class="footer-message">
        <strong>Thank You! Please Visit Again.</strong><br>
        <span style="font-size: 8px; color: #444;">Printed: {{ date('d/m/Y h:i A') }}</span>
    </div>
</body>
</html>
