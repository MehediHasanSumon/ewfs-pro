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

interface Sale {
    sale_date: string;
    vehicle_number: string;
    invoice_no: string;
    product_name: string;
    unit_name: string;
    price: number;
    quantity: number;
    total_amount: number;
}

interface VehicleGroup {
    vehicle_number: string;
    sales: Sale[];
    total_quantity: number;
    total_amount: number;
}

interface Bill {
    customer_name: string;
    customer_mobile: string;
    customer_address: string;
    vehicle_groups: VehicleGroup[];
    total_quantity: number;
    total_amount: number;
}

interface Customer {
    id: number;
    name: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Customer Details Bill', href: '/customer-details-bill' },
];

interface CustomerDetailsBillProps {
    bills: Bill[];
    customers: Customer[];
    filters: {
        customer_id?: string;
        start_date?: string;
        end_date?: string;
    };
}

export default function CustomerDetailsBill({ bills = [], customers = [], filters = {} }: CustomerDetailsBillProps) {
    const { can } = usePermission();
    const canFilter = can('can-customer-filter');
    const canDownload = can('can-customer-download');

    const [customerId, setCustomerId] = useState(filters.customer_id || '');
    const [startDate, setStartDate] = useState(filters.start_date || new Date().toISOString().split('T')[0]);
    const [endDate, setEndDate] = useState(filters.end_date || new Date().toISOString().split('T')[0]);

    const applyFilters = () => {
        if (!customerId) return;
        const params: any = {
            customer_id: customerId,
            start_date: startDate,
            end_date: endDate,
        };
        router.get('/customer-details-bill', params, { preserveState: true });
    };

    const clearFilters = () => {
        const today = new Date().toISOString().split('T')[0];
        setCustomerId('');
        setStartDate(today);
        setEndDate(today);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Customer Details Bill" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Customer Details Bill</h1>
                        <p className="text-gray-600 dark:text-gray-400">View detailed customer credit sales</p>
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
                                window.location.href = `/customer-details-bill/download-pdf?${params.toString()}`;
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
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-5">
                                <div>
                                    <Label className="dark:text-gray-200">Customer</Label>
                                    <Select value={customerId} onValueChange={setCustomerId}>
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

                <div className="space-y-6">
                    {bills.length > 0 ? (
                        bills.map((bill, billIndex) => (
                            <Card key={billIndex} className="dark:border-gray-700 dark:bg-gray-800">
                                <CardHeader>
                                    <CardTitle className="text-[16px] font-bold dark:text-white mb-2">Customer Details</CardTitle>
                                    <div className="space-y-1 text-[13px]">
                                        <div><span className="font-semibold dark:text-gray-300">Customer:</span> <span className="dark:text-white">{bill.customer_name}</span></div>
                                        <div><span className="font-semibold dark:text-gray-300">Mobile:</span> <span className="dark:text-white">{bill.customer_mobile || '-'}</span></div>
                                        <div><span className="font-semibold dark:text-gray-300">Address:</span> <span className="dark:text-white">{bill.customer_address || '-'}</span></div>
                                    </div>
                                </CardHeader>
                                <CardContent className="p-0 space-y-4">
                                    {bill.vehicle_groups.map((vehicleGroup, vgIndex) => (
                                        <div key={vgIndex} className="overflow-x-auto">
                                            <table className="w-full">
                                                <thead>
                                                    <tr className="border-b dark:border-gray-700">
                                                        <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Date</th>
                                                        <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Invoice No</th>
                                                        <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Product</th>
                                                        <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Unit</th>
                                                        <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Price</th>
                                                        <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Quantity</th>
                                                        <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Total</th>
                                                    </tr>
                                                    <tr className="bg-gray-100 dark:bg-gray-700">
                                                        <th colSpan={7} className="p-2 text-left text-[13px] font-semibold dark:text-white">
                                                            Vehicle: {vehicleGroup.vehicle_number}
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {vehicleGroup.sales.map((sale, saleIndex) => (
                                                        <tr key={saleIndex} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                            <td className="p-2 text-[13px] dark:text-white">{sale.sale_date}</td>
                                                            <td className="p-2 text-[13px] dark:text-gray-300">{sale.invoice_no}</td>
                                                            <td className="p-2 text-[13px] dark:text-gray-300">{sale.product_name}</td>
                                                            <td className="p-2 text-[13px] dark:text-gray-300">{sale.unit_name}</td>
                                                            <td className="p-2 text-right text-[13px] dark:text-gray-300">{sale.price.toLocaleString()}</td>
                                                            <td className="p-2 text-right text-[13px] dark:text-gray-300">{sale.quantity.toLocaleString()}</td>
                                                            <td className="p-2 text-right text-[13px] dark:text-gray-300">{sale.total_amount.toLocaleString()}</td>
                                                        </tr>
                                                    ))}
                                                    <tr className="border-b font-bold bg-gray-50 dark:bg-gray-700 dark:border-gray-700">
                                                        <td colSpan={5} className="p-2 text-[13px] dark:text-white">Total:</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-white">{vehicleGroup.total_quantity.toFixed(2)}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-white">{vehicleGroup.total_amount.toFixed(2)}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    ))}
                                    <div className="p-3 bg-gray-50 dark:bg-gray-800">
                                        <div className="font-bold text-[14px] dark:text-white mb-2">
                                            Grand Total: {bill.total_amount.toFixed(2)}
                                        </div>
                                        <div className="text-[13px] italic dark:text-gray-300">
                                            In words: {numberToWords(Math.floor(bill.total_amount))}
                                        </div>
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
