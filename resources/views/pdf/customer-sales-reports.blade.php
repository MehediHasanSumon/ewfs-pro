<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Sales Reports</title>
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
        <div class="title-box">Customer Sales Reports</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Customer</th>
                <th>Shift</th>
                <th>Invoice No</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($customerSales as $sale)
            <tr>
                <td>{{ \Carbon\Carbon::parse($sale['sale_date'])->format('d/m/Y') }}</td>
                <td>{{ $sale['customer'] }}</td>
                <td>{{ $sale['shift_name'] }}</td>
                <td>{{ $sale['invoice_no'] }}</td>
                <td class="text-right">{{ number_format($sale['quantity'], 2) }}</td>
                <td class="text-right">{{ number_format($sale['total_amount'], 2) }}</td>
                <td class="text-right">{{ number_format($sale['total_amount'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
            <tr style="font-weight: bold; background-color: #e0e0e0;">
                <td colspan="5">Grand Total:</td>
                <td class="text-right">{{ number_format($customerSales->sum('total_amount'), 2) }}</td>
                <td class="text-right">{{ number_format($customerSales->sum('total_amount'), 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="total-section">
        <p class="amount">Total Amount: {{ number_format($customerSales->sum('total_amount'), 2) }}</p>
        <p class="words">In words: {{ \App\Helpers\NumberToWordsHelper::convert(floor($customerSales->sum('total_amount'))) }}</p>
    </div>

    <div class="footer">
        <p>Generated on {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>