<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Voucher Categories</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #222; }
        .header { text-align: center; margin-bottom: 18px; }
        .header h2 { margin: 0 0 5px; font-size: 18px; }
        .header p { margin: 2px 0; color: #555; }
        .title { text-align: center; font-size: 14px; font-weight: bold; margin: 16px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 7px; text-align: left; vertical-align: top; }
        th { }
        .center { text-align: center; }
        .footer { margin-top: 16px; color: #666; font-size: 10px; }
    </style>
</head>
<body>
    @include('pdf.components.watermark')
    <div class="header">
        <h2>{{ $companySetting?->company_name ?? 'Voucher Categories' }}</h2>
        @if($companySetting?->company_address)
            <p>{{ $companySetting->company_address }}</p>
        @endif
    </div>

    <div class="title">Voucher Category Management</div>

    <table>
        <thead>
            <tr>
                <th class="center">SL</th>
                <th>Code</th>
                <th>Name</th>
                <th>Description</th>
                <th class="center">Sort Order</th>
                <th>Status</th>
                <th>System Category</th>
                <th>Created Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($voucherCategories as $index => $voucherCategory)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $voucherCategory->code }}</td>
                    <td>{{ $voucherCategory->name }}</td>
                    <td>{{ $voucherCategory->description ?: '-' }}</td>
                    <td class="center">{{ $voucherCategory->sort_order }}</td>
                    <td>{{ $voucherCategory->status ? 'Active' : 'Inactive' }}</td>
                    <td>{{ $voucherCategory->isSystemCategory() ? 'Yes' : 'No' }}</td>
                    <td>{{ $voucherCategory->created_at?->format('Y-m-d') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="center">No voucher categories found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Generated on: {{ now()->format('Y-m-d H:i:s') }} |
        Total Records: {{ $voucherCategories->count() }}
    </div>
</body>
</html>
