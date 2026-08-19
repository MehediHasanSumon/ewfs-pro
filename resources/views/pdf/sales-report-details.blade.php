<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Report</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 10mm 15mm 10mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
        }
        .header {
            padding: 20px;
            display: flex;
            align-items: center;
            width: 100%;
            position: relative;
        }
        .header .logo {
            width: 120px;
            flex-shrink: 0;
        }
        .header .logo img {
            height: 80px;
            width: auto;
            display: block;
        }
        .header .company-info {
            position: absolute;
            left: 50%;
            transform: translateX(-60%);
            text-align: center;
            width: auto;
            margin-top: -80px;
        }
        .header .company-info h2 {
            margin: 0 0 8px 0;
            font-size: 20px;
            font-weight: bold;
            color: #000;
        }
        .header .company-info p {
            margin: 4px 0;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
        }
        .title-section {
            text-align: center;
            margin-bottom: 20px;
        }
        .title-box {
            border: 1px solid #000;
            display: inline-block;
            padding: 8px 20px;
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 14px;
        }
        .customer-section {
            margin-top: 20px;
            margin-bottom: 5px;
        }
        .customer-title {
            font-size: 13px;
            font-weight: bold;
            color: #000;
            margin-bottom: 6px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 15px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px 6px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 11px;
            color: #000;
        }
        td {
            font-size: 10px;
            color: #333;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .total-row {
            background-color: #e0e0e0 !important;
            font-weight: bold;
        }
        .total-section {
            margin-top: 20px;
            padding: 15px;
            background-color: #f5f5f5;
            border: 1px solid #ddd;
        }
        .total-section p {
            margin: 5px 0;
            font-size: 13px;
        }
        .total-section .amount {
            font-weight: bold;
            font-size: 14px;
        }
        .total-section .words {
            font-style: italic;
            font-size: 12px;
        }
        .footer {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    @include('pdf.components.watermark')
    @include('pdf.components.header')

    <div class="title-section">
        <div class="title-box">Sales Report</div>
        <p style="margin-top: 8px; font-size: 12px; color: #333;">
            Date: {{ \Carbon\Carbon::parse($filters['start_date'])->format('d/m/Y') }} to {{ \Carbon\Carbon::parse($filters['end_date'])->format('d/m/Y') }}
        </p>
        @if(!empty($filters['customer']))
            <p style="margin-top: 2px; font-size: 11px; color: #555;">
                Customer: <strong>{{ $filters['customer'] }}</strong>
            </p>
        @endif
    </div>

    @if(empty($report['customers']))
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Vehicle</th>
                    <th>Invoice No</th>
                    <th>Memo No</th>
                    <th>Product</th>
                    <th>Unit</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Total</th>
                    <th class="text-center">Type</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="10" class="text-center" style="padding: 20px; color: #888;">No records</td>
                </tr>
            </tbody>
        </table>
    @else
        @foreach($report['customers'] as $customerGroup)
            <div class="customer-section">
                <div class="customer-title">Customer: {{ $customerGroup['customer_name'] }}</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Vehicle</th>
                        <th>Invoice No</th>
                        <th>Memo No</th>
                        <th>Product</th>
                        <th>Unit</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Total</th>
                        <th class="text-center">Type</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customerGroup['sales'] as $sale)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($sale->date)->format('d/m/Y') }}</td>
                            <td>{{ $sale->vehicle_no ?? 'N/A' }}</td>
                            <td>{{ $sale->invoice_no }}</td>
                            <td>{{ $sale->memo_no ?? 'N/A' }}</td>
                            <td>{{ $sale->product_name }}</td>
                            <td>{{ $sale->unit_name }}</td>
                            <td class="text-right">{{ number_format($sale->quantity, 2) }}</td>
                            <td class="text-right">{{ number_format($sale->unit_price, 2) }}</td>
                            <td class="text-right">{{ number_format($sale->total_amount, 2) }}</td>
                            <td class="text-center">{{ $sale->type }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="6">Total From {{ $customerGroup['customer_name'] }} :</td>
                        <td class="text-right">{{ number_format($customerGroup['total_quantity'], 2) }}</td>
                        <td></td>
                        <td class="text-right">{{ number_format($customerGroup['total_amount'], 2) }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        @endforeach

        <div class="total-section">
            <p class="amount">Grand Total Sales: {{ number_format($report['grand_total_amount'], 2) }}</p>
            <p class="words">In words: {{ \App\Helpers\NumberToWordsHelper::convert(floor($report['grand_total_amount'])) }}</p>
        </div>
    @endif

    <div class="footer">
        <p>Generated on {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
