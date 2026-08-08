import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, XCircle } from 'lucide-react';

interface Period {
    id: number;
    label: string;
    status: string;
    item_count?: number;
}

interface Paginator {
    data: Period[];
    current_page: number;
    last_page: number;
}

export default function History({ periods }: { periods: Paginator }) {
    return (
        <AppLayout>
            <Head title="Payroll History" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold dark:text-white">Payroll History</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">Completed and cancelled payroll records.</p>
                    </div>
                    <Button type="button" variant="outline" onClick={() => router.get('/payroll')}>
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Periods
                    </Button>
                </div>
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b text-left text-sm text-gray-500 dark:border-gray-700">
                                    <th className="p-4">Period</th>
                                    <th className="p-4">Employees</th>
                                    <th className="p-4">Status</th>
                                    <th className="p-4 text-right">View</th>
                                </tr>
                            </thead>
                            <tbody>
                                {periods.data.map((period) => (
                                    <tr key={period.id} className="border-b dark:border-gray-700">
                                        <td className="p-4 font-medium dark:text-white">{period.label}</td>
                                        <td className="p-4 text-sm dark:text-gray-300">{period.item_count ?? 0}</td>
                                        <td className="p-4 flex items-center gap-2 text-sm capitalize dark:text-gray-300">
                                            {period.status === 'paid' && <CheckCircle2 className="h-4 w-4 text-emerald-600" />}
                                            {period.status === 'cancelled' && <XCircle className="h-4 w-4 text-gray-500" />}
                                            {period.status}
                                        </td>
                                        <td className="p-4 text-right">
                                            <Button type="button" size="sm" variant="outline" onClick={() => router.get(`/payroll/${period.id}/processing`)}>
                                                View
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
                {periods.last_page > 1 && (
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={periods.current_page <= 1}
                            onClick={() => router.get('/payroll/history', { page: periods.current_page - 1 }, { preserveState: true })}
                        >
                            Previous
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={periods.current_page >= periods.last_page}
                            onClick={() => router.get('/payroll/history', { page: periods.current_page + 1 }, { preserveState: true })}
                        >
                            Next
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
