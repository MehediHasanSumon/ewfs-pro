<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shift Details</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
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
        .section-title {
            font-size: 13px;
            font-weight: bold;
            margin: 20px 0 10px 0;
            padding: 5px 10px;
            background-color: #f2f2f2;
            border-left: 3px solid #000;
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
            font-size: 10px;
            color: #000;
        }
        td {
            font-size: 9px;
            color: #333;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
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
                @if($companySetting->company_email){{ $companySetting->company_email }}@endif
                @if($companySetting->company_mobile && $companySetting->company_email) | @endif
                @if($companySetting->company_mobile){{ $companySetting->company_mobile }}@endif
            </p>
            @endif
            @else
            <h2>East West Filling Station</h2>
            @endif
        </div>
    </div>

    <div class="title-section">
        <div class="title-box">Shift Details - {{ $shiftClosed->shift->name }} ({{ date('d/m/Y', strtotime($shiftClosed->close_date)) }})</div>
    </div>

    <table style="margin-bottom: 25px;">
        <thead>
            <tr>
                <th class="text-center">Credit Sales</th>
                <th class="text-center">Bank Sales</th>
                <th class="text-center">Cash Sales</th>
                <th class="text-center">Total Cash</th>
                <th class="text-center">Cash Payment</th>
                <th class="text-center">Office Payment</th>
                <th class="text-center">Final Due Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center">{{ number_format($shiftClosed->daily_reading->credit_sales ?? 0, 2) }}</td>
                <td class="text-center">{{ number_format($shiftClosed->daily_reading->bank_sales ?? 0, 2) }}</td>
                <td class="text-center">{{ number_format($shiftClosed->daily_reading->cash_sales ?? 0, 2) }}</td>
                <td class="text-center">{{ number_format($shiftClosed->daily_reading->total_cash ?? 0, 2) }}</td>
                <td class="text-center">{{ number_format($shiftClosed->daily_reading->cash_payment ?? 0, 2) }}</td>
                <td class="text-center">{{ number_format($shiftClosed->daily_reading->office_payment ?? 0, 2) }}</td>
                <td class="text-center" style="font-weight: bold;">{{ number_format($shiftClosed->daily_reading->final_due_amount ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Dispenser Readings</div>
    <table>
        <thead>
            <tr>
                <th style="width: 80px;">Dispenser</th>
                <th style="width: 80px;">Product</th>
                <th class="text-right" style="width: 50px;">Rate</th>
                <th class="text-right" style="width: 50px;">Start</th>
                <th class="text-right" style="width: 50px;">End</th>
                <th class="text-right" style="width: 40px;">Test</th>
                <th class="text-right" style="width: 50px;">Net</th>
                <th class="text-right" style="width: 60px;">Total Sale</th>
                <th style="width: 70px;">Employee</th>
            </tr>
        </thead>
        <tbody>
            @forelse($shiftClosed->dispenser_readings as $reading)
            <tr>
                <td>{{ $reading['dispenser']['dispenser_name'] }}</td>
                <td>{{ $reading['product']['product_name'] }}</td>
                <td class="text-right">{{ number_format($reading['item_rate'], 2) }}</td>
                <td class="text-right">{{ number_format($reading['start_reading'], 2) }}</td>
                <td class="text-right">{{ number_format($reading['end_reading'], 2) }}</td>
                <td class="text-right">{{ number_format($reading['meter_test'], 2) }}</td>
                <td class="text-right">{{ number_format($reading['net_reading'], 2) }}</td>
                <td class="text-right">{{ number_format($reading['total_sale'], 2) }}</td>
                <td>{{ $reading['employee']['employee_name'] ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center" style="padding: 15px; color: #999;">No dispenser readings</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if(count($shiftClosed->other_product_sales) > 0)
    <div class="section-title">Other Products Sales</div>
    <table>
        <thead>
            <tr>
                <th style="width: 100px;">Product</th>
                <th style="width: 60px;">Code</th>
                <th style="width: 50px;">Unit</th>
                <th class="text-right" style="width: 60px;">Rate</th>
                <th class="text-right" style="width: 60px;">Quantity</th>
                <th class="text-right" style="width: 70px;">Total</th>
                <th style="width: 80px;">Employee</th>
            </tr>
        </thead>
        <tbody>
            @foreach($shiftClosed->other_product_sales as $sale)
            <tr>
                <td>{{ $sale['product']['product_name'] }}</td>
                <td>{{ $sale['product']['product_code'] }}</td>
                <td>{{ $sale['unit']['name'] }}</td>
                <td class="text-right">{{ number_format($sale['item_rate'], 2) }}</td>
                <td class="text-right">{{ $sale['sell_quantity'] }}</td>
                <td class="text-right">{{ number_format($sale['total_sales'], 2) }}</td>
                <td>{{ $sale['employee']['employee_name'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        <div>Generated on: {{ date('Y-m-d H:i:s') }}</div>
        <div>Shift: {{ $shiftClosed->shift->name }}</div>
    </div>
</body>
</html>
