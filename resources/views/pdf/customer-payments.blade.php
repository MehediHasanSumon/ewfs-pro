<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Summary - {{ $customer->name }}</title>
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
        .customer-info {
            margin-bottom: 15px;
        }
        .customer-info table {
            width: 100%;
            border: none;
        }
        .customer-info td {
            border: none;
            padding: 2px 0;
            font-size: 12px;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 10px 8px; text-align: left; }
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
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        tr:nth-child(even) { background-color: #f9f9f9; }
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
        .footer-left { text-align: left; }
        .footer-right { text-align: right; }
        @media print {
            .footer { position: fixed; bottom: 0; }
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
        <div class="title-box">Payment Summary</div>
    </div>

    <div class="customer-info">
        <table>
            <tr>
                <td><strong>Customer Name:</strong> {{ $customer->name }}</td>
                <td><strong>Mobile:</strong> {{ $customer->mobile ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Address:</strong> {{ $customer->address ?? 'N/A' }}</td>
                <td><strong>Account Number:</strong> {{ $customer->account->ac_number ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center">SL</th>
                <th>Date</th>
                <th class="text-right">Amount</th>
                <th>Payment Type</th>
                <th>Remark</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $index => $payment)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($payment['date'])->format('d/m/Y') }}</td>
                <td class="text-right">{{ number_format($payment['amount'], 2) }}</td>
                <td>{{ $payment['payment_type'] ?? 'N/A' }}</td>
                <td>{{ $payment['remarks'] ?? 'N/A' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center" style="padding: 20px; color: #999;">No records found</td></tr>
            @endforelse
        </tbody>
    </table>

    @if(count($payments) > 0)
    @php
        $grandTotal = collect($payments)->sum('amount');
        
        function numberToWords($num) {
            $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
            $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
            $teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
            
            if ($num == 0) return 'Zero';
            
            function convertLessThanThousand($n, $ones, $tens, $teens) {
                if ($n == 0) return '';
                if ($n < 10) return $ones[$n];
                if ($n < 20) return $teens[$n - 10];
                if ($n < 100) return $tens[floor($n / 10)] . ($n % 10 != 0 ? ' ' . $ones[$n % 10] : '');
                return $ones[floor($n / 100)] . ' Hundred' . ($n % 100 != 0 ? ' ' . convertLessThanThousand($n % 100, $ones, $tens, $teens) : '');
            }
            
            $billion = floor($num / 1000000000);
            $million = floor(($num % 1000000000) / 1000000);
            $thousand = floor(($num % 1000000) / 1000);
            $remainder = floor($num % 1000);
            
            $result = '';
            if ($billion > 0) $result .= convertLessThanThousand($billion, $ones, $tens, $teens) . ' Billion ';
            if ($million > 0) $result .= convertLessThanThousand($million, $ones, $tens, $teens) . ' Million ';
            if ($thousand > 0) $result .= convertLessThanThousand($thousand, $ones, $tens, $teens) . ' Thousand ';
            if ($remainder > 0) $result .= convertLessThanThousand($remainder, $ones, $tens, $teens);
            
            return trim($result);
        }
    @endphp
    <table style="margin-top: 20px;">
        <tbody>
            <tr style="font-weight: bold; background-color: #d0d0d0; font-size: 14px;">
                <td colspan="4" style="padding: 12px;">Grand Total:</td>
                <td class="text-right" style="padding: 12px;">{{ number_format($grandTotal, 2) }}</td>
            </tr>
            <tr style="background-color: #f5f5f5;">
                <td colspan="5" style="padding: 12px; font-style: italic; font-size: 12px;">
                    In words: {{ numberToWords(floor($grandTotal)) }}
                </td>
            </tr>
        </tbody>
    </table>
    @endif

    <div class="footer">
        <div class="footer-left">
            Generated on: {{ date('Y-m-d H:i:s') }}
        </div>
        <div class="footer-right">
            Total Payments: {{ count($payments) }}
        </div>
    </div>
</body>
</html>
