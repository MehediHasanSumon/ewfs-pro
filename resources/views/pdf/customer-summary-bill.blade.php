<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Summary Bill</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 10mm 15mm 10mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
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
            font-size: 11px;
            color: #333;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    @include('pdf.components.watermark')
    @include('pdf.components.header', ['title' => 'Customer Summary Bill (' . $startDate . ' to ' . $endDate . ')'])

    @php
        if (!function_exists('numberToWords')) {
            function numberToWords($num) {
                $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
                $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
                $teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
                
                if ($num == 0) return 'Zero';
                
                $convertLessThanThousand = function ($n) use ($ones, $tens, $teens, &$convertLessThanThousand) {
                    if ($n == 0) return '';
                    if ($n < 10) return $ones[$n];
                    if ($n < 20) return $teens[$n - 10];
                    if ($n < 100) return $tens[floor($n / 10)] . ($n % 10 != 0 ? ' ' . $ones[$n % 10] : '');
                    return $ones[floor($n / 100)] . ' Hundred' . ($n % 100 != 0 ? ' ' . $convertLessThanThousand($n % 100) : '');
                };
                
                $billion = floor($num / 1000000000);
                $million = floor(($num % 1000000000) / 1000000);
                $thousand = floor(($num % 1000000) / 1000);
                $remainder = floor($num % 1000);
                
                $result = '';
                if ($billion > 0) $result .= convertLessThanThousand($billion) . ' Billion ';
                if ($million > 0) $result .= convertLessThanThousand($million) . ' Million ';
                if ($thousand > 0) $result .= convertLessThanThousand($thousand) . ' Thousand ';
                if ($remainder > 0) $result .= convertLessThanThousand($remainder);
                
                return trim($result);
            }
        }
    @endphp

    @forelse($bills as $bill)
    <table style="margin-bottom: 15px;">
        <thead>
            <tr>
                <th>Date</th>
                <th>Vehicle</th>
                <th>Invoice No</th>
                <th>Product</th>
                <th>Unit</th>
                <th class="text-right">Price</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Amount</th>
            </tr>
            <tr>
                <th colspan="8" style="padding: 8px; text-align: left; font-size: 13px;">
                    {{ $bill['customer_name'] }}
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach($bill['sales'] as $sale)
            <tr>
                <td>{{ $sale->sale_date }}</td>
                <td>{{ $sale->vehicle_number }}</td>
                <td>{{ $sale->invoice_no }}</td>
                <td>{{ $sale->product_name }}</td>
                <td>{{ $sale->unit_name }}</td>
                <td class="text-right">{{ number_format($sale->price, 2) }}</td>
                <td class="text-right">{{ number_format($sale->quantity, 2) }}</td>
                <td class="text-right">{{ number_format($sale->total_amount, 2) }}</td>
            </tr>
            @endforeach
            <tr style="font-weight: bold;">
                <td colspan="6">Total:</td>
                <td class="text-right">{{ number_format($bill['total_quantity'], 2) }}</td>
                <td class="text-right">{{ number_format($bill['total_amount'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Product-wise Sales Summary Table --}}
    @if(!empty($bill['product_summary']) && count($bill['product_summary']) > 0)
    <div style="margin-top: 15px; margin-bottom: 5px; font-weight: bold; font-size: 13px;">Product-wise Sales Summary</div>
    <table style="margin-bottom: 25px;">
        <thead>
            <tr>
                <th style="width: 40px;" class="text-center">SL</th>
                <th>Product</th>
                <th>Unit</th>
                <th class="text-right">Price</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bill['product_summary'] as $index => $product)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $product['product_name'] }}</td>
                <td>{{ $product['unit_name'] }}</td>
                <td class="text-right">{{ number_format($product['price'], 2) }}</td>
                <td class="text-right">{{ number_format($product['quantity'], 2) }}</td>
                <td class="text-right">{{ number_format($product['total_amount'], 2) }}</td>
            </tr>
            @endforeach
            <tr style="font-weight: bold;">
                <td colspan="5" class="text-right">Total:</td>
                <td class="text-right">{{ number_format($bill['total_amount'], 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif
    @empty
    <p style="text-align: center; padding: 20px; color: #999;">No records found</p>
    @endforelse

    @if(count($bills) > 0)
    @php
        $grandTotal = collect($bills)->sum('total_amount');
    @endphp
    <div style="margin-top: 20px; margin-bottom: 30px; padding: 10px 0;">
        <p style="margin: 0; font-weight: bold; font-size: 13px;">Grand Total: {{ number_format($grandTotal, 2) }}</p>
        <p style="margin: 5px 0 0 0; font-style: italic; font-size: 12px;">In words: {{ numberToWords(floor($grandTotal)) }}</p>
    </div>
    @endif

    @include('pdf.components.footer', ['totalRecords' => count($bills)])
</body>
</html>
