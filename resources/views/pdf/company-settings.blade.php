<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Company Settings List</title>
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

        th,
        td {
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

        .text-center {
            text-align: center;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
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

        .footer-left {
            text-align: left;
        }

        .footer-right {
            text-align: right;
        }

        @media print {
            .footer {
                position: fixed;
                bottom: 0;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="logo">
            @php
                $headerCompany = $companySettings->first() ?? null;
            @endphp
            @if($headerCompany && $headerCompany->company_logo)
            <img src="{{ public_path('storage/' . $headerCompany->company_logo) }}" alt="Company Logo">
            @endif
        </div>
        <div class="company-info">
            @if($headerCompany)
            <h2>{{ $headerCompany->company_name ?? 'Company Management System' }}</h2>
            @if($headerCompany->company_address)
            <p>{{ $headerCompany->company_address }}</p>
            @endif
            @if($headerCompany->company_mobile || $headerCompany->company_email)
            <p>
                @if($headerCompany->company_email)
                {{ $headerCompany->company_email }}
                @endif
                @if($headerCompany->company_mobile && $headerCompany->company_email) | @endif
                @if($headerCompany->company_mobile)
                {{ $headerCompany->company_mobile }}
                @endif
            </p>
            @endif
            @else
            <h2>Company Management System</h2>
            <p>System Generated Report</p>
            @endif
        </div>
    </div>

    <div class="title-section">
        <div class="title-box">Company Settings List</div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 50px;">SL</th>
                <th>Company Name</th>
                <th>Proprietor</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Address</th>
            </tr>
        </thead>
        <tbody>
            @forelse($companySettings as $index => $setting)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $setting->company_name }}</td>
                <td>{{ $setting->proprietor_name ?? 'N/A' }}</td>
                <td>{{ $setting->company_email ?? 'N/A' }}</td>
                <td>{{ $setting->company_phone ?? $setting->company_mobile ?? 'N/A' }}</td>
                <td>{{ $setting->company_address ?? 'N/A' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center" style="padding: 20px; color: #999;">No company settings found</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            Generated on: {{ date('Y-m-d H:i:s') }}
        </div>
        <div class="footer-right">
            Total Records: {{ count($companySettings) }}
        </div>
    </div>
</body>

</html>