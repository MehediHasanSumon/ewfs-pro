<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Details Bill (Short Summary)</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 10mm 15mm 10mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 0;
        }
        .title-section {
            text-align: center;
            margin: 12px 0 15px 0;
        }
        .title-box {
            border: 1px solid #000;
            display: inline-block;
            padding: 6px 20px;
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 13px;
        }
        .customer-info {
            margin-bottom: 12px;
        }
        .customer-info table {
            width: 100%;
            border: none;
            margin: 0;
        }
        .customer-info td {
            border: none;
            padding: 2px 0;
            font-size: 11px;
        }
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.report-table th, table.report-table td {
            border: 1px solid #000;
            padding: 6px 5px;
            text-align: left;
        }
        table.report-table th {
            font-weight: bold;
            font-size: 11px;
            color: #000;
            background-color: #f9f9f9;
        }
        table.report-table td {
            font-size: 11px;
            color: #333;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .signature-section {
            margin-top: 50px;
            padding-top: 15px;
        }
        .signature-section table {
            width: 100%;
            border: none;
            margin: 0;
        }
        .signature-section tr {
            background: none !important;
        }
        .signature-section td {
            border: none !important;
            border-top: 1px solid #444 !important;
            text-align: center;
            padding: 10px 5px;
            font-weight: bold;
            font-size: 10px;
            width: 16.66%;
        }
    </style>
</head>
<body>
    @include('pdf.components.watermark')
    @include('pdf.components.header')

    @if(!empty($report))
    <div class="customer-info">
        <table>
            <tr>
                <td style="width: 60%;"><strong>To,</strong></td>
                <td style="width: 40%;" class="text-right"><strong>Period :</strong> {{ $report['period']['formatted'] }}</td>
            </tr>
            <tr>
                <td><strong>Name :</strong> {{ $report['customer']['name'] }}</td>
                @if(!empty($report['selected_vehicle']))
                    <td class="text-right"><strong>Vehicle :</strong> {{ $report['selected_vehicle']['vehicle_number'] }}</td>
                @else
                    <td></td>
                @endif
            </tr>
            <tr>
                <td><strong>Phone :</strong> {{ $report['customer']['mobile'] ?? '-' }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="2"><strong>Address :</strong> {{ $report['customer']['address'] ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="title-section">
        <div class="title-box">Bill (Short Summary)</div>
    </div>

    <div style="margin-bottom: 10px; font-size: 11px; line-height: 1.4;">
        <p style="margin: 0 0 3px 0; font-weight: bold;">Dear Sir,</p>
        <p style="margin: 0;">For your kind information details bill given below your consideration and early payment.</p>
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 35px;" class="text-center">SN</th>
                <th>Product Name</th>
                <th style="width: 85px;" class="text-right">Sales Price</th>
                <th style="width: 85px;" class="text-right">Quantity</th>
                <th style="width: 95px;" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['product_summary'] as $item)
            <tr>
                <td class="text-center">{{ $item['sn'] }}</td>
                <td>{{ $item['product_name'] }}</td>
                <td class="text-right">{{ number_format($item['price'], 2) }}</td>
                <td class="text-right">{{ number_format($item['quantity'], 2) }}</td>
                <td class="text-right">{{ number_format($item['total_amount'], 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center" style="padding: 15px; color: #999;">No records found</td>
            </tr>
            @endforelse
            <tr style="font-weight: bold; background-color: #f9f9f9;">
                <td colspan="3" class="text-right">Total:</td>
                <td class="text-right">{{ number_format($report['total_quantity'], 2) }}</td>
                <td class="text-right">{{ number_format($report['total'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 15px; width: 100%;">
        <table style="width: 100%; border: none; margin: 0; padding: 0;">
            <tr style="background: none !important;">
                <td style="border: none !important; width: 55%; vertical-align: top; padding: 0;">
                    <p style="margin: 3px 0; font-size: 11px;"><strong>Total Slip Quantity :</strong> {{ $report['total_slip_quantity'] }}</p>
                    <p style="margin: 3px 0; font-size: 11px;"><strong>In Words :</strong> <span style="font-style: italic;">{{ $report['amount_in_words'] }}</span></p>
                    <p style="margin: 10px 0 0 0; font-size: 10px; color: #555;"><strong>Note :</strong> Supply on Credit will be stopped without notice if the bill is not paid within 15 days from date issue.</p>
                </td>
                <td style="border: none !important; width: 45%; vertical-align: top; text-align: right; padding: 0;">
                    <p style="margin: 3px 0; font-size: 11px;"><strong>Total :</strong> {{ number_format($report['total'], 2) }}</p>
                    <p style="margin: 3px 0; font-size: 11px;"><strong>VAT {{ number_format($report['vat_percent'], 2) }} % :</strong> {{ number_format($report['vat_amount'], 2) }}</p>
                    <p style="margin: 5px 0 0 0; font-size: 12px; font-weight: bold;"><strong>Grand Total :</strong> {{ number_format($report['grand_total'], 2) }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="signature-section">
        <table>
            <tr>
                <td>Prepared By</td>
                <td>Checked By</td>
                <td>Chief Accountant</td>
                <td>Manager</td>
                <td>Director</td>
                <td>Managing Director</td>
            </tr>
        </table>
    </div>
    @else
    <p style="text-align: center; padding: 30px; color: #999;">No records found</p>
    @endif

    @include('pdf.components.footer')
</body>
</html>
