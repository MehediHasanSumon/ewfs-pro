<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Balance Sheet & Financial Notes</title>
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
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin: 20px 0 10px 0;
            color: #000;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px; 
        }
        th, td { 
            border: 1px solid #ccc; 
            padding: 8px; 
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
        .total-row {
            font-weight: bold;
            background-color: #ffebee;
        }
        .summary-box {
            margin: 10px 0;
            padding: 8px;
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            font-size: 11px;
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
            <!-- Logo placeholder -->
        </div>
        <div class="company-info">
            <h2>East West Filling Station</h2>
            <p>Dhaka, Bangladesh</p>
            <p>mehedihassan2992001@gmail.com | 01750542923</p>
        </div>
    </div>

    <div class="title-section">
        <div class="title-box">Balance Sheet & Financial Notes</div>
    </div>

    <div style="text-align: center; margin-bottom: 15px; font-size: 11px;">
        Period: {{ \Carbon\Carbon::parse($data['start_date'])->format('F j, Y') }} to {{ \Carbon\Carbon::parse($data['end_date'])->format('F j, Y') }}
    </div>

    <!-- Total Purchase Section -->
    <div class="section-title">Total Purchase</div>
    <table>
        <thead>
            <tr>
                <th class="text-center">SL</th>
                <th>Product</th>
                <th class="text-right">Purchase Price</th>
                <th class="text-right">Total Liter</th>
                <th class="text-right">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['purchase_data'] as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item['product_name'] }}</td>
                <td class="text-right">{{ number_format($item['avg_price'], 2) }}</td>
                <td class="text-right">{{ number_format($item['total_quantity'], 2) }}</td>
                <td class="text-right">{{ number_format($item['total_amount'], 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center" style="padding: 20px; color: #999;">No purchase data found</td></tr>
            @endforelse
            @if(count($data['purchase_data']) > 0)
            <tr class="total-row">
                <td colspan="3" style="padding: 10px;"><strong>Total</strong></td>
                <td class="text-right" style="padding: 10px;"><strong>{{ number_format(collect($data['purchase_data'])->sum('total_quantity'), 2) }}</strong></td>
                <td class="text-right" style="padding: 10px;"><strong>{{ number_format($data['totals']['total_purchases'], 2) }}</strong></td>
            </tr>
            @endif
        </tbody>
    </table>

    <!-- Total Sales Section -->
    <div class="section-title">Total Sales</div>
    <table>
        <thead>
            <tr>
                <th class="text-center">SL</th>
                <th>Product</th>
                <th class="text-right">Purchase Price</th>
                <th class="text-right">Sale Price</th>
                <th class="text-right">Total Liter</th>
                <th class="text-right">Total Amount</th>
                <th class="text-right">Total Profit</th>
            </tr>
        </thead>
        <tbody>
            @php $salesIndex = 0; @endphp
            @forelse(array_merge($data['sales_data']->toArray(), $data['credit_sales_data']->toArray()) as $item)
            @php
                $totalProfit = ($item['sale_price'] - $item['purchase_price']) * $item['total_quantity'];
                $salesIndex++;
            @endphp
            <tr>
                <td class="text-center">{{ $salesIndex }}</td>
                <td>{{ $item['product_name'] }}</td>
                <td class="text-right">{{ number_format($item['purchase_price'], 2) }}</td>
                <td class="text-right">{{ number_format($item['sale_price'], 2) }}</td>
                <td class="text-right">{{ number_format($item['total_quantity'], 2) }}</td>
                <td class="text-right">{{ number_format($item['total_amount'], 2) }}</td>
                <td class="text-right">{{ number_format($totalProfit, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center" style="padding: 20px; color: #999;">No sales data found</td></tr>
            @endforelse
            @if($salesIndex > 0)
            <tr class="total-row">
                <td colspan="4" style="padding: 10px;"><strong>Total</strong></td>
                <td class="text-right" style="padding: 10px;"><strong>{{ number_format($data['sales_data']->sum('total_quantity') + $data['credit_sales_data']->sum('total_quantity'), 2) }}</strong></td>
                <td class="text-right" style="padding: 10px;"><strong>{{ number_format($data['totals']['total_sales'], 2) }}</strong></td>
                <td class="text-right" style="padding: 10px;"><strong>{{ number_format($data['sales_data']->sum(function($item) { return ($item['sale_price'] - $item['purchase_price']) * $item['total_quantity']; }) + $data['credit_sales_data']->sum(function($item) { return ($item['sale_price'] - $item['purchase_price']) * $item['total_quantity']; }), 2) }}</strong></td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="summary-box">
        <strong>In Stock: {{ number_format(collect($data['stock_data'])->sum('quantity'), 2) }}</strong>
    </div>

    <!-- Profit Summary Section -->
    <div class="section-title">Profit Summary</div>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Amount (৳)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total Sales</td>
                <td class="text-right">{{ number_format($data['totals']['total_sales'], 2) }}</td>
            </tr>
            <tr>
                <td>Total Purchase</td>
                <td class="text-right">({{ number_format($data['totals']['total_purchases'], 2) }})</td>
            </tr>
            <tr style="font-weight: bold; background-color: #e8f5e8;">
                <td><strong>Gross Profit</strong></td>
                <td class="text-right"><strong>{{ number_format($data['totals']['total_sales'] - $data['totals']['total_purchases'], 2) }}</strong></td>
            </tr>
            <tr>
                <td>Total Admin Expenses</td>
                <td class="text-right">({{ number_format($data['totals']['total_admin_expenses'], 2) }})</td>
            </tr>
            <tr style="font-weight: bold; background-color: #d4edda;">
                <td><strong>Net Profit</strong></td>
                <td class="text-right"><strong>{{ number_format(($data['totals']['total_sales'] - $data['totals']['total_purchases']) - $data['totals']['total_admin_expenses'], 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <!-- General Admin Expenses Section -->
    <div class="section-title">General Admin Expenses</div>
    <table>
        <thead>
            <tr>
                <th class="text-center">SL</th>
                <th>Expense Type</th>
                <th class="text-right">Amount (৳)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['admin_expenses'] as $index => $expense)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $expense['expense_type'] }}</td>
                <td class="text-right">{{ number_format($expense['total_amount'], 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="3" class="text-center" style="padding: 20px; color: #999;">No admin expenses found</td></tr>
            @endforelse
            @if(count($data['admin_expenses']) > 0)
            <tr class="total-row">
                <td colspan="2" style="padding: 10px;"><strong>Total Admin Expenses</strong></td>
                <td class="text-right" style="padding: 10px;"><strong>{{ number_format($data['totals']['total_admin_expenses'], 2) }}</strong></td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            Generated on: {{ date('Y-m-d H:i:s') }}
        </div>
        <div class="footer-right">
            Purchases: {{ count($data['purchase_data']) }} | Sales: {{ count($data['sales_data']) + count($data['credit_sales_data']) }} | Expenses: {{ count($data['admin_expenses']) }}
        </div>
    </div>
</body>
</html>