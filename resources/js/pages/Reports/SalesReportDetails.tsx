import { openPdfViewer } from '@/components/documents/pdf-viewer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePermission } from '@/hooks/usePermission';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    Building2,
    Calendar,
    Car,
    Clock,
    CreditCard,
    DollarSign,
    FileSpreadsheet,
    FileText,
    Filter,
    Package,
    RotateCcw,
    Users,
} from 'lucide-react';
import { useState } from 'react';

interface SaleItem {
    item_id: number;
    sale_id: number;
    date: string;
    customer_name: string;
    vehicle_no: string;
    invoice_no: string;
    memo_no: string;
    done_by: string;
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
        vehicle_id?: number | null;
        vehicle?: string | null;
        product_id?: number | null;
        payment_type?: string | null;
        shift_id?: number | null;
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
    vehicle_number?: string;
    product_name?: string;
}

interface PaymentTypeOption {
    value: string;
    label: string;
}

interface SalesReportDetailsProps {
    report: ReportData;
    filters: ReportData['filters'];
    customers: DropdownOption[];
    vehicles: DropdownOption[];
    products: DropdownOption[];
    shifts: DropdownOption[];
    paymentTypes: PaymentTypeOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Sales Report', href: '/sales-report' },
];

export default function SalesReportDetails({
    report,
    filters = { start_date: '', end_date: '' },
    customers = [],
    vehicles = [],
    products = [],
    shifts = [],
    paymentTypes = [],
}: SalesReportDetailsProps) {
    const { can } = usePermission();
    const canDownload = can('can-sale-download') || can('view-sale');

    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');
    const [customerId, setCustomerId] = useState(filters.customer_id ? String(filters.customer_id) : 'all');
    const [vehicleId, setVehicleId] = useState(filters.vehicle_id ? String(filters.vehicle_id) : 'all');
    const [productId, setProductId] = useState(filters.product_id ? String(filters.product_id) : 'all');
    const [paymentType, setPaymentType] = useState(filters.payment_type || 'all');
    const [shiftId, setShiftId] = useState(filters.shift_id ? String(filters.shift_id) : 'all');

    const formatDate = (dateStr: string) => {
        if (!dateStr) return 'N/A';
        try {
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            });
        } catch {
            return dateStr;
        }
    };

    const formatCurrency = (amount: number) => {
        return Number(amount || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    };

    const applyFilters = () => {
        const params: Record<string, string> = {};
        if (startDate) params.start_date = startDate;
        if (endDate) params.end_date = endDate;
        if (customerId && customerId !== 'all') params.customer_id = customerId;
        if (vehicleId && vehicleId !== 'all') params.vehicle_id = vehicleId;
        if (productId && productId !== 'all') params.product_id = productId;
        if (paymentType && paymentType !== 'all') params.payment_type = paymentType;
        if (shiftId && shiftId !== 'all') params.shift_id = shiftId;

        router.get('/sales-report', params, { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        setStartDate('');
        setEndDate('');
        setCustomerId('all');
        setVehicleId('all');
        setProductId('all');
        setPaymentType('all');
        setShiftId('all');

        router.get('/sales-report', {}, { preserveState: true, replace: true });
    };

    const handleDownloadPdf = () => {
        const params = new URLSearchParams();
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        if (customerId && customerId !== 'all') params.append('customer_id', customerId);
        if (vehicleId && vehicleId !== 'all') params.append('vehicle_id', vehicleId);
        if (productId && productId !== 'all') params.append('product_id', productId);
        if (paymentType && paymentType !== 'all') params.append('payment_type', paymentType);
        if (shiftId && shiftId !== 'all') params.append('shift_id', shiftId);

        openPdfViewer(`/sales-report/download-pdf?${params.toString()}`);
    };

    const getTypeBadgeClass = (type: string) => {
        switch (type) {
            case 'Cash':
                return 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300';
            case 'Bank':
                return 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-950 dark:text-blue-300';
            case 'Mobile Bank':
                return 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950 dark:text-amber-300';
            case 'Credit':
                return 'bg-rose-100 text-rose-800 border-rose-300 dark:bg-rose-950 dark:text-rose-300';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-300 dark:bg-gray-800 dark:text-gray-300';
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sales Report (Details)" />

            <div className="space-y-6 p-4 sm:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl sm:text-3xl font-bold tracking-tight dark:text-white">
                            Sales Report (Details)
                        </h1>
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            Comprehensive customer-wise grouped sales report with multi-criteria filtering
                        </p>
                    </div>

                    {canDownload && (
                        <div className="flex items-center gap-2">
                            <Button
                                variant="success"
                                onClick={handleDownloadPdf}
                                className="shadow-sm"
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Download PDF
                            </Button>
                        </div>
                    )}
                </div>

                {/* KPI Summary Cards */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Card className="dark:border-gray-700 dark:bg-gray-800 shadow-sm">
                        <CardContent className="p-4 flex items-center gap-3">
                            <div className="rounded-lg bg-blue-100 p-2.5 dark:bg-blue-950 text-blue-600 dark:text-blue-400">
                                <Users className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-gray-500 dark:text-gray-400">Customers</p>
                                <p className="text-lg font-bold dark:text-white">{report.total_customers}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800 shadow-sm">
                        <CardContent className="p-4 flex items-center gap-3">
                            <div className="rounded-lg bg-indigo-100 p-2.5 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400">
                                <FileSpreadsheet className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-gray-500 dark:text-gray-400">Invoices</p>
                                <p className="text-lg font-bold dark:text-white">{report.total_invoices}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800 shadow-sm">
                        <CardContent className="p-4 flex items-center gap-3">
                            <div className="rounded-lg bg-amber-100 p-2.5 dark:bg-amber-950 text-amber-600 dark:text-amber-400">
                                <Package className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-gray-500 dark:text-gray-400">Total Quantity</p>
                                <p className="text-lg font-bold dark:text-white">{formatCurrency(report.grand_total_quantity)}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800 shadow-sm">
                        <CardContent className="p-4 flex items-center gap-3">
                            <div className="rounded-lg bg-emerald-100 p-2.5 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">
                                <DollarSign className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-gray-500 dark:text-gray-400">Grand Total Sales</p>
                                <p className="text-lg font-bold text-emerald-600 dark:text-emerald-400">
                                    {formatCurrency(report.grand_total_amount)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters Section */}
                <Card className="dark:border-gray-700 dark:bg-gray-800 shadow-sm">
                    <CardHeader className="pb-3 border-b dark:border-gray-700">
                        <CardTitle className="text-base font-semibold flex items-center gap-2 dark:text-white">
                            <Filter className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                            Filter Criteria
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-4 space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {/* Start Date */}
                            <div className="space-y-1.5">
                                <Label htmlFor="start_date" className="text-xs font-semibold flex items-center gap-1.5">
                                    <Calendar className="h-3.5 w-3.5 text-gray-500" />
                                    Start Date
                                </Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="h-9 text-xs"
                                />
                            </div>

                            {/* End Date */}
                            <div className="space-y-1.5">
                                <Label htmlFor="end_date" className="text-xs font-semibold flex items-center gap-1.5">
                                    <Calendar className="h-3.5 w-3.5 text-gray-500" />
                                    End Date
                                </Label>
                                <Input
                                    id="end_date"
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="h-9 text-xs"
                                />
                            </div>

                            {/* Customer */}
                            <div className="space-y-1.5">
                                <Label className="text-xs font-semibold flex items-center gap-1.5">
                                    <Building2 className="h-3.5 w-3.5 text-gray-500" />
                                    Customer
                                </Label>
                                <Select value={customerId} onValueChange={setCustomerId}>
                                    <SelectTrigger className="h-9 text-xs">
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

                            {/* Vehicle */}
                            <div className="space-y-1.5">
                                <Label className="text-xs font-semibold flex items-center gap-1.5">
                                    <Car className="h-3.5 w-3.5 text-gray-500" />
                                    Vehicle
                                </Label>
                                <Select value={vehicleId} onValueChange={setVehicleId}>
                                    <SelectTrigger className="h-9 text-xs">
                                        <SelectValue placeholder="All Vehicles" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Vehicles</SelectItem>
                                        {vehicles.map((v) => (
                                            <SelectItem key={v.id} value={String(v.id)}>
                                                {v.vehicle_number}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Product */}
                            <div className="space-y-1.5">
                                <Label className="text-xs font-semibold flex items-center gap-1.5">
                                    <Package className="h-3.5 w-3.5 text-gray-500" />
                                    Product
                                </Label>
                                <Select value={productId} onValueChange={setProductId}>
                                    <SelectTrigger className="h-9 text-xs">
                                        <SelectValue placeholder="All Products" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Products</SelectItem>
                                        {products.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>
                                                {p.product_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Payment Type */}
                            <div className="space-y-1.5">
                                <Label className="text-xs font-semibold flex items-center gap-1.5">
                                    <CreditCard className="h-3.5 w-3.5 text-gray-500" />
                                    Payment Type
                                </Label>
                                <Select value={paymentType} onValueChange={setPaymentType}>
                                    <SelectTrigger className="h-9 text-xs">
                                        <SelectValue placeholder="All Payment Types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Payment Types</SelectItem>
                                        {paymentTypes.map((pt) => (
                                            <SelectItem key={pt.value} value={pt.value}>
                                                {pt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Shift */}
                            <div className="space-y-1.5">
                                <Label className="text-xs font-semibold flex items-center gap-1.5">
                                    <Clock className="h-3.5 w-3.5 text-gray-500" />
                                    Shift
                                </Label>
                                <Select value={shiftId} onValueChange={setShiftId}>
                                    <SelectTrigger className="h-9 text-xs">
                                        <SelectValue placeholder="All Shifts" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Shifts</SelectItem>
                                        {shifts.map((s) => (
                                            <SelectItem key={s.id} value={String(s.id)}>
                                                {s.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Filter Actions */}
                            <div className="flex items-end gap-2 pt-2 sm:pt-0">
                                <Button
                                    onClick={applyFilters}
                                    className="flex-1 h-9 text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-medium"
                                >
                                    <Filter className="mr-1.5 h-3.5 w-3.5" />
                                    Filter
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={clearFilters}
                                    className="h-9 text-xs font-medium dark:border-gray-700"
                                >
                                    <RotateCcw className="mr-1.5 h-3.5 w-3.5" />
                                    Reset
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Customer-Wise Grouped Sales Report Tables */}
                {report.customers.length === 0 ? (
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-12 text-center">
                            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-gray-400">
                                <FileSpreadsheet className="h-6 w-6" />
                            </div>
                            <h3 className="mt-3 text-sm font-semibold text-gray-900 dark:text-white">No sales records found</h3>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Try adjusting your date range or filter criteria to see sales data.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-6">
                        {report.customers.map((customerGroup, idx) => (
                            <Card key={idx} className="dark:border-gray-700 dark:bg-gray-800 overflow-hidden shadow-sm">
                                <CardHeader className="py-3 px-4 bg-slate-50 dark:bg-slate-800/80 border-b dark:border-gray-700">
                                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                        <CardTitle className="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                            <Building2 className="h-4 w-4 text-indigo-500" />
                                            Customer: {customerGroup.customer_name}
                                        </CardTitle>
                                        <span className="text-xs text-slate-500 dark:text-slate-400">
                                            {customerGroup.sales.length} items recorded
                                        </span>
                                    </div>
                                </CardHeader>

                                <CardContent className="p-0">
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-left border-collapse">
                                            <thead>
                                                <tr className="border-b bg-gray-50/50 dark:bg-gray-800/50 dark:border-gray-700 text-[11px] font-semibold text-gray-600 dark:text-gray-300">
                                                    <th className="py-2.5 px-3">Date</th>
                                                    <th className="py-2.5 px-3">Vehicle</th>
                                                    <th className="py-2.5 px-3">Invoice No</th>
                                                    <th className="py-2.5 px-3">Memo No</th>
                                                    <th className="py-2.5 px-3">Done By</th>
                                                    <th className="py-2.5 px-3">Product</th>
                                                    <th className="py-2.5 px-3">Unit</th>
                                                    <th className="py-2.5 px-3 text-right">Quantity</th>
                                                    <th className="py-2.5 px-3 text-right">Price</th>
                                                    <th className="py-2.5 px-3 text-right">Total</th>
                                                    <th className="py-2.5 px-3 text-center">Type</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y dark:divide-gray-700 text-xs">
                                                {customerGroup.sales.map((sale, sIdx) => (
                                                    <tr
                                                        key={`${sale.sale_id}-${sale.item_id}-${sIdx}`}
                                                        className="hover:bg-gray-50/80 dark:hover:bg-gray-700/40 transition-colors"
                                                    >
                                                        <td className="py-2 px-3 whitespace-nowrap font-medium dark:text-gray-200">
                                                            {formatDate(sale.date)}
                                                        </td>
                                                        <td className="py-2 px-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                                            {sale.vehicle_no || 'N/A'}
                                                        </td>
                                                        <td className="py-2 px-3 whitespace-nowrap font-medium text-indigo-600 dark:text-indigo-400">
                                                            {sale.invoice_no}
                                                        </td>
                                                        <td className="py-2 px-3 whitespace-nowrap text-gray-600 dark:text-gray-400">
                                                            {sale.memo_no || 'N/A'}
                                                        </td>
                                                        <td className="py-2 px-3 whitespace-nowrap text-gray-600 dark:text-gray-400">
                                                            {sale.done_by || 'N/A'}
                                                        </td>
                                                        <td className="py-2 px-3 font-medium text-gray-800 dark:text-gray-200">
                                                            {sale.product_name}
                                                        </td>
                                                        <td className="py-2 px-3 text-gray-600 dark:text-gray-400">
                                                            {sale.unit_name}
                                                        </td>
                                                        <td className="py-2 px-3 text-right font-medium dark:text-gray-200">
                                                            {formatCurrency(sale.quantity)}
                                                        </td>
                                                        <td className="py-2 px-3 text-right dark:text-gray-300">
                                                            {formatCurrency(sale.unit_price)}
                                                        </td>
                                                        <td className="py-2 px-3 text-right font-bold text-gray-900 dark:text-white">
                                                            {formatCurrency(sale.total_amount)}
                                                        </td>
                                                        <td className="py-2 px-3 text-center whitespace-nowrap">
                                                            <Badge
                                                                variant="outline"
                                                                className={`text-[10px] font-semibold border ${getTypeBadgeClass(
                                                                    sale.type
                                                                )}`}
                                                            >
                                                                {sale.type}
                                                            </Badge>
                                                        </td>
                                                    </tr>
                                                ))}

                                                {/* Customer Subtotal Row */}
                                                <tr className="bg-slate-100/75 dark:bg-slate-800/90 font-bold border-t-2 border-slate-300 dark:border-gray-600">
                                                    <td colSpan={7} className="py-2.5 px-3 text-right text-slate-700 dark:text-slate-200">
                                                        Total From {customerGroup.customer_name} :
                                                    </td>
                                                    <td className="py-2.5 px-3 text-right text-slate-800 dark:text-slate-100">
                                                        {formatCurrency(customerGroup.total_quantity)}
                                                    </td>
                                                    <td className="py-2.5 px-3"></td>
                                                    <td className="py-2.5 px-3 text-right text-slate-900 dark:text-white text-sm">
                                                        {formatCurrency(customerGroup.total_amount)}
                                                    </td>
                                                    <td className="py-2.5 px-3"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}

                        {/* Grand Total Summary Banner */}
                        <Card className="border-2 border-indigo-600 bg-indigo-50/50 dark:bg-slate-800 dark:border-indigo-500 shadow-md">
                            <CardContent className="p-4 sm:p-6">
                                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                    <div>
                                        <p className="text-xs font-semibold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">
                                            Summary of Report
                                        </p>
                                        <p className="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                                            {report.total_customers} Customer{report.total_customers !== 1 ? 's' : ''} &bull;{' '}
                                            {report.total_invoices} Invoice{report.total_invoices !== 1 ? 's' : ''} &bull;{' '}
                                            {report.total_rows} Total Item Row{report.total_rows !== 1 ? 's' : ''}
                                        </p>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-6 sm:text-right">
                                        <div>
                                            <p className="text-xs text-gray-500 dark:text-gray-400 font-medium">Grand Total Quantity</p>
                                            <p className="text-lg font-bold text-gray-900 dark:text-white">
                                                {formatCurrency(report.grand_total_quantity)}
                                            </p>
                                        </div>

                                        <div className="border-l pl-6 dark:border-gray-700">
                                            <p className="text-xs text-gray-500 dark:text-gray-400 font-medium">Grand Total Sales</p>
                                            <p className="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400">
                                                {formatCurrency(report.grand_total_amount)}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
