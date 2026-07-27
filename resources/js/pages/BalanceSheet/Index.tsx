import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { FileText, Filter, X } from 'lucide-react';
import { useState } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface BalanceSheetData {
    assets: {
        office_cash: number;
        bank_deposit: number;
        customer_due: number;
        stock_value: number;
        total_assets: number;
    };
    liabilities: {
        purchase_due: number;
        customer_advance: number;
        customer_security: number;
        bank_loan: number;
        total_liabilities: number;
    };
    net_worth: number;
    purchase_data: Array<{
        product_name: string;
        avg_price: number;
        total_quantity: number;
        total_amount: number;
    }>;
    sales_data: Array<{
        product_name: string;
        purchase_price: number;
        sale_price: number;
        effective_date: string;
        total_quantity: number;
        total_amount: number;
    }>;
    credit_sales_data: Array<{
        product_name: string;
        purchase_price: number;
        sale_price: number;
        effective_date: string;
        total_quantity: number;
        total_amount: number;
    }>;
    stock_data: Array<{
        product_name: string;
        quantity: number;
        purchase_price: number;
        stock_value: number;
    }>;
    admin_expenses: Array<{
        expense_type: string;
        total_amount: number;
    }>;
    totals: {
        total_purchases: number;
        total_sales: number;
        total_stock_value: number;
        total_admin_expenses: number;
        gross_profit: number;
        net_profit: number;
    };
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

    const [date, setDate] = useState(filters.date || new Date().toISOString().split('T')[0]);
    const [startDate, setStartDate] = useState(filters.start_date || new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0]);
    const [endDate, setEndDate] = useState(filters.end_date || new Date(Date.now() + 24*60*60*1000).toISOString().split('T')[0]);

    const applyFilters = () => {
        const params: any = {
            start_date: startDate,
            end_date: endDate,
        };
        router.get('/balance-sheet', params, { preserveState: true });
    };

    const clearFilters = () => {
        const today = new Date().toISOString().split('T')[0];
        setStartDate(today);
        setEndDate(today);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Balance Sheet" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Balance Sheet & Financial Notes</h1>
                        <p className="text-gray-600 dark:text-gray-400">View company's financial position with detailed notes</p>
                    </div>
                    {canDownload && (
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                params.append('start_date', startDate);
                                params.append('end_date', endDate);
                                window.location.href = `/balance-sheet/download-pdf?${params.toString()}`;
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

                {/* Purchase Details */}
                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="text-[16px] font-bold dark:text-white mb-2">Total Purchase</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Product</th>
                                        <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Purchase Price</th>
                                        <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Total Liter</th>
                                        <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Total Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.purchase_data.map((item, index) => (
                                        <tr key={index} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                            <td className="p-2 text-[13px] dark:text-white">{item.product_name}</td>
                                            <td className="p-2 text-right text-[13px] dark:text-gray-300">{parseFloat(item.avg_price).toFixed(2)}</td>
                                            <td className="p-2 text-right text-[13px] dark:text-gray-300">{item.total_quantity.toLocaleString()}</td>
                                            <td className="p-2 text-right text-[13px] dark:text-gray-300">{item.total_amount.toLocaleString()}</td>
                                        </tr>
                                    ))}
                                    <tr className="border-b font-bold bg-gray-50 dark:bg-gray-700 dark:border-gray-700">
                                        <td className="p-2 text-[13px] dark:text-white">Total</td>
                                        <td className="p-2 text-right text-[13px] dark:text-white">-</td>
                                        <td className="p-2 text-right text-[13px] dark:text-white">{data.purchase_data.reduce((sum, item) => sum + parseFloat(item.total_quantity), 0).toLocaleString()}</td>
                                        <td className="p-2 text-right text-[13px] dark:text-white">{data.totals.total_purchases.toLocaleString()}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {/* Sales Details */}
                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="text-[16px] font-bold dark:text-white mb-2">Total Sales</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Product</th>
                                        <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Purchase Price</th>
                                        <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Sale Price</th>
                                        <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Effective Date</th>
                                        <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Total Liter</th>
                                        <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Total Amount</th>
                                        <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Total Profit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {[...data.sales_data, ...data.credit_sales_data].map((item, index) => {
                                        const purchasePrice = parseFloat(item.purchase_price);
                                        const salePrice = parseFloat(item.sale_price);
                                        const totalProfit = (salePrice - purchasePrice) * parseFloat(item.total_quantity);
                                        return (
                                            <tr key={index} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                <td className="p-2 text-[13px] dark:text-white">{item.product_name}</td>
                                                <td className="p-2 text-right text-[13px] dark:text-gray-300">{purchasePrice.toFixed(2)}</td>
                                                <td className="p-2 text-right text-[13px] dark:text-gray-300">{salePrice.toFixed(2)}</td>
                                                <td className="p-2 text-right text-[13px] dark:text-gray-300">{item.effective_date}</td>
                                                <td className="p-2 text-right text-[13px] dark:text-gray-300">{item.total_quantity.toLocaleString()}</td>
                                                <td className="p-2 text-right text-[13px] dark:text-gray-300">{item.total_amount.toLocaleString()}</td>
                                                <td className="p-2 text-right text-[13px] dark:text-gray-300">{totalProfit.toLocaleString()}</td>
                                            </tr>
                                        );
                                    })}
                                    <tr className="border-b font-bold bg-gray-50 dark:bg-gray-700 dark:border-gray-700">
                                        <td className="p-2 text-[13px] dark:text-white">Total</td>
                                        <td className="p-2 text-right text-[13px] dark:text-white">-</td>
                                        <td className="p-2 text-right text-[13px] dark:text-white">-</td>
                                        <td className="p-2 text-right text-[13px] dark:text-white">-</td>
                                        <td className="p-2 text-right text-[13px] dark:text-white">{[...data.sales_data, ...data.credit_sales_data].reduce((sum, item) => sum + parseFloat(item.total_quantity), 0).toLocaleString()}</td>
                                        <td className="p-2 text-right text-[13px] dark:text-white">{data.totals.total_sales.toLocaleString()}</td>
                                        <td className="p-2 text-right text-[13px] dark:text-white">{[...data.sales_data, ...data.credit_sales_data].reduce((sum, item) => {
                                            const purchasePrice = parseFloat(item.purchase_price);
                                            const salePrice = parseFloat(item.sale_price);
                                            return sum + ((salePrice - purchasePrice) * parseFloat(item.total_quantity));
                                        }, 0).toLocaleString()}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div className="p-3 bg-gray-50 dark:bg-gray-800">
                            <div className="font-bold text-[14px] dark:text-white mb-2">
                                In Stock: {data.stock_data.reduce((sum, item) => sum + parseFloat(item.quantity || 0), 0).toLocaleString()}
                            </div>
                            <div className="font-bold text-[14px] dark:text-white mb-2">
                                Total Profit: {[...data.sales_data, ...data.credit_sales_data].reduce((sum, item) => {
                                    const purchasePrice = parseFloat(item.purchase_price);
                                    const salePrice = parseFloat(item.sale_price);
                                    return sum + ((salePrice - purchasePrice) * parseFloat(item.total_quantity));
                                }, 0).toLocaleString()}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Profit Summary */}
                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="text-[16px] font-bold dark:text-white mb-2">Profit Summary</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Description</th>
                                        <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                        <td className="p-2 text-[13px] dark:text-white">Total Sales</td>
                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{data.totals.total_sales.toLocaleString()}</td>
                                    </tr>
                                    <tr className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                        <td className="p-2 text-[13px] dark:text-white">Total Purchase</td>
                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">({data.totals.total_purchases.toLocaleString()})</td>
                                    </tr>
                                    <tr className="border-b font-bold bg-gray-50 dark:bg-gray-700 dark:border-gray-700">
                                        <td className="p-2 text-[13px] dark:text-white">Gross Profit</td>
                                        <td className="p-2 text-right text-[13px] dark:text-white">{(data.totals.total_sales - data.totals.total_purchases).toLocaleString()}</td>
                                    </tr>
                                    <tr className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                        <td className="p-2 text-[13px] dark:text-white">Total Admin Expenses</td>
                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">({data.totals.total_admin_expenses.toLocaleString()})</td>
                                    </tr>
                                    <tr className="border-b font-bold bg-green-50 dark:bg-green-700 dark:border-gray-700">
                                        <td className="p-2 text-[13px] dark:text-white">Net Profit</td>
                                        <td className="p-2 text-right text-[13px] dark:text-white">{((data.totals.total_sales - data.totals.total_purchases) - data.totals.total_admin_expenses).toLocaleString()}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div className="p-3 bg-gray-50 dark:bg-gray-800">
                            <div className="font-bold text-[14px] dark:text-white">
                                In Stock: {data.stock_data.reduce((sum, item) => sum + parseFloat(item.quantity || 0), 0).toLocaleString()}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Admin Expenses */}
                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="text-[16px] font-bold dark:text-white mb-2">General Admin Expenses</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Expense Type</th>
                                        <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.admin_expenses.map((expense, index) => (
                                        <tr key={index} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                            <td className="p-2 text-[13px] dark:text-white">{expense.expense_type}</td>
                                            <td className="p-2 text-right text-[13px] dark:text-gray-300">{expense.total_amount.toLocaleString()}</td>
                                        </tr>
                                    ))}
                                    <tr className="border-b font-bold bg-gray-50 dark:bg-gray-700 dark:border-gray-700">
                                        <td className="p-2 text-[13px] dark:text-white">Total Admin Expenses</td>
                                        <td className="p-2 text-right text-[13px] dark:text-white">{data.totals.total_admin_expenses.toLocaleString()}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}