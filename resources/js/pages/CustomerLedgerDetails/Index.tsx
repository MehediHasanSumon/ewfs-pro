import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { FileText, Filter, X } from 'lucide-react';
import { useState } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface Transaction {
    date: string;
    shift: string;
    transaction_id: string;
    debit: number;
    credit: number;
    due: number;
    remarks?: string;
}

interface Ledger {
    customer_name: string;
    ac_number: string;
    transactions: Transaction[];
    total_debit: number;
    total_credit: number;
    total_due: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Customers', href: '/customers' },
    { title: 'Ledger Details', href: '#' },
];

interface CustomerLedgerDetailsProps {
    ledgers: Ledger[];
    filters: {
        start_date?: string;
        end_date?: string;
    };
}

export default function CustomerLedgerDetails({ ledgers = [], filters = {} }: CustomerLedgerDetailsProps) {
    const { can } = usePermission();
    const canFilter = can('can-customer-filter');
    const canDownload = can('can-customer-download');

    const [startDate, setStartDate] = useState(filters.start_date || new Date().toISOString().split('T')[0]);
    const [endDate, setEndDate] = useState(filters.end_date || new Date().toISOString().split('T')[0]);

    const applyFilters = () => {
        const customerId = window.location.pathname.split('/').pop();
        router.get(`/customer-ledger-details/${customerId}`, {
            start_date: startDate,
            end_date: endDate,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        const today = new Date().toISOString().split('T')[0];
        setStartDate(today);
        setEndDate(today);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Customer Ledger Details" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Customer Ledger Details</h1>
                        <p className="text-gray-600 dark:text-gray-400">View customer wise ledger details</p>
                    </div>
                    {canDownload && (
                        <Button
                            variant="success"
                            onClick={() => {
                                const customerId = window.location.pathname.split('/').pop();
                                const params = new URLSearchParams();
                                params.append('start_date', startDate);
                                params.append('end_date', endDate);
                                window.location.href = `/customer-ledger-details/${customerId}/download-pdf?${params.toString()}`;
                            }}
                        >
                            <FileText className="mr-2 h-4 w-4" />Download
                        </Button>
                    )}
                </div>

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
                                    <Button onClick={applyFilters} className="px-4">Apply Filters</Button>
                                    <Button onClick={clearFilters} variant="secondary" className="px-4">
                                        <X className="mr-2 h-4 w-4" />Clear
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Customer Details Card */}
                {ledgers.length > 0 && (
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="dark:text-white">Customer Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-5">
                                <div>
                                    <Label className="text-sm font-medium text-gray-500 dark:text-gray-400">Customer Name</Label>
                                    <p className="text-gray-900 dark:text-white font-medium">{ledgers[0].customer_name}</p>
                                </div>
                                <div>
                                    <Label className="text-sm font-medium text-gray-500 dark:text-gray-400">Account Number</Label>
                                    <p className="text-gray-900 dark:text-white font-medium">{ledgers[0].ac_number}</p>
                                </div>
                                <div>
                                    <Label className="text-sm font-medium text-gray-500 dark:text-gray-400">Mobile</Label>
                                    <p className="text-gray-900 dark:text-white font-medium">{ledgers[0].customer_mobile || 'N/A'}</p>
                                </div>
                                <div>
                                    <Label className="text-sm font-medium text-gray-500 dark:text-gray-400">Address</Label>
                                    <p className="text-gray-900 dark:text-white font-medium">{ledgers[0].customer_address || 'N/A'}</p>
                                </div>
                                <div>
                                    <Label className="text-sm font-medium text-gray-500 dark:text-gray-400">Total Due</Label>
                                    <p className={`font-bold ${
                                        ledgers[0].total_due > 0 
                                            ? 'text-red-600 dark:text-red-400' 
                                            : ledgers[0].total_due < 0 
                                                ? 'text-green-600 dark:text-green-400' 
                                                : 'text-gray-900 dark:text-white'
                                    }`}>
                                        {ledgers[0].total_due < 0 ? '-' : ''}{Math.abs(ledgers[0].total_due).toFixed(2)}
                                        {ledgers[0].total_due > 0 && ' (Due)'}
                                        {ledgers[0].total_due < 0 && ' (Advanced)'}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="space-y-6">
                    {ledgers.length > 0 ? (
                        ledgers.map((ledger, ledgerIndex) => (
                            <Card key={ledgerIndex} className="dark:border-gray-700 dark:bg-gray-800">
                                <CardContent className="p-0">
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="border-b dark:border-gray-700">
                                                    <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">SL</th>
                                                    <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Date</th>
                                                    <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Invoice no</th>
                                                    <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Debit</th>
                                                    <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Credit</th>
                                                    <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">
                                                        {ledger.total_due >= 0 ? 'Due Amount' : 'Advanced Amount'}
                                                    </th>
                                                    <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Remarks</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {ledger.transactions.map((transaction, transactionIndex) => (
                                                    <tr key={transactionIndex} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                        <td className="p-2 text-[13px] dark:text-white">{transactionIndex + 1}</td>
                                                        <td className="p-2 text-[13px] dark:text-white">{transaction.date}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300">{transaction.transaction_id}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{Number(transaction.debit).toFixed(2)}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{Number(transaction.credit).toFixed(2)}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{Number(transaction.due).toFixed(2)}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300">{transaction.remarks || 'N/A'}</td>
                                                    </tr>
                                                ))}
                                                <tr className="border-b font-bold bg-gray-50 dark:bg-gray-700 dark:border-gray-700">
                                                    <td colSpan={3} className="p-2 text-[13px] dark:text-white">Total:</td>
                                                    <td className="p-2 text-right text-[13px] dark:text-white">{Number(ledger.total_debit).toFixed(2)}</td>
                                                    <td className="p-2 text-right text-[13px] dark:text-white">{Number(ledger.total_credit).toFixed(2)}</td>
                                                    <td className="p-2 text-right text-[13px] dark:text-white">{Number(ledger.total_due).toFixed(2)}</td>
                                                    <td className="p-2 text-[13px] dark:text-white">-</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </CardContent>
                            </Card>
                        ))
                    ) : (
                        <Card className="dark:border-gray-700 dark:bg-gray-800">
                            <CardContent>
                                <p className="p-4 text-center text-[13px] text-gray-500 dark:text-gray-400">No records</p>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
