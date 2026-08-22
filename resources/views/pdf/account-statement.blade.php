<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $account->name }} Statement</title>
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
            margin-top: 10px;
        }
        .title-box {
            border: 1px solid #000;
            display: inline-block;
            padding: 6px 20px;
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 13px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 25px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #000;
            padding: 7px 8px;
            text-align: left;
        }
        table.data-table th {
            font-weight: bold;
            font-size: 11px;
            background-color: #f5f5f5;
            color: #000;
        }
        table.data-table td {
            font-size: 10.5px;
            color: #333;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .signature-section {
            margin-top: 50px;
            padding: 10px 0;
            border-top: 1px solid #ddd;
        }
        .signature-section table {
            width: 100%;
            border: none;
            margin: 0;
        }
        .signature-section tr {
            background: none !important;
        }
        .signature-section td {
            border: none !important;
            text-align: center;
            padding: 15px 5px;
            font-weight: bold;
            font-size: 11px;
        }
    </style>
</head>
<body>
    @include('pdf.components.watermark')
    @include('pdf.components.header')

    <div class="title-section">
        <div class="title-box">{{ $account->name }} Statement ({{ $startDate }} to {{ $endDate }})</div>
    </div>

    @php
        $sumAmount = 0;
    @endphp

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 35px;">SL</th>
                <th style="width: 85px;">Date</th>
                <th>Transaction Type</th>
                <th style="width: 100px;">Payment Type</th>
                <th style="width: 105px;">Payment Method</th>
                <th class="text-right" style="width: 110px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $index => $transaction)
                @php
                    $amt = (float) ($transaction->amount ?? 0);
                    $sumAmount += $amt;
                    $payType = $transaction->voucher_payment_type ?? (
                        isset($transaction->voucher_type) && in_array(strtolower($transaction->voucher_type), ['payment', 'receipt', 'received'])
                            ? ucfirst($transaction->voucher_type)
                            : ($transaction->transaction_type === 'Dr' ? 'Payment' : 'Received')
                    );
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $transaction->transaction_date ?? $transaction->voucher_date }}</td>
                    <td>{{ $transaction->transaction_type_name ?? $transaction->voucher_type ?? '-' }}</td>
                    <td style="font-weight: 500;">{{ $payType }}</td>
                    <td style="text-transform: capitalize;">{{ $transaction->payment_type ?? '-' }}</td>
                    <td class="text-right">{{ number_format($amt, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 20px; color: #888;">
                        No transactions found for the selected period
                    </td>
                </tr>
            @endforelse
            @if(count($transactions) > 0)
                <tr style="font-weight: bold; background-color: #f5f5f5;">
                    <td colspan="5" class="text-right">Total:</td>
                    <td class="text-right">{{ number_format($totalAmount > 0 ? $totalAmount : $sumAmount, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

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
</body>
</html>
