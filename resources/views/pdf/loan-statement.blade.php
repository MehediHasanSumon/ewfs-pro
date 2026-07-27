<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Loan Statement - {{ $loanAccount['name'] }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 5px; border: 1px solid #ddd; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .data-table th, .data-table td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        .data-table th { background-color: #f5f5f5; }
        .section-title { font-size: 14px; font-weight: bold; margin: 20px 0 10px 0; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Loan Statement</h2>
        <h3>{{ $loanAccount['name'] }}</h3>
        <p>Generated on: {{ date('d/m/Y H:i:s') }}</p>
    </div>

    <table class="info-table">
        <tr>
            <td><strong>Account Name:</strong></td>
            <td>{{ $loanAccount['name'] }}</td>
            <td><strong>Account Number:</strong></td>
            <td>{{ $loanAccount['ac_number'] }}</td>
        </tr>
        <tr>
            <td><strong>Total Loan:</strong></td>
            <td>{{ number_format($totalLoan) }}</td>
            <td><strong>Total Payment:</strong></td>
            <td>{{ number_format($totalPayment) }}</td>
        </tr>
        <tr>
            <td><strong>Current Balance:</strong></td>
            <td colspan="3"><strong>{{ number_format($currentBalance) }}</strong></td>
        </tr>
    </table>

    <div class="section-title">Loan Transactions</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>SL</th>
                <th>Voucher No</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recentLoans as $index => $loan)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $loan['voucher_no'] }}</td>
                <td>{{ date('d/m/Y', strtotime($loan['date'])) }}</td>
                <td class="text-right">{{ number_format($loan['amount']) }}</td>
                <td>{{ $loan['description'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center;">No loan transactions found</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Payment Transactions</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>SL</th>
                <th>Voucher No</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recentPayments as $index => $payment)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $payment['voucher_no'] }}</td>
                <td>{{ date('d/m/Y', strtotime($payment['date'])) }}</td>
                <td class="text-right">{{ number_format($payment['amount']) }}</td>
                <td>{{ $payment['description'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center;">No payment transactions found</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>