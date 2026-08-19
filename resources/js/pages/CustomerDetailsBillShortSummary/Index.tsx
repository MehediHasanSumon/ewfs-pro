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
import { FileText, Filter, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface ProductSummaryItem {
    sn: number;
    product_name: string;
    unit_name: string;
    price: number;
    quantity: number;
    total_amount: number;
}

interface ShortSummaryReport {
    customer: {
        id: number;
        name: string;
        mobile?: string | null;
        address?: string | null;
    };
    period: {
        start_date: string;
        end_date: string;
        formatted: string;
    };
    selected_vehicle?: {
        id: number;
        vehicle_number: string;
    } | null;
    product_summary: ProductSummaryItem[];
    total_slip_quantity: number;
    total: number;
    total_quantity: number;
    vat_percent: number;
    vat_amount: number;
    grand_total: number;
    amount_in_words: string;
}

interface Customer {
    id: number;
    name: string;
}

interface Vehicle {
    id: number;
    vehicle_number: string;
    customer_id: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Customer Details Bill (Short Summary)', href: '/customer-details-bill-short-summary' },
];

interface CustomerDetailsBillShortSummaryProps {
    report?: ShortSummaryReport | null;
    customers: Customer[];
    vehicles: Vehicle[];
    filters: {
        customer_id?: string;
        vehicle_id?: string;
        start_date?: string;
        end_date?: string;
    };
}

export default function CustomerDetailsBillShortSummary({
    report = null,
    customers = [],
    vehicles = [],
    filters = {},
}: CustomerDetailsBillShortSummaryProps) {
    const { can } = usePermission();
    const canFilter = can('can-customer-filter');
    const canDownload = can('can-customer-download');

    const defaultStartDate = useMemo(() => {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        return `${year}-${month}-01`;
    }, []);

    const [customerId, setCustomerId] = useState(filters.customer_id || '');
    const [vehicleId, setVehicleId] = useState(filters.vehicle_id || 'all');
    const [startDate, setStartDate] = useState(filters.start_date || defaultStartDate);
    const [endDate, setEndDate] = useState(filters.end_date || new Date().toISOString().split('T')[0]);

    const availableVehicles = useMemo(() => {
        if (!customerId) return [];
        return vehicles.filter((v) => v.customer_id.toString() === customerId);
    }, [vehicles, customerId]);

    const handleCustomerChange = (val: string) => {
        setCustomerId(val);
        setVehicleId('all');
    };

    const applyFilters = () => {
        const params: any = {
            start_date: startDate,
            end_date: endDate,
        };
        if (customerId) {
            params.customer_id = customerId;
        }
        if (vehicleId && vehicleId !== 'all') {
            params.vehicle_id = vehicleId;
        }
        router.get('/customer-details-bill-short-summary', params, { preserveState: true });
    };

    const clearFilters = () => {
        setCustomerId('');
        setVehicleId('all');
        setStartDate(defaultStartDate);
        setEndDate(new Date().toISOString().split('T')[0]);
        router.get('/customer-details-bill-short-summary', {}, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Customer Details Bill (Short Summary)" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Customer Details Bill (Short Summary)</h1>
                        <p className="text-gray-600 dark:text-gray-400">View customer credit sales short summary report</p>
                    </div>
                    {canDownload && (
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                params.append('start_date', startDate);
                                params.append('end_date', endDate);
                                if (customerId) {
                                    params.append('customer_id', customerId);
                                }
                                if (vehicleId && vehicleId !== 'all') {
                                    params.append('vehicle_id', vehicleId);
                                }
                                openPdfViewer(`/customer-details-bill-short-summary/download-pdf?${params.toString()}`);
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
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                <div>
                                    <Label className="dark:text-gray-200">Customer</Label>
                                    <Select value={customerId} onValueChange={handleCustomerChange}>
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="Select customer" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {customers.map((customer) => (
                                                <SelectItem key={customer.id} value={customer.id.toString()}>
                                                    {customer.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label className="dark:text-gray-200">Vehicle</Label>
                                    <Select
                                        value={vehicleId}
                                        onValueChange={setVehicleId}
                                        disabled={!customerId}
                                    >
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder={customerId ? 'All Vehicles' : 'Select customer first'} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Vehicles</SelectItem>
                                            {availableVehicles.map((vehicle) => (
                                                <SelectItem key={vehicle.id} value={vehicle.id.toString()}>
                                                    {vehicle.vehicle_number}
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
                                <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-1">
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
                    {report && report.product_summary ? (
                        <Card className="dark:border-gray-700 dark:bg-gray-800">
                            <CardContent className="p-0">
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-750">
                                                <th className="p-2 text-center text-[13px] font-medium dark:text-gray-300 w-12">SL</th>
                                                <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Product Name</th>
                                                <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Sales Price</th>
                                                <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Quantity</th>
                                                <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {report.product_summary.length > 0 ? (
                                                report.product_summary.map((item, index) => (
                                                    <tr key={index} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                        <td className="p-2 text-center text-[13px] dark:text-gray-300">{item.sn || index + 1}</td>
                                                        <td className="p-2 text-[13px] font-medium dark:text-white">{item.product_name}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{item.price.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{item.quantity.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{item.total_amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                    </tr>
                                                ))
                                            ) : (
                                                <tr>
                                                    <td colSpan={5} className="p-4 text-center text-[13px] text-gray-500 dark:text-gray-400">
                                                        No records found for the selected period
                                                    </td>
                                                </tr>
                                            )}
                                            <tr className="border-b font-bold bg-gray-50 dark:bg-gray-700 dark:border-gray-700">
                                                <td colSpan={3} className="p-2 text-right text-[13px] dark:text-white">Total:</td>
                                                <td className="p-2 text-right text-[13px] dark:text-white">{report.total_quantity.toFixed(2)}</td>
                                                <td className="p-2 text-right text-[13px] dark:text-white">{report.total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card className="dark:border-gray-700 dark:bg-gray-800">
                            <CardContent>
                                <p className="p-4 text-center text-[13px] text-gray-500 dark:text-gray-400">
                                    {customerId ? 'No records found' : 'Please select a customer to view short summary bill'}
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
