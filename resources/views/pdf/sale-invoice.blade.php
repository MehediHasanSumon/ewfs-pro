<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sale Invoice {{ $sale->invoice_no }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; color: #222; }
        .header { text-align: center; margin-bottom: 20px; }
        .header img { max-height: 70px; margin-bottom: 8px; }
        .header h2 { margin: 0 0 5px; }
        .header p { margin: 2px 0; }
        .title { margin: 18px 0; text-align: center; font-size: 16px; font-weight: bold; }
        .info { width: 100%; margin-bottom: 16px; border-collapse: collapse; }
        .info td { width: 50%; padding: 3px 0; vertical-align: top; }
        .items { width: 100%; border-collapse: collapse; }
        .items th, .items td { border: 1px solid #bbb; padding: 7px; }
        .items th { background: #f0f0f0; text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row { font-weight: bold; background: #f5f5f5; }
        .words { margin-top: 12px; font-style: italic; }
        .signatures { width: 100%; margin-top: 55px; }
        .signatures td { width: 50%; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.components.watermark')
    <div class="header">
        @if($companySetting?->company_logo)
            <img src="{{ public_path('storage/'.$companySetting->company_logo) }}" alt="Company Logo">
        @endif
        <h2>{{ $companySetting?->company_name ?? 'East West Filling Station' }}</h2>
        @if($companySetting?->company_address)
            <p>{{ $companySetting->company_address }}</p>
        @endif
        @if($companySetting?->company_mobile)
            <p>{{ $companySetting->company_mobile }}</p>
        @endif
    </div>

    <div class="title">Sale Invoice</div>

    <table class="info">
        <tr>
            <td>
                <strong>Customer:</strong> {{ $sale->customer_name_snapshot ?: 'Walk-in Customer' }}<br>
                <strong>Mobile:</strong> {{ $sale->customer_mobile_snapshot ?: 'N/A' }}<br>
                <strong>Vehicle:</strong> {{ $sale->vehicle_number_snapshot ?: 'N/A' }}
            </td>
            <td class="text-right">
                <strong>Invoice:</strong> {{ $sale->invoice_no }}<br>
                <strong>Date:</strong> {{ $sale->sale_date->format('d/m/Y') }}<br>
                <strong>Shift:</strong> {{ $sale->shift?->name ?? 'N/A' }}<br>
                <strong>Payment:</strong> {{ str($sale->paymentDetail?->payment_method ?? $sale->transaction?->payment_method ?? 'cash')->replace('_', ' ')->title() }}
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="text-center">SL</th>
                <th>Product</th>
                <th>Unit</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->product_name_snapshot }}</td>
                    <td>{{ $item->unit_name_snapshot }}</td>
                    <td class="text-right">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $item->discount_amount, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="6" class="text-right">Grand Total</td>
                <td class="text-right">{{ number_format((float) $sale->grand_total, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <p class="words">
        In words:
        {{ \App\Helpers\NumberToWordsHelper::convert((int) floor((float) $sale->grand_total)) }}
    </p>

    @if($sale->remarks)
        <p><strong>Remarks:</strong> {{ $sale->remarks }}</p>
    @endif

    <table class="signatures">
        <tr>
            <td>Customer Signature</td>
            <td>Authorized Signature</td>
        </tr>
    </table>
</body>
</html>
