<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Bank Book Ledger</title>
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

        .page-break {
            page-break-before: always;
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
        <div class="title-box">Bank Book Ledger ({{ $startDate }} to {{ $endDate }})</div>
    </div>

    @forelse($ledgers as $index => $ledger)
    @if($index > 0)
    <div class="page-break"></div>
    @endif

    <div class="account-info">
        <p><strong>Account Name:</strong> {{ $ledger['account']->name }}</p>
        <p><strong>Account Number:</strong> {{ $ledger['account']->ac_number }}</p>
        @if($ledger['account']->group)
        <p><strong>Group:</strong> {{ $ledger['account']->group->name }}</p>
        @endif
        <p><strong>Closing Balance:</strong> {{ number_format(abs($ledger['closing_balance']), 2) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>SL</th>
                <th>Date</th>
                <th>Transaction ID</th>
                <th>Description</th>
                <th>Payment Type</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ledger['transactions'] as $txIndex => $transaction)
            <tr>
                <td>{{ $txIndex + 1 }}</td>
                <td>{{ $transaction->transaction_date }}</td>
                <td>{{ $transaction->transaction_id }}</td>
                <td>{{ $transaction->description }}</td>
                <td style="text-transform: capitalize;">{{ $transaction->payment_type }}</td>
                <td class="text-right">
                    {{ $transaction->transaction_type === 'Dr' ? number_format($transaction->amount, 2) : '-' }}
                </td>
                <td class="text-right">
                    {{ $transaction->transaction_type === 'Cr' ? number_format($transaction->amount, 2) : '-' }}
                </td>
                <td class="text-right">
                    {{ number_format(abs($transaction->balance), 2) }}
                </td>
            </tr>
            @endforeach
            <tr style="font-weight: bold; background-color: #e0e0e0;">
                <td colspan="5">Total:</td>
                <td class="text-right">{{ number_format($ledger['total_debit'], 2) }}</td>
                <td class="text-right">{{ number_format($ledger['total_credit'], 2) }}</td>
                <td class="text-right">-</td>
            </tr>
        </tbody>
    </table>
    @empty
    <p style="text-align: center; padding: 20px; color: #999;">No bank or mobile bank transactions found for the selected period</p>
    @endforelse

    @if(count($ledgers) > 0)
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