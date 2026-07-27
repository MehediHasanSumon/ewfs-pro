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
import { numberToWords } from '@/lib/utils';
import { usePermission } from '@/hooks/usePermission';

interface CustomerSale {
    sale_date: string;
    customer: string;
    shift_name: string;
    invoice_no: string;
    quantity: number;
    total_amount: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Customer Sales Reports', href: '/customer-wise-sales-reports' },
];

interface CustomerSalesReportsProps {
    customerSales: CustomerSale[];
    customers: string[];
    filters: {
        customer?: string;
        start_date?: string;
        end_date?: string;
    };
}

export default function CustomerSalesReports({ customerSales = [], customers = [], filters = {} }: CustomerSalesReportsProps) {
    const { can } = usePermission();
    const canFilter = can('can-sale-filter');
    const canDownload = can('can-sale-download');

    const [customer, setCustomer] = useState(filters.customer || 'all');
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');

    const applyFilters = () => {
        const params: any = {};
        if (startDate) {
            params.start_date = startDate;
        }
        if (endDate) {
            params.end_date = endDate;
        }
        if (customer && customer !== 'all') {
            params.customer = customer;
        }
        router.get('/customer-wise-sales-reports', params, { preserveState: true });
    };

    const clearFilters = () => {
        setCustomer('all');
        setStartDate('');
        setEndDate('');
        router.get('/customer-wise-sales-reports', {}, { preserveState: true });
    };

    const totalQuantity = customerSales.reduce((sum, sale) => sum + parseFloat(sale.quantity.toString()), 0);
    const totalAmount = customerSales.reduce((sum, sale) => sum + parseFloat(sale.total_amount.toString()), 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Customer Sales Reports" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Customer Sales Reports</h1>
                        <p className="text-gray-600 dark:text-gray-400">View customer wise sales summary</p>
                    </div>
                    {canDownload && (
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                if (startDate) params.append('start_date', startDate);
                                if (endDate) params.append('end_date', endDate);
                                if (customer && customer !== 'all') params.append('customer', customer);
                                window.location.href = `/customer-wise-sales-reports/download-pdf?${params.toString()}`;
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
                                    <Label className="dark:text-gray-200">Customer</Label>
                                    <Select value={customer} onValueChange={setCustomer}>
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="All customers" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Customers</SelectItem>
                                            {customers.map((customerName) => (
                                                <SelectItem key={customerName} value={customerName}>
                                                    {customerName}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
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
                                <div className="flex items-end gap-2">
                                    <Button onClick={applyFilters} className="px-4">Apply Filters</Button>
                                    <Button onClick={clearFilters} variant="secondary" className="px-4">
                                        <X className="mr-2 h-4 w-4" />Clear
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="space-y-6">
                    {customerSales.length > 0 ? (
                        <Card className="dark:border-gray-700 dark:bg-gray-800">
                            <CardContent className="p-0">
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b dark:border-gray-700">
                                                <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Date</th>
                                                <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Customer</th>
                                                <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Shift</th>
                                                <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Invoice No</th>
                                                <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Quantity</th>
                                                <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Amount</th>
                                                <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Total Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {customerSales.map((sale, index) => (
                                                <tr key={index} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                    <td className="p-2 text-[13px] dark:text-white">{new Date(sale.sale_date).toLocaleDateString('en-GB')}</td>
                                                    <td className="p-2 text-[13px] dark:text-gray-300">{sale.customer}</td>
                                                    <td className="p-2 text-[13px] dark:text-gray-300">{sale.shift_name}</td>
                                                    <td className="p-2 text-[13px] dark:text-gray-300">{sale.invoice_no}</td>
                                                    <td className="p-2 text-right text-[13px] dark:text-gray-300">{parseFloat(sale.quantity.toString()).toLocaleString()}</td>
                                                    <td className="p-2 text-right text-[13px] dark:text-gray-300">{parseFloat(sale.total_amount.toString()).toLocaleString()}</td>
                                                    <td className="p-2 text-right text-[13px] dark:text-gray-300">{parseFloat(sale.total_amount.toString()).toLocaleString()}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                        <tfoot>
                                            <tr className="border-t font-bold bg-gray-50 dark:bg-gray-700 dark:border-gray-700">
                                                <td colSpan={5} className="p-2 text-[13px] dark:text-white">Grand Total:</td>
                                                <td className="p-2 text-right text-[13px] dark:text-white">{totalAmount.toFixed(2)}</td>
                                                <td className="p-2 text-right text-[13px] dark:text-white">{totalAmount.toFixed(2)}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card className="dark:border-gray-700 dark:bg-gray-800">
                            <CardContent>
                                <p className="p-4 text-center text-[13px] text-gray-500 dark:text-gray-400">No records</p>
                            </CardContent>
                        </Card>
                    )}
                    
                    {customerSales.length > 0 && (
                        <Card className="dark:border-gray-700 dark:bg-gray-800">
                            <CardContent className="p-4">
                                <div className="font-bold text-[16px] dark:text-white mb-2">
                                    Grand Total: {totalAmount.toFixed(2)}
                                </div>
                                <div className="text-[14px] italic dark:text-gray-300">
                                    In words: {numberToWords(Math.floor(totalAmount))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}