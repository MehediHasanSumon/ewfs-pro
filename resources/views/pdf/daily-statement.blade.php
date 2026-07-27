<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Statement Report</title>
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
        th, td {
            border: 1px solid #ccc;
            padding: 10px 8px;
            text-align: left;
        }
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
        <div class="title-box">Daily Statement Report ({{ $startDate }} to {{ $endDate }})</div>
    </div>

    <h3 style="margin: 20px 0 10px 0; font-size: 14px;">1. Sales Summary (Product Wise)</h3>
    <table>
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Unit Name</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($allProductSales as $sale)
            <tr>
                <td>{{ $sale['product_name'] }}</td>
                <td>{{ $sale['unit_name'] }}</td>
                <td class="text-right">{{ number_format($sale['unit_price'], 2) }}</td>
                <td class="text-right">{{ number_format($sale['total_quantity'], 2) }}</td>
                <td class="text-right">{{ number_format($sale['total_amount'], 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center">No records</td></tr>
            @endforelse
            @if(count($allProductSales) > 0)
            <tr style="font-weight: bold; background-color: #e0e0e0;">
                <td colspan="3">Total:</td>
                <td class="text-right">{{ number_format(collect($allProductSales)->sum('total_quantity'), 2) }}</td>
                <td class="text-right">{{ number_format(collect($allProductSales)->sum('total_amount'), 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <h3 style="margin: 20px 0 10px 0; font-size: 14px;">2. Sales Summary (Cash & Bank)</h3>
    <table>
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Unit Name</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cashBankSales as $sale)
            <tr>
                <td>{{ $sale->product_name }}</td>
                <td>{{ $sale->unit_name }}</td>
                <td class="text-right">{{ number_format($sale->unit_price, 2) }}</td>
                <td class="text-right">{{ number_format($sale->total_quantity, 2) }}</td>
                <td class="text-right">{{ number_format($sale->total_amount, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center">No records</td></tr>
            @endforelse
            @if(count($cashBankSales) > 0)
            <tr style="font-weight: bold; background-color: #e0e0e0;">
                <td colspan="3">Total:</td>
                <td class="text-right">{{ number_format($cashBankSales->sum('total_quantity'), 2) }}</td>
                <td class="text-right">{{ number_format($cashBankSales->sum('total_amount'), 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <h3 style="margin: 20px 0 10px 0; font-size: 14px;">3. Sales Summary (Credit)</h3>
    <table>
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Unit Name</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($creditSales as $sale)
            <tr>
                <td>{{ $sale->product_name }}</td>
                <td>{{ $sale->unit_name }}</td>
                <td class="text-right">{{ number_format($sale->unit_price, 2) }}</td>
                <td class="text-right">{{ number_format($sale->total_quantity, 2) }}</td>
                <td class="text-right">{{ number_format($sale->total_amount, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center">No records</td></tr>
            @endforelse
            @if(count($creditSales) > 0)
            <tr style="font-weight: bold; background-color: #e0e0e0;">
                <td colspan="3">Total:</td>
                <td class="text-right">{{ number_format($creditSales->sum('total_quantity'), 2) }}</td>
                <td class="text-right">{{ number_format($creditSales->sum('total_amount'), 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <h3 style="margin: 20px 0 10px 0; font-size: 14px;">4. Customer Wise Sales Summary (Credit)</h3>
    <table>
        <thead>
            <tr>
                <th>Customer Name</th>
                <th>Vehicle Number</th>
                <th>Product Name</th>
                <th>Unit Name</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($customerWiseSales as $sale)
            <tr>
                <td>{{ $sale->customer_name }}</td>
                <td>{{ $sale->vehicle_no }}</td>
                <td>{{ $sale->product_name }}</td>
                <td>{{ $sale->unit_name }}</td>
                <td class="text-right">{{ number_format($sale->unit_price, 2) }}</td>
                <td class="text-right">{{ number_format($sale->quantity, 2) }}</td>
                <td class="text-right">{{ number_format($sale->total_amount, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center">No records</td></tr>
            @endforelse
            @if(count($customerWiseSales) > 0)
            <tr style="font-weight: bold; background-color: #e0e0e0;">
                <td colspan="5">Total:</td>
                <td class="text-right">{{ number_format($customerWiseSales->sum('quantity'), 2) }}</td>
                <td class="text-right">{{ number_format($customerWiseSales->sum('total_amount'), 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <h3 style="margin: 20px 0 10px 0; font-size: 14px;">5. Cash Received Summary</h3>
    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 50px;">Sl</th>
                <th>Name</th>
                <th>Received Type</th>
                <th class="text-right">Received Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cashReceived as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item->account_name }}</td>
                <td>{{ $item->payment_type ?? '-' }}</td>
                <td class="text-right">{{ number_format($item->amount, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center">No records</td></tr>
            @endforelse
            @if(count($cashReceived) > 0)
            <tr style="font-weight: bold; background-color: #e0e0e0;">
                <td colspan="3">Total:</td>
                <td class="text-right">{{ number_format($cashReceived->sum('amount'), 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <h3 style="margin: 20px 0 10px 0; font-size: 14px;">6. Cash Payment Summary</h3>
    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 50px;">Sl</th>
                <th>Name</th>
                <th>Payment Type</th>
                <th class="text-right">Payment Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cashPayment as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item->account_name }}</td>
                <td>{{ $item->payment_type ?? '-' }}</td>
                <td class="text-right">{{ number_format($item->amount, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center">No records</td></tr>
            @endforelse
            @if(count($cashPayment) > 0)
            <tr style="font-weight: bold; background-color: #e0e0e0;">
                <td colspan="3">Total:</td>
                <td class="text-right">{{ number_format($cashPayment->sum('amount'), 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <table style="margin-top: 30px; border: 1px solid #000;">
        <tr>
            <td style="width: 50%; border-right: 1px solid #000; padding: 80px 20px 20px 20px; vertical-align: bottom;">
                <div style="border-top: 1px solid #000; width: 200px; padding-top: 5px;">Manager Signature</div>
            </td>
            <td style="width: 50%; padding: 20px;">
                <table style="width: 100%; border: none;">
                    <tr>
                        <td style="border: none; padding: 5px; font-weight: bold;">Sales Summary (Product Wise):</td>
                        <td style="border: none; padding: 5px; text-align: right; font-weight: bold;">{{ number_format(collect($allProductSales)->sum('total_amount'), 2) }}</td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 5px;">Cash Received Summary:</td>
                        <td style="border: none; padding: 5px; text-align: right;">{{ number_format($cashReceived->sum('amount'), 2) }}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #000;">
                        <td style="border: none; padding: 5px; font-weight: bold;">Total Received:</td>
                        <td style="border: none; padding: 5px; text-align: right; font-weight: bold; border-bottom: 1px solid #000;">{{ number_format(collect($allProductSales)->sum('total_amount') + $cashReceived->sum('amount'), 2) }}</td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 5px; padding-top: 15px;">Sales Summary (Credit):</td>
                        <td style="border: none; padding: 5px; padding-top: 15px; text-align: right;">{{ number_format($creditSales->sum('total_amount'), 2) }}</td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 5px;">Sales Summary (Bank):</td>
                        <td style="border: none; padding: 5px; text-align: right;">{{ number_format($cashBankSales->sum('total_amount'), 2) }}</td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 5px;">Cash Payment Summary:</td>
                        <td style="border: none; padding: 5px; text-align: right;">{{ number_format($cashPayment->sum('amount'), 2) }}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #000;">
                        <td style="border: none; padding: 5px; font-weight: bold;">Total Payment:</td>
                        <td style="border: none; padding: 5px; text-align: right; font-weight: bold; border-bottom: 1px solid #000;">{{ number_format($creditSales->sum('total_amount') + $cashBankSales->sum('total_amount') + $cashPayment->sum('amount'), 2) }}</td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 5px; padding-top: 15px; font-weight: bold; font-size: 14px;">CASH IN HAND</td>
                        <td style="border: none; padding: 5px; padding-top: 15px; text-align: right; font-weight: bold; font-size: 14px;">{{ number_format((collect($allProductSales)->sum('total_amount') + $cashReceived->sum('amount')) - ($creditSales->sum('total_amount') + $cashBankSales->sum('total_amount') + $cashPayment->sum('amount')), 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">
        <div class="footer-left">
            Generated on: {{ date('Y-m-d H:i:s') }}
        </div>
        <div class="footer-right">
            Date Range: {{ $startDate }} to {{ $endDate }}
        </div>
    </div>
</body>
</html>
