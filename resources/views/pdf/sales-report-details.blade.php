<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Report (Details)</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm 10mm 12mm 10mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 0;
            color: #222;
        }
        .header {
            width: 100%;
            text-align: center;
            margin-bottom: 15px;
            position: relative;
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
        }
        .title-section {
            text-align: center;
            margin-bottom: 12px;
        }
        .title-box {
            border: 1px solid #333;
            display: inline-block;
            padding: 6px 18px;
            background-color: #f4f4f4;
            font-weight: bold;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .date-range {
            margin-top: 4px;
            font-size: 11px;
            color: #555;
            font-weight: normal;
        }
        .filter-meta {
            font-size: 10px;
            color: #666;
            margin-top: 3px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 14px;
        }
        thead {
            display: table-header-group;
        }
        tr {
            page-break-inside: avoid;
        }
        th, td {
            border: 1px solid #bbb;
            padding: 5px 6px;
            text-align: left;
            font-size: 10px;
        }
        th {
            background-color: #e9ecef;
            font-weight: bold;
            color: #111;
            text-transform: capitalize;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .customer-header-row {
            background-color: #dbeafe !important;
            font-weight: bold;
            font-size: 11px;
            color: #1e3a8a;
        }
        .customer-total-row {
            background-color: #f1f5f9 !important;
            font-weight: bold;
            font-size: 10.5px;
            color: #0f172a;
        }
        .grand-total-box {
            margin-top: 15px;
            padding: 10px 14px;
            background-color: #f8fafc;
            border: 1.5px solid #0f172a;
            page-break-inside: avoid;
        }
        .grand-total-box table {
            margin: 0;
            border: none;
        }
        .grand-total-box td {
            border: none;
            padding: 4px 6px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            font-size: 9px;
            font-weight: bold;
            border-radius: 3px;
        }
        .badge-cash { background-color: #dcfce7; color: #166534; }
        .badge-bank { background-color: #e0e7ff; color: #3730a3; }
        .badge-credit { background-color: #fee2e2; color: #991b1b; }
        .badge-mobile { background-color: #fef3c7; color: #92400e; }
        .footer {
            margin-top: 25px;
            padding-top: 10px;
            width: 100%;
            font-size: 9px;
            color: #777;
            text-align: right;
            page-break-inside: avoid;
        }
        .print-date {
            float: left;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <h2>{{ $companySetting->name ?? config('app.name', 'ERP System') }}</h2>
            @if(isset($companySetting->address) && $companySetting->address)
                <p>{{ $companySetting->address }}</p>
            @endif
            @if((isset($companySetting->phone) && $companySetting->phone) || (isset($companySetting->email) && $companySetting->email))
                <p>
                    @if(isset($companySetting->phone) && $companySetting->phone) Phone: {{ $companySetting->phone }} @endif
                    @if(isset($companySetting->email) && $companySetting->email) | Email: {{ $companySetting->email }} @endif
                </p>
            @endif
        </div>
    </div>

    <div class="title-section">
        <div class="title-box">
            Sales Report (Details)
            <div class="date-range">
                Date Between: {{ \Carbon\Carbon::parse($filters['start_date'])->format('d/m/Y') }} to {{ \Carbon\Carbon::parse($filters['end_date'])->format('d/m/Y') }}
            </div>
        </div>
        @if(!empty($filters['payment_type']) || !empty($filters['customer']) || !empty($filters['vehicle']))
            <div class="filter-meta">
                Filtered By:
                @if(!empty($filters['payment_type'])) Payment Type: <strong>{{ $filters['payment_type'] }}</strong> | @endif
                @if(!empty($filters['customer'])) Customer: <strong>{{ $filters['customer'] }}</strong> | @endif
                @if(!empty($filters['vehicle'])) Vehicle: <strong>{{ $filters['vehicle'] }}</strong> @endif
            </div>
        @endif
    </div>

    @if(empty($report['customers']))
        <table>
            <thead>
                <tr>
                    <th style="width: 70px;">Date</th>
                    <th style="width: 90px;">Vehicle</th>
                    <th style="width: 85px;">Invoice No</th>
                    <th style="width: 75px;">Memo No</th>
                    <th style="width: 80px;">Done By</th>
                    <th>Product</th>
                    <th style="width: 50px;">Unit</th>
                    <th class="text-right" style="width: 60px;">Quantity</th>
                    <th class="text-right" style="width: 65px;">Price</th>
                    <th class="text-right" style="width: 80px;">Total</th>
                    <th class="text-center" style="width: 70px;">Type</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="11" class="text-center" style="padding: 20px; color: #888;">No sales records found for the selected criteria.</td>
                </tr>
            </tbody>
        </table>
    @else
        @foreach($report['customers'] as $customerGroup)
            <table>
                <thead>
                    <tr class="customer-header-row">
                        <td colspan="11" style="padding: 6px 8px; font-size: 11px; font-weight: bold;">
                            Customer: {{ $customerGroup['customer_name'] }}
                        </td>
                    </tr>
                    <tr>
                        <th style="width: 70px;">Date</th>
                        <th style="width: 95px;">Vehicle</th>
                        <th style="width: 85px;">Invoice No</th>
                        <th style="width: 75px;">Memo No</th>
                        <th style="width: 85px;">Done By</th>
                        <th>Product</th>
                        <th style="width: 45px;">Unit</th>
                        <th class="text-right" style="width: 60px;">Quantity</th>
                        <th class="text-right" style="width: 65px;">Price</th>
                        <th class="text-right" style="width: 80px;">Total</th>
                        <th class="text-center" style="width: 70px;">Type</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customerGroup['sales'] as $sale)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($sale->date)->format('d/m/Y') }}</td>
                            <td>{{ $sale->vehicle_no ?? 'N/A' }}</td>
                            <td>{{ $sale->invoice_no }}</td>
                            <td>{{ $sale->memo_no ?? 'N/A' }}</td>
                            <td>{{ $sale->done_by ?? 'N/A' }}</td>
                            <td>{{ $sale->product_name }}</td>
                            <td>{{ $sale->unit_name }}</td>
                            <td class="text-right">{{ number_format($sale->quantity, 2) }}</td>
                            <td class="text-right">{{ number_format($sale->unit_price, 2) }}</td>
                            <td class="text-right">{{ number_format($sale->total_amount, 2) }}</td>
                            <td class="text-center">
                                @if($sale->type === 'Cash')
                                    <span class="badge badge-cash">Cash</span>
                                @elseif($sale->type === 'Bank')
                                    <span class="badge badge-bank">Bank</span>
                                @elseif($sale->type === 'Mobile Bank')
                                    <span class="badge badge-mobile">Mobile Bank</span>
                                @else
                                    <span class="badge badge-credit">Credit</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    <tr class="customer-total-row">
                        <td colspan="7" class="text-right" style="font-weight: bold;">
                            Total From {{ $customerGroup['customer_name'] }} :
                        </td>
                        <td class="text-right" style="font-weight: bold;">
                            {{ number_format($customerGroup['total_quantity'], 2) }}
                        </td>
                        <td></td>
                        <td class="text-right" style="font-weight: bold;">
                            {{ number_format($customerGroup['total_amount'], 2) }}
                        </td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        @endforeach

        <div class="grand-total-box">
            <table>
                <tr>
                    <td style="width: 50%; color: #1e293b;">
                        Total Customers: {{ $report['total_customers'] }} | Total Invoices: {{ $report['total_invoices'] }} | Total Items: {{ $report['total_rows'] }}
                    </td>
                    <td class="text-right" style="font-size: 13px; color: #0f172a;">
                        Grand Total Quantity: <span style="font-weight: bold;">{{ number_format($report['grand_total_quantity'], 2) }}</span>
                        &nbsp;&nbsp;|&nbsp;&nbsp;
                        Grand Total Sales: <span style="font-weight: bold; color: #059669;">{{ number_format($report['grand_total_amount'], 2) }}</span>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    <div class="footer">
        <span class="print-date">Generated on: {{ now()->format('d/m/Y h:i A') }}</span>
        <span>Page <span class="page-num"></span></span>
    </div>
</body>
</html>
