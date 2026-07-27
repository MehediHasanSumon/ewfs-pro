<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Bank Book Ledger - {{ $account->name }}</title>
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

        .account-info {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f5f5f5;
        }

        .account-info p {
            margin: 2px 0;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 30px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 10px 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 12px;
            color: #000;
        }

        td {
            font-size: 11px;
            color: #333;
        }

        .text-right {
            text-align: right;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .signature-section {
            margin-top: 60px;
            padding: 20px 0;
            border-top: 1px solid #ddd;
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
            font-size: 12px;
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
        <div class="title-box">Bank Book Ledger</div>
    </div>

    <div class="account-info">
        <p><strong>Account Name:</strong> {{ $account->name }}</p>
        <p><strong>Account Number:</strong> {{ $account->ac_number }}</p>
        <p><strong>Group:</strong> {{ $account->group->name ?? 'N/A' }}</p>
        <p><strong>Period:</strong> {{ date('d/m/Y', strtotime($startDate)) }} to {{ date('d/m/Y', strtotime($endDate)) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Shift</th>
                <th>Voucher No</th>
                <th>Description</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @php
                $runningBalance = 0;
            @endphp
            @forelse($transactions as $transaction)
            @php
                if ($transaction->transaction_type === 'Dr') {
                    $runningBalance -= $transaction->amount;
                } else {
                    $runningBalance += $transaction->amount;
                }
            @endphp
            <tr>
                <td>{{ date('d/m/Y', strtotime($transaction->voucher_date)) }}</td>
                <td>{{ $transaction->shift_name ?? '-' }}</td>
                <td>{{ $transaction->voucher_no ?? '-' }}</td>
                <td>{{ $transaction->description }}</td>
                <td class="text-right">{{ $transaction->transaction_type === 'Dr' ? number_format($transaction->amount, 2) : '-' }}</td>
                <td class="text-right">{{ $transaction->transaction_type === 'Cr' ? number_format($transaction->amount, 2) : '-' }}</td>
                <td class="text-right">{{ number_format(abs($runningBalance), 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align: center; padding: 20px; color: #999;">No transactions found</td>
            </tr>
            @endforelse
            @if(count($transactions) > 0)
            <tr style="font-weight: bold; background-color: #e0e0e0;">
                <td colspan="4" class="text-right">Total:</td>
                <td class="text-right">{{ number_format($totalDebit, 2) }}</td>
                <td class="text-right">{{ number_format($totalCredit, 2) }}</td>
                <td class="text-right">-</td>
            </tr>
            @endif
        </tbody>
    </table>

    @if(count($transactions) > 0)
    <div class="signature-section">
        <table>
            <tr>
                <td>Prepared By</td>
                <td>Checked By</td>
                <td>Chief Accountant</td>
                <td>Manager</td>
                <td>Director</td>
                <td>Managing Director</td>
            </tr>
        </table>
    </div>
    @endif
</body>

</html>
