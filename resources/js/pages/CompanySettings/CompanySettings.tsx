import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DeleteModal } from '@/components/ui/delete-modal';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, Link } from '@inertiajs/react';
import { usePermission } from '@/hooks/usePermission';
import { Plus, Edit, Trash2, Building, Filter, X, Eye, FileText } from 'lucide-react';
import { useState, useEffect } from 'react';

interface CompanySetting {
    id: number;
    company_name: string;
    company_details?: string;
    proprietor_name?: string;
    company_address?: string;
    factory_address?: string;
    company_mobile?: string;
    company_phone?: string;
    company_email?: string;
    trade_license?: string;
    tin_no?: string;
    bin_no?: string;
    vat_no?: string;
    vat_rate?: number;
    currency?: string;
    company_logo?: string;
    status: number;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Company Settings',
        href: '/company-settings',
    },
];

interface CompanySettingsProps {
    companySettings: CompanySetting[];
    filters: {
        search?: string;
        status?: string;
        start_date?: string;
        end_date?: string;
    };
}

export default function CompanySettings({ companySettings = [], filters }: CompanySettingsProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-company-setting') || can('delete-company-setting');
    const canFilter = can('can-company-setting-filter');
    const canDownload = can('can-company-setting-download');
    const [deletingSetting, setDeletingSetting] = useState<CompanySetting | null>(null);
    const [selectedSettings, setSelectedSettings] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || 'all');
    const [startDate, setStartDate] = useState(filters?.start_date || '');
    const [endDate, setEndDate] = useState(filters?.end_date || '');

    const handleDelete = (setting: CompanySetting) => {
        setDeletingSetting(setting);
    };

    const confirmDelete = () => {
        if (deletingSetting) {
            router.delete(`/company-settings/${deletingSetting.id}`, {
                onSuccess: () => setDeletingSetting(null)
            });
        }
    };

    const toggleSelectAll = () => {
        if (selectedSettings.length === companySettings.length) {
            setSelectedSettings([]);
        } else {
            setSelectedSettings(companySettings.map(setting => setting.id));
        }
    };

    const toggleSelectSetting = (settingId: number) => {
        if (selectedSettings.includes(settingId)) {
            setSelectedSettings(selectedSettings.filter(id => id !== settingId));
        } else {
            setSelectedSettings([...selectedSettings, settingId]);
        }
    };

    const applyFilters = () => {
        router.get('/company-settings', {
            search: search || undefined,
            status: status === 'all' ? undefined : status,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('all');
        setStartDate('');
        setEndDate('');
        router.get('/company-settings', {}, { preserveState: true });
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            if (search !== (filters?.search || '')) {
                applyFilters();
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [search]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Company Settings" />
            
            <div className="p-6 space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Company Settings</h1>
                        <p className="text-gray-600 dark:text-gray-400">Manage company information and configuration</p>
                    </div>
                    <div className="flex gap-2">
                        {selectedSettings.length > 0 && can('delete-company-setting') && (
                            <Button 
                                variant="destructive" 
                                onClick={() => setIsBulkDeleting(true)}
                            >
                                <Trash2 className="h-4 w-4 mr-2" />
                                Delete Selected ({selectedSettings.length})
                            </Button>
                        )}
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={() => {
                                    const params = new URLSearchParams();
                                    if (search) params.append('search', search);
                                    if (status !== 'all') params.append('status', status);
                                    if (startDate) params.append('start_date', startDate);
                                    if (endDate) params.append('end_date', endDate);
                                    window.location.href = `/company-settings/download-pdf?${params.toString()}`;
                                }}
                            >
                                <FileText className="h-4 w-4 mr-2" />
                                Download
                            </Button>
                        )}
                        {can('create-company-setting') && (
                            <Link href="/company-settings/create">
                                <Button>
                                    <Plus className="h-4 w-4 mr-2" />
                                    Add Company Setting
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>

                {/* Filter Card */}
                {canFilter && (
                <Card className="dark:bg-gray-800 dark:border-gray-700">
                    <CardHeader>
                        <CardTitle className="dark:text-white flex items-center gap-2">
                            <Filter className="h-5 w-5" />
                            Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div>
                                <Label className="dark:text-gray-200">Search</Label>
                                <Input
                                    placeholder="Search company settings..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">Status</Label>
                                <Select value={status} onValueChange={(value) => {
                                    setStatus(value);
                                    router.get('/company-settings', {
                                        search: search || undefined,
                                        status: value === 'all' ? undefined : value,
                                        start_date: startDate || undefined,
                                        end_date: endDate || undefined,
                                    }, { preserveState: true });
                                }}>
                                    <SelectTrigger className="dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <SelectValue placeholder="All status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All status</SelectItem>
                                        <SelectItem value="1">Active</SelectItem>
                                        <SelectItem value="0">Disabled</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">Start Date</Label>
                                <Input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">End Date</Label>
                                <Input
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                />
                            </div>
                            <div className="flex items-end gap-2">
                                <Button onClick={applyFilters} className="flex-1">
                                    Apply Filters
                                </Button>
                                <Button onClick={clearFilters} variant="secondary" className="flex-1">
                                    <X className="h-4 w-4 mr-2" />
                                    Clear
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                )}

                <Card className="dark:bg-gray-800 dark:border-gray-700">
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="text-left p-4 font-medium dark:text-gray-300">
                                            <input
                                                type="checkbox"
                                                checked={selectedSettings.length === companySettings.length && companySettings.length > 0}
                                                onChange={toggleSelectAll}
                                                className="rounded"
                                            />
                                        </th>
                                        <th className="text-left p-4 text-[13px] font-medium dark:text-gray-300">SL</th>
                                        <th className="text-left p-4 text-[13px] font-medium dark:text-gray-300">Company Name</th>
                                        <th className="text-left p-4 text-[13px] font-medium dark:text-gray-300">E-mail</th>
                                        <th className="text-left p-4 text-[13px] font-medium dark:text-gray-300">Cell Number</th>
                                        <th className="text-left p-4 text-[13px] font-medium dark:text-gray-300">Phone Number</th>
                                        <th className="text-left p-4 text-[13px] font-medium dark:text-gray-300">Status</th>
                                        {hasActionPermission && (
                                            <th className="text-left p-4 text-[13px] font-medium dark:text-gray-300">Actions</th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {companySettings.length > 0 ? companySettings.map((setting, index) => (
                                        <tr key={setting.id} className="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td className="p-4">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedSettings.includes(setting.id)}
                                                    onChange={() => toggleSelectSetting(setting.id)}
                                                    className="rounded"
                                                />
                                            </td>
                                            <td className="p-4 text-[13px] dark:text-white">{index + 1}</td>
                                            <td className="p-4 text-[13px] dark:text-white">{setting.company_name}</td>
                                            <td className="p-4 text-[13px] dark:text-gray-300">{setting.company_email || '-'}</td>
                                            <td className="p-4 text-[13px] dark:text-gray-300">{setting.company_mobile || '-'}</td>
                                            <td className="p-4 text-[13px] dark:text-gray-300">{setting.company_phone || '-'}</td>
                                            <td className="p-4">
                                                <span className={`px-2 py-1 rounded text-[11px] ${setting.status == 1 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}`}>
                                                    {setting.status == 1 ? 'Active' : 'Disabled'}
                                                </span>
                                            </td>
                                            {hasActionPermission && (
                                                <td className="p-4">
                                                    <div className="flex gap-2">
                                                        <Link href={`/company-settings/${setting.id}`}>
                                                            <Button 
                                                                variant="ghost" 
                                                                size="sm"
                                                                className="text-blue-600 hover:text-blue-800"
                                                            >
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        {can('update-company-setting') && (
                                                            <Link href={`/company-settings/${setting.id}/edit`}>
                                                                <Button 
                                                                    variant="ghost" 
                                                                    size="sm"
                                                                    className="text-indigo-600 hover:text-indigo-800"
                                                                >
                                                                    <Edit className="h-4 w-4" />
                                                                </Button>
                                                            </Link>
                                                        )}
                                                        {can('delete-company-setting') && (
                                                            <Button 
                                                                variant="ghost" 
                                                                size="sm"
                                                                onClick={() => handleDelete(setting)}
                                                                className="text-red-600 hover:text-red-800"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            )}
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan={hasActionPermission ? 8 : 7} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                <Building className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                                No company settings found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <DeleteModal
                    isOpen={!!deletingSetting}
                    onClose={() => setDeletingSetting(null)}
                    onConfirm={confirmDelete}
                    title="Delete Company Setting"
                    message={`Are you sure you want to delete the company setting "${deletingSetting?.company_name}"? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={() => {
                        router.delete('/company-settings/bulk/delete', {
                            data: { ids: selectedSettings },
                            onSuccess: () => {
                                setSelectedSettings([]);
                                setIsBulkDeleting(false);
                            }
                        });
                    }}
                    title="Delete Selected Company Settings"
                    message={`Are you sure you want to delete ${selectedSettings.length} selected company settings? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}