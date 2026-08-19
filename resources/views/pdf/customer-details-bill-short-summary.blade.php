<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bill (ShortSummary)</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 10mm 15mm 10mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .title-section {
            text-align: center;
            margin: 10px 0 12px 0;
        }
        .title-box {
            border: 1.5px solid #000;
            display: inline-block;
            padding: 4px 22px;
            background-color: #d1d5db;
            font-weight: bold;
            font-size: 13px;
        }
        .period-text {
            font-weight: bold;
            font-size: 11px;
            margin-top: 5px;
        }
        .customer-section {
            width: 100%;
            margin-bottom: 8px;
        }
        .customer-table {
            width: 100%;
            border: none;
            border-collapse: collapse;
        }
        .customer-table td {
            border: none;
            padding: 1px 0;
            font-size: 11px;
            vertical-align: top;
        }
        .intro-text {
            margin: 8px 0 10px 0;
            font-size: 11px;
            line-height: 1.35;
        }
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 12px;
        }
        table.report-table th, table.report-table td {
            border: 1px solid #000;
            padding: 8px 6px;
            text-align: left;
        }
        table.report-table th {
            font-weight: bold;
            font-size: 11px;
            color: #000;
        }
        table.report-table td {
            font-size: 11px;
            color: #333;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bottom-section {
            width: 100%;
            margin-top: 10px;
        }
        .bottom-table {
            width: 100%;
            border: none;
            border-collapse: collapse;
        }
        .bottom-table td {
            border: none;
            vertical-align: top;
            padding: 0;
        }
    </style>
</head>
<body>
    @include('pdf.components.watermark')
    @include('pdf.components.header')

    @if(!empty($report))
    <!-- Title Section -->
    <div class="title-section">
        <div class="title-box">Bill (ShortSummary)</div>
        <div class="period-text">Period : {{ $report['period']['formatted'] }}</div>
    </div>

    <!-- Customer Details Section -->
    <div class="customer-section">
        <div style="font-weight: bold; margin-bottom: 2px;">To,</div>
        <table class="customer-table" style="width: 100%;">
            <tr>
                <td style="width: 58px; font-weight: bold;">Name :</td>
                <td style="font-weight: bold;">{{ $report['customer']['name'] }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold; vertical-align: top;">Address :</td>
                <td style="font-weight: bold; vertical-align: top;">{{ $report['customer']['address'] ?? '-' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Phone :</td>
                <td style="font-weight: bold;">{{ $report['customer']['mobile'] ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <!-- Introduction Text -->
    <div class="intro-text">
        <div style="font-weight: bold;">Dear Sir,</div>
        <div style="font-weight: bold; margin-top: 2px;">
            For your kind information details bill given below your consideration and early payment.
        </div>
    </div>

    <!-- Standard ERP Table -->
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 40px;" class="text-center">SN</th>
                <th>Product Name</th>
                <th style="width: 95px;" class="text-right">Sales Price</th>
                <th style="width: 95px;" class="text-right">Quantity</th>
                <th style="width: 110px;" class="text-right">Amount</th>
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
            <tr style="font-weight: bold;">
                <td colspan="3">Total Slip Quantity : {{ $report['total_slip_quantity'] }}</td>
                <td class="text-right">{{ number_format($report['total_quantity'], 2) }}</td>
                <td class="text-right">{{ number_format($report['total'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Bottom Summary, Totals & Signature Section -->
    <div class="bottom-section">
        <table class="bottom-table">
            <tr>
                <!-- Left Column: In Words & Note -->
                <td style="width: 52%; padding-right: 15px;">
                    <div style="font-size: 11px; line-height: 1.4; font-weight: bold;">
                        <span>In Words : </span> {{ $report['amount_in_words'] }}
                    </div>

                    <div style="margin-top: 30px; font-size: 10px; line-height: 1.3;">
                        <span style="font-weight: bold;">Note :</span> Supply on Credit will be stopped without notice if the bill is not paid within 15 days from date issue.
                    </div>
                </td>

                <!-- Right Column: Calculation Totals & Signature Block -->
                <td style="width: 48%;">
                    <table style="width: 100%; border: none; border-collapse: collapse;">
                        <tr>
                            <td style="text-align: right; padding: 2px 10px; font-size: 11px; font-weight: bold; width: 50%;">Total :</td>
                            <td style="text-align: right; padding: 2px 0; font-size: 11px; font-weight: bold; width: 50%;">{{ number_format($report['total'], 2) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: right; padding: 2px 10px; font-size: 11px; font-weight: bold;">VAT {{ number_format($report['vat_percent'], 2) }} %</td>
                            <td style="text-align: right; padding: 2px 0; font-size: 11px; font-weight: bold;">{{ number_format($report['vat_amount'], 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding: 0;">
                                <div style="border-top: 1.5px solid #000; margin: 3px 0;"></div>
                            </td>
                        </tr>
                        <tr>
                            <td style="text-align: right; padding: 2px 10px; font-size: 11px; font-weight: bold;">Grand Total :</td>
                            <td style="text-align: right; padding: 2px 0; font-size: 11px; font-weight: bold;">{{ number_format($report['grand_total'], 2) }}</td>
                        </tr>
                    </table>

                    <!-- Signature Box -->
                    <div style="margin-top: 30px; text-align: center;">
                        <div style="border-top: 1.5px solid #000; width: 85%; margin: 0 auto; padding-top: 4px; font-size: 11px; font-weight: bold;">
                            Signature
                        </div>
                        <div style="font-size: 11px; font-weight: bold; margin-top: 3px;">
                            For {{ $companySetting->company_name ?? 'East West Filling Station' }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    @else
    <p style="text-align: center; padding: 30px; color: #999;">No records found</p>
    @endif

    @include('pdf.components.footer')
</body>
</html>
