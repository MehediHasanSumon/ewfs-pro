<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMS Templates Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
            color: #222;
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
        
        .center {
            text-align: center;
        }
        
        .message-cell {
            max-width: 250px;
            word-wrap: break-word;
        }
        
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10px 20px;
            border-top: 1px solid #000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
            color: #666;
        }
        
        .footer-left { text-align: left; }
        .footer-right { text-align: right; }
    </style>
</head>
<body>
    @include('pdf.components.watermark')
    @include('pdf.components.header')
    
    <div class="title-section">
        <div class="title-box">SMS Templates Report</div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th class="center" style="width: 8%">SL</th>
                <th style="width: 20%">Title</th>
                <th style="width: 15%">Type</th>
                <th style="width: 40%">Message</th>
                <th class="center" style="width: 10%">Status</th>
                <th class="center" style="width: 12%">Created</th>
            </tr>
        </thead>
        <tbody>
            @forelse($smsTemplates as $index => $smsTemplate)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $smsTemplate->title }}</td>
                    <td>{{ $smsTemplate->type }}</td>
                    <td class="message-cell">{{ $smsTemplate->message }}</td>
                    <td class="center">{{ $smsTemplate->status ? 'Active' : 'Inactive' }}</td>
                    <td class="center">{{ $smsTemplate->created_at->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="center" style="padding: 20px; color: #999;">
                        No SMS templates found
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    
    @include('pdf.components.footer', ['totalRecords' => count($smsTemplates)])
</body>
</html>