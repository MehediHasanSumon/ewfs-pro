<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Batch Invoice</title>
    <style>
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
        .invoice-info {
            margin-bottom: 20px;
        }
        .invoice-info table {
            width: 100%;
            border: none;
        }
        .invoice-info td {
            border: none;
            padding: 4px 0;
            font-size: 12px;
        }
        .invoice-info .left {
            width: 50%;
            vertical-align: top;
        }
        .invoice-info .right {
            width: 50%;
            vertical-align: top;
            text-align: right;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
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
        .signature-section {
            margin-top: 40px;
            padding: 20px 0;
        }
        .signature-section table {
            border: none;
            margin: 0;
        }
        .signature-section tr {
            background: none !important;
        }
        .signature-section td {
            border: none !important;
            text-align: center;
            padding: 20px 10px;
            font-weight: bold;
            font-size: 11px;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    @foreach($customerGroups as $customer => $sales)
        @if(!$loop->first)
            <div class="page-break"></div>
        @endif
        
        <div class="header">
            <div class="logo">
                @if($companySetting && $companySetting->company_logo)
                <img src="{{ public_path('storage/' . $companySetting->company_logo) }}" alt="Company Logo">
                @endif
            </div>
            <div class="company-info">
                @if($companySetting)
                <h2>{{ $companySetting->company_name ?? 'East West Filling Station' }}</h2>
                @if($companySetting->company_address)
                <p>{{ $companySetting->company_address }}</p>
                @endif
                @if($companySetting->company_mobile || $companySetting->company_email)
                <p>
                    @if($companySetting->company_email)
                    {{ $companySetting->company_email }}
                    @endif
                    @if($companySetting->company_mobile && $companySetting->company_email) | @endif
                    @if($companySetting->company_mobile)
                    {{ $companySetting->company_mobile }}
                    @endif
                </p>
                @endif
                @else
                <h2>East West Filling Station</h2>
                <p>Dhaka, Bangladesh</p>
                <p>mehedihassan2992001@gmail.com | 01750542923</p>
                @endif
            </div>
        </div>

        <div class="title-section">
            <div class="title-box">Sale Invoice</div>
        </div>

        <div class="invoice-info">
            <table>
                <tr>
                    <td class="left">
                        <strong>Customer:</strong> {{ $customer }}<br>
                        <strong>Mobile:</strong> {{ $sales->first()->mobile_number ?? 'N/A' }}<br>
                        <strong>Shift:</strong> {{ $sales->first()->shift->name ?? 'N/A' }}<br>
                        <strong>Date:</strong> {{ \Carbon\Carbon::parse($sales->first()->sale_date)->format('d/m/Y') }}<br>
                    </td>
                    <td class="right">
                        <strong>Total Product:</strong> {{ $sales->count() }}<br>
                        <strong>Total Amount:</strong> {{ number_format($sales->sum('total_amount'), 2) }}<br>
                        <strong>Paid Amount:</strong> {{ number_format($sales->sum('paid_amount'), 2) }}<br>
                        <strong>Due Amount:</strong> {{ number_format($sales->sum('due_amount'), 2) }}<br>
                    </td>
                </tr>
            </table>
        </div>

        <table>
            <thead>
                <tr>
                    <th>SL</th>
                    <th>Invoice No</th>
                    <th>Vehicle No</th>
                    <th>Product Name</th>
                    <th>Unit</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sales as $index => $sale)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $sale->invoice_no }}</td>
                    <td>{{ $sale->vehicle_no }}</td>
                    <td>{{ $sale->product->product_name ?? 'N/A' }}</td>
                    <td>{{ $sale->product->unit->name ?? 'N/A' }}</td>
                    <td class="text-right">{{ number_format($sale->quantity, 2) }}</td>
                    <td class="text-right">{{ number_format($sale->amount / $sale->quantity, 2) }}</td>
                    <td class="text-right">{{ number_format($sale->total_amount, 2) }}</td>
                </tr>
                @endforeach
                <tr style="font-weight: bold; background-color: #e0e0e0;">
                    <td colspan="7" class="text-right">Grand Total:</td>
                    <td class="text-right">{{ number_format($sales->sum('total_amount'), 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="total-section">
            <p class="amount">Total Amount: {{ number_format($sales->sum('total_amount'), 2) }}</p>
            <p class="amount">Paid Amount: {{ number_format($sales->sum('paid_amount'), 2) }}</p>
            @if($sales->sum('due_amount') > 0)
            <p class="amount" style="color: red;">Due Amount: {{ number_format($sales->sum('due_amount'), 2) }}</p>
            @endif
            <p class="words">In words: {{ $sales->totalInWords }}</p>
        </div>

        <div class="signature-section">
            <table>
                <tr>
                    <td>Customer Signature</td>
                    <td>Prepared By</td>
                    <td>Authorized By</td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <p>Generated on {{ now()->format('d/m/Y H:i:s') }} | Batch: {{ $batchCode }} | Customer: {{ $customer }}</p>
        </div>
    @endforeach
</body>
</html>