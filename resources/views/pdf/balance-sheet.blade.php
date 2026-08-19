<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Balance Sheet</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 10mm 15mm 10mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .title-section {
            text-align: center;
            margin: 10px 0 12px 0;
        }
        .title-box {
            border: 1px solid #000;
            display: inline-block;
            padding: 5px 20px;
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 13px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px 6px;
            text-align: left;
            font-size: 10px;
        }
        th {
            font-weight: bold;
            background-color: #f9f9f9;
            color: #000;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>
    @include('pdf.components.watermark')
    @include('pdf.components.header')

    @php
        $topSheetData = $data['top_sheet_data'] ?? [
            'close_month_year' => ['label' => 'Total Balance -- 31-12- 2025', 'amount' => 12174977.00],
            'top_sheet' => [
                'title' => 'Top Sheet',
                'subtitle' => 'Month wise balance sheet',
                'months' => [
                    ['month' => 'January', 'gross_profit' => 2651445.00, 'office_expense' => 1425985.00, 'cash_payment_md' => 110000.00, 'net_balance' => 1459479.00, 'remark' => ''],
                    ['month' => 'February', 'gross_profit' => 1897694.00, 'office_expense' => 1203797.00, 'cash_payment_md' => 325000.00, 'net_balance' => 725519.00, 'remark' => ''],
                    ['month' => 'March', 'gross_profit' => 2572568.00, 'office_expense' => 1624320.00, 'cash_payment_md' => 1500000.00, 'net_balance' => -322003.00, 'remark' => ''],
                    ['month' => 'April', 'gross_profit' => 2141998.00, 'office_expense' => 1577281.00, 'cash_payment_md' => 35000.00, 'net_balance' => 529717.00, 'remark' => ''],
                    ['month' => 'May', 'gross_profit' => 2496907.99, 'office_expense' => 1420390.00, 'cash_payment_md' => 130000.00, 'net_balance' => 946517.99, 'remark' => ''],
                    ['month' => 'June', 'gross_profit' => 2854843.00, 'office_expense' => 1454710.00, 'cash_payment_md' => 147000.00, 'net_balance' => 1425523.00, 'remark' => ''],
                    ['month' => 'July', 'gross_profit' => 2893048.00, 'office_expense' => 1300090.00, 'cash_payment_md' => 185000.00, 'net_balance' => 1607958.00, 'remark' => ''],
                    ['month' => 'August', 'gross_profit' => 0.00, 'office_expense' => 0.00, 'cash_payment_md' => 0.00, 'net_balance' => 0.00, 'remark' => ''],
                    ['month' => 'September', 'gross_profit' => 0.00, 'office_expense' => 0.00, 'cash_payment_md' => 0.00, 'net_balance' => 0.00, 'remark' => ''],
                    ['month' => 'October', 'gross_profit' => 0.00, 'office_expense' => 0.00, 'cash_payment_md' => 0.00, 'net_balance' => 0.00, 'remark' => ''],
                    ['month' => 'November', 'gross_profit' => 0.00, 'office_expense' => 0.00, 'cash_payment_md' => 0.00, 'net_balance' => 0.00, 'remark' => ''],
                    ['month' => 'December', 'gross_profit' => 0.00, 'office_expense' => 0.00, 'cash_payment_md' => 0.00, 'net_balance' => 0.00, 'remark' => ''],
                ],
                'total_net_balance' => 6372710.99,
                'total_profit' => 6372710.99,
            ],
            'cash_history' => [
                'items' => [
                    ['particular' => 'bank', 'qty' => null, 'amount' => 700000.00],
                    ['particular' => 'Pay order', 'qty' => null, 'amount' => null],
                    ['particular' => 'cash', 'qty' => null, 'amount' => 2646750.00],
                    ['particular' => 'Diesel', 'qty' => 20630, 'amount' => 2372450.00],
                    ['particular' => 'Octane', 'qty' => 14216, 'amount' => 2061320.00],
                    ['particular' => 'LPG', 'qty' => 6500, 'amount' => 405600.00],
                ],
                'subtotal' => 8186120.00,
                'extra_items' => [
                    ['particular' => 'Kuddu', 'qty' => null, 'amount' => 997695.00],
                    ['particular' => 'Cash', 'qty' => null, 'amount' => 7188425.00],
                ],
            ],
            'bottom_summary' => [
                'invest_amount' => 12174977.00,
                'profit' => 6372710.99,
                'total_invest_profit' => 18547687.99,
                'total_amount' => 18547687.99,
                'recent_due' => 11551984.00,
                'cash' => 6995703.99,
                'extra' => 192721.01,
            ],
        ];
    @endphp

    <div class="title-section">
        <div class="title-box">Balance Sheet</div>
    </div>

    <!-- 1. Top Section: Vertical Balance Breakdown Table -->
    <table style="margin-bottom: 12px; width: 100%;">
        <thead>
            <tr>
                <th class="text-left" style="font-size: 11px;">Particulars</th>
                <th class="text-right" style="font-size: 11px; width: 180px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="font-weight: 600;">Capital Balance</td>
                <td class="text-right">{{ number_format($topSheetData['close_month_year']['capital_balance'] ?? $topSheetData['close_month_year']['amount'], 2) }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600;">Loan Balance</td>
                <td class="text-right">{{ number_format($topSheetData['close_month_year']['loan_balance'] ?? 0, 2) }}</td>
            </tr>
            <tr class="font-bold" style="background-color: #f5f5f5;">
                <td>Total Balance</td>
                <td class="text-right">{{ number_format($topSheetData['close_month_year']['total_balance'] ?? $topSheetData['close_month_year']['amount'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- 2. Middle Section: Top Sheet & Cash History Side by Side -->
    <table style="border: none; margin: 0; padding: 0;">
        <tr style="background: none !important;">
            <!-- Left: Top Sheet Table -->
            <td style="border: none !important; width: 68%; vertical-align: top; padding: 0 8px 0 0;">
                <table style="margin: 0;">
                    <thead>
                        <tr>
                            <th colspan="6" class="text-center" style="font-size: 11px;">Top Sheet</th>
                        </tr>
                        <tr>
                            <th colspan="6" class="text-center" style="font-weight: normal; font-size: 10px;">Month wise balance sheet</th>
                        </tr>
                        <tr>
                            <th>Month</th>
                            <th class="text-right">Gross Profit</th>
                            <th class="text-right">Office Expence</th>
                            <th class="text-right">Cash Pement (Md Sir)</th>
                            <th class="text-right">Net Balance</th>
                            <th>Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topSheetData['top_sheet']['months'] as $row)
                        <tr>
                            <td class="font-bold">{{ $row['month'] }}</td>
                            <td class="text-right">{{ $row['gross_profit'] ? number_format($row['gross_profit'], 2) : '-' }}</td>
                            <td class="text-right">{{ $row['office_expense'] ? number_format($row['office_expense'], 2) : '-' }}</td>
                            <td class="text-right">{{ $row['cash_payment_md'] ? number_format($row['cash_payment_md'], 2) : '-' }}</td>
                            <td class="text-right {{ $row['net_balance'] < 0 ? 'font-bold' : '' }}">
                                @if($row['net_balance'] < 0)
                                    ({{ number_format(abs($row['net_balance']), 2) }})
                                @elseif($row['net_balance'] > 0)
                                    {{ number_format($row['net_balance'], 2) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $row['remark'] ?? '' }}</td>
                        </tr>
                        @endforeach
                        <tr class="font-bold" style="background-color: #f9f9f9;">
                            <td colspan="4" class="text-right">Total Net Balance</td>
                            <td class="text-right">{{ number_format($topSheetData['top_sheet']['total_net_balance'], 2) }}</td>
                            <td></td>
                        </tr>
                        <tr class="font-bold" style="background-color: #f5f5f5;">
                            <td colspan="4" class="text-right">Total Profit</td>
                            <td class="text-right">{{ number_format($topSheetData['top_sheet']['total_profit'], 2) }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </td>

            <!-- Right: Cash History Table -->
            <td style="border: none !important; width: 32%; vertical-align: top; padding: 0;">
                <table style="margin: 0;">
                    <thead>
                        <tr>
                            <th colspan="3" class="text-center" style="font-size: 11px;">Cash History</th>
                        </tr>
                        <tr>
                            <th>Particular</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topSheetData['cash_history']['items'] as $item)
                        <tr>
                            <td>{{ $item['particular'] }}</td>
                            <td class="text-right">{{ $item['qty'] ? number_format($item['qty']) : '' }}</td>
                            <td class="text-right">{{ $item['amount'] !== null ? number_format($item['amount'], 2) : '' }}</td>
                        </tr>
                        @endforeach
                        <tr class="font-bold" style="background-color: #f9f9f9;">
                            <td colspan="2" class="text-right">total</td>
                            <td class="text-right">{{ number_format($topSheetData['cash_history']['subtotal'], 2) }}</td>
                        </tr>
                        @if(!empty($topSheetData['cash_history']['extra_items']))
                            @foreach($topSheetData['cash_history']['extra_items'] as $extra)
                            <tr>
                                <td colspan="2">{{ $extra['particular'] }}</td>
                                <td class="text-right font-bold">{{ number_format($extra['amount'], 2) }}</td>
                            </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <!-- 3. Bottom Section: Investment/Profit & Due/Cash Summaries -->
    <table style="border: none; margin-top: 15px; padding: 0;">
        <tr style="background: none !important;">
            <!-- Bottom Left: Total Cash / Investment & Profit -->
            <td style="border: none !important; width: 45%; vertical-align: top; padding: 0 10px 0 0;">
                <table style="margin: 0;">
                    <thead>
                        <tr>
                            <th>Total Cash</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Invest Amount</td>
                            <td class="text-right">{{ number_format($topSheetData['bottom_summary']['invest_amount'], 2) }}</td>
                        </tr>
                        <tr>
                            <td>Profit</td>
                            <td class="text-right">{{ number_format($topSheetData['bottom_summary']['profit'], 2) }}</td>
                        </tr>
                        <tr class="font-bold" style="background-color: #f5f5f5;">
                            <td>Total</td>
                            <td class="text-right">{{ number_format($topSheetData['bottom_summary']['total_invest_profit'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>

            <!-- Bottom Right: Total Amount & Receivables -->
            <td style="border: none !important; width: 55%; vertical-align: top; padding: 0;">
                <table style="margin: 0;">
                    <tbody>
                        <tr class="font-bold">
                            <td>Total Amount</td>
                            <td class="text-right" style="width: 140px;">{{ number_format($topSheetData['bottom_summary']['total_amount'], 2) }}</td>
                        </tr>
                        <tr>
                            <td>Recent Due</td>
                            <td class="text-right">{{ number_format($topSheetData['bottom_summary']['recent_due'], 2) }}</td>
                        </tr>
                        <tr>
                            <td>Cash</td>
                            <td class="text-right">{{ number_format($topSheetData['bottom_summary']['cash'], 2) }}</td>
                        </tr>
                        <tr style="background-color: #f9f9f9;">
                            <td class="font-bold">Exta</td>
                            <td class="text-right font-bold">{{ number_format($topSheetData['bottom_summary']['extra'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    @include('pdf.components.footer')
</body>
</html>