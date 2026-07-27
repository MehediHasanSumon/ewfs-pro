<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Report Details</title>
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
            font-size: 11px;
            color: #000;
        }
        td {
            font-size: 10px;
            color: #333;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .date-row {
            background-color: #e0e0e0 !important;
            font-weight: bold;
        }
        .total-row {
            background-color: #e0e0e0 !important;
            font-weight: bold;
        }
        .grand-total-section {
            margin-top: 20px;
            padding: 15px;
            background-color: #f5f5f5;
            border: 1px solid #ddd;
        }
        .grand-total-section p {
            margin: 5px 0;
            font-size: 13px;
        }
        .grand-total-section .amount {
            font-weight: bold;
            font-size: 14px;
        }
        .grand-total-section .words {
            font-style: italic;
            font-size: 12px;
        }
        .footer {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
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
        <div class="title-box">
            Purchase Report Details
            @if($startDate && $endDate)
                ({{ $startDate }} to {{ $endDate }})
            @elseif($startDate)
                (From {{ $startDate }})
            @elseif($endDate)
                (Up to {{ $endDate }})
            @endif
        </div>
    </div>

    @if(count($purchases) > 0)
    <table>
        <thead>
            <tr>
                <th>Supplier</th>
                <th>Invoice No</th>
                <th>Sup. Inv</th>
                <th>Product Name</th>
                <th>Unit</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @php
                $currentDate = '';
                $totalQuantity = 0;
                $totalAmount = 0;
            @endphp
            @foreach($purchases as $purchase)
                @if($purchase['date'] !== $currentDate)
                    @php $currentDate = $purchase['date']; @endphp
                    <tr class="date-row">
                        <td colspan="8">Date: {{ \Carbon\Carbon::parse($purchase['date'])->format('d/m/Y') }}</td>
                    </tr>
                @endif
                <tr>
                    <td>{{ $purchase['supplier_name'] }}</td>
                    <td>{{ $purchase['invoice_no'] }}</td>
                    <td>{{ $purchase['memo_no'] ?? '-' }}</td>
                    <td>{{ $purchase['product_name'] }}</td>
                    <td>{{ $purchase['unit_name'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format($purchase['quantity'], 2) }}</td>
                    <td class="text-right">{{ number_format($purchase['price'], 2) }}</td>
                    <td class="text-right">{{ number_format($purchase['total_amount'], 2) }}</td>
                </tr>
                @php
                    $totalQuantity += $purchase['quantity'];
                    $totalAmount += $purchase['total_amount'];
                @endphp
            @endforeach
            <tr class="total-row">
                <td colspan="5">Grand Total:</td>
                <td class="text-right">{{ number_format($totalQuantity, 2) }}</td>
                <td class="text-right"></td>
                <td class="text-right">{{ number_format($totalAmount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="grand-total-section">
        <p class="amount">Grand Total: {{ number_format($totalAmount, 2) }}</p>
        <p class="words">In words: {{ numberToWords(floor($totalAmount)) }}</p>
    </div>
    @else
    <p style="text-align: center; padding: 40px; color: #999; font-size: 14px;">No records found</p>
    @endif

    @php
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

    <div class="footer">
        <p>Generated on {{ now()->format('d/m/Y H:i:s') }} | Total Records: {{ count($purchases) }}</p>
    </div>
</body>
</html>