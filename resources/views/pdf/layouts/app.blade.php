<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Report')</title>
    <style>
        @page {
            size: @yield('pageSize', 'A4 portrait');
            margin: @yield('pageMargin', '15mm 10mm 15mm 10mm');
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
            color: #222;
        }
        .header {
            padding: 10px 20px 20px 20px;
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
        .total-row {
            font-weight: bold;
        }
        .total-section {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #000;
        }
        .total-section p {
            margin: 5px 0;
            font-size: 13px;
        }
        .total-section .amount {
            font-weight: bold;
            font-size: 14px;
        }
        .total-section .words {
            font-style: italic;
            font-size: 12px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10px 20px;
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
        @yield('extraCss')
    </style>
</head>
<body>
    @include('pdf.components.watermark')

    @sectionMissing('customHeader')
        @include('pdf.components.header', [
            'title' => $__env->yieldContent('reportTitle', $title ?? null),
            'subtitle' => $__env->yieldContent('reportSubtitle', $subtitle ?? null),
        ])
    @else
        @yield('customHeader')
    @endif

    <div class="pdf-content">
        @yield('content')
    </div>

    @sectionMissing('customFooter')
        @include('pdf.components.footer', [
            'totalRecords' => $totalRecords ?? null,
            'leftText' => $leftText ?? null,
            'rightText' => $rightText ?? null,
        ])
    @else
        @yield('customFooter')
    @endif
</body>
</html>
