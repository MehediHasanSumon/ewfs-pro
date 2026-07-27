<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Dispenser Report</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 15mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding-bottom: 60px;
            position: relative;
            min-height: 100vh;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 3px 2px;
            text-align: center;
            font-size: 8px;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            color: #000;
        }
        td {
            color: #333;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 15px 20px;
            border-top: 1px solid #ccc;
            background-color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
            color: #666;
        }
        .footer-left { text-align: left; }
        .footer-right { text-align: right; }
        @media print {
            .footer { position: fixed; bottom: 0; }
        }
    </style>
</head>
<body>
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
        <div class="title-box">Monthly Dispenser Report</div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">SL</th>
                <th rowspan="2">Date</th>
                <th rowspan="2">Shift</th>
                @foreach($products as $product)
                    @if(($visibleProducts[$product->id] ?? true))
                    <th colspan="3">{{ $product->product_name }}</th>
                    @endif
                @endforeach
                @if($visibleColumns['received_due_paid'] ?? true)<th rowspan="2">Received</th>@endif
                @if($visibleColumns['amount'] ?? true)<th rowspan="2">Amount</th>@endif
                @if($visibleColumns['credit_sale'] ?? true)<th rowspan="2">Credit</th>@endif
                @if($visibleColumns['bank_sale'] ?? true)<th rowspan="2">Bank</th>@endif
                @if($visibleColumns['expenses'] ?? true)<th rowspan="2">Expenses</th>@endif
                @if($visibleColumns['purchase'] ?? true)<th rowspan="2">Purchase</th>@endif
                @if($visibleColumns['cash_in_hand'] ?? true)<th rowspan="2">Cash</th>@endif
                @if($visibleColumns['total_balance'] ?? true)<th rowspan="2">Balance</th>@endif
            </tr>
            <tr>
                @foreach($products as $product)
                    @if(($visibleProducts[$product->id] ?? true))
                    <th>Sale</th>
                    <th>Price</th>
                    <th>Amount</th>
                    @endif
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($readings as $index => $reading)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td class="text-left">{{ $reading['date'] }}</td>
                <td>{{ $reading['shift'] }}</td>
                @foreach($products as $product)
                    @if(($visibleProducts[$product->id] ?? true))
                    @php
                        $productSale = collect($reading['product_sales'])->firstWhere('product_id', $product->id);
                        $totalSale = $productSale ? $productSale->total_sale : 0;
                        $price = $productSale ? $productSale->price : 0;
                        $amount = $productSale ? $productSale->amount : 0;
                    @endphp
                    <td class="text-right">{{ number_format($totalSale, 2) }}</td>
                    <td class="text-right">{{ number_format($price, 2) }}</td>
                    <td class="text-right">{{ number_format($amount, 2) }}</td>
                    @endif
                @endforeach
                @if($visibleColumns['received_due_paid'] ?? true)<td class="text-right">{{ number_format($reading['received_due_paid'], 2) }}</td>@endif
                @if($visibleColumns['amount'] ?? true)<td class="text-right">{{ number_format($reading['amount'], 2) }}</td>@endif
                @if($visibleColumns['credit_sale'] ?? true)<td class="text-right">{{ number_format($reading['credit_sale'], 2) }}</td>@endif
                @if($visibleColumns['bank_sale'] ?? true)<td class="text-right">{{ number_format($reading['bank_sale'], 2) }}</td>@endif
                @if($visibleColumns['expenses'] ?? true)<td class="text-right">{{ number_format($reading['expenses'], 2) }}</td>@endif
                @if($visibleColumns['purchase'] ?? true)<td class="text-right">{{ number_format($reading['purchase'], 2) }}</td>@endif
                @if($visibleColumns['cash_in_hand'] ?? true)<td class="text-right">{{ number_format($reading['cash_in_hand'], 2) }}</td>@endif
                @if($visibleColumns['total_balance'] ?? true)<td class="text-right">{{ number_format($reading['total_balance'], 2) }}</td>@endif
            </tr>
            @empty
            <tr>
                <td colspan="{{ 12 + (count($products) * 3) }}">No data found</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            Generated on: {{ date('Y-m-d H:i:s') }}
        </div>
        <div class="footer-right">
            This is a computer generated report
        </div>
    </div>
</body>
</html>