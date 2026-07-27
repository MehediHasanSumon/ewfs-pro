import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Pagination } from '@/components/ui/pagination';
import { FormModal } from '@/components/ui/form-modal';
import { DeleteModal } from '@/components/ui/delete-modal';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/react';
import { usePermission } from '@/hooks/usePermission';
import { Plus, Edit, Trash2, Building, ChevronUp, ChevronDown, Filter, X, FileText } from 'lucide-react';
import { useState, useEffect } from 'react';

interface EmpDepartment {
    id: number;
    name: string;
    emp_type_id: number | null;
    emp_type_name: string | null;
    status: boolean;
    created_at: string;
}

interface EmpType {
    id: number;
    name: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Employee Departments',
        href: '/emp-departments',
    },
];

interface EmpDepartmentsProps {
    departments: {
        data: EmpDepartment[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    empTypes: EmpType[];
    filters: {
        search?: string;
        status?: string;
        emp_type_id?: string;
        start_date?: string;
        end_date?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function EmpDepartments({ departments, empTypes, filters }: EmpDepartmentsProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-employee') || can('delete-employee');
    const canFilter = can('can-emp-department-filter');
    const canDownload = can('can-emp-department-download');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingDepartment, setEditingDepartment] = useState<EmpDepartment | null>(null);
    const [deletingDepartment, setDeletingDepartment] = useState<EmpDepartment | null>(null);
    const [selectedDepartments, setSelectedDepartments] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || 'all');
    const [empTypeId, setEmpTypeId] = useState(filters?.emp_type_id || 'all');
    const [startDate, setStartDate] = useState(filters?.start_date || '');
    const [endDate, setEndDate] = useState(filters?.end_date || '');
    const [sortBy, setSortBy] = useState(filters?.sort_by || 'name');
    const [sortOrder, setSortOrder] = useState(filters?.sort_order || 'asc');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);
    
    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        emp_type_id: null as number | null,
        status: true
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingDepartment) {
            put(`/emp-departments/${editingDepartment.id}`, {
                onSuccess: () => {
                    setEditingDepartment(null);
                    reset();
                }
            });
        } else {
            post('/emp-departments', {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    reset();
                }
            });
        }
    };

    const handleEdit = (department: EmpDepartment) => {
        setEditingDepartment(department);
        setData({
            name: department.name,
            emp_type_id: department.emp_type_id,
            status: department.status
        });
    };

    const handleDelete = (department: EmpDepartment) => {
        setDeletingDepartment(department);
    };

    const confirmDelete = () => {
        if (deletingDepartment) {
            router.delete(`/emp-departments/${deletingDepartment.id}`, {
                onSuccess: () => setDeletingDepartment(null)
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/emp-departments/bulk/delete', {
            data: { ids: selectedDepartments },
            onSuccess: () => {
                setSelectedDepartments([]);
                setIsBulkDeleting(false);
            }
        });
    };

    const applyFilters = () => {
        router.get('/emp-departments', {
            search: search || undefined,
            status: status === 'all' ? undefined : status,
            emp_type_id: empTypeId === 'all' ? undefined : empTypeId,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('all');
        setEmpTypeId('all');
        setStartDate('');
        setEndDate('');
        router.get('/emp-departments', {
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const handleSort = (column: string) => {
        const newOrder = sortBy === column && sortOrder === 'asc' ? 'desc' : 'asc';
        setSortBy(column);
        setSortOrder(newOrder);
        router.get('/emp-departments', {
            search: search || undefined,
            status: status === 'all' ? undefined : status,
            emp_type_id: empTypeId === 'all' ? undefined : empTypeId,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort_by: column,
            sort_order: newOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const handlePageChange = (page: number) => {
        router.get('/emp-departments', {
            search: search || undefined,
            status: status === 'all' ? undefined : status,
            emp_type_id: empTypeId === 'all' ? undefined : empTypeId,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
            page,
        }, { preserveState: true });
    };

    const toggleSelectAll = () => {
        if (selectedDepartments.length === departments.data.length) {
            setSelectedDepartments([]);
        } else {
            setSelectedDepartments(departments.data.map(department => department.id));
        }
    };

    const toggleSelectDepartment = (departmentId: number) => {
        if (selectedDepartments.includes(departmentId)) {
            setSelectedDepartments(selectedDepartments.filter(id => id !== departmentId));
        } else {
            setSelectedDepartments([...selectedDepartments, departmentId]);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            if (search !== (filters?.search || '')) {
                applyFilters();
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [search]);

    useEffect(() => {
        if (status !== (filters?.status || 'all')) {
            applyFilters();
        }
    }, [status]);

    useEffect(() => {
        if (empTypeId !== (filters?.emp_type_id || 'all')) {
            applyFilters();
        }
    }, [empTypeId]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Employee Departments" />
            
            <div className="p-6 space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-bold dark:text-white">Employee Departments</h1>
                        <p className="text-gray-600 dark:text-gray-400">Manage employee departments and divisions</p>
                    </div>
                    <div className="flex gap-2">
                        {selectedDepartments.length > 0 && can('delete-employee') && (
                            <Button 
                                variant="destructive" 
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="h-4 w-4 mr-2" />
                                Delete Selected ({selectedDepartments.length})
                            </Button>
                        )}
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={() => {
                                    const params = new URLSearchParams();
                                    if (search) params.append('search', search);
                                    if (status !== 'all') params.append('status', status);
                                    if (empTypeId !== 'all') params.append('emp_type_id', empTypeId);
                                    if (startDate) params.append('start_date', startDate);
                                    if (endDate) params.append('end_date', endDate);
                                    if (sortBy) params.append('sort_by', sortBy);
                                    if (sortOrder) params.append('sort_order', sortOrder);
                                    window.location.href = `/emp-departments/download-pdf?${params.toString()}`;
                                }}
                            >
                                <FileText className="h-4 w-4 mr-2" />
                                Download
                            </Button>
                        )}
                        {can('create-employee') && (
                            <Button onClick={() => setIsCreateOpen(true)}>
                                <Plus className="h-4 w-4 mr-2" />
                                Add Department
                            </Button>
                        )}
                    </div>
                </div>

                {/* Filter Card */}
                {canFilter && (
                <Card className="dark:bg-gray-800 dark:border-gray-700">
                    <CardHeader>
                        <CardTitle className="dark:text-white flex items-center gap-2">
                            <Filter className="h-4 w-4" />
                            Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-6 gap-4">
                            <div>
                                <Label className="text-sm dark:text-gray-200">Search</Label>
                                <Input
                                    placeholder="Search departments..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="text-sm dark:text-gray-200">Employee Type</Label>
                                <Select value={empTypeId} onValueChange={setEmpTypeId}>
                                    <SelectTrigger className="dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <SelectValue placeholder="All types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All types</SelectItem>
                                        {empTypes.map((type) => (
                                            <SelectItem key={type.id} value={type.id.toString()}>
                                                {type.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="text-sm dark:text-gray-200">Status</Label>
                                <Select value={status} onValueChange={setStatus}>
                                    <SelectTrigger className="dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <SelectValue placeholder="All status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All status</SelectItem>
                                        <SelectItem value="true">Active</SelectItem>
                                        <SelectItem value="false">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="text-sm dark:text-gray-200">Start Date</Label>
                                <Input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="text-sm dark:text-gray-200">End Date</Label>
                                <Input
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                />
                            </div>
                            <div className="flex items-end gap-2">
                                <Button onClick={applyFilters} className="px-4">
                                    Apply Filters
                                </Button>
                                <Button onClick={clearFilters} variant="outline" className="px-4">
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
                                    <tr className="border-b border-gray-200 dark:border-gray-700">
                                        <th className="text-left py-3 px-4 text-[13px] font-semibold text-gray-700 dark:text-gray-300">
                                            <input
                                                type="checkbox"
                                                checked={selectedDepartments.length === departments.data.length && departments.data.length > 0}
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
                                        </th>
                                        <th className="text-left py-3 px-4 text-[13px] font-semibold text-gray-700 dark:text-gray-300 cursor-pointer" onClick={() => handleSort('name')}>
                                            <div className="flex items-center gap-1">
                                                Department Name
                                                {sortBy === 'name' && (sortOrder === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />)}
                                            </div>
                                        </th>
                                        <th className="text-left py-3 px-4 text-[13px] font-semibold text-gray-700 dark:text-gray-300">Employee Type</th>
                                        <th className="text-left py-3 px-4 text-[13px] font-semibold text-gray-700 dark:text-gray-300">Status</th>
                                        {hasActionPermission && (
                                            <th className="text-left py-3 px-4 text-[13px] font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {departments.data.length > 0 ? departments.data.map((department) => (
                                        <tr key={department.id} className="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                            <td className="py-3 px-4">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedDepartments.includes(department.id)}
                                                    onChange={() => toggleSelectDepartment(department.id)}
                                                    className="rounded border-gray-300 dark:border-gray-600"
                                                />
                                            </td>
                                            <td className="py-3 px-4 text-[13px] font-medium text-gray-900 dark:text-gray-100">{department.name}</td>
                                            <td className="py-3 px-4 text-[13px] text-gray-700 dark:text-gray-300">
                                                {department.emp_type_name || 'No Type'}
                                            </td>
                                            <td className="py-3 px-4">
                                                <span className={`px-2 py-1 text-[11px] rounded-full ${department.status ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}`}>
                                                    {department.status ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            {hasActionPermission && (
                                                <td className="py-3 px-4">
                                                    <div className="flex gap-2">
                                                        {can('update-employee') && (
                                                            <Button 
                                                                variant="ghost" 
                                                                size="sm"
                                                                onClick={() => handleEdit(department)}
                                                                className="text-indigo-600 hover:text-indigo-800"
                                                            >
                                                                <Edit className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {can('delete-employee') && (
                                                            <Button 
                                                                variant="ghost" 
                                                                size="sm"
                                                                onClick={() => handleDelete(department)}
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
                                            <td colSpan={hasActionPermission ? 5 : 4} className="py-12 text-center text-gray-500 dark:text-gray-400">
                                                <Building className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                                No departments found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        
                        <Pagination
                            currentPage={departments.current_page}
                            lastPage={departments.last_page}
                            from={departments.from}
                            to={departments.to}
                            total={departments.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                router.get('/emp-departments', {
                                    search: search || undefined,
                                    status: status === 'all' ? undefined : status,
                                    emp_type_id: empTypeId === 'all' ? undefined : empTypeId,
                                    start_date: startDate || undefined,
                                    end_date: endDate || undefined,
                                    sort_by: sortBy,
                                    sort_order: sortOrder,
                                    per_page: newPerPage,
                                }, { preserveState: true });
                            }}
                        />
                    </CardContent>
                </Card>

                <FormModal
                    isOpen={isCreateOpen}
                    onClose={() => setIsCreateOpen(false)}
                    title="Create Department"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Create"
                >
                    <div>
                        <Label htmlFor="name" className="dark:text-gray-200">Department Name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            placeholder="e.g., IT Department"
                        />
                        {errors.name && <span className="text-red-500 text-sm">{errors.name}</span>}
                    </div>
                    <div>
                        <Label htmlFor="emp_type_id" className="dark:text-gray-200">Employee Type</Label>
                        <Select value={data.emp_type_id?.toString() || 'none'} onValueChange={(value) => setData('emp_type_id', value === 'none' ? null : parseInt(value))}>
                            <SelectTrigger className="dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <SelectValue placeholder="Select employee type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">No Type</SelectItem>
                                {empTypes.map((type) => (
                                    <SelectItem key={type.id} value={type.id.toString()}>
                                        {type.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label htmlFor="status" className="dark:text-gray-200">Status</Label>
                        <Select value={data.status ? '1' : '0'} onValueChange={(value) => setData('status', value === '1')}>
                            <SelectTrigger className="dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">Active</SelectItem>
                                <SelectItem value="0">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </FormModal>

                <FormModal
                    isOpen={!!editingDepartment}
                    onClose={() => setEditingDepartment(null)}
                    title="Edit Department"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Update"
                >
                    <div>
                        <Label htmlFor="edit-name" className="dark:text-gray-200">Department Name</Label>
                        <Input
                            id="edit-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        />
                        {errors.name && <span className="text-red-500 text-sm">{errors.name}</span>}
                    </div>
                    <div>
                        <Label htmlFor="edit-emp_type_id" className="dark:text-gray-200">Employee Type</Label>
                        <Select value={data.emp_type_id?.toString() || 'none'} onValueChange={(value) => setData('emp_type_id', value === 'none' ? null : parseInt(value))}>
                            <SelectTrigger className="dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <SelectValue placeholder="Select employee type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">No Type</SelectItem>
                                {empTypes.map((type) => (
                                    <SelectItem key={type.id} value={type.id.toString()}>
                                        {type.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label htmlFor="edit-status" className="dark:text-gray-200">Status</Label>
                        <Select value={data.status ? '1' : '0'} onValueChange={(value) => setData('status', value === '1')}>
                            <SelectTrigger className="dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">Active</SelectItem>
                                <SelectItem value="0">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </FormModal>

                <DeleteModal
                    isOpen={!!deletingDepartment}
                    onClose={() => setDeletingDepartment(null)}
                    onConfirm={confirmDelete}
                    title="Delete Department"
                    message={`Are you sure you want to delete the department "${deletingDepartment?.name}"? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Departments"
                    message={`Are you sure you want to delete ${selectedDepartments.length} selected departments? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}