import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ChevronDown, ChevronUp, FileText, Filter, BarChart3, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface ProductSale {
    product_name: string;
    unit_name: string;
    unit_price?: number;
    total_quantity: number;
    total_amount: number;
}

interface CustomerSale {
    customer_name: string;
    vehicle_no: string;
    product_name: string;
    unit_name: string;
    unit_price: number;
    quantity: number;
    total_amount: number;
}

interface CashTransaction {
    account_name: string;
    payment_type?: string;
    amount: number;
    description?: string;
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
    productWiseSales: ProductSale[];
    cashBankSales: ProductSale[];
    creditSales: ProductSale[];
    customerWiseSales: CustomerSale[];
    cashReceived: CashTransaction[];
    cashPayment: CashTransaction[];
    customers: Customer[];
    shifts: Shift[];
    filters: {
        search?: string;
        customer_id?: string;
        start_date?: string;
        end_date?: string;
        shift_id?: string;
    };
}

export default function DailyStatement({ productWiseSales = [], cashBankSales = [], creditSales = [], customerWiseSales = [], cashReceived = [], cashPayment = [], customers = [], shifts = [], filters = {} }: DailyStatementProps) {
    const { can } = usePermission();
    const canFilter = can('can-account-filter');
    const canDownload = can('can-account-download');

    const [search, setSearch] = useState(filters.search || '');
    const [customerId, setCustomerId] = useState(filters.customer_id || 'all');
    const [shiftId, setShiftId] = useState(filters.shift_id || 'all');
    const [startDate, setStartDate] = useState(filters.start_date || new Date().toISOString().split('T')[0]);
    const [endDate, setEndDate] = useState(filters.end_date || new Date().toISOString().split('T')[0]);

    const applyFilters = () => {
        const params: any = {
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
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Daily Statement Report</h1>
                        <p className="text-gray-600 dark:text-gray-400">View customer credit sales statements</p>
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
                                window.location.href = `/daily-statement/download-pdf?${params.toString()}`;
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
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="dark:text-white">1. Sales Summary (Product Wise)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Product Name</th>
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Unit Name</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Unit Price</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Quantity</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Total Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {productWiseSales.length > 0 ? (
                                            <>
                                                {productWiseSales.map((sale, index) => (
                                                    <tr key={index} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                        <td className="p-2 text-[13px] dark:text-white">{sale.product_name}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300">{sale.unit_name}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{sale.unit_price?.toLocaleString() || '0'}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{sale.total_quantity.toLocaleString()}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{sale.total_amount.toLocaleString()}</td>
                                                    </tr>
                                                ))}
                                                <tr className="border-b font-bold dark:border-gray-700">
                                                    <td colSpan={3} className="p-2 text-[13px] dark:text-white">Total:</td>
                                                    <td className="p-2 text-right text-[13px] dark:text-white">{productWiseSales.reduce((sum, sale) => sum + Number(sale.total_quantity), 0).toFixed(2)}</td>
                                                    <td className="p-2 text-right text-[13px] dark:text-white">{productWiseSales.reduce((sum, sale) => sum + Number(sale.total_amount), 0).toFixed(2)}</td>
                                                </tr>
                                            </>
                                        ) : (
                                            <tr>
                                                <td colSpan={5} className="p-4 text-center text-[13px] text-gray-500 dark:text-gray-400">No records</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="dark:text-white">2. Sales Summary (Cash & Bank)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Product Name</th>
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Unit Name</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Unit Price</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Quantity</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Total Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {cashBankSales.length > 0 ? (
                                            <>
                                                {cashBankSales.map((sale, index) => (
                                                    <tr key={index} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                        <td className="p-2 text-[13px] dark:text-white">{sale.product_name}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300">{sale.unit_name}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{sale.unit_price?.toLocaleString() || '0'}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{sale.total_quantity.toLocaleString()}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{sale.total_amount.toLocaleString()}</td>
                                                    </tr>
                                                ))}
                                                <tr className="border-b font-bold dark:border-gray-700">
                                                    <td colSpan={3} className="p-2 text-[13px] dark:text-white">Total:</td>
                                                    <td className="p-2 text-right text-[13px] dark:text-white">{cashBankSales.reduce((sum, sale) => sum + Number(sale.total_quantity), 0).toFixed(2)}</td>
                                                    <td className="p-2 text-right text-[13px] dark:text-white">{cashBankSales.reduce((sum, sale) => sum + Number(sale.total_amount), 0).toFixed(2)}</td>
                                                </tr>
                                            </>
                                        ) : (
                                            <tr>
                                                <td colSpan={5} className="p-4 text-center text-[13px] text-gray-500 dark:text-gray-400">No records</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="dark:text-white">3. Sales Summary (Credit)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Product Name</th>
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Unit Name</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Unit Price</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Quantity</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Total Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {creditSales.length > 0 ? (
                                            <>
                                                {creditSales.map((sale, index) => (
                                                    <tr key={index} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                        <td className="p-2 text-[13px] dark:text-white">{sale.product_name}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300">{sale.unit_name}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{sale.unit_price?.toLocaleString() || '0'}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{sale.total_quantity.toLocaleString()}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{sale.total_amount.toLocaleString()}</td>
                                                    </tr>
                                                ))}
                                                <tr className="border-b font-bold dark:border-gray-700">
                                                    <td colSpan={3} className="p-2 text-[13px] dark:text-white">Total:</td>
                                                    <td className="p-2 text-right text-[13px] dark:text-white">{creditSales.reduce((sum, sale) => sum + Number(sale.total_quantity), 0).toFixed(2)}</td>
                                                    <td className="p-2 text-right text-[13px] dark:text-white">{creditSales.reduce((sum, sale) => sum + Number(sale.total_amount), 0).toFixed(2)}</td>
                                                </tr>
                                            </>
                                        ) : (
                                            <tr>
                                                <td colSpan={5} className="p-4 text-center text-[13px] text-gray-500 dark:text-gray-400">No records</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="dark:text-white">4. Customer Wise Sales Summary (Credit)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Customer Name</th>
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Vehicle Number</th>
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Product Name</th>
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Unit Name</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Unit Price</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Quantity</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Total Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {customerWiseSales.length > 0 ? (
                                            <>
                                                {customerWiseSales.map((sale, index) => (
                                                    <tr key={index} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                        <td className="p-2 text-[13px] dark:text-white">{sale.customer_name}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300">{sale.vehicle_no}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300">{sale.product_name}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300">{sale.unit_name}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{sale.unit_price.toLocaleString()}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{sale.quantity.toLocaleString()}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{sale.total_amount.toLocaleString()}</td>
                                                    </tr>
                                                ))}
                                                <tr className="border-b font-bold dark:border-gray-700">
                                                    <td colSpan={5} className="p-2 text-[13px] dark:text-white">Total:</td>
                                                    <td className="p-2 text-right text-[13px] dark:text-white">{customerWiseSales.reduce((sum, sale) => sum + Number(sale.quantity), 0).toFixed(2)}</td>
                                                    <td className="p-2 text-right text-[13px] dark:text-white">{customerWiseSales.reduce((sum, sale) => sum + Number(sale.total_amount), 0).toFixed(2)}</td>
                                                </tr>
                                            </>
                                        ) : (
                                            <tr>
                                                <td colSpan={7} className="p-4 text-center text-[13px] text-gray-500 dark:text-gray-400">No records</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="dark:text-white">5. Cash Received Summary</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Sl</th>
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Name</th>
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Received Type</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Received Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {cashReceived.length > 0 ? (
                                            <>
                                                {cashReceived.map((item, index) => (
                                                    <tr key={index} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                        <td className="p-2 text-[13px] dark:text-white">{index + 1}</td>
                                                        <td className="p-2 text-[13px] dark:text-white">{item.account_name}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300">{item.payment_type || '-'}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{item.amount.toLocaleString()}</td>
                                                    </tr>
                                                ))}
                                                <tr className="border-b font-bold dark:border-gray-700">
                                                    <td colSpan={3} className="p-2 text-[13px] dark:text-white">Total:</td>
                                                    <td className="p-2 text-right text-[13px] dark:text-white">{cashReceived.reduce((sum, item) => sum + Number(item.amount), 0).toFixed(2)}</td>
                                                </tr>
                                            </>
                                        ) : (
                                            <tr>
                                                <td colSpan={4} className="p-4 text-center text-[13px] text-gray-500 dark:text-gray-400">No records</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="dark:text-white">6. Cash Payment Summary</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Sl</th>
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Name</th>
                                            <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Payment Type</th>
                                            <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Payment Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {cashPayment.length > 0 ? (
                                            <>
                                                {cashPayment.map((item, index) => (
                                                    <tr key={index} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                        <td className="p-2 text-[13px] dark:text-white">{index + 1}</td>
                                                        <td className="p-2 text-[13px] dark:text-white">{item.account_name}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300">{item.payment_type || '-'}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">{item.amount.toLocaleString()}</td>
                                                    </tr>
                                                ))}
                                                <tr className="border-b font-bold dark:border-gray-700">
                                                    <td colSpan={3} className="p-2 text-[13px] dark:text-white">Total:</td>
                                                    <td className="p-2 text-right text-[13px] dark:text-white">{cashPayment.reduce((sum, item) => sum + Number(item.amount), 0).toFixed(2)}</td>
                                                </tr>
                                            </>
                                        ) : (
                                            <tr>
                                                <td colSpan={4} className="p-4 text-center text-[13px] text-gray-500 dark:text-gray-400">No records</td>
                                            </tr>
                                        )}
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
