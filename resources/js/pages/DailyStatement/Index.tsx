import { openPdfViewer } from '@/components/documents/pdf-viewer';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { FileText, Filter, X, DollarSign, ArrowDownRight, ArrowUpRight, Wallet, Building2, TrendingUp, CreditCard } from 'lucide-react';
import { useState } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface ProductSale {
    product_id: number;
    product_name: string;
    unit_name: string;
    unit_price?: number;
    cash_quantity?: number;
    cash_amount?: number;
    bank_quantity?: number;
    bank_amount?: number;
    credit_quantity?: number;
    credit_amount?: number;
    total_quantity: number;
    total_amount: number;
}

interface CustomerSale {
    sale_date?: string;
    customer_name: string;
    vehicle_no?: string;
    product_name: string;
    unit_name: string;
    unit_price: number;
    quantity: number;
    total_amount: number;
}

interface TransactionItem {
    id?: number;
    account_name: string;
    category?: string;
    payment_type?: string;
    amount: number;
    description?: string;
}

interface CashFlowSummary {
    opening_balance: number;
    total_receipts: number;
    total_payments: number;
    net_movement: number;
    closing_balance: number;
    expenses?: number;
    salaries?: number;
    advances?: number;
    loans?: number;
    purchases?: number;
    supplier_payments?: number;
    customer_refunds?: number;
    other_payments?: number;
    transfers_out?: number;
}

interface BankFlowSummary {
    opening_balance: number;
    total_receipts: number;
    total_payments: number;
    net_movement: number;
    closing_balance: number;
}

interface ExecutiveSummary {
    total_sales: number;
    cash_sales: number;
    bank_sales: number;
    credit_sales: number;
    opening_cash: number;
    total_cash_receipts: number;
    total_cash_payments: number;
    net_cash_movement: number;
    closing_cash: number;
    opening_bank: number;
    total_bank_receipts: number;
    total_bank_payments: number;
    net_bank_movement: number;
    closing_bank: number;
    total_expenses: number;
}

interface Customer {
    id: number;
    name: string;
}

interface Shift {
    id: number;
    name: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Daily Statement Report', href: '/daily-statement' },
];

interface DailyStatementProps {
    productWiseSales?: ProductSale[];
    cashBankSales?: ProductSale[];
    creditSales?: ProductSale[];
    customerWiseSales?: CustomerSale[];
    cashReceived?: TransactionItem[];
    cashPayment?: TransactionItem[];
    bankReceived?: TransactionItem[];
    bankPayment?: TransactionItem[];
    cashFlow?: CashFlowSummary;
    bankFlow?: BankFlowSummary;
    summary?: ExecutiveSummary;
    customers?: Customer[];
    shifts?: Shift[];
    filters?: {
        search?: string;
        customer_id?: string;
        start_date?: string;
        end_date?: string;
        shift_id?: string;
    };
}

export default function DailyStatement({
    productWiseSales = [],
    customerWiseSales = [],
    cashReceived = [],
    cashPayment = [],
    cashFlow = {
        opening_balance: 0,
        total_receipts: 0,
        total_payments: 0,
        net_movement: 0,
        closing_balance: 0,
    },
    bankFlow = {
        opening_balance: 0,
        total_receipts: 0,
        total_payments: 0,
        net_movement: 0,
        closing_balance: 0,
    },
    summary = {
        total_sales: 0,
        cash_sales: 0,
        bank_sales: 0,
        credit_sales: 0,
        opening_cash: 0,
        total_cash_receipts: 0,
        total_cash_payments: 0,
        net_cash_movement: 0,
        closing_cash: 0,
        opening_bank: 0,
        total_bank_receipts: 0,
        total_bank_payments: 0,
        net_bank_movement: 0,
        closing_bank: 0,
        total_expenses: 0,
    },
    shifts = [],
    filters = {},
}: DailyStatementProps) {
    const { can } = usePermission();
    const canFilter = can('can-account-filter');
    const canDownload = can('can-account-download');

    const [shiftId, setShiftId] = useState(filters.shift_id || 'all');
    const [startDate, setStartDate] = useState(filters.start_date || new Date().toISOString().split('T')[0]);
    const [endDate, setEndDate] = useState(filters.end_date || new Date().toISOString().split('T')[0]);

    const applyFilters = () => {
        const params: Record<string, string> = {
            start_date: startDate,
            end_date: endDate,
        };
        if (shiftId !== 'all') {
            params.shift_id = shiftId;
        }
        router.get('/daily-statement', params, { preserveState: true });
    };

    const clearFilters = () => {
        const today = new Date().toISOString().split('T')[0];
        setStartDate(today);
        setEndDate(today);
        setShiftId('all');
        router.get('/daily-statement', {
            start_date: today,
            end_date: today,
        }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Daily Statement Report" />

            <div className="space-y-6 p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Daily Statement Report</h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Comprehensive financial statement, product sales, and cash/bank reconciliation
                        </p>
                    </div>
                    {canDownload && (
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                params.append('start_date', startDate);
                                params.append('end_date', endDate);
                                if (shiftId !== 'all') {
                                    params.append('shift_id', shiftId);
                                }
                                openPdfViewer(`/daily-statement/download-pdf?${params.toString()}`);
                            }}
                        >
                            <FileText className="mr-2 h-4 w-4" />Download PDF
                        </Button>
                    )}
                </div>

                {canFilter && (
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base font-semibold dark:text-white">
                                <Filter className="h-5 w-5 text-gray-500" />
                                Date & Shift Filters
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
                                <div>
                                    <Label className="dark:text-gray-200">Shift</Label>
                                    <Select value={shiftId} onValueChange={setShiftId}>
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="Select Shift" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Shifts</SelectItem>
                                            {shifts.map((shift) => (
                                                <SelectItem key={shift.id} value={shift.id.toString()}>
                                                    {shift.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex items-end gap-2">
                                    <Button onClick={applyFilters} className="px-5">Apply</Button>
                                    <Button onClick={clearFilters} variant="secondary" className="px-4">
                                        <X className="mr-2 h-4 w-4" />Clear
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Executive Summary Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-l-4 border-blue-500 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Product Sales</p>
                                    <h3 className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{Number(summary.total_sales || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</h3>
                                </div>
                                <div className="rounded-full bg-blue-100 p-2.5 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                    <TrendingUp className="h-6 w-6" />
                                </div>
                            </div>
                            <div className="mt-3 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 border-t pt-2 dark:border-gray-700">
                                <span>Cash: {Number(summary.cash_sales || 0).toLocaleString()}</span>
                                <span>Credit: {Number(summary.credit_sales || 0).toLocaleString()}</span>
                                <span>Bank: {Number(summary.bank_sales || 0).toLocaleString()}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-l-4 border-green-500 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Cash Inflow</p>
                                    <h3 className="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">+{Number(cashFlow.total_receipts || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</h3>
                                </div>
                                <div className="rounded-full bg-green-100 p-2.5 text-green-600 dark:bg-green-900/30 dark:text-green-400">
                                    <ArrowDownRight className="h-6 w-6" />
                                </div>
                            </div>
                            <p className="mt-3 text-xs text-gray-500 dark:text-gray-400 border-t pt-2 dark:border-gray-700">
                                Sales + Collections + Receipts
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-l-4 border-red-500 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Cash Outflow</p>
                                    <h3 className="mt-1 text-2xl font-bold text-red-600 dark:text-red-400">-{Number(cashFlow.total_payments || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</h3>
                                </div>
                                <div className="rounded-full bg-red-100 p-2.5 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                    <ArrowUpRight className="h-6 w-6" />
                                </div>
                            </div>
                            <p className="mt-3 text-xs text-gray-500 dark:text-gray-400 border-t pt-2 dark:border-gray-700">
                                Expenses + Salaries + Purchases + Transfers
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-l-4 border-amber-500 bg-amber-50/50 shadow-sm dark:border-amber-600 dark:bg-amber-950/20">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-xs font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300">CLOSING CASH IN HAND</p>
                                    <h3 className="mt-1 text-2xl font-black text-amber-900 dark:text-amber-200">{Number(cashFlow.closing_balance || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</h3>
                                </div>
                                <div className="rounded-full bg-amber-200 p-2.5 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">
                                    <Wallet className="h-6 w-6" />
                                </div>
                            </div>
                            <p className="mt-3 text-xs font-medium text-amber-700 dark:text-amber-300 border-t border-amber-200 pt-2 dark:border-amber-800">
                                Opening ({Number(cashFlow.opening_balance || 0).toLocaleString()}) + Net ({Number(cashFlow.net_movement || 0).toLocaleString()})
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-6">
                    {/* 1. Sales Summary (Product Wise) */}
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="text-base font-bold dark:text-white">1. Sales Summary (Product Wise)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b bg-gray-50 text-xs font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                            <th className="p-2.5 text-left">Product Name</th>
                                            <th className="p-2.5 text-left">Unit</th>
                                            <th className="p-2.5 text-right">Unit Price</th>
                                            <th className="p-2.5 text-right">Cash Qty</th>
                                            <th className="p-2.5 text-right">Bank Qty</th>
                                            <th className="p-2.5 text-right">Credit Qty</th>
                                            <th className="p-2.5 text-right font-bold">Total Qty</th>
                                            <th className="p-2.5 text-right">Cash (Tk)</th>
                                            <th className="p-2.5 text-right">Bank (Tk)</th>
                                            <th className="p-2.5 text-right">Credit (Tk)</th>
                                            <th className="p-2.5 text-right font-bold">Total Sales (Tk)</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y text-xs dark:divide-gray-700">
                                        {productWiseSales.length > 0 ? (
                                            <>
                                                {productWiseSales.map((sale, index) => (
                                                    <tr key={index} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                        <td className="p-2.5 font-medium dark:text-white">{sale.product_name}</td>
                                                        <td className="p-2.5 dark:text-gray-300">{sale.unit_name}</td>
                                                        <td className="p-2.5 text-right dark:text-gray-300">{Number(sale.unit_price || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                                                        <td className="p-2.5 text-right dark:text-gray-300">{Number(sale.cash_quantity || 0).toLocaleString()}</td>
                                                        <td className="p-2.5 text-right dark:text-gray-300">{Number(sale.bank_quantity || 0).toLocaleString()}</td>
                                                        <td className="p-2.5 text-right dark:text-gray-300">{Number(sale.credit_quantity || 0).toLocaleString()}</td>
                                                        <td className="p-2.5 text-right font-semibold dark:text-white">{Number(sale.total_quantity || 0).toLocaleString()}</td>
                                                        <td className="p-2.5 text-right dark:text-gray-300">{Number(sale.cash_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                                                        <td className="p-2.5 text-right dark:text-gray-300">{Number(sale.bank_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                                                        <td className="p-2.5 text-right dark:text-gray-300">{Number(sale.credit_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                                                        <td className="p-2.5 text-right font-bold dark:text-white">{Number(sale.total_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                                                    </tr>
                                                ))}
                                                <tr className="bg-gray-100 font-bold dark:bg-gray-900/60">
                                                    <td colSpan={3} className="p-2.5 dark:text-white">Total:</td>
                                                    <td className="p-2.5 text-right dark:text-white">{productWiseSales.reduce((sum, s) => sum + Number(s.cash_quantity || 0), 0).toFixed(2)}</td>
                                                    <td className="p-2.5 text-right dark:text-white">{productWiseSales.reduce((sum, s) => sum + Number(s.bank_quantity || 0), 0).toFixed(2)}</td>
                                                    <td className="p-2.5 text-right dark:text-white">{productWiseSales.reduce((sum, s) => sum + Number(s.credit_quantity || 0), 0).toFixed(2)}</td>
                                                    <td className="p-2.5 text-right dark:text-white">{productWiseSales.reduce((sum, s) => sum + Number(s.total_quantity || 0), 0).toFixed(2)}</td>
                                                    <td className="p-2.5 text-right dark:text-white">{productWiseSales.reduce((sum, s) => sum + Number(s.cash_amount || 0), 0).toFixed(2)}</td>
                                                    <td className="p-2.5 text-right dark:text-white">{productWiseSales.reduce((sum, s) => sum + Number(s.bank_amount || 0), 0).toFixed(2)}</td>
                                                    <td className="p-2.5 text-right dark:text-white">{productWiseSales.reduce((sum, s) => sum + Number(s.credit_amount || 0), 0).toFixed(2)}</td>
                                                    <td className="p-2.5 text-right text-sm font-black dark:text-white">{productWiseSales.reduce((sum, s) => sum + Number(s.total_amount || 0), 0).toFixed(2)}</td>
                                                </tr>
                                            </>
                                        ) : (
                                            <tr>
                                                <td colSpan={11} className="p-4 text-center text-gray-500 dark:text-gray-400">No product sales records found</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    {/* 2. Customer Wise Credit Sales Detail */}
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="text-base font-bold dark:text-white">2. Customer Wise Credit Sales Detail</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b bg-gray-50 text-xs font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                            <th className="p-2.5 text-left">Date</th>
                                            <th className="p-2.5 text-left">Customer Name</th>
                                            <th className="p-2.5 text-left">Vehicle Number</th>
                                            <th className="p-2.5 text-left">Product Name</th>
                                            <th className="p-2.5 text-left">Unit</th>
                                            <th className="p-2.5 text-right">Unit Price</th>
                                            <th className="p-2.5 text-right">Quantity</th>
                                            <th className="p-2.5 text-right font-bold">Total Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y text-xs dark:divide-gray-700">
                                        {customerWiseSales.length > 0 ? (
                                            <>
                                                {customerWiseSales.map((sale, index) => (
                                                    <tr key={index} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                        <td className="p-2.5 dark:text-gray-300">{sale.sale_date || '-'}</td>
                                                        <td className="p-2.5 font-medium dark:text-white">{sale.customer_name}</td>
                                                        <td className="p-2.5 dark:text-gray-300">{sale.vehicle_no || '-'}</td>
                                                        <td className="p-2.5 dark:text-gray-300">{sale.product_name}</td>
                                                        <td className="p-2.5 dark:text-gray-300">{sale.unit_name}</td>
                                                        <td className="p-2.5 text-right dark:text-gray-300">{Number(sale.unit_price || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                                                        <td className="p-2.5 text-right dark:text-gray-300">{Number(sale.quantity || 0).toLocaleString()}</td>
                                                        <td className="p-2.5 text-right font-semibold dark:text-white">{Number(sale.total_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                                                    </tr>
                                                ))}
                                                <tr className="bg-gray-100 font-bold dark:bg-gray-900/60">
                                                    <td colSpan={6} className="p-2.5 dark:text-white">Total Credit Sales:</td>
                                                    <td className="p-2.5 text-right dark:text-white">{customerWiseSales.reduce((sum, s) => sum + Number(s.quantity || 0), 0).toFixed(2)}</td>
                                                    <td className="p-2.5 text-right text-sm font-black dark:text-white">{customerWiseSales.reduce((sum, s) => sum + Number(s.total_amount || 0), 0).toFixed(2)}</td>
                                                </tr>
                                            </>
                                        ) : (
                                            <tr>
                                                <td colSpan={8} className="p-4 text-center text-gray-500 dark:text-gray-400">No credit sales records found</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    {/* 3. Cash Receipts & Payments Grid */}
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        {/* Cash Receipts */}
                        <Card className="dark:border-gray-700 dark:bg-gray-800">
                            <CardHeader>
                                <CardTitle className="text-base font-bold text-green-700 dark:text-green-400">3. Cash Receipts Summary (Inflows)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b bg-gray-50 text-xs font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                                <th className="p-2.5 text-left">Sl</th>
                                                <th className="p-2.5 text-left">Source / Purpose</th>
                                                <th className="p-2.5 text-left">Category</th>
                                                <th className="p-2.5 text-right">Amount (Tk)</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y text-xs dark:divide-gray-700">
                                            {cashReceived.length > 0 ? (
                                                <>
                                                    {cashReceived.map((item, index) => (
                                                        <tr key={index} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                            <td className="p-2.5 dark:text-gray-400">{index + 1}</td>
                                                            <td className="p-2.5 font-medium dark:text-white">{item.account_name}</td>
                                                            <td className="p-2.5 dark:text-gray-300">{item.category || 'Receipt'}</td>
                                                            <td className="p-2.5 text-right font-semibold text-green-600 dark:text-green-400">{Number(item.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                                                        </tr>
                                                    ))}
                                                    <tr className="bg-gray-100 font-bold dark:bg-gray-900/60">
                                                        <td colSpan={3} className="p-2.5 text-green-700 dark:text-green-400">Total Cash Receipts:</td>
                                                        <td className="p-2.5 text-right text-sm font-black text-green-700 dark:text-green-400">{cashReceived.reduce((sum, i) => sum + Number(i.amount || 0), 0).toFixed(2)}</td>
                                                    </tr>
                                                </>
                                            ) : (
                                                <tr>
                                                    <td colSpan={4} className="p-4 text-center text-gray-500 dark:text-gray-400">No cash receipts found</td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Cash Payments */}
                        <Card className="dark:border-gray-700 dark:bg-gray-800">
                            <CardHeader>
                                <CardTitle className="text-base font-bold text-red-700 dark:text-red-400">4. Cash Payments Summary (Outflows)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b bg-gray-50 text-xs font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                                <th className="p-2.5 text-left">Sl</th>
                                                <th className="p-2.5 text-left">Recipient / Purpose</th>
                                                <th className="p-2.5 text-left">Category</th>
                                                <th className="p-2.5 text-right">Amount (Tk)</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y text-xs dark:divide-gray-700">
                                            {cashPayment.length > 0 ? (
                                                <>
                                                    {cashPayment.map((item, index) => (
                                                        <tr key={index} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                            <td className="p-2.5 dark:text-gray-400">{index + 1}</td>
                                                            <td className="p-2.5 font-medium dark:text-white">{item.account_name}</td>
                                                            <td className="p-2.5 dark:text-gray-300">{item.category || 'Expense'}</td>
                                                            <td className="p-2.5 text-right font-semibold text-red-600 dark:text-red-400">{Number(item.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                                                        </tr>
                                                    ))}
                                                    <tr className="bg-gray-100 font-bold dark:bg-gray-900/60">
                                                        <td colSpan={3} className="p-2.5 text-red-700 dark:text-red-400">Total Cash Payments:</td>
                                                        <td className="p-2.5 text-right text-sm font-black text-red-700 dark:text-red-400">{cashPayment.reduce((sum, i) => sum + Number(i.amount || 0), 0).toFixed(2)}</td>
                                                    </tr>
                                                </>
                                            ) : (
                                                <tr>
                                                    <td colSpan={4} className="p-4 text-center text-gray-500 dark:text-gray-400">No cash payments found</td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* 5. Daily Statement Reconciliation Card */}
                    <Card className="border-2 border-gray-300 bg-gray-50 shadow-sm dark:border-gray-700 dark:bg-gray-850">
                        <CardHeader>
                            <CardTitle className="text-lg font-bold dark:text-white">5. Daily Financial Statement Reconciliation</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div className="space-y-3 rounded-lg border bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                    <h4 className="flex items-center gap-2 font-bold text-gray-900 dark:text-white">
                                        <Wallet className="h-5 w-5 text-amber-600" />
                                        Cash Account Reconciliation
                                    </h4>
                                    <div className="space-y-2 text-sm">
                                        <div className="flex justify-between border-b pb-1.5 dark:border-gray-700">
                                            <span className="text-gray-600 dark:text-gray-400">Opening Cash Balance:</span>
                                            <span className="font-semibold dark:text-white">{Number(cashFlow.opening_balance || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                                        </div>
                                        <div className="flex justify-between border-b pb-1.5 text-green-600 dark:border-gray-700 dark:text-green-400">
                                            <span>(+) Total Cash Inflow (Receipts & Cash Sales):</span>
                                            <span className="font-semibold">+{Number(cashFlow.total_receipts || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                                        </div>
                                        <div className="flex justify-between border-b pb-1.5 text-red-600 dark:border-gray-700 dark:text-red-400">
                                            <span>(-) Total Cash Outflow (Expenses & Payments):</span>
                                            <span className="font-semibold">-{Number(cashFlow.total_payments || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                                        </div>
                                        <div className="flex justify-between rounded bg-amber-100 p-2.5 text-base font-black text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                                            <span>CASH IN HAND (Closing Cash):</span>
                                            <span>{Number(cashFlow.closing_balance || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-3 rounded-lg border bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                    <h4 className="flex items-center gap-2 font-bold text-gray-900 dark:text-white">
                                        <Building2 className="h-5 w-5 text-blue-600" />
                                        Bank Accounts Movement
                                    </h4>
                                    <div className="space-y-2 text-sm">
                                        <div className="flex justify-between border-b pb-1.5 dark:border-gray-700">
                                            <span className="text-gray-600 dark:text-gray-400">Opening Bank Balance:</span>
                                            <span className="font-semibold dark:text-white">{Number(bankFlow.opening_balance || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                                        </div>
                                        <div className="flex justify-between border-b pb-1.5 text-green-600 dark:border-gray-700 dark:text-green-400">
                                            <span>(+) Total Bank Inflow:</span>
                                            <span className="font-semibold">+{Number(bankFlow.total_receipts || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                                        </div>
                                        <div className="flex justify-between border-b pb-1.5 text-red-600 dark:border-gray-700 dark:text-red-400">
                                            <span>(-) Total Bank Outflow:</span>
                                            <span className="font-semibold">-{Number(bankFlow.total_payments || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                                        </div>
                                        <div className="flex justify-between rounded bg-blue-100 p-2.5 text-base font-black text-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
                                            <span>CLOSING BANK BALANCE:</span>
                                            <span>{Number(bankFlow.closing_balance || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
