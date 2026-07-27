<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shift Closed List</title>
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 9px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 6px 4px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 9px;
            color: #000;
        }
        td {
            font-size: 8px;
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
        <div class="title-box">Shift Closed List</div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 30px;">SL</th>
                <th style="width: 60px;">Date</th>
                <th style="width: 60px;">Shift</th>
                <th class="text-right" style="width: 55px;">Credit Sales</th>
                <th class="text-right" style="width: 55px;">Bank Sales</th>
                <th class="text-right" style="width: 55px;">Cash Sales</th>
                <th class="text-right" style="width: 55px;">Total Cash</th>
                <th class="text-right" style="width: 55px;">Cash Payment</th>
                <th class="text-right" style="width: 55px;">Office Payment</th>
                <th class="text-right" style="width: 55px;">Final Due</th>
            </tr>
        </thead>
        <tbody>
            @forelse($shiftClosedList as $index => $record)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ date('d/m/Y', strtotime($record->close_date)) }}</td>
                <td>{{ $record->shift->name ?? '-' }}</td>
                <td class="text-right">{{ number_format($record->daily_reading->credit_sales ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($record->daily_reading->bank_sales ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($record->daily_reading->cash_sales ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($record->daily_reading->total_cash ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($record->daily_reading->cash_payment ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($record->daily_reading->office_payment ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($record->daily_reading->final_due_amount ?? 0, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="text-center" style="padding: 20px; color: #999;">No records found</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div>Generated on: {{ date('Y-m-d H:i:s') }}</div>
        <div>Total Records: {{ count($shiftClosedList) }}</div>
    </div>
</body>
</html>
