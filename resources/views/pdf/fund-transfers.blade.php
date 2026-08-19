<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Internal Fund Transfers Report</title>
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

        th,
        td {
            border: 1px solid #000;
            padding: 8px 6px;
            text-align: left;
        }

        th {
            font-weight: bold;
            font-size: 11px;
            color: #000;
        }

        td {
            font-size: 10px;
            color: #333;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
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

        .footer-left {
            text-align: left;
        }

        .footer-right {
            text-align: right;
        }

        @media print {
            .footer {
                position: fixed;
                bottom: 0;
            }
        }
    </style>
</head>
<body>
    @include('pdf.components.watermark')
    @include('pdf.components.header')

    <div class="title-section">
        <div class="title-box">Internal Fund Transfers Report</div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 30px;">SL</th>
                <th style="width: 75px;">Transfer No</th>
                <th style="width: 70px;">Date</th>
                <th>From Account</th>
                <th>To Account</th>
                <th class="text-right" style="width: 80px;">Amount</th>
                <th class="text-right" style="width: 60px;">Fee</th>
                <th class="text-right" style="width: 80px;">Total Out</th>
                <th style="width: 60px;">Status</th>
                <th>Reference / Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transfers as $index => $transfer)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td><strong>{{ $transfer->transfer_no }}</strong></td>
                <td>{{ \Carbon\Carbon::parse($transfer->transfer_date)->format('d/m/Y') }}</td>
                <td>{{ $transfer->fromAccount->name ?? 'N/A' }}</td>
                <td>{{ $transfer->toAccount->name ?? 'N/A' }}</td>
                <td class="text-right">{{ number_format($transfer->amount, 2) }}</td>
                <td class="text-right">{{ number_format($transfer->transfer_fee, 2) }}</td>
                <td class="text-right">{{ number_format($transfer->amount + $transfer->transfer_fee, 2) }}</td>
                <td class="text-center">{{ ucfirst($transfer->status) }}</td>
                <td>
                    @if($transfer->reference_no)
                        Ref: {{ $transfer->reference_no }}<br>
                    @endif
                    {{ $transfer->remarks ?? '-' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="text-center" style="padding: 20px; color: #999;">No fund transfers found</td>
            </tr>
            @endforelse
        </tbody>
        @if(count($transfers) > 0)
        <tfoot>
            <tr style="font-weight: bold;">
                <td colspan="5" class="text-right">Total:</td>
                <td class="text-right">{{ number_format($transfers->sum('amount'), 2) }}</td>
                <td class="text-right">{{ number_format($transfers->sum('transfer_fee'), 2) }}</td>
                <td class="text-right">{{ number_format($transfers->sum(fn ($t) => $t->amount + $t->transfer_fee), 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="footer">
        <div class="footer-left">
            Generated on: {{ date('Y-m-d H:i:s') }}
        </div>
        <div class="footer-right">
            Total Records: {{ count($transfers) }}
        </div>
    </div>
</body>
</html>
