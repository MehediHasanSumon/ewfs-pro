import { openPdfViewer } from '@/components/documents/pdf-viewer';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermission } from '@/hooks/usePermission';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, FileText, Filter, X } from 'lucide-react';
import { useState } from 'react';

interface Group {
    id: number;
    code: string;
    name: string;
}

interface VoucherCategory {
    id: number;
    code: string;
    name: string;
}

interface VoucherTransactionType {
    id: number;
    voucher_category_id: number;
    code: string;
    name: string;
    voucher_type: string;
}

interface Account {
    id: number;
    name: string;
    ac_number: string;
    group_id?: number;
    group?: Group;
    status: boolean;
    created_at: string;
}

interface Transaction {
    id: number;
    transaction_id: string;
    transaction_date: string;
    transaction_type: 'Dr' | 'Cr';
    voucher_no?: string;
    voucher_type?: string;
    transaction_type_name?: string;
    voucher_date?: string;
    description?: string;
    payment_type?: string;
    debit_amount: number;
    credit_amount: number;
    amount: number;
    balance: number;
}

interface AccountDetailsProps {
    account: Account;
    categories: VoucherCategory[];
    transactionTypes: VoucherTransactionType[];
    transactions: {
        data: Transaction[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    openingBalance: number;
    periodDebit: number;
    periodCredit: number;
    closingBalance: number;
    filters: {
        start_date?: string;
        end_date?: string;
        category_id?: string;
        transaction_type?: string;
        per_page?: number;
    };
}

export default function AccountDetails({
    account,
    categories = [],
    transactionTypes = [],
    transactions,
    openingBalance = 0,
    periodDebit = 0,
    periodCredit = 0,
    closingBalance = 0,
    filters = {},
}: AccountDetailsProps) {
    const { can } = usePermission();
    const canFilter = can('can-account-filter') || can('view-account');
    const canDownload = can('can-account-download') || can('view-account');

    const [startDate, setStartDate] = useState(filters.start_date || new Date().toISOString().split('T')[0]);
    const [endDate, setEndDate] = useState(filters.end_date || new Date().toISOString().split('T')[0]);
    const [categoryId, setCategoryId] = useState(filters.category_id || 'all');
    const [transactionType, setTransactionType] = useState(filters.transaction_type || 'all');
    const [perPage, setPerPage] = useState(filters.per_page || 15);

    const filteredTransactionTypes = categoryId && categoryId !== 'all'
        ? transactionTypes.filter(t => t.voucher_category_id.toString() === categoryId)
        : transactionTypes;

    const handleCategoryChange = (val: string) => {
        setCategoryId(val);
        // Reset transaction type if not belonging to new category
        if (val !== 'all') {
            const valid = transactionTypes.some(t => t.voucher_category_id.toString() === val && t.id.toString() === transactionType);
            if (!valid) {
                setTransactionType('all');
            }
        }
    };

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: dashboard().url,
        },
        {
            title: 'Accounts',
            href: '/accounts',
        },
        {
            title: account.name,
            href: `/accounts/${account.id}`,
        },
    ];

    const applyFilters = () => {
        router.get(
            `/accounts/${account.id}`,
            {
                start_date: startDate,
                end_date: endDate,
                category_id: categoryId === 'all' ? undefined : categoryId,
                transaction_type: transactionType === 'all' ? undefined : transactionType,
                per_page: perPage,
            },
            { preserveState: true }
        );
    };

    const clearFilters = () => {
        const today = new Date().toISOString().split('T')[0];
        setStartDate(today);
        setEndDate(today);
        setCategoryId('all');
        setTransactionType('all');
        router.get(`/accounts/${account.id}`, {
            start_date: today,
            end_date: today,
            per_page: perPage,
        }, { preserveState: true });
    };

    const handlePageChange = (page: number) => {
        router.get(
            `/accounts/${account.id}`,
            {
                page,
                start_date: startDate,
                end_date: endDate,
                category_id: categoryId === 'all' ? undefined : categoryId,
                transaction_type: transactionType === 'all' ? undefined : transactionType,
                per_page: perPage,
            },
            { preserveState: true }
        );
    };

    const handleDownloadPdf = () => {
        const params = new URLSearchParams();
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        if (categoryId && categoryId !== 'all') params.append('category_id', categoryId);
        if (transactionType && transactionType !== 'all') params.append('transaction_type', transactionType);
        openPdfViewer(`/accounts/${account.id}/statement-pdf?${params.toString()}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Account Details - ${account.name}`} />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            {account.name}
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            A/C Number: {account.ac_number}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={handleDownloadPdf}
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Download
                            </Button>
                        )}
                        <Button
                            variant="secondary"
                            onClick={() => router.get('/accounts')}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back
                        </Button>
                    </div>
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
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-6">
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
                                <div>
                                    <Label className="dark:text-gray-200">Transaction Category</Label>
                                    <Select value={categoryId} onValueChange={handleCategoryChange}>
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="All Categories" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Categories</SelectItem>
                                            {categories.map((cat) => (
                                                <SelectItem key={cat.id} value={cat.id.toString()}>
                                                    {cat.name} ({cat.code})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label className="dark:text-gray-200">Transaction Type</Label>
                                    <Select value={transactionType} onValueChange={setTransactionType}>
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="All Transaction Types" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Transaction Types</SelectItem>
                                            {filteredTransactionTypes.map((type) => (
                                                <SelectItem key={type.id} value={type.id.toString()}>
                                                    {type.name} ({type.code})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
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
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">SL</th>
                                        <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Date</th>
                                        <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Transaction Type</th>
                                        <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Description</th>
                                        <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Payment Method</th>
                                        <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Debit</th>
                                        <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Credit</th>
                                        <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {transactions.data && transactions.data.length > 0 ? (
                                        <>
                                            {transactions.data.map((transaction, index) => (
                                                <tr
                                                    key={transaction.id || index}
                                                    className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                                >
                                                    <td className="p-2 text-[13px] dark:text-white">
                                                        {(transactions.current_page - 1) * transactions.per_page + index + 1}
                                                    </td>
                                                    <td className="p-2 text-[13px] dark:text-white">
                                                        {transaction.transaction_date || transaction.voucher_date}
                                                    </td>
                                                    <td className="p-2 text-[13px] dark:text-gray-300 font-medium">
                                                        {transaction.transaction_type_name || transaction.voucher_type || '-'}
                                                    </td>
                                                    <td className="p-2 text-[13px] dark:text-gray-300">
                                                        {transaction.description || '-'}
                                                    </td>
                                                    <td className="p-2 text-[13px] dark:text-gray-300 capitalize">
                                                        {transaction.payment_type || '-'}
                                                    </td>
                                                    <td className="p-2 text-right text-[13px] dark:text-gray-300">
                                                        {transaction.debit_amount > 0 ? Number(transaction.debit_amount).toFixed(2) : '-'}
                                                    </td>
                                                    <td className="p-2 text-right text-[13px] dark:text-gray-300">
                                                        {transaction.credit_amount > 0 ? Number(transaction.credit_amount).toFixed(2) : '-'}
                                                    </td>
                                                    <td
                                                        className={`p-2 text-right text-[13px] font-medium ${
                                                            transaction.balance >= 0 ? 'text-green-600' : 'text-red-600'
                                                        }`}
                                                    >
                                                        {Math.abs(transaction.balance).toFixed(2)} {transaction.balance >= 0 ? 'Cr' : 'Dr'}
                                                    </td>
                                                </tr>
                                            ))}
                                            <tr className="border-b font-bold bg-gray-50 dark:bg-gray-700 dark:border-gray-700">
                                                <td colSpan={5} className="p-2 text-[13px] dark:text-white">
                                                    Total:
                                                </td>
                                                <td className="p-2 text-right text-[13px] dark:text-white">
                                                    {Number(periodDebit).toFixed(2)}
                                                </td>
                                                <td className="p-2 text-right text-[13px] dark:text-white">
                                                    {Number(periodCredit).toFixed(2)}
                                                </td>
                                                <td className="p-2 text-right text-[13px] dark:text-white">
                                                    {Math.abs(closingBalance).toFixed(2)} {closingBalance >= 0 ? 'Cr' : 'Dr'}
                                                </td>
                                            </tr>
                                        </>
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={8}
                                                className="p-4 text-center text-[13px] text-gray-500 dark:text-gray-400"
                                            >
                                                No transactions found for the selected period
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        <div className="border-t p-4 dark:border-gray-700">
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
                                    router.get(
                                        `/accounts/${account.id}`,
                                        {
                                            start_date: startDate,
                                            end_date: endDate,
                                            category_id: categoryId === 'all' ? undefined : categoryId,
                                            transaction_type: transactionType === 'all' ? undefined : transactionType,
                                            per_page: newPerPage,
                                        },
                                        { preserveState: true }
                                    );
                                }}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
