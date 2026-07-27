import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, FileText, Filter, X } from 'lucide-react';
import { useState } from 'react';
import { Pagination } from '@/components/ui/pagination';
import { usePermission } from '@/hooks/usePermission';

interface Account {
    id: number;
    name: string;
    ac_number: string;
    group: {
        name: string;
    };
}

interface Transaction {
    id: number;
    transaction_id: string;
    transaction_date: string;
    transaction_type: 'Dr' | 'Cr';
    amount: number;
    description: string;
    payment_type: string;
    balance: number;
    voucher_no?: string;
    voucher_type?: string;
    shift_name?: string;
}

interface Props {
    account: Account;
    transactions: {
        data: Transaction[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    total_debit: number;
    total_credit: number;
    closing_balance: number;
    filters: {
        start_date?: string;
        end_date?: string;
        per_page?: number;
    };
}

export default function Show({ account, transactions, total_debit, total_credit, closing_balance, filters }: Props) {
    const { can } = usePermission();
    const canFilter = can('can-account-filter');
    const canDownload = can('can-account-download');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Bank Book Ledger', href: '/bank-book-ledger' },
        { title: account.name, href: `/bank-book-ledger/${account.ac_number}` },
    ];

    const [startDate, setStartDate] = useState(
        filters?.start_date || new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    );
    const [endDate, setEndDate] = useState(
        filters?.end_date || new Date().toISOString().split('T')[0],
    );

    const [perPage, setPerPage] = useState(filters?.per_page || 10);

    const applyFilters = () => {
        const params = new URLSearchParams();
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);

        router.get(`/bank-book-ledger/${account.ac_number}?${params.toString()}`);
    };

    const handlePageChange = (page: number) => {
        const params = new URLSearchParams();
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        params.append('per_page', perPage.toString());
        params.append('page', page.toString());
        router.get(`/bank-book-ledger/${account.ac_number}?${params.toString()}`);
    };

    const clearFilters = () => {
        const firstDay = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
        const today = new Date().toISOString().split('T')[0];
        setStartDate(firstDay);
        setEndDate(today);
        router.get(`/bank-book-ledger/${account.ac_number}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${account.name} - Bank Book Ledger`} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => router.get('/bank-book-ledger')}
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold dark:text-white">
                                {account.name}
                            </h1>
                            <p className="text-gray-600 dark:text-gray-400">
                                Account Number: {account.ac_number}
                            </p>
                        </div>
                    </div>
                    {transactions.data.length > 0 && canDownload && (
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                if (startDate) params.append('start_date', startDate);
                                if (endDate) params.append('end_date', endDate);
                                window.location.href = `/bank-book-ledger/${account.ac_number}/download-pdf?${params.toString()}`;
                            }}
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Download
                        </Button>
                    )}
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="flex items-center p-6">
                            <div>
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Total Debit</p>
                                <p className="text-2xl font-bold text-red-600 dark:text-red-400">
                                    {total_debit.toLocaleString()}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="flex items-center p-6">
                            <div>
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Total Credit</p>
                                <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                                    {total_credit.toLocaleString()}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="flex items-center p-6">
                            <div>
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Closing Balance</p>
                                <p className={`text-2xl font-bold ${closing_balance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                                    {Math.abs(closing_balance).toLocaleString()}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filter Card */}
                {canFilter && (
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 dark:text-white">
                                <Filter className="h-5 w-5" />
                                Filters
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                <div>
                                    <Label className="dark:text-gray-200">Start Date</Label>
                                    <Input
                                        type="date"
                                        value={startDate}
                                        onChange={(e) => setStartDate(e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label className="dark:text-gray-200">End Date</Label>
                                    <Input
                                        type="date"
                                        value={endDate}
                                        onChange={(e) => setEndDate(e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div className="flex items-end gap-2 md:col-span-2">
                                    <Button onClick={applyFilters} className="px-4">
                                        Apply Filters
                                    </Button>
                                    <Button
                                        onClick={clearFilters}
                                        variant="secondary"
                                        className="px-4"
                                    >
                                        <X className="mr-2 h-4 w-4" />
                                        Clear
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Transactions Table */}
                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="text-[16px] dark:text-white">
                            Transactions
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {transactions.data.length > 0 ? (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Date
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Shift
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Voucher No
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Description
                                            </th>
                                            <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                                Debit
                                            </th>
                                            <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                                Credit
                                            </th>
                                            <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                                Balance
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {transactions.data.map((transaction) => (
                                            <tr
                                                key={transaction.id}
                                                className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                            >
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {transaction.transaction_date}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {transaction.shift_name || '-'}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {transaction.voucher_no || '-'}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {transaction.description}
                                                </td>
                                                <td className="p-4 text-right text-[13px] dark:text-gray-300">
                                                    {transaction.transaction_type === 'Dr'
                                                        ? transaction.amount.toLocaleString()
                                                        : '-'}
                                                </td>
                                                <td className="p-4 text-right text-[13px] dark:text-gray-300">
                                                    {transaction.transaction_type === 'Cr'
                                                        ? transaction.amount.toLocaleString()
                                                        : '-'}
                                                </td>
                                                <td
                                                    className={`p-4 text-right text-[13px] font-medium ${transaction.balance >= 0 ? 'text-green-600' : 'text-red-600'}`}
                                                >
                                                    {Math.abs(transaction.balance).toLocaleString()}
                                                </td>
                                            </tr>
                                        ))}
                                        <tr className="border-b bg-gray-50 font-bold dark:border-gray-700 dark:bg-gray-700">
                                            <td colSpan={4} className="p-4 text-[13px] dark:text-white">
                                                Total:
                                            </td>
                                            <td className="p-4 text-right text-[13px] dark:text-white">
                                                {total_debit.toFixed(2)}
                                            </td>
                                            <td className="p-4 text-right text-[13px] dark:text-white">
                                                {total_credit.toFixed(2)}
                                            </td>
                                            <td className="p-4 text-right text-[13px] dark:text-white">
                                                -
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <Pagination
                                currentPage={transactions.current_page}
                                lastPage={transactions.last_page}
                                from={transactions.from}
                                to={transactions.to}
                                total={transactions.total}
                                perPage={perPage}
                                onPageChange={handlePageChange}
                                onPerPageChange={(newPerPage) => {
                                    setPerPage(newPerPage);
                                    const params = new URLSearchParams();
                                    if (startDate) params.append('start_date', startDate);
                                    if (endDate) params.append('end_date', endDate);
                                    params.append('per_page', newPerPage.toString());
                                    router.get(`/bank-book-ledger/${account.ac_number}?${params.toString()}`);
                                }}
                            />
                            </>
                        ) : (
                            <p className="p-4 text-center text-[13px] text-gray-500 dark:text-gray-400">
                                No transactions found for the selected period
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
