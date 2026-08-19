import { openPdfViewer } from '@/components/documents/pdf-viewer';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Calendar, FileText, Filter, X } from 'lucide-react';
import { useState } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface MonthlySheetRow {
    month: string;
    gross_profit: number;
    office_expense: number;
    cash_payment_md: number;
    net_balance: number;
    remark: string;
}

interface CashHistoryItem {
    particular: string;
    qty?: number | null;
    amount?: number | null;
}

interface BalanceSheetData {
    top_sheet_data?: {
        close_month_year: {
            label: string;
            capital_balance?: number;
            loan_balance?: number;
            total_balance?: number;
            amount: number;
        };
        top_sheet: {
            title: string;
            subtitle: string;
            months: MonthlySheetRow[];
            total_net_balance: number;
            total_profit: number;
        };
        cash_history: {
            items: CashHistoryItem[];
            subtotal: number;
            extra_items: CashHistoryItem[];
        };
        bottom_summary: {
            invest_amount: number;
            profit: number;
            total_invest_profit: number;
            total_amount: number;
            recent_due: number;
            cash: number;
            extra: number;
        };
    };
    [key: string]: any;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Balance Sheet', href: '/balance-sheet' },
];

interface BalanceSheetProps {
    data: BalanceSheetData;
    filters: {
        date?: string;
        start_date?: string;
        end_date?: string;
    };
}

export default function BalanceSheet({ data, filters = {} }: BalanceSheetProps) {
    const { can } = usePermission();
    const canFilter = can('can-account-filter');
    const canDownload = can('can-account-download');

    const [startDate, setStartDate] = useState(
        filters.start_date || new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0]
    );
    const [endDate, setEndDate] = useState(
        filters.end_date || new Date().toISOString().split('T')[0]
    );

    const applyFilters = () => {
        const params: any = {
            start_date: startDate,
            end_date: endDate,
        };
        router.get('/balance-sheet', params, { preserveState: true });
    };

    const clearFilters = () => {
        const startOfYear = new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0];
        const today = new Date().toISOString().split('T')[0];
        setStartDate(startOfYear);
        setEndDate(today);
        router.get('/balance-sheet', {}, { preserveState: true });
    };

    const topSheetData = data.top_sheet_data || {
        close_month_year: {
            label: `Total Balance -- 31-12- ${new Date().getFullYear() - 1}`,
            capital_balance: 12174977.0,
            loan_balance: 0.0,
            total_balance: 12174977.0,
            amount: 12174977.0,
        },
        top_sheet: {
            title: 'Top Sheet',
            subtitle: 'Month wise balance sheet',
            months: [
                { month: 'January', gross_profit: 2651445.0, office_expense: 1425985.0, cash_payment_md: 110000.0, net_balance: 1459479.0, remark: '' },
                { month: 'February', gross_profit: 1897694.0, office_expense: 1203797.0, cash_payment_md: 325000.0, net_balance: 725519.0, remark: '' },
                { month: 'March', gross_profit: 2572568.0, office_expense: 1624320.0, cash_payment_md: 1500000.0, net_balance: -322003.0, remark: '' },
                { month: 'April', gross_profit: 2141998.0, office_expense: 1577281.0, cash_payment_md: 35000.0, net_balance: 529717.0, remark: '' },
                { month: 'May', gross_profit: 2496907.99, office_expense: 1420390.0, cash_payment_md: 130000.0, net_balance: 946517.99, remark: '' },
                { month: 'June', gross_profit: 2854843.0, office_expense: 1454710.0, cash_payment_md: 147000.0, net_balance: 1425523.0, remark: '' },
                { month: 'July', gross_profit: 2893048.0, office_expense: 1300090.0, cash_payment_md: 185000.0, net_balance: 1607958.0, remark: '' },
                { month: 'August', gross_profit: 0.0, office_expense: 0.0, cash_payment_md: 0.0, net_balance: 0.0, remark: '' },
                { month: 'September', gross_profit: 0.0, office_expense: 0.0, cash_payment_md: 0.0, net_balance: 0.0, remark: '' },
                { month: 'October', gross_profit: 0.0, office_expense: 0.0, cash_payment_md: 0.0, net_balance: 0.0, remark: '' },
                { month: 'November', gross_profit: 0.0, office_expense: 0.0, cash_payment_md: 0.0, net_balance: 0.0, remark: '' },
                { month: 'December', gross_profit: 0.0, office_expense: 0.0, cash_payment_md: 0.0, net_balance: 0.0, remark: '' },
            ],
            total_net_balance: 6372710.99,
            total_profit: 6372710.99,
        },
        cash_history: {
            items: [
                { particular: 'bank', qty: null, amount: 700000.0 },
                { particular: 'Pay order', qty: null, amount: null },
                { particular: 'cash', qty: null, amount: 2646750.0 },
                { particular: 'Diesel', qty: 20630, amount: 2372450.0 },
                { particular: 'Octane', qty: 14216, amount: 2061320.0 },
                { particular: 'LPG', qty: 6500, amount: 405600.0 },
            ],
            subtotal: 8186120.0,
            extra_items: [
                { particular: 'Kuddu', qty: null, amount: 997695.0 },
                { particular: 'Cash', qty: null, amount: 7188425.0 },
            ],
        },
        bottom_summary: {
            invest_amount: 12174977.0,
            profit: 6372710.99,
            total_invest_profit: 18547687.99,
            total_amount: 18547687.99,
            recent_due: 11551984.0,
            cash: 6995703.99,
            extra: 192721.01,
        },
    };

    const formatCurrency = (val?: number | null) => {
        if (val === undefined || val === null) return '-';
        if (val < 0) {
            return `(${Math.abs(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })})`;
        }
        return val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Balance Sheet" />

            <div className="space-y-6 p-6">
                {/* Top Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Balance Sheet</h1>
                        <p className="text-gray-600 dark:text-gray-400">View month-wise top sheet and cash history summary</p>
                    </div>
                    {canDownload && (
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                params.append('start_date', startDate);
                                params.append('end_date', endDate);
                                openPdfViewer(`/balance-sheet/download-pdf?${params.toString()}`);
                            }}
                        >
                            <FileText className="mr-2 h-4 w-4" />Download
                        </Button>
                    )}
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
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
                                <div className="flex items-end gap-2 sm:col-span-2">
                                    <Button onClick={applyFilters} className="px-4">Apply Filters</Button>
                                    <Button onClick={clearFilters} variant="secondary" className="px-4">
                                        <X className="mr-2 h-4 w-4" />Clear
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* 1. Top Section: Capital & Loan Balance Table Card */}
                <Card className="dark:border-gray-700 dark:bg-gray-800 shadow-sm">
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-750">
                                        <th className="p-3 text-left text-[13px] font-bold dark:text-gray-200">Close Month And Year</th>
                                        <th className="p-3 text-right text-[13px] font-bold dark:text-gray-200">
                                            Capital Balance
                                            <span className="block text-[11px] font-normal text-gray-500 dark:text-gray-400">Opening Balance + Investment</span>
                                        </th>
                                        <th className="p-3 text-right text-[13px] font-bold dark:text-gray-200">
                                            Loan Balance
                                            <span className="block text-[11px] font-normal text-gray-500 dark:text-gray-400">Loan Received</span>
                                        </th>
                                        <th className="p-3 text-right text-[13px] font-bold dark:text-gray-200">
                                            Total Balance
                                            <span className="block text-[11px] font-normal text-gray-500 dark:text-gray-400">Total Opening Fund</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr className="hover:bg-gray-50 dark:hover:bg-gray-700 text-[13px] font-medium">
                                        <td className="p-3 dark:text-white font-semibold">{topSheetData.close_month_year.label}</td>
                                        <td className="p-3 text-right dark:text-white font-semibold">
                                            {formatCurrency(topSheetData.close_month_year.capital_balance ?? topSheetData.close_month_year.amount)}
                                        </td>
                                        <td className="p-3 text-right dark:text-white font-semibold">
                                            {formatCurrency(topSheetData.close_month_year.loan_balance ?? 0)}
                                        </td>
                                        <td className="p-3 text-right text-emerald-600 dark:text-emerald-400 font-bold text-[14px]">
                                            {formatCurrency(topSheetData.close_month_year.total_balance ?? topSheetData.close_month_year.amount)}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {/* 2. Middle Section: Top Sheet & Cash History */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    {/* Left: Top Sheet (Month-wise Balance Sheet) */}
                    <Card className="lg:col-span-8 dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader className="py-3 px-4 border-b dark:border-gray-700 bg-gray-50/50 dark:bg-gray-750">
                            <CardTitle className="text-[15px] font-bold dark:text-white text-center">
                                Top Sheet - Month-wise Balance Sheet
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-750">
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Month</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Gross Profit</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Office Expense</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Cash Payment (Md Sir)</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Net Balance</th>
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300 w-24">Remark</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {topSheetData.top_sheet.months.map((row, idx) => (
                                            <tr key={idx} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                <td className="p-2 text-[13px] font-medium dark:text-white">{row.month}</td>
                                                <td className="p-2 text-right text-[13px] dark:text-gray-300">{formatCurrency(row.gross_profit)}</td>
                                                <td className="p-2 text-right text-[13px] dark:text-gray-300">{formatCurrency(row.office_expense)}</td>
                                                <td className="p-2 text-right text-[13px] dark:text-gray-300">{formatCurrency(row.cash_payment_md)}</td>
                                                <td className={`p-2 text-right text-[13px] font-semibold ${row.net_balance < 0 ? 'text-red-500 dark:text-red-400' : 'dark:text-white'}`}>
                                                    {formatCurrency(row.net_balance)}
                                                </td>
                                                <td className="p-2 text-[13px] text-gray-500 dark:text-gray-400">{row.remark || '-'}</td>
                                            </tr>
                                        ))}
                                        {/* Total Rows */}
                                        <tr className="border-b font-bold bg-gray-50 dark:bg-gray-700 dark:border-gray-700">
                                            <td colSpan={4} className="p-2 text-right text-[13px] dark:text-white">Total Net Balance:</td>
                                            <td className="p-2 text-right text-[13px] dark:text-white">{formatCurrency(topSheetData.top_sheet.total_net_balance)}</td>
                                            <td></td>
                                        </tr>
                                        <tr className="font-bold bg-gray-100 dark:bg-gray-750 dark:border-gray-700">
                                            <td colSpan={4} className="p-2 text-right text-[13px] dark:text-white">Total Profit:</td>
                                            <td className="p-2 text-right text-[13px] dark:text-white">{formatCurrency(topSheetData.top_sheet.total_profit)}</td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Right: Cash History */}
                    <Card className="lg:col-span-4 dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader className="py-3 px-4 border-b dark:border-gray-700 bg-gray-50/50 dark:bg-gray-750">
                            <CardTitle className="text-[15px] font-bold dark:text-white text-center">
                                Cash History
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-750">
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Particular</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Qty</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {topSheetData.cash_history.items.map((item, idx) => (
                                            <tr key={idx} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                <td className="p-2 text-[13px] capitalize font-medium dark:text-white">{item.particular}</td>
                                                <td className="p-2 text-right text-[13px] dark:text-gray-300">{item.qty ? item.qty.toLocaleString() : '-'}</td>
                                                <td className="p-2 text-right text-[13px] dark:text-gray-300">{formatCurrency(item.amount)}</td>
                                            </tr>
                                        ))}
                                        <tr className="border-b font-bold bg-gray-50 dark:bg-gray-700 dark:border-gray-700">
                                            <td colSpan={2} className="p-2 text-right text-[13px] dark:text-white">Total:</td>
                                            <td className="p-2 text-right text-[13px] dark:text-white">{formatCurrency(topSheetData.cash_history.subtotal)}</td>
                                        </tr>
                                        {topSheetData.cash_history.extra_items && topSheetData.cash_history.extra_items.map((item, idx) => (
                                            <tr key={`extra-${idx}`} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700 font-semibold">
                                                <td colSpan={2} className="p-2 text-left text-[13px] dark:text-white">{item.particular}</td>
                                                <td className="p-2 text-right text-[13px] dark:text-white">{formatCurrency(item.amount)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* 3. Bottom Section: Investment & Profit Summary / Due & Cash Summary */}
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    {/* Bottom Left Summary */}
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader className="py-3 px-4 border-b dark:border-gray-700 bg-gray-50/50 dark:bg-gray-750">
                            <CardTitle className="text-[15px] font-bold dark:text-white">
                                Total Cash & Profit Summary
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-750">
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Total Cash</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                            <td className="p-2 text-[13px] font-medium dark:text-white">Invest Amount</td>
                                            <td className="p-2 text-right text-[13px] dark:text-gray-300">{formatCurrency(topSheetData.bottom_summary.invest_amount)}</td>
                                        </tr>
                                        <tr className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                            <td className="p-2 text-[13px] font-medium dark:text-white">Profit</td>
                                            <td className="p-2 text-right text-[13px] dark:text-gray-300">{formatCurrency(topSheetData.bottom_summary.profit)}</td>
                                        </tr>
                                        <tr className="font-bold bg-indigo-50 dark:bg-indigo-950 dark:border-gray-700">
                                            <td className="p-3 text-[14px] dark:text-white">Total</td>
                                            <td className="p-3 text-right text-[14px] dark:text-white">{formatCurrency(topSheetData.bottom_summary.total_invest_profit)}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Bottom Right Summary */}
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader className="py-3 px-4 border-b dark:border-gray-700 bg-gray-50/50 dark:bg-gray-750">
                            <CardTitle className="text-[15px] font-bold dark:text-white">
                                Total Amount & Due/Cash Summary
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <tbody>
                                        <tr className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700 font-semibold">
                                            <td className="p-2 text-[13px] dark:text-white">Total Amount</td>
                                            <td className="p-2 text-right text-[13px] dark:text-white">{formatCurrency(topSheetData.bottom_summary.total_amount)}</td>
                                        </tr>
                                        <tr className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                            <td className="p-2 text-[13px] font-medium dark:text-white">Recent Due</td>
                                            <td className="p-2 text-right text-[13px] dark:text-gray-300">{formatCurrency(topSheetData.bottom_summary.recent_due)}</td>
                                        </tr>
                                        <tr className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                            <td className="p-2 text-[13px] font-medium dark:text-white">Cash</td>
                                            <td className="p-2 text-right text-[13px] dark:text-gray-300">{formatCurrency(topSheetData.bottom_summary.cash)}</td>
                                        </tr>
                                        <tr className="bg-gray-50 dark:bg-gray-750">
                                            <td className="p-3 text-[13px] font-bold text-gray-700 dark:text-gray-300">Extra</td>
                                            <td className="p-3 text-right text-[13px] font-bold text-gray-700 dark:text-gray-300">{formatCurrency(topSheetData.bottom_summary.extra)}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
