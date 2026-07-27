<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Customers List</title>
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
            padding: 6px 4px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 10px;
            color: #000;
            padding: 6px 4px;
        }

        td {
            font-size: 9px;
            color: #333;
            padding: 6px 4px;
        }

        .text-center {
            text-align: center;
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
        <div class="title-box">Customers List</div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 30px; font-size: 10px;">SL</th>
                <th style="width: 120px; font-size: 10px;">Name</th>
                <th style="width: 80px; font-size: 10px;">Account Number</th>
                <th style="width: 80px; font-size: 10px;">Mobile</th>
                <th style="width: 70px; text-align: right; font-size: 10px;">Total Sale</th>
                <th style="width: 70px; text-align: right; font-size: 10px;">Total Payment</th>
                <th style="width: 80px; text-align: right; font-size: 10px;">Total Due/Advanced</th>
            </tr>
        </thead>
        <tbody>
            @forelse($customers as $index => $customer)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $customer->name }}</td>
                <td>{{ $customer->account->ac_number ?? 'N/A' }}</td>
                <td>{{ $customer->mobile ?? 'N/A' }}</td>
                <td style="text-align: right;">{{ number_format($customer->total_sales ?? 0, 2) }}</td>
                <td style="text-align: right;">{{ number_format($customer->total_paid ?? 0, 2) }}</td>
                <td style="text-align: right; color: {{ ($customer->current_due ?? 0) > 0 ? '#dc2626' : (($customer->current_due ?? 0) < 0 ? '#16a34a' : '#333') }}">
                    {{ ($customer->current_due ?? 0) < 0 ? '-' : '' }}{{ number_format(abs($customer->current_due ?? 0), 2) }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center" style="padding: 20px; color: #999;">No customers found</td>
            </tr>
            @endforelse
            @if(count($customers) > 0)
            @php
                $grandTotalSales = $customers->sum('total_sales');
                $grandTotalPaid = $customers->sum('total_paid');
                $grandTotalDue = $customers->sum('current_due');
                
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
            <tr style="font-weight: bold; background-color: #e0e0e0; border-top: 2px solid #000;">
                <td colspan="4" style="text-align: center; font-size: 10px;">Grand Total:</td>
                <td style="text-align: right; font-size: 10px;">{{ number_format($grandTotalSales, 2) }}</td>
                <td style="text-align: right; font-size: 10px;">{{ number_format($grandTotalPaid, 2) }}</td>
                <td style="text-align: right; font-size: 10px; color: {{ $grandTotalDue > 0 ? '#dc2626' : ($grandTotalDue < 0 ? '#16a34a' : '#333') }}">
                    {{ $grandTotalDue < 0 ? '-' : '' }}{{ number_format(abs($grandTotalDue), 2) }}
                </td>
            </tr>
            <tr style="background-color: #f5f5f5;">
                <td colspan="7" style="padding: 8px; font-style: italic; font-size: 9px;">
                    Total Due/Advanced in words: {{ numberToWords(floor(abs($grandTotalDue))) }} Taka Only
                </td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            Generated on: {{ date('Y-m-d H:i:s') }}
        </div>
        <div class="footer-right">
            Total Records: {{ count($customers) }}
        </div>
    </div>
</body>

</html>