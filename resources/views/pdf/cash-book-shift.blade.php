<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cash Book - {{ $shiftClosed->shift->name }}</title>
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
        .total-row {
            font-weight: bold;
            background-color: #e8e8e8 !important;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            @if($companySetting && $companySetting->company_logo)
            <img src="{{ public_path('storage' . $companySetting->company_logo) }}" alt="Company Logo">
            @endif
        </div>
        <div class="company-info">
            @if($companySetting)
            <h2>{{ $companySetting->company_name ?? 'Company Name' }}</h2>
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
            <h2>Company Name</h2>
            @endif
        </div>
    </div>

    <div class="title-section">
        <div class="title-box">Cash Book - {{ $shiftClosed->shift->name }} ({{ date('d/m/Y', strtotime($shiftClosed->close_date)) }})</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 50px;">Time</th>
                <th style="width: 60px;">Voucher No</th>
                <th style="width: 55px;">Type</th>
                <th style="width: 80px;">From Account</th>
                <th style="width: 80px;">To Account</th>
                <th style="width: 60px;">Category</th>
                <th class="text-right" style="width: 70px;">Transaction ID</th>
                <th class="text-right" style="width: 60px;">Debit</th>
                <th class="text-right" style="width: 60px;">Credit</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalDebit = 0;
                $totalCredit = 0;
            @endphp
            @forelse($cashTransactions as $transaction)
            @php
                $isCashFrom = stripos($transaction->from_account_name, 'cash') !== false;
                $isCashTo = stripos($transaction->to_account_name, 'cash') !== false;
                
                if($isCashFrom) {
                    $totalDebit += $transaction->amount;
                }
                if($isCashTo) {
                    $totalCredit += $transaction->amount;
                }
            @endphp
            <tr>
                <td class="text-center">{{ date('h:i A', strtotime($transaction->transaction_time)) }}</td>
                <td>{{ $transaction->voucher_no }}</td>
                <td>{{ $transaction->voucher_type }}</td>
                <td>{{ $transaction->from_account_name }}</td>
                <td>{{ $transaction->to_account_name }}</td>
                <td>{{ $transaction->category_name }}</td>
                <td class="text-right">{{ $transaction->transaction_id }}</td>
                <td class="text-right">{{ stripos($transaction->from_account_name, 'cash') !== false ? number_format($transaction->amount, 2) : '-' }}</td>
                <td class="text-right">{{ stripos($transaction->to_account_name, 'cash') !== false ? number_format($transaction->amount, 2) : '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center" style="padding: 15px; color: #999;">No cash transactions found</td>
            </tr>
            @endforelse
            @if(count($cashTransactions) > 0)
            <tr class="total-row">
                <td colspan="7" class="text-right">Total:</td>
                <td class="text-right">{{ number_format($totalDebit, 2) }}<br><small>Cash Payment</small></td>
                <td class="text-right">{{ number_format($totalCredit, 2) }}<br><small>Cash Received</small></td>
            </tr>
            <tr class="total-row">
                <td colspan="7" class="text-right">Balance:</td>
                <td colspan="2" class="text-right">{{ number_format($totalCredit - $totalDebit, 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        <div>Generated on: {{ date('Y-m-d H:i:s') }}</div>
        <div>Shift: {{ $shiftClosed->shift->name }} | Date: {{ date('d/m/Y', strtotime($shiftClosed->close_date)) }}</div>
    </div>
</body>
</html>
