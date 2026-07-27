import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DeleteModal } from '@/components/ui/delete-modal';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { usePermission } from '@/hooks/usePermission';
import {
    ChevronDown,
    ChevronUp,
    Edit,
    Eye,
    FileText,
    Filter,
    Plus,
    Trash2,
    Users as EmployeeIcon,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface Employee {
    id: number;
    employee_name: string;
    email: string;
    mobile: string;
    designation: {
        id: number;
        name: string;
    };
    department: {
        id: number;
        name: string;
    };
    empType: {
        id: number;
        name: string;
    };
    status: boolean;
    created_at: string;
}

interface Department {
    id: number;
    name: string;
}

interface Designation {
    id: number;
    name: string;
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
        title: 'Employees',
        href: '/employees',
    },
];

interface EmployeesProps {
    employees?: {
        data: Employee[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    departments?: Department[];
    designations?: Designation[];
    empTypes?: EmpType[];
    filters?: {
        search?: string;
        department?: string;
        designation?: string;
        type?: string;
        status?: string;
        start_date?: string;
        end_date?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function EmployeeIndex({ 
    employees = { data: [], current_page: 1, last_page: 1, per_page: 10, total: 0, from: 0, to: 0 }, 
    departments = [], 
    designations = [], 
    empTypes = [], 
    filters = {} 
}: EmployeesProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-employee') || can('delete-employee');
    const canFilter = can('can-employee-filter');
    const canDownload = can('can-employee-download');
    const [deletingEmployee, setDeletingEmployee] = useState<Employee | null>(null);
    const [selectedEmployees, setSelectedEmployees] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters?.search || '');
    const [department, setDepartment] = useState(filters?.department || 'all');
    const [designation, setDesignation] = useState(filters?.designation || 'all');
    const [type, setType] = useState(filters?.type || 'all');
    const [status, setStatus] = useState(filters?.status || 'all');
    const [startDate, setStartDate] = useState(filters?.start_date || '');
    const [endDate, setEndDate] = useState(filters?.end_date || '');
    const [sortBy, setSortBy] = useState(filters?.sort_by || 'created_at');
    const [sortOrder, setSortOrder] = useState(filters?.sort_order || 'desc');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);

    const handleDelete = (employee: Employee) => {
        setDeletingEmployee(employee);
    };

    const confirmDelete = () => {
        if (deletingEmployee) {
            router.delete(`/employees/${deletingEmployee.id}`, {
                onSuccess: () => setDeletingEmployee(null),
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/employees/bulk/delete', {
            data: { ids: selectedEmployees },
            onSuccess: () => {
                setSelectedEmployees([]);
                setIsBulkDeleting(false);
            },
        });
    };

    const applyFilters = () => {
        router.get(
            '/employees',
            {
                search: search || undefined,
                department: department === 'all' ? undefined : department,
                designation: designation === 'all' ? undefined : designation,
                type: type === 'all' ? undefined : type,
                status: status === 'all' ? undefined : status,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        setDepartment('all');
        setDesignation('all');
        setType('all');
        setStatus('all');
        setStartDate('');
        setEndDate('');
        router.get(
            '/employees',
            {
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const handleSort = (column: string) => {
        const newOrder = sortBy === column && sortOrder === 'asc' ? 'desc' : 'asc';
        setSortBy(column);
        setSortOrder(newOrder);
        router.get(
            '/employees',
            {
                search: search || undefined,
                department: department === 'all' ? undefined : department,
                designation: designation === 'all' ? undefined : designation,
                type: type === 'all' ? undefined : type,
                status: status === 'all' ? undefined : status,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                sort_by: column,
                sort_order: newOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/employees',
            {
                search: search || undefined,
                department: department === 'all' ? undefined : department,
                designation: designation === 'all' ? undefined : designation,
                type: type === 'all' ? undefined : type,
                status: status === 'all' ? undefined : status,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
                page,
            },
            { preserveState: true },
        );
    };

    const toggleSelectAll = () => {
        if (selectedEmployees.length === employees?.data?.length) {
            setSelectedEmployees([]);
        } else {
            setSelectedEmployees(employees?.data?.map((employee) => employee.id) || []);
        }
    };

    const toggleSelectEmployee = (employeeId: number) => {
        if (selectedEmployees.includes(employeeId)) {
            setSelectedEmployees(selectedEmployees.filter((id) => id !== employeeId));
        } else {
            setSelectedEmployees([...selectedEmployees, employeeId]);
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Employees" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Employees
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Manage company employees and their information
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {selectedEmployees.length > 0 && can('delete-employee') && (
                            <Button
                                variant="destructive"
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedEmployees.length})
                            </Button>
                        )}
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={() => {
                                    const params = new URLSearchParams();
                                    if (search) params.append('search', search);
                                    if (department !== 'all') params.append('department', department);
                                    if (designation !== 'all') params.append('designation', designation);
                                    if (type !== 'all') params.append('type', type);
                                    if (status !== 'all') params.append('status', status);
                                    if (startDate) params.append('start_date', startDate);
                                    if (endDate) params.append('end_date', endDate);
                                    if (sortBy) params.append('sort_by', sortBy);
                                    if (sortOrder) params.append('sort_order', sortOrder);
                                    window.location.href = `/employees/download-pdf?${params.toString()}`;
                                }}
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Download
                            </Button>
                        )}
                        {can('create-employee') && (
                            <Button onClick={() => router.get('/employees/create')}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Employee
                            </Button>
                        )}
                    </div>
                </div>

                {/* Filter Card */}
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
                                <Label className="dark:text-gray-200">Search</Label>
                                <Input
                                    placeholder="Search employees..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">Department</Label>
                                <Select
                                    value={department}
                                    onValueChange={(value) => {
                                        setDepartment(value);
                                        applyFilters();
                                    }}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All departments" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All departments</SelectItem>
                                        {departments.map((dept) => (
                                            <SelectItem key={dept.id} value={dept.name}>
                                                {dept.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">Designation</Label>
                                <Select
                                    value={designation}
                                    onValueChange={(value) => {
                                        setDesignation(value);
                                        applyFilters();
                                    }}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All designations" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All designations</SelectItem>
                                        {designations.map((desig) => (
                                            <SelectItem key={desig.id} value={desig.name}>
                                                {desig.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">Type</Label>
                                <Select
                                    value={type}
                                    onValueChange={(value) => {
                                        setType(value);
                                        applyFilters();
                                    }}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All types</SelectItem>
                                        {empTypes.map((empType) => (
                                            <SelectItem key={empType.id} value={empType.name}>
                                                {empType.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-end gap-2">
                                <Button onClick={applyFilters} className="px-4">
                                    Apply Filters
                                </Button>
                                <Button onClick={clearFilters} variant="secondary" className="px-4">
                                    <X className="mr-2 h-4 w-4" />
                                    Clear
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                )}

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="p-4 text-left font-medium dark:text-gray-300">
                                            <input
                                                type="checkbox"
                                                checked={selectedEmployees.length === employees?.data?.length && employees?.data?.length > 0}
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
                                        </th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('employee_name')}
                                        >
                                            <div className="flex items-center gap-1">
                                                Name
                                                {sortBy === 'employee_name' && (sortOrder === 'asc' ? (
                                                    <ChevronUp className="h-4 w-4" />
                                                ) : (
                                                    <ChevronDown className="h-4 w-4" />
                                                ))}
                                            </div>
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Account Number</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Email</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Phone</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Department</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Designation</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Status</th>
                                        {hasActionPermission && (
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Actions</th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {employees?.data?.length > 0 ? (
                                        employees.data.map((employee) => (
                                            <tr
                                                key={employee.id}
                                                className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                            >
                                                <td className="p-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedEmployees.includes(employee.id)}
                                                        onChange={() => toggleSelectEmployee(employee.id)}
                                                        className="rounded border-gray-300 dark:border-gray-600"
                                                    />
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">{employee.employee_name}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{employee.account?.ac_number || 'N/A'}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{employee.email}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{employee.mobile}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{employee.department?.name}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{employee.designation?.name}</td>
                                                <td className="p-4">
                                                    <span className={`px-2 py-1 rounded text-xs ${
                                                        employee.status 
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                                                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                    }`}>
                                                        {employee.status ? 'Active' : 'Inactive'}
                                                    </span>
                                                </td>
                                                {hasActionPermission && (
                                                    <td className="p-4">
                                                        <div className="flex gap-2">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => router.get(`/employees/${employee.id}`)}
                                                                className="text-blue-600 hover:text-blue-800"
                                                            >
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                            {can('update-employee') && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => router.get(`/employees/${employee.id}/edit`)}
                                                                    className="text-indigo-600 hover:text-indigo-800"
                                                                >
                                                                    <Edit className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                            {can('delete-employee') && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => handleDelete(employee)}
                                                                    className="text-red-600 hover:text-red-800"
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </td>
                                                )}
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={hasActionPermission ? 9 : 8} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                <EmployeeIcon className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No employees found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={employees?.current_page || 1}
                            lastPage={employees?.last_page || 1}
                            from={employees?.from || 0}
                            to={employees?.to || 0}
                            total={employees?.total || 0}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                applyFilters();
                            }}
                        />
                    </CardContent>
                </Card>

                <DeleteModal
                    isOpen={!!deletingEmployee}
                    onClose={() => setDeletingEmployee(null)}
                    onConfirm={confirmDelete}
                    title="Delete Employee"
                    message={`Are you sure you want to delete the employee "${deletingEmployee?.employee_name}"? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Employees"
                    message={`Are you sure you want to delete ${selectedEmployees.length} selected employees? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}