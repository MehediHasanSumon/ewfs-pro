<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Voucher Transaction Types</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #222; }
        .header { text-align: center; margin-bottom: 18px; }
        .header h2 { margin: 0 0 5px; font-size: 18px; }
        .header p { margin: 2px 0; color: #555; }
        .title { text-align: center; font-size: 14px; font-weight: bold; margin: 16px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cfcfcf; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        .center { text-align: center; }
        .footer { margin-top: 16px; color: #666; font-size: 9px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $companySetting?->company_name ?? 'Voucher Transaction Types' }}</h2>
        @if($companySetting?->company_address)
            <p>{{ $companySetting->company_address }}</p>
        @endif
    </div>

    <div class="title">Voucher Transaction Types</div>

    <table>
        <thead>
            <tr>
                <th class="center">SL</th>
                <th>Category</th>
                <th>Code</th>
                <th>Name</th>
                <th>Voucher Type</th>
                <th>Description</th>
                <th class="center">Sort Order</th>
                <th>Status</th>
                <th>System</th>
            </tr>
        </thead>
        <tbody>
            @forelse($voucherTransactionTypes as $index => $transactionType)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $transactionType->voucherCategory?->name ?? '-' }}</td>
                    <td>{{ $transactionType->code }}</td>
                    <td>{{ $transactionType->name }}</td>
                    <td>{{ ucfirst($transactionType->voucher_type) }}</td>
                    <td>{{ $transactionType->description ?: '-' }}</td>
                    <td class="center">{{ $transactionType->sort_order }}</td>
                    <td>{{ $transactionType->status ? 'Active' : 'Inactive' }}</td>
                    <td>{{ $transactionType->isSystemType() ? 'Yes' : 'No' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="center">No voucher transaction types found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Generated on: {{ now()->format('Y-m-d H:i:s') }} |
        Total Records: {{ $voucherTransactionTypes->count() }}
    </div>
</body>
</html>
