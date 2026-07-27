<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Ledger Summary</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
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
        .text-right { text-align: right; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .signature-section {
            margin-top: 60px;
            padding: 20px 0;
            border-top: 1px solid #ddd;
        }
        .signature-section table {
            border: none;
            margin: 0;
        }
        .signature-section tr {
            background: none !important;
        }
        .signature-section td {
            border: none !important;
            text-align: center;
            padding: 20px 10px;
            font-weight: bold;
            font-size: 12px;
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
        <div class="title-box">Customer Ledger Summary ({{ $startDate }} to {{ $endDate }})</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Customer Name</th>
                <th>Mobile</th>
                <th>Address</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
                <th class="text-right">Due</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ledgers as $ledger)
            <tr>
                <td>{{ $ledger->customer_name }}</td>
                <td>{{ $ledger->customer_mobile ?? '-' }}</td>
                <td>{{ $ledger->customer_address ?? '-' }}</td>
                <td class="text-right">{{ number_format($ledger->debit, 2) }}</td>
                <td class="text-right">{{ number_format($ledger->credit, 2) }}</td>
                <td class="text-right">{{ number_format($ledger->due, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center; padding: 20px; color: #999;">No records found</td>
            </tr>
            @endforelse
            @if(count($ledgers) > 0)
            <tr style="font-weight: bold; background-color: #d0d0d0;">
                <td colspan="3">Total:</td>
                <td class="text-right">{{ number_format($ledgers->sum('debit'), 2) }}</td>
                <td class="text-right">{{ number_format($ledgers->sum('credit'), 2) }}</td>
                <td class="text-right">{{ number_format($ledgers->sum('due'), 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

</body>
</html>
