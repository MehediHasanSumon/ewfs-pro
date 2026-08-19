<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Ledger Details</title>
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
        .customer-info {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f5f5f5;
        }
        .customer-info p {
            margin: 2px 0;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #000;
            padding: 10px 8px;
            text-align: left;
        }
        th {
            font-weight: bold;
            font-size: 12px;
            color: #000;
        }
        td {
            font-size: 11px;
            color: #333;
        }
        .text-right { text-align: right; }
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
    @include('pdf.components.watermark')
    @include('pdf.components.header')

    <div class="title-section">
        <div class="title-box">Customer Ledger Details ({{ $startDate }} to {{ $endDate }})</div>
    </div>

    @forelse($ledgers as $ledger)
    <div class="customer-info">
        <p><strong>Customer:</strong> {{ $ledger['customer_name'] }}</p>
        <p><strong>Account Number:</strong> {{ $ledger['ac_number'] }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>SL</th>
                <th>Date</th>
                <th>Invoice/Transaction ID</th>
                <th>Transaction Type</th>
                <th>Vehicle No</th>
                <th>Memo No</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
                <th class="text-right">Due Amount</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ledger['transactions'] as $index => $transaction)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $transaction->date }}</td>
                <td>{{ $transaction->transaction_id }}</td>
                <td>{{ $transaction->transaction_type_name ?? \Illuminate\Support\Str::headline($transaction->event_type) }}</td>
                <td>{{ $transaction->vehicle_no ?? '-' }}</td>
                <td>{{ $transaction->memo_no ?? 'N/A' }}</td>
                <td class="text-right">{{ number_format($transaction->debit, 2) }}</td>
                <td class="text-right">{{ number_format($transaction->credit, 2) }}</td>
                <td class="text-right">{{ number_format($transaction->due, 2) }}</td>
                <td>{{ $transaction->remarks ?? 'N/A' }}</td>
            </tr>
            @endforeach
            <tr style="font-weight: bold;">
                <td colspan="6">Total:</td>
                <td class="text-right">{{ number_format($ledger['total_debit'], 2) }}</td>
                <td class="text-right">{{ number_format($ledger['total_credit'], 2) }}</td>
                <td class="text-right">{{ number_format($ledger['total_due'], 2) }}</td>
                <td>-</td>
            </tr>
        </tbody>
    </table>
    @empty
    <p style="text-align: center; padding: 20px; color: #999;">No records found</p>
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
