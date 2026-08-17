<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Statement Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding-bottom: 50px;
            color: #333;
        }
        .header {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            width: 100%;
            position: relative;
        }
        .header .logo {
            width: 100px;
            flex-shrink: 0;
        }
        .header .logo img {
            height: 65px;
            width: auto;
            display: block;
        }
        .header .company-info {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
            width: auto;
            margin-top: -65px;
        }
        .header .company-info h2 {
            margin: 0 0 4px 0;
            font-size: 18px;
            font-weight: bold;
            color: #000;
        }
        .header .company-info p {
            margin: 2px 0;
            font-size: 11px;
            color: #444;
            line-height: 1.3;
        }
        .title-section {
            text-align: center;
            margin: 10px 0 15px 0;
        }
        .title-box {
            border: 1px solid #1f2937;
            display: inline-block;
            padding: 6px 18px;
            background-color: #f3f4f6;
            font-weight: bold;
            font-size: 13px;
            color: #111;
        }
        .section-title {
            margin: 15px 0 6px 0;
            font-size: 12px;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
            border-bottom: 1px solid #9ca3af;
            padding-bottom: 3px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 12px;
            font-size: 10px;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 5px 6px;
            text-align: left;
        }
        th {
            background-color: #e5e7eb;
            font-weight: bold;
            font-size: 10px;
            color: #111;
        }
        td {
            font-size: 9.5px;
            color: #374151;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        tr:nth-child(even) { background-color: #f9fafb; }
        .total-row {
            font-weight: bold;
            background-color: #e5e7eb !important;
            color: #000;
        }
        .kpi-grid {
            width: 100%;
            margin-bottom: 15px;
            border: 1px solid #9ca3af;
            background-color: #f9fafb;
        }
        .kpi-grid td {
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
        }
        .kpi-label {
            font-size: 9px;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .kpi-val {
            font-size: 13px;
            font-weight: bold;
            color: #111827;
        }
        .recon-table {
            width: 100%;
            border: 1px solid #374151;
            margin-top: 20px;
            page-break-inside: avoid;
        }
        .recon-table td {
            padding: 10px;
            vertical-align: top;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10px 20px;
            border-top: 1px solid #d1d5db;
            background-color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 9px;
            color: #6b7280;
        }
        .footer-left { text-align: left; }
        .footer-right { text-align: right; }
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
                @if($companySetting->company_email){{ $companySetting->company_email }}@endif
                @if($companySetting->company_mobile && $companySetting->company_email) | @endif
                @if($companySetting->company_mobile){{ $companySetting->company_mobile }}@endif
            </p>
            @endif
            @else
            <h2>East West Filling Station</h2>
            <p>Dhaka, Bangladesh</p>
            @endif
        </div>
    </div>

    <div class="title-section">
        <div class="title-box">Daily Statement Report ({{ $startDate }} to {{ $endDate }})</div>
    </div>

    <!-- Executive KPI Overview -->
    <table class="kpi-grid">
        <tr>
            <td style="width: 25%;">
                <div class="kpi-label">Total Product Sales</div>
                <div class="kpi-val">{{ number_format($summary['total_sales'] ?? 0, 2) }}</div>
            </td>
            <td style="width: 25%;">
                <div class="kpi-label">Cash Sales</div>
                <div class="kpi-val">{{ number_format($summary['cash_sales'] ?? 0, 2) }}</div>
            </td>
            <td style="width: 25%;">
                <div class="kpi-label">Credit Sales</div>
                <div class="kpi-val">{{ number_format($summary['credit_sales'] ?? 0, 2) }}</div>
            </td>
            <td style="width: 25%;">
                <div class="kpi-label">Bank Sales</div>
                <div class="kpi-val">{{ number_format($summary['bank_sales'] ?? 0, 2) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="kpi-label">Opening Cash</div>
                <div class="kpi-val">{{ number_format($cashFlow['opening_balance'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Total Cash Inflow</div>
                <div class="kpi-val" style="color: #059669;">+{{ number_format($cashFlow['total_receipts'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Total Cash Outflow</div>
                <div class="kpi-val" style="color: #dc2626;">-{{ number_format($cashFlow['total_payments'] ?? 0, 2) }}</div>
            </td>
            <td style="background-color: #fef3c7;">
                <div class="kpi-label" style="font-weight: bold; color: #92400e;">CLOSING CASH IN HAND</div>
                <div class="kpi-val" style="color: #92400e; font-size: 14px;">{{ number_format($cashFlow['closing_balance'] ?? 0, 2) }}</div>
            </td>
        </tr>
    </table>

    <!-- 1. Product Wise Sales Summary -->
    <div class="section-title">1. Sales Summary (Product Wise)</div>
    <table>
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Unit</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Cash Qty</th>
                <th class="text-right">Bank Qty</th>
                <th class="text-right">Credit Qty</th>
                <th class="text-right">Total Qty</th>
                <th class="text-right">Cash (Tk)</th>
                <th class="text-right">Bank (Tk)</th>
                <th class="text-right">Credit (Tk)</th>
                <th class="text-right">Total Sales (Tk)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($allProductSales as $sale)
            <tr>
                <td>{{ $sale['product_name'] }}</td>
                <td>{{ $sale['unit_name'] }}</td>
                <td class="text-right">{{ number_format($sale['unit_price'] ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($sale['cash_quantity'] ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($sale['bank_quantity'] ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($sale['credit_quantity'] ?? 0, 2) }}</td>
                <td class="text-right" style="font-weight: bold;">{{ number_format($sale['total_quantity'] ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($sale['cash_amount'] ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($sale['bank_amount'] ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($sale['credit_amount'] ?? 0, 2) }}</td>
                <td class="text-right" style="font-weight: bold;">{{ number_format($sale['total_amount'] ?? 0, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="11" class="text-center">No sales records found</td></tr>
            @endforelse
            @if(count($allProductSales) > 0)
            <tr class="total-row">
                <td colspan="3">Total:</td>
                <td class="text-right">{{ number_format(collect($allProductSales)->sum('cash_quantity'), 2) }}</td>
                <td class="text-right">{{ number_format(collect($allProductSales)->sum('bank_quantity'), 2) }}</td>
                <td class="text-right">{{ number_format(collect($allProductSales)->sum('credit_quantity'), 2) }}</td>
                <td class="text-right">{{ number_format(collect($allProductSales)->sum('total_quantity'), 2) }}</td>
                <td class="text-right">{{ number_format(collect($allProductSales)->sum('cash_amount'), 2) }}</td>
                <td class="text-right">{{ number_format(collect($allProductSales)->sum('bank_amount'), 2) }}</td>
                <td class="text-right">{{ number_format(collect($allProductSales)->sum('credit_amount'), 2) }}</td>
                <td class="text-right">{{ number_format(collect($allProductSales)->sum('total_amount'), 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <!-- 2. Customer Wise Credit Sales Detail -->
    <div class="section-title">2. Customer Wise Sales Detail (Credit)</div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Customer Name</th>
                <th>Vehicle Number</th>
                <th>Product Name</th>
                <th>Unit</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Total Amount (Tk)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($customerWiseSales as $sale)
            <tr>
                <td>{{ $sale->sale_date ?? '-' }}</td>
                <td>{{ $sale->customer_name }}</td>
                <td>{{ $sale->vehicle_no ?? '-' }}</td>
                <td>{{ $sale->product_name }}</td>
                <td>{{ $sale->unit_name }}</td>
                <td class="text-right">{{ number_format($sale->unit_price, 2) }}</td>
                <td class="text-right">{{ number_format($sale->quantity, 2) }}</td>
                <td class="text-right">{{ number_format($sale->total_amount, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center">No credit sales records found</td></tr>
            @endforelse
            @if(count($customerWiseSales) > 0)
            <tr class="total-row">
                <td colspan="6">Total:</td>
                <td class="text-right">{{ number_format($customerWiseSales->sum('quantity'), 2) }}</td>
                <td class="text-right">{{ number_format($customerWiseSales->sum('total_amount'), 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <!-- 3. Cash Receipts Summary -->
    <div class="section-title">3. Cash Receipts Summary (Inflows)</div>
    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 35px;">Sl</th>
                <th>Source / Purpose</th>
                <th>Category</th>
                <th>Payment Type</th>
                <th>Description</th>
                <th class="text-right" style="width: 90px;">Amount (Tk)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cashReceived as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item->account_name }}</td>
                <td>{{ $item->category ?? 'Receipt' }}</td>
                <td>{{ strtoupper($item->payment_type ?? 'CASH') }}</td>
                <td>{{ $item->description ?? '-' }}</td>
                <td class="text-right">{{ number_format($item->amount, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center">No cash receipts found</td></tr>
            @endforelse
            @if(count($cashReceived) > 0)
            <tr class="total-row">
                <td colspan="5">Total Cash Inflow:</td>
                <td class="text-right">{{ number_format($cashReceived->sum('amount'), 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <!-- 4. Cash Payments Summary -->
    <div class="section-title">4. Cash Payments Summary (Outflows)</div>
    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 35px;">Sl</th>
                <th>Recipient / Purpose</th>
                <th>Category</th>
                <th>Payment Type</th>
                <th>Description</th>
                <th class="text-right" style="width: 90px;">Amount (Tk)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cashPayment as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item->account_name }}</td>
                <td>{{ $item->category ?? 'Expense' }}</td>
                <td>{{ strtoupper($item->payment_type ?? 'CASH') }}</td>
                <td>{{ $item->description ?? '-' }}</td>
                <td class="text-right">{{ number_format($item->amount, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center">No cash payments found</td></tr>
            @endforelse
            @if(count($cashPayment) > 0)
            <tr class="total-row">
                <td colspan="5">Total Cash Outflow:</td>
                <td class="text-right">{{ number_format($cashPayment->sum('amount'), 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <!-- 5. Reconciliation & Settlement Box -->
    <table class="recon-table">
        <tr>
            <td style="width: 45%; border-right: 1px solid #374151; padding: 60px 20px 20px 20px; vertical-align: bottom;">
                <div style="display: flex; justify-content: space-between;">
                    <div style="border-top: 1px solid #000; width: 140px; padding-top: 5px; text-align: center; font-size: 10px;">
                        Prepared By
                    </div>
                    <div style="border-top: 1px solid #000; width: 140px; padding-top: 5px; text-align: center; font-size: 10px; margin-top: 40px;">
                        Manager / Authorized Signature
                    </div>
                </div>
            </td>
            <td style="width: 55%; padding: 10px 15px;">
                <table style="width: 100%; border: none; margin: 0; font-size: 10.5px;">
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="border: none; padding: 4px; color: #4b5563;">Opening Cash Balance:</td>
                        <td style="border: none; padding: 4px; text-align: right; font-weight: bold;">{{ number_format($cashFlow['opening_balance'] ?? 0, 2) }}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="border: none; padding: 4px; color: #059669;">(+) Total Cash Inflow (Receipts & Sales):</td>
                        <td style="border: none; padding: 4px; text-align: right; color: #059669; font-weight: bold;">+{{ number_format($cashFlow['total_receipts'] ?? 0, 2) }}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="border: none; padding: 4px; color: #dc2626;">(-) Total Cash Outflow (Expenses & Payments):</td>
                        <td style="border: none; padding: 4px; text-align: right; color: #dc2626; font-weight: bold;">-{{ number_format($cashFlow['total_payments'] ?? 0, 2) }}</td>
                    </tr>
                    <tr style="border-bottom: 2px solid #111827; background-color: #fef3c7;">
                        <td style="border: none; padding: 6px; font-weight: bold; font-size: 12px; color: #92400e;">CASH IN HAND (Closing Cash):</td>
                        <td style="border: none; padding: 6px; text-align: right; font-weight: bold; font-size: 12px; color: #92400e;">{{ number_format($cashFlow['closing_balance'] ?? 0, 2) }}</td>
                    </tr>
                    @if(isset($bankFlow) && (($bankFlow['opening_balance'] ?? 0) != 0 || ($bankFlow['total_receipts'] ?? 0) != 0 || ($bankFlow['total_payments'] ?? 0) != 0))
                    <tr style="border-bottom: 1px solid #e5e7eb; padding-top: 8px;">
                        <td style="border: none; padding: 4px; color: #4b5563;">Opening Bank Balance:</td>
                        <td style="border: none; padding: 4px; text-align: right;">{{ number_format($bankFlow['opening_balance'] ?? 0, 2) }}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="border: none; padding: 4px; color: #059669;">(+) Total Bank Inflow:</td>
                        <td style="border: none; padding: 4px; text-align: right; color: #059669;">+{{ number_format($bankFlow['total_receipts'] ?? 0, 2) }}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="border: none; padding: 4px; color: #dc2626;">(-) Total Bank Outflow:</td>
                        <td style="border: none; padding: 4px; text-align: right; color: #dc2626;">-{{ number_format($bankFlow['total_payments'] ?? 0, 2) }}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #111827; background-color: #eff6ff;">
                        <td style="border: none; padding: 5px; font-weight: bold; color: #1e40af;">CLOSING BANK BALANCE:</td>
                        <td style="border: none; padding: 5px; text-align: right; font-weight: bold; color: #1e40af;">{{ number_format($bankFlow['closing_balance'] ?? 0, 2) }}</td>
                    </tr>
                    @endif
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
