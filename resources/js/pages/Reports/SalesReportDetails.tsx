import { openPdfViewer } from '@/components/documents/pdf-viewer';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermission } from '@/hooks/usePermission';
import AppLayout from '@/layouts/app-layout';
import { numberToWords } from '@/lib/utils';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { FileText, Filter, X } from 'lucide-react';
import { useState } from 'react';

interface SaleItem {
    item_id: number;
    sale_id: number;
    date: string;
    customer_name: string;
    vehicle_no: string;
    invoice_no: string;
    memo_no: string;
    product_name: string;
    unit_name: string;
    quantity: number;
    unit_price: number;
    total_amount: number;
    type: 'Cash' | 'Bank' | 'Credit' | 'Mobile Bank';
}

interface CustomerGroup {
    customer_name: string;
    sales: SaleItem[];
    total_quantity: number;
    total_amount: number;
}

interface ReportData {
    filters: {
        start_date: string;
        end_date: string;
        customer_id?: number | null;
        customer?: string | null;
    };
    customers: CustomerGroup[];
    grand_total_quantity: number;
    grand_total_amount: number;
    total_customers: number;
    total_invoices: number;
    total_rows: number;
}

interface DropdownOption {
    id: number;
    name?: string;
}

interface SalesReportDetailsProps {
    report: ReportData;
    filters: ReportData['filters'];
    customers: DropdownOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Sales Report', href: '/sales-report' },
];

export default function SalesReportDetails({
    report = {
        filters: { start_date: '', end_date: '' },
        customers: [],
        grand_total_quantity: 0,
        grand_total_amount: 0,
        total_customers: 0,
        total_invoices: 0,
        total_rows: 0,
    },
    filters = { start_date: '', end_date: '' },
    customers = [],
}: SalesReportDetailsProps) {
    const { can } = usePermission();
    const canFilter = can('can-sale-filter') || can('view-sale');
    const canDownload = can('can-sale-download') || can('view-sale');

    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');
    const [customerId, setCustomerId] = useState(filters.customer_id ? String(filters.customer_id) : 'all');

    const formatDate = (dateStr: string) => {
        if (!dateStr) return 'N/A';
        try {
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-GB');
        } catch {
            return dateStr;
        }
    };

    const applyFilters = () => {
        const params: Record<string, string> = {};
        if (startDate) params.start_date = startDate;
        if (endDate) params.end_date = endDate;
        if (customerId && customerId !== 'all') params.customer_id = customerId;

        router.get('/sales-report', params, { preserveState: true });
    };

    const clearFilters = () => {
        setStartDate('');
        setEndDate('');
        setCustomerId('all');

        router.get('/sales-report', {}, { preserveState: true });
    };

    const handleDownloadPdf = () => {
        const params = new URLSearchParams();
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        if (customerId && customerId !== 'all') params.append('customer_id', customerId);

        openPdfViewer(`/sales-report/download-pdf?${params.toString()}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sales Report" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Sales Report</h1>
                        <p className="text-gray-600 dark:text-gray-400">View sales report details</p>
                    </div>
                    {canDownload && (
                        <Button
                            variant="success"
                            onClick={handleDownloadPdf}
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
                                    <Select value={customerId} onValueChange={setCustomerId}>
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="All Customers" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Customers</SelectItem>
                                            {customers.map((c) => (
                                                <SelectItem key={c.id} value={String(c.id)}>
                                                    {c.name}
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
                                    <Button onClick={applyFilters} className="px-4">
                                        Apply Filters
                                    </Button>
                                    <Button onClick={clearFilters} variant="secondary" className="px-4">
                                        <X className="mr-2 h-4 w-4" />Clear
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="space-y-6">
                    {report.customers.length > 0 ? (
                        report.customers.map((customerGroup, customerIndex) => (
                            <Card key={customerIndex} className="dark:border-gray-700 dark:bg-gray-800">
                                <CardHeader>
                                    <CardTitle className="text-[16px] font-bold dark:text-white">
                                        {customerGroup.customer_name}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="p-0">
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="border-b dark:border-gray-700">
                                                    <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Date</th>
                                                    <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Vehicle</th>
                                                    <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Invoice No</th>
                                                    <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Memo No</th>
                                                    <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Product</th>
                                                    <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Unit</th>
                                                    <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Quantity</th>
                                                    <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Price</th>
                                                    <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Total</th>
                                                    <th className="p-2 text-center text-[13px] font-medium dark:text-gray-300">Type</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {customerGroup.sales.map((sale, saleIndex) => (
                                                    <tr
                                                        key={saleIndex}
                                                        className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                                    >
                                                        <td className="p-2 text-[13px] dark:text-white">{formatDate(sale.date)}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300">{sale.vehicle_no || 'N/A'}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300">{sale.invoice_no}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300">{sale.memo_no || 'N/A'}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300">{sale.product_name}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300">{sale.unit_name}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">
                                                            {parseFloat(sale.quantity.toString()).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                        </td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">
                                                            {parseFloat(sale.unit_price.toString()).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                        </td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">
                                                            {parseFloat(sale.total_amount.toString()).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                        </td>
                                                        <td className="p-2 text-center text-[13px] dark:text-gray-300">{sale.type}</td>
                                                    </tr>
                                                ))}
                                                <tr className="border-b font-bold bg-gray-50 dark:bg-gray-700 dark:border-gray-700">
                                                    <td colSpan={6} className="p-2 text-[13px] dark:text-white">
                                                        Total From {customerGroup.customer_name} :
                                                    </td>
                                                    <td className="p-2 text-right text-[13px] dark:text-white">
                                                        {customerGroup.total_quantity.toFixed(2)}
                                                    </td>
                                                    <td className="p-2"></td>
                                                    <td className="p-2 text-right text-[13px] dark:text-white">
                                                        {customerGroup.total_amount.toFixed(2)}
                                                    </td>
                                                    <td className="p-2"></td>
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

                    {report.customers.length > 0 && (
                        <Card className="dark:border-gray-700 dark:bg-gray-800">
                            <CardContent className="p-4">
                                <div className="font-bold text-[16px] dark:text-white mb-2">
                                    Grand Total Sales: {report.grand_total_amount.toFixed(2)}
                                </div>
                                <div className="text-[14px] italic dark:text-gray-300">
                                    In words: {numberToWords(Math.floor(report.grand_total_amount))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
