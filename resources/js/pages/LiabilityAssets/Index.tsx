import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { numberToWords } from '@/lib/utils';
import { usePermission } from '@/hooks/usePermission';

interface AccountItem {
    id: number;
    name: string;
    ac_number: string;
    group_name: string;
    balance: number;
    type: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Liability and Assets', href: '/liability-assets' },
];

interface LiabilityAssetsProps {
    liabilities: AccountItem[];
    assets: AccountItem[];
    totalLiabilities: number;
    totalAssets: number;
}

export default function LiabilityAssets({ liabilities = [], assets = [], totalLiabilities = 0, totalAssets = 0 }: LiabilityAssetsProps) {
    const { can } = usePermission();
    const canDownload = can('can-account-download');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Liability and Assets" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Liability and Assets</h1>
                        <p className="text-gray-600 dark:text-gray-400">View liability and asset accounts summary</p>
                    </div>
                    {canDownload && (
                        <Button
                            variant="success"
                            onClick={() => window.location.href = '/liability-assets/download-pdf'}
                        >
                            <FileText className="mr-2 h-4 w-4" />Download
                        </Button>
                    )}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Liabilities */}
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="dark:text-white">Liabilities</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Account Name</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Type</th>
                                            <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {liabilities.length > 0 ? (
                                            liabilities.map((liability, index) => (
                                                <tr key={liability.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                    <td className="p-4 text-[13px] dark:text-white">{liability.name}</td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">{liability.type}</td>
                                                    <td className="p-4 text-right text-[13px] dark:text-gray-300">{liability.balance.toLocaleString()}</td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={3} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                    No liability accounts found
                                                </td>
                                            </tr>
                                        )}
                                        {liabilities.length > 0 && (
                                            <tr className="border-b font-bold bg-red-50 dark:bg-red-900 dark:border-gray-700">
                                                <td colSpan={2} className="p-4 text-[14px] dark:text-white">Total Liabilities:</td>
                                                <td className="p-4 text-right text-[14px] dark:text-white">{totalLiabilities.toLocaleString()}</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Assets */}
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="dark:text-white">Assets</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Account Name</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Type</th>
                                            <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {assets.length > 0 ? (
                                            assets.map((asset, index) => (
                                                <tr key={asset.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                    <td className="p-4 text-[13px] dark:text-white">{asset.name}</td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">{asset.type}</td>
                                                    <td className="p-4 text-right text-[13px] dark:text-gray-300">{asset.balance.toLocaleString()}</td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={3} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                    No asset accounts found
                                                </td>
                                            </tr>
                                        )}
                                        {assets.length > 0 && (
                                            <tr className="border-b font-bold bg-green-50 dark:bg-green-900 dark:border-gray-700">
                                                <td colSpan={2} className="p-4 text-[14px] dark:text-white">Total Assets:</td>
                                                <td className="p-4 text-right text-[14px] dark:text-white">{totalAssets.toLocaleString()}</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Summary */}
                {(liabilities.length > 0 || assets.length > 0) && (
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <tbody>
                                        <tr className="border-b font-bold bg-indigo-100 dark:bg-indigo-900 dark:border-gray-700">
                                            <td className="p-4 text-[14px] dark:text-white">Total Liabilities:</td>
                                            <td className="p-4 text-right text-[14px] dark:text-white">{totalLiabilities.toLocaleString()}</td>
                                        </tr>
                                        <tr className="border-b font-bold bg-indigo-100 dark:bg-indigo-900 dark:border-gray-700">
                                            <td className="p-4 text-[14px] dark:text-white">Total Assets:</td>
                                            <td className="p-4 text-right text-[14px] dark:text-white">{totalAssets.toLocaleString()}</td>
                                        </tr>
                                        <tr className="border-b font-bold bg-gray-200 dark:bg-gray-700 dark:border-gray-700">
                                            <td className="p-4 text-[14px] dark:text-white">Net Worth (Assets - Liabilities):</td>
                                            <td className="p-4 text-right text-[14px] dark:text-white">{(totalAssets - totalLiabilities).toLocaleString()}</td>
                                        </tr>
                                        <tr className="bg-gray-50 dark:bg-gray-800">
                                            <td colSpan={2} className="p-4 text-[13px] italic dark:text-gray-300">
                                                Net Worth in words: {numberToWords(Math.floor(totalAssets - totalLiabilities))}
                                            </td>
                                        </tr>
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