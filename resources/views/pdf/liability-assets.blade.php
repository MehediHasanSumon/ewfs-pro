<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Liability and Assets Report</title>
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
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin: 20px 0 10px 0;
            color: #000;
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
        <div class="title-box">Liability and Assets Report</div>
    </div>

    <div class="section-title">Liabilities</div>
    <table>
        <thead>
            <tr>
                <th class="text-center">SL</th>
                <th>Account Name</th>
                <th>Group</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse($liabilities as $index => $liability)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $liability['name'] }}</td>
                <td>{{ $liability['group_name'] }}</td>
                <td class="text-right">{{ number_format($liability['balance'], 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center" style="padding: 20px; color: #999;">No liability accounts found</td></tr>
            @endforelse
            @if(count($liabilities) > 0)
            <tr style="font-weight: bold; background-color: #ffebee;">
                <td colspan="3" style="padding: 12px;">Total Liabilities:</td>
                <td class="text-right" style="padding: 12px;">{{ number_format($totalLiabilities, 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="section-title">Assets</div>
    <table>
        <thead>
            <tr>
                <th class="text-center">SL</th>
                <th>Account Name</th>
                <th>Group</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse($assets as $index => $asset)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $asset['name'] }}</td>
                <td>{{ $asset['group_name'] }}</td>
                <td class="text-right">{{ number_format($asset['balance'], 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center" style="padding: 20px; color: #999;">No asset accounts found</td></tr>
            @endforelse
            @if(count($assets) > 0)
            <tr style="font-weight: bold; background-color: #e8f5e8;">
                <td colspan="3" style="padding: 12px;">Total Assets:</td>
                <td class="text-right" style="padding: 12px;">{{ number_format($totalAssets, 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    @if(count($liabilities) > 0 || count($assets) > 0)
    @php
        $netWorth = $totalAssets - $totalLiabilities;
        
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
            <tr style="font-weight: bold; background-color: #e3f2fd; font-size: 14px;">
                <td style="padding: 12px;">Total Liabilities:</td>
                <td class="text-right" style="padding: 12px;">{{ number_format($totalLiabilities, 2) }}</td>
            </tr>
            <tr style="font-weight: bold; background-color: #e3f2fd; font-size: 14px;">
                <td style="padding: 12px;">Total Assets:</td>
                <td class="text-right" style="padding: 12px;">{{ number_format($totalAssets, 2) }}</td>
            </tr>
            <tr style="font-weight: bold; background-color: #d0d0d0; font-size: 14px;">
                <td style="padding: 12px;">Net Worth (Assets - Liabilities):</td>
                <td class="text-right" style="padding: 12px;">{{ number_format($netWorth, 2) }}</td>
            </tr>
            <tr style="background-color: #f5f5f5;">
                <td colspan="2" style="padding: 12px; font-style: italic; font-size: 12px;">
                    Net Worth in words: {{ numberToWords(floor($netWorth)) }}
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
            Liabilities: {{ count($liabilities) }} | Assets: {{ count($assets) }}
        </div>
    </div>
</body>
</html>