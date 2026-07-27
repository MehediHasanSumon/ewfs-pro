import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, FileText } from 'lucide-react';

interface ShiftClosedShowProps {
    shiftClosed: {
        id: number;
        close_date: string;
        shift: {
            id: number;
            name: string;
        };
        daily_reading: {
            credit_sales: string;
            bank_sales: string;
            cash_sales: string;
            credit_sales_other: string;
            bank_sales_other: string;
            cash_sales_other: string;
            cash_receive: string;
            bank_receive: string;
            total_cash: string;
            cash_payment: string;
            bank_payment: string;
            office_payment: string;
            final_due_amount: string;
        };
        dispenser_readings: Array<{
            id: number;
            dispenser: { dispenser_name: string };
            product: { product_name: string };
            item_rate: string;
            start_reading: string;
            end_reading: string;
            meter_test: string;
            net_reading: string;
            total_sale: string;
            employee: { employee_name: string };
        }>;
        other_product_sales: Array<{
            id: number;
            product: { product_name: string; product_code: string };
            unit: { name: string };
            sell_quantity: string;
            item_rate: string;
            total_sales: string;
            employee: { employee_name: string };
        }>;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Shift Closed List', href: '/shift-closed-list' },
    { title: 'Details', href: '#' },
];

export default function ShiftClosedShow({ shiftClosed }: ShiftClosedShowProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Shift Details" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Shift Details</h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            {shiftClosed.shift.name} - {new Date(shiftClosed.close_date).toLocaleDateString()}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="success" onClick={() => window.location.href = `/shift-closed-list/${shiftClosed.id}/download-pdf`}>
                            <FileText className="mr-2 h-4 w-4" />
                            Download
                        </Button>
                        <Button variant="outline" onClick={() => router.get('/shift-closed-list')}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back
                        </Button>
                    </div>
                </div>

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="dark:text-white">Financial Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {shiftClosed.daily_reading ? (
                            <div className="grid grid-cols-2 gap-6 md:grid-cols-4">
                                <div>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Credit Sales</p>
                                    <p className="text-xl font-semibold dark:text-white">{Number(shiftClosed.daily_reading.credit_sales).toFixed(2)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Bank Sales</p>
                                    <p className="text-xl font-semibold dark:text-white">{Number(shiftClosed.daily_reading.bank_sales).toFixed(2)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Cash Sales</p>
                                    <p className="text-xl font-semibold dark:text-white">{Number(shiftClosed.daily_reading.cash_sales).toFixed(2)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Credit Sales Other</p>
                                    <p className="text-xl font-semibold dark:text-white">{Number(shiftClosed.daily_reading.credit_sales_other).toFixed(2)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Bank Sales Other</p>
                                    <p className="text-xl font-semibold dark:text-white">{Number(shiftClosed.daily_reading.bank_sales_other).toFixed(2)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Cash Sales Other</p>
                                    <p className="text-xl font-semibold dark:text-white">{Number(shiftClosed.daily_reading.cash_sales_other).toFixed(2)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Cash Receive</p>
                                    <p className="text-xl font-semibold dark:text-white">{Number(shiftClosed.daily_reading.cash_receive).toFixed(2)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Bank Receive</p>
                                    <p className="text-xl font-semibold dark:text-white">{Number(shiftClosed.daily_reading.bank_receive).toFixed(2)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Total Cash</p>
                                    <p className="text-xl font-semibold dark:text-white">{Number(shiftClosed.daily_reading.total_cash).toFixed(2)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Cash Payment</p>
                                    <p className="text-xl font-semibold dark:text-white">{Number(shiftClosed.daily_reading.cash_payment).toFixed(2)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Office Payment</p>
                                    <p className="text-xl font-semibold dark:text-white">{Number(shiftClosed.daily_reading.office_payment).toFixed(2)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Final Due Amount</p>
                                    <p className="text-xl font-semibold dark:text-white">{Number(shiftClosed.daily_reading.final_due_amount).toFixed(2)}</p>
                                </div>
                            </div>
                        ) : (
                            <p className="text-gray-500 dark:text-gray-400">No financial data available</p>
                        )}
                    </CardContent>
                </Card>

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="dark:text-white">Dispenser Readings</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="p-3 text-left text-sm font-medium dark:text-gray-300">Dispenser</th>
                                        <th className="p-3 text-left text-sm font-medium dark:text-gray-300">Product</th>
                                        <th className="p-3 text-right text-sm font-medium dark:text-gray-300">Rate</th>
                                        <th className="p-3 text-right text-sm font-medium dark:text-gray-300">Start</th>
                                        <th className="p-3 text-right text-sm font-medium dark:text-gray-300">End</th>
                                        <th className="p-3 text-right text-sm font-medium dark:text-gray-300">Test</th>
                                        <th className="p-3 text-right text-sm font-medium dark:text-gray-300">Net</th>
                                        <th className="p-3 text-right text-sm font-medium dark:text-gray-300">Total Sale</th>
                                        <th className="p-3 text-left text-sm font-medium dark:text-gray-300">Employee</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {shiftClosed.dispenser_readings.map((reading) => (
                                        <tr key={reading.id} className="border-b dark:border-gray-700">
                                            <td className="p-3 text-sm dark:text-white">{reading.dispenser.dispenser_name}</td>
                                            <td className="p-3 text-sm dark:text-white">{reading.product.product_name}</td>
                                            <td className="p-3 text-right text-sm dark:text-white">{Number(reading.item_rate).toFixed(2)}</td>
                                            <td className="p-3 text-right text-sm dark:text-white">{Number(reading.start_reading).toFixed(2)}</td>
                                            <td className="p-3 text-right text-sm dark:text-white">{Number(reading.end_reading).toFixed(2)}</td>
                                            <td className="p-3 text-right text-sm dark:text-white">{Number(reading.meter_test).toFixed(2)}</td>
                                            <td className="p-3 text-right text-sm dark:text-white">{Number(reading.net_reading).toFixed(2)}</td>
                                            <td className="p-3 text-right text-sm dark:text-white">{Number(reading.total_sale).toFixed(2)}</td>
                                            <td className="p-3 text-sm dark:text-white">{reading.employee?.employee_name || '-'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {shiftClosed.other_product_sales.length > 0 && (
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="dark:text-white">Other Products Sales</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-3 text-left text-sm font-medium dark:text-gray-300">Product</th>
                                            <th className="p-3 text-left text-sm font-medium dark:text-gray-300">Code</th>
                                            <th className="p-3 text-left text-sm font-medium dark:text-gray-300">Unit</th>
                                            <th className="p-3 text-right text-sm font-medium dark:text-gray-300">Rate</th>
                                            <th className="p-3 text-right text-sm font-medium dark:text-gray-300">Quantity</th>
                                            <th className="p-3 text-right text-sm font-medium dark:text-gray-300">Total</th>
                                            <th className="p-3 text-left text-sm font-medium dark:text-gray-300">Employee</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {shiftClosed.other_product_sales.map((sale) => (
                                            <tr key={sale.id} className="border-b dark:border-gray-700">
                                                <td className="p-3 text-sm dark:text-white">{sale.product.product_name}</td>
                                                <td className="p-3 text-sm dark:text-white">{sale.product.product_code}</td>
                                                <td className="p-3 text-sm dark:text-white">{sale.unit.name}</td>
                                                <td className="p-3 text-right text-sm dark:text-white">{Number(sale.item_rate).toFixed(2)}</td>
                                                <td className="p-3 text-right text-sm dark:text-white">{sale.sell_quantity}</td>
                                                <td className="p-3 text-right text-sm dark:text-white">{Number(sale.total_sales).toFixed(2)}</td>
                                                <td className="p-3 text-sm dark:text-white">{sale.employee?.employee_name || '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
