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
import { numberToWords } from '@/lib/utils';
import { usePermission } from '@/hooks/usePermission';

interface Purchase {
    date: string;
    invoice_no: string;
    supplier_name: string;
    product_name: string;
    unit_name?: string;
    memo_no?: string;
    quantity: number;
    price: number;
    total_amount: number;
}

interface Report {
    purchases: Purchase[];
    total_quantity: number;
    total_amount: number;
}



const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Purchase Report Details', href: '/purchase-report-details' },
];

interface PurchaseReportDetailsProps {
    report: Report;
    filters: {
        start_date?: string;
        end_date?: string;
    };
}

export default function PurchaseReportDetails({ report, filters = {} }: PurchaseReportDetailsProps) {
    const { can } = usePermission();
    const canFilter = can('can-purchase-filter');
    const canDownload = can('can-purchase-download');

    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-GB');
    };

    const applyFilters = () => {
        const params: Record<string, string> = {};
        if (startDate) {
            params.start_date = startDate;
        }
        if (endDate) {
            params.end_date = endDate;
        }
        router.get('/purchase-report-details', params, { preserveState: true });
    };

    const clearFilters = () => {
        setStartDate('');
        setEndDate('');
        router.get('/purchase-report-details', {}, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Purchase Report Details" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Purchase Report Details</h1>
                        <p className="text-gray-600 dark:text-gray-400">View detailed purchase reports</p>
                    </div>
                    {canDownload && (
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                if (startDate) params.append('start_date', startDate);
                                if (endDate) params.append('end_date', endDate);
                                window.location.href = `/purchase-report-details/download-pdf?${params.toString()}`;
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

                <div className="space-y-6">
                    {report && report.purchases && report.purchases.length > 0 ? (
                        <Card className="dark:border-gray-700 dark:bg-gray-800">
                            <CardContent className="p-0">
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b dark:border-gray-700">
                                                <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Supplier</th>
                                                <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Invoice No</th>
                                                <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Sup. Inv</th>
                                                <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Product Name</th>
                                                <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Unit</th>
                                                <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Quantity</th>
                                                <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Price</th>
                                                <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {(() => {
                                                let currentDate = '';
                                                return report.purchases.map((purchase, purchaseIndex) => {
                                                    const showDateRow = purchase.date !== currentDate;
                                                    currentDate = purchase.date;
                                                    return (
                                                        <>
                                                            {showDateRow && (
                                                                <tr key={`date-${purchaseIndex}`} className="bg-gray-100 dark:bg-gray-700">
                                                                    <td colSpan={8} className="p-2 text-[13px] font-semibold dark:text-white">
                                                                        Date: {formatDate(purchase.date)}
                                                                    </td>
                                                                </tr>
                                                            )}
                                                            <tr key={purchaseIndex} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                                <td className="p-2 text-[13px] dark:text-gray-300">{purchase.supplier_name}</td>
                                                                <td className="p-2 text-[13px] dark:text-gray-300">{purchase.invoice_no}</td>
                                                                <td className="p-2 text-[13px] dark:text-gray-300">{purchase.memo_no || '-'}</td>
                                                                <td className="p-2 text-[13px] dark:text-gray-300">{purchase.product_name}</td>
                                                                <td className="p-2 text-[13px] dark:text-gray-300">{purchase.unit_name || '-'}</td>
                                                                <td className="p-2 text-right text-[13px] dark:text-gray-300">{purchase.quantity.toLocaleString()}</td>
                                                                <td className="p-2 text-right text-[13px] dark:text-gray-300">{purchase.price.toLocaleString()}</td>
                                                                <td className="p-2 text-right text-[13px] dark:text-gray-300">{purchase.total_amount.toLocaleString()}</td>
                                                            </tr>
                                                        </>
                                                    );
                                                });
                                            })()}
                                        </tbody>
                                        <tfoot>
                                            <tr className="border-t font-bold bg-gray-50 dark:bg-gray-700 dark:border-gray-700">
                                                <td colSpan={5} className="p-2 text-[13px] dark:text-white">Grand Total:</td>
                                                <td className="p-2 text-right text-[13px] dark:text-white">{report.total_quantity.toFixed(2)}</td>
                                                <td className="p-2 text-right text-[13px] dark:text-white"></td>
                                                <td className="p-2 text-right text-[13px] dark:text-white">{report.total_amount.toFixed(2)}</td>
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

                    {report && report.purchases && report.purchases.length > 0 && (
                        <Card className="dark:border-gray-700 dark:bg-gray-800">
                            <CardContent className="p-4">
                                <div className="font-bold text-[16px] dark:text-white mb-2">
                                    Grand Total: {report.total_amount.toFixed(2)}
                                </div>
                                <div className="text-[14px] italic dark:text-gray-300">
                                    In words: {numberToWords(Math.floor(report.total_amount))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
