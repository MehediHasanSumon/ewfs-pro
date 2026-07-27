<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Sale Invoice</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .company-details {
            font-size: 14px;
            margin-bottom: 10px;
        }

        .title-box {
            background: #f8f9fa;
            border: 2px solid #333;
            padding: 10px;
            text-align: center;
            margin: 20px 0;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
        }

        .batch-info {
            margin: 20px 0;
        }

        .batch-info table {
            width: 100%;
        }

        .batch-info td {
            padding: 5px 0;
        }

        .sales-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .sales-table th,
        .sales-table td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
        }

        .sales-table th {
            background: #f8f9fa;
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .total-section {
            margin-top: 30px;
        }

        .grand-total {
            font-size: 16px;
            font-weight: bold;
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="company-name">{{ $companySetting->company_name ?? 'Company Name' }}</div>
        <div class="company-details">{{ $companySetting->company_address ?? 'Company Address' }}</div>
        <div class="company-details">Mobile: {{ $companySetting->company_mobile ?? 'N/A' }} | Email: {{ $companySetting->company_email ?? 'N/A' }}</div>
    </div>

    <div class="title-box">
        <div class="title">SALE BATCH INVOICE</div>
    </div>

    <div class="batch-info">
        <table>
            <tr>
                <td><strong>Batch Code:</strong></td>
                <td>{{ $batchCode }}</td>
                <td><strong>Date:</strong></td>
                <td>{{ $sales->first()->sale_date ?? date('Y-m-d') }}</td>
            </tr>
            <tr>
                <td><strong>Shift:</strong></td>
                <td>{{ $sales->first()->shift->name ?? 'N/A' }}</td>
                <td><strong>Total Items:</strong></td>
                <td>{{ $sales->count() }}</td>
            </tr>
        </table>
    </div>

    <table class="sales-table">
        <thead>
            <tr>
                <th>SL</th>
                <th>Invoice No</th>
                <th>Customer</th>
                <th>Mobile</th>
                <th>Vehicle</th>
                <th>Product</th>
                <th>Quantity</th>
                <th>Amount</th>
                <th>Paid</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sales as $index => $sale)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $sale->invoice_no }}</td>
                <td>{{ $sale->customer }}</td>
                <td>{{ $sale->mobile_number ?? '-' }}</td>
                <td>{{ $sale->vehicle_no }}</td>
                <td>{{ $sale->product->product_name ?? 'N/A' }}</td>
                <td class="text-right">{{ number_format($sale->quantity, 2) }}</td>
                <td class="text-right">{{ number_format($sale->total_amount, 2) }}</td>
                <td class="text-right">{{ number_format($sale->paid_amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background: #f8f9fa; font-weight: bold;">
                <td colspan="6">Grand Total:</td>
                <td class="text-right">{{ number_format($sales->sum('quantity'), 2) }}</td>
                <td class="text-right">{{ number_format($sales->sum('total_amount'), 2) }}</td>
                <td class="text-right">{{ number_format($sales->sum('paid_amount'), 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="total-section">
        <div class="grand-total">Grand Total: {{ number_format($sales->sum('total_amount'), 2) }}</div>
        <div>In words: {{ ucwords((new NumberFormatter('en', NumberFormatter::SPELLOUT))->format(floor($sales->sum('total_amount')))) }} Taka Only</div>
    </div>

    <div class="footer">
        <p>Generated on {{ date('d/m/Y H:i:s') }}</p>
    </div>
</body>

</html>