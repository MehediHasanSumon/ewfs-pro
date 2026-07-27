<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payment Vouchers Report</title>
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
        <div class="title-box">Payment Vouchers Report</div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 30px;">SL</th>
                <th style="width: 70px;">Date</th>
                <th style="width: 60px;">Shift</th>
                <th>From Account</th>
                <th>To Account</th>
                <th class="text-right" style="width: 80px;">Amount</th>
                <th style="width: 80px;">Payment Type</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse($vouchers as $index => $voucher)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($voucher->date)->format('d/m/Y') }}</td>
                <td>{{ $voucher->shift->name ?? 'N/A' }}</td>
                <td>{{ $voucher->fromAccount->name ?? 'N/A' }}</td>
                <td>{{ $voucher->toAccount->name ?? 'N/A' }}</td>
                <td class="text-right">{{ number_format($voucher->fromTransaction->amount ?? 0, 2) }}</td>
                <td>{{ ucfirst($voucher->fromTransaction->payment_type ?? 'N/A') }}</td>
                <td>{{ $voucher->remarks ?? 'N/A' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center" style="padding: 20px; color: #999;">No payment vouchers found</td>
            </tr>
            @endforelse
        </tbody>
        @if(count($vouchers) > 0)
        <tfoot>
            <tr style="background-color: #e9ecef; font-weight: bold;">
                <td colspan="5" class="text-right">Total:</td>
                <td class="text-right">{{ number_format($vouchers->sum(function($voucher) { return $voucher->fromTransaction->amount ?? 0; }), 2) }}</td>
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
            Total Records: {{ count($vouchers) }}
        </div>
    </div>
</body>
</html>