import { openPdfViewer } from '@/components/documents/pdf-viewer';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FormModal } from '@/components/ui/form-modal';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    ProductAssignment,
    VehicleProductSelector,
} from '@/components/vehicle-product-selector';
import { usePermission } from '@/hooks/usePermission';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    ChevronDown,
    ChevronUp,
    CreditCard,
    DollarSign,
    FileText,
    MessageSquare,
    Plus,
} from 'lucide-react';
import { useState } from 'react';

interface Customer {
    id: number;
    code?: string;
    name: string;
    proprietor_name?: string;
    mobile?: string;
    email?: string;
    nid_number?: string;
    vat_reg_no?: string;
    tin_no?: string;
    trade_license?: string;
    discount_rate?: number;
    security_deposit?: number;
    credit_limit?: number;
    address?: string;
    status: boolean;
    account?: {
        id: number;
        name: string;
        ac_number: string;
    };
    vehicles?: Array<{
        id: number;
        vehicle_number: string;
        vehicle_name?: string;
        vehicle_type?: string;
        reg_date?: string;
        status: boolean;
        products?: Array<{
            id: number;
            product_name: string;
            sort_order: number;
        }>;
    }>;
    created_at: string;
}

interface RecentPayment {
    id: number;
    key?: string;
    voucher_no: string;
    date: string;
    amount: number;
    type: string;
    sub_type: string;
    status: string;
}

interface RecentSale {
    id: number;
    date: string;
    amount: number;
    quantity: number;
    vehicle_number: string;
    invoice_no: string;
    status: boolean;
}

interface CustomerDetailsProps {
    customer: Customer;
    recentPayments: RecentPayment[];
    recentSales: RecentSale[];
    totalSales: number;
    totalPaid: number;
    currentBalance: number;
    smsTemplates: Array<{
        id: number;
        title: string;
        type: string;
        message: string;
    }>;
    products: Array<{
        id: number;
        name: string;
    }>;
    vehicleProductLimit: number;
}

export default function CustomerDetails({
    customer,
    recentPayments,
    recentSales,
    totalSales,
    totalPaid,
    currentBalance,
    smsTemplates = [],
    products = [],
    vehicleProductLimit,
}: CustomerDetailsProps) {
    const { can } = usePermission();
    const [isVehicleOpen, setIsVehicleOpen] = useState(false);
    const [isAddVehicleOpen, setIsAddVehicleOpen] = useState(false);
    const [isSMSModalOpen, setIsSMSModalOpen] = useState(false);
    const [messageType, setMessageType] = useState('template');
    const [selectedTemplate, setSelectedTemplate] = useState('');
    const [customMessage, setCustomMessage] = useState('');
    const [phoneNumber, setPhoneNumber] = useState(customer.mobile || '');
    const [processing, setProcessing] = useState(false);
    const {
        data: vehicleData,
        setData: setVehicleData,
        post: postVehicle,
        processing: vehicleProcessing,
        errors: vehicleErrors,
        reset: resetVehicle,
    } = useForm({
        vehicle_number: '',
        vehicle_name: '',
        vehicle_type: '',
        reg_date: '',
        status: true,
        products: [] as ProductAssignment[],
    });
    const productAssignmentError =
        vehicleErrors.products ||
        Object.entries(vehicleErrors).find(([key]) =>
            key.startsWith('products.'),
        )?.[1];

    const closeVehicleModal = () => {
        setIsAddVehicleOpen(false);
        resetVehicle();
    };

    const submitVehicle = (event: React.FormEvent) => {
        event.preventDefault();
        postVehicle(`/customers/${customer.id}/vehicles`, {
            preserveScroll: true,
            only: ['customer'],
            onSuccess: () => {
                closeVehicleModal();
                setIsVehicleOpen(true);
            },
        });
    };

    return (
        <AppLayout>
            <Head title={`Customer - ${customer.name}`} />

            {/* Header */}
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            {customer.name}
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Customer details and information
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="success"
                            onClick={() =>
                                openPdfViewer(
                                    `/customers/${customer.id}/download-pdf`,
                                )
                            }
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Download
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get(
                                    `/customers/${customer.id}/statement`,
                                )
                            }
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Statement
                        </Button>
                        <Button
                            onClick={() => setIsSMSModalOpen(true)}
                            className="bg-blue-600 text-white hover:bg-blue-700"
                        >
                            <MessageSquare className="mr-2 h-4 w-4" />
                            Send SMS
                        </Button>
                        <Button
                            variant="secondary"
                            onClick={() => router.get('/customers')}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to List
                        </Button>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Security Deposit
                                    </p>
                                    <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                        {customer.security_deposit?.toLocaleString() ||
                                            '0'}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Total Sales
                                    </p>
                                    <p className="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                                        {totalSales.toLocaleString()}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Total Paid
                                    </p>
                                    <p className="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                                        {totalPaid.toLocaleString()}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {currentBalance < 0
                                            ? 'Current Advance'
                                            : 'Current Due'}
                                    </p>
                                    <p
                                        className={`text-2xl font-bold ${
                                            currentBalance < 0
                                                ? 'text-green-600 dark:text-green-400'
                                                : currentBalance > 0
                                                  ? 'text-red-600 dark:text-red-400'
                                                  : 'text-gray-900 dark:text-white'
                                        }`}
                                    >
                                        {Math.abs(
                                            currentBalance,
                                        ).toLocaleString()}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Customer Details Card */}
                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="dark:text-white">
                            Customer Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Name
                                    </label>
                                    <p className="text-gray-900 dark:text-white">
                                        {customer.name}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Code
                                    </label>
                                    <p className="text-gray-900 dark:text-white">
                                        {customer.code || 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Proprietor Name
                                    </label>
                                    <p className="text-gray-900 dark:text-white">
                                        {customer.proprietor_name || 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Mobile
                                    </label>
                                    <p className="text-gray-900 dark:text-white">
                                        {customer.mobile || 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Email
                                    </label>
                                    <p className="text-gray-900 dark:text-white">
                                        {customer.email || 'N/A'}
                                    </p>
                                </div>
                            </div>
                            <div className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        NID Number
                                    </label>
                                    <p className="text-gray-900 dark:text-white">
                                        {customer.nid_number || 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Address
                                    </label>
                                    <p className="text-gray-900 dark:text-white">
                                        {customer.address || 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Status
                                    </label>
                                    <span
                                        className={`rounded px-2 py-1 text-xs ${
                                            customer.status
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                        }`}
                                    >
                                        {customer.status
                                            ? 'Active'
                                            : 'Inactive'}
                                    </span>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Created Date
                                    </label>
                                    <p className="text-gray-900 dark:text-white">
                                        {new Date(
                                            customer.created_at,
                                        ).toLocaleDateString('en-GB')}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Action Cards */}
                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <Card
                        className="cursor-pointer dark:border-gray-700 dark:bg-gray-800"
                        onClick={() => router.get('/sales')}
                    >
                        <CardContent className="p-6">
                            <div className="flex items-center gap-4">
                                <div className="rounded-lg bg-green-100 p-3 dark:bg-green-900">
                                    <DollarSign className="h-6 w-6 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                        Cash Sale
                                    </h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Create new cash sale
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        className="cursor-pointer dark:border-gray-700 dark:bg-gray-800"
                        onClick={() => router.get('/credit-sales')}
                    >
                        <CardContent className="p-6">
                            <div className="flex items-center gap-4">
                                <div className="rounded-lg bg-blue-100 p-3 dark:bg-blue-900">
                                    <CreditCard className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                        Credit Sale
                                    </h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Create new credit sale
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        className="cursor-pointer dark:border-gray-700 dark:bg-gray-800"
                        onClick={() => router.get('/vouchers/received')}
                    >
                        <CardContent className="p-6">
                            <div className="flex items-center gap-4">
                                <div className="rounded-lg bg-purple-100 p-3 dark:bg-purple-900">
                                    <Banknote className="h-6 w-6 text-purple-600 dark:text-purple-400" />
                                </div>
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                        Receive Payment
                                    </h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Receive customer payment
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Activity Cards */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="dark:text-white">
                                Recent Sales
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Date
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Vehicle Number
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Quantity
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Amount
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentSales &&
                                        recentSales.length > 0 ? (
                                            recentSales.map((sale) => (
                                                <tr
                                                    key={sale.id}
                                                    className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                                >
                                                    <td className="p-4 text-[13px] dark:text-white">
                                                        {new Date(
                                                            sale.date,
                                                        ).toLocaleDateString(
                                                            'en-GB',
                                                        )}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        {sale.vehicle_number}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        {sale.quantity}
                                                    </td>
                                                    <td className="p-4 text-[13px] font-semibold dark:text-white">
                                                        {sale.amount.toLocaleString()}
                                                    </td>
                                                    <td className="p-4">
                                                        <span
                                                            className={`rounded px-2 py-1 text-xs ${
                                                                sale.status
                                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                                    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                            }`}
                                                        >
                                                            {sale.status
                                                                ? 'Active'
                                                                : 'Inactive'}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="p-8 text-center text-gray-500 dark:text-gray-400"
                                                >
                                                    No recent sales found
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="dark:text-white">
                                Recent Payments
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                SL
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Voucher No
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Date
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Amount
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Type
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Payment Type
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentPayments &&
                                        recentPayments.length > 0 ? (
                                            recentPayments.map(
                                                (payment, index) => (
                                                    <tr
                                                        key={
                                                            payment.key ||
                                                            payment.id
                                                        }
                                                        className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                                    >
                                                        <td className="p-4 text-[13px] dark:text-white">
                                                            {index + 1}
                                                        </td>
                                                        <td className="p-4 text-[13px] dark:text-white">
                                                            {payment.voucher_no}
                                                        </td>
                                                        <td className="p-4 text-[13px] dark:text-white">
                                                            {new Date(
                                                                payment.date,
                                                            ).toLocaleDateString(
                                                                'en-GB',
                                                            )}
                                                        </td>
                                                        <td className="p-4 text-[13px] font-semibold dark:text-white">
                                                            {payment.amount.toLocaleString()}
                                                        </td>
                                                        <td className="p-4 text-[13px] dark:text-gray-300">
                                                            {payment.sub_type}
                                                        </td>
                                                        <td className="p-4 text-[13px] dark:text-gray-300">
                                                            {payment.type}
                                                        </td>
                                                        <td className="p-4">
                                                            <span className="rounded bg-green-100 px-2 py-1 text-xs text-green-800 dark:bg-green-900 dark:text-green-200">
                                                                {payment.status}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                ),
                                            )
                                        ) : (
                                            <tr>
                                                <td
                                                    colSpan={7}
                                                    className="p-8 text-center text-gray-500 dark:text-gray-400"
                                                >
                                                    No recent payments found
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Vehicle Accordion */}
                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader className="flex flex-row items-center justify-between gap-3">
                        <button
                            onClick={() => setIsVehicleOpen(!isVehicleOpen)}
                            className="flex min-w-0 flex-1 cursor-pointer items-center justify-between text-left"
                        >
                            <CardTitle className="dark:text-white">
                                Vehicles
                            </CardTitle>
                            {isVehicleOpen ? (
                                <ChevronUp className="h-5 w-5 dark:text-white" />
                            ) : (
                                <ChevronDown className="h-5 w-5 dark:text-white" />
                            )}
                        </button>
                        {can('create-vehicle') && (
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => setIsAddVehicleOpen(true)}
                            >
                                <Plus className="h-4 w-4" />
                                Add Vehicle
                            </Button>
                        )}
                    </CardHeader>
                    {isVehicleOpen && (
                        <CardContent className="pb-6">
                            {customer.vehicles &&
                            customer.vehicles.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b dark:border-gray-700">
                                                <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                    SL
                                                </th>
                                                <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                    Vehicle Number
                                                </th>
                                                <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                    Vehicle Name
                                                </th>
                                                <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                    Type
                                                </th>
                                                <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                    Product
                                                </th>
                                                <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                    Registration Date
                                                </th>
                                                <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {customer.vehicles.map(
                                                (vehicle, index) => (
                                                    <tr
                                                        key={vehicle.id}
                                                        className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                                    >
                                                        <td className="p-4 text-[13px] dark:text-white">
                                                            {index + 1}
                                                        </td>
                                                        <td className="p-4 text-[13px] dark:text-white">
                                                            {
                                                                vehicle.vehicle_number
                                                            }
                                                        </td>
                                                        <td className="p-4 text-[13px] dark:text-gray-300">
                                                            {vehicle.vehicle_name ||
                                                                'N/A'}
                                                        </td>
                                                        <td className="p-4 text-[13px] dark:text-gray-300">
                                                            {vehicle.vehicle_type ||
                                                                'N/A'}
                                                        </td>
                                                        <td className="p-4">
                                                            {vehicle.products &&
                                                            vehicle.products
                                                                .length > 0 ? (
                                                                <div className="flex flex-wrap gap-1">
                                                                    {vehicle.products.map(
                                                                        (
                                                                            p,
                                                                            productIndex,
                                                                        ) => (
                                                                            <Badge
                                                                                key={
                                                                                    p.id
                                                                                }
                                                                                variant="secondary"
                                                                            >
                                                                                {productIndex +
                                                                                    1}

                                                                                .{' '}
                                                                                {
                                                                                    p.product_name
                                                                                }
                                                                            </Badge>
                                                                        ),
                                                                    )}
                                                                </div>
                                                            ) : (
                                                                <span className="text-[13px] dark:text-gray-300">
                                                                    N/A
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="p-4 text-[13px] dark:text-gray-300">
                                                            {vehicle.reg_date
                                                                ? new Date(
                                                                      vehicle.reg_date,
                                                                  ).toLocaleDateString(
                                                                      'en-GB',
                                                                  )
                                                                : 'N/A'}
                                                        </td>
                                                        <td className="p-4">
                                                            <Badge
                                                                variant={
                                                                    vehicle.status
                                                                        ? 'default'
                                                                        : 'secondary'
                                                                }
                                                            >
                                                                {vehicle.status
                                                                    ? 'Active'
                                                                    : 'Inactive'}
                                                            </Badge>
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <p className="py-8 text-center text-gray-500 dark:text-gray-400">
                                    No vehicles found
                                </p>
                            )}
                        </CardContent>
                    )}
                </Card>

                {/* Design your page here */}
            </div>

            <FormModal
                isOpen={isAddVehicleOpen}
                onClose={closeVehicleModal}
                title={`Add Vehicle - ${customer.name}`}
                onSubmit={submitVehicle}
                processing={vehicleProcessing}
                submitText="Create Vehicle"
                errors={vehicleErrors}
                className="w-[calc(100vw-2rem)] max-w-4xl"
            >
                <div className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label>
                                Vehicle Number{' '}
                                <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                value={vehicleData.vehicle_number}
                                onChange={(event) =>
                                    setVehicleData(
                                        'vehicle_number',
                                        event.target.value,
                                    )
                                }
                                placeholder="Enter vehicle number"
                            />
                            <InputError
                                message={vehicleErrors.vehicle_number}
                            />
                        </div>
                        <div>
                            <Label>Vehicle Name</Label>
                            <Input
                                value={vehicleData.vehicle_name}
                                onChange={(event) =>
                                    setVehicleData(
                                        'vehicle_name',
                                        event.target.value,
                                    )
                                }
                                placeholder="Enter vehicle name"
                            />
                            <InputError message={vehicleErrors.vehicle_name} />
                        </div>
                        <div>
                            <Label>Vehicle Type</Label>
                            <Input
                                value={vehicleData.vehicle_type}
                                onChange={(event) =>
                                    setVehicleData(
                                        'vehicle_type',
                                        event.target.value,
                                    )
                                }
                                placeholder="Enter vehicle type"
                            />
                            <InputError message={vehicleErrors.vehicle_type} />
                        </div>
                        <div>
                            <Label>Registration Date</Label>
                            <Input
                                type="date"
                                value={vehicleData.reg_date}
                                onChange={(event) =>
                                    setVehicleData(
                                        'reg_date',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={vehicleErrors.reg_date} />
                        </div>
                        <div>
                            <Label>Status</Label>
                            <Select
                                value={
                                    vehicleData.status ? 'active' : 'inactive'
                                }
                                onValueChange={(value) =>
                                    setVehicleData('status', value === 'active')
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">
                                        Active
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Inactive
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={vehicleErrors.status} />
                        </div>
                    </div>

                    <div>
                        <Label>Assigned Products</Label>
                        <VehicleProductSelector
                            products={products}
                            value={vehicleData.products}
                            onChange={(assignments) =>
                                setVehicleData('products', assignments)
                            }
                            error={productAssignmentError}
                            disabled={vehicleProcessing}
                            maxProducts={vehicleProductLimit}
                        />
                    </div>
                </div>
            </FormModal>

            {/* SMS Modal */}
            <FormModal
                isOpen={isSMSModalOpen}
                onClose={() => setIsSMSModalOpen(false)}
                title="Send SMS"
                onSubmit={(e) => {
                    e.preventDefault();
                    setProcessing(true);
                    router.post(
                        `/customers/${customer.id}/send-sms`,
                        {
                            phone_number: phoneNumber,
                            message_type: messageType,
                            ...(messageType === 'template'
                                ? { template_id: selectedTemplate }
                                : {}),
                            ...(messageType === 'custom'
                                ? { custom_message: customMessage }
                                : {}),
                        },
                        {
                            onSuccess: () => {
                                setIsSMSModalOpen(false);
                                setProcessing(false);
                                // Reset form
                                setMessageType('template');
                                setSelectedTemplate('');
                                setCustomMessage('');
                                setPhoneNumber(customer.mobile || '');
                            },
                            onError: () => {
                                setProcessing(false);
                            },
                        },
                    );
                }}
                processing={processing}
                submitText="Send SMS"
            >
                <div>
                    <Label className="dark:text-gray-200">Customer</Label>
                    <Input
                        value={customer.name}
                        readOnly
                        className="bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                </div>
                <div>
                    <Label className="dark:text-gray-200">Phone Number</Label>
                    <Input
                        value={phoneNumber}
                        onChange={(e) => setPhoneNumber(e.target.value)}
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        placeholder="Enter phone number"
                    />
                </div>
                <div>
                    <Label className="dark:text-gray-200">Message Type</Label>
                    <Select value={messageType} onValueChange={setMessageType}>
                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="template">
                                Use SMS Template
                            </SelectItem>
                            <SelectItem value="custom">Custom SMS</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                {messageType === 'template' && (
                    <div>
                        <Label className="dark:text-gray-200">
                            Select SMS Template
                        </Label>
                        <Select
                            value={selectedTemplate}
                            onValueChange={setSelectedTemplate}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Choose a template" />
                            </SelectTrigger>
                            <SelectContent>
                                {smsTemplates.map((template) => (
                                    <SelectItem
                                        key={template.id}
                                        value={template.id.toString()}
                                    >
                                        {template.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}
                {messageType === 'custom' && (
                    <div>
                        <Label className="dark:text-gray-200">
                            Custom Message
                        </Label>
                        <Textarea
                            id="custom-message"
                            value={customMessage}
                            onChange={(e) => setCustomMessage(e.target.value)}
                            rows={4}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            placeholder="Enter your custom SMS message..."
                        />
                        <div className="mt-2">
                            <div className="mb-2 text-xs text-gray-500 dark:text-gray-400">
                                Click variables to insert:
                            </div>
                            <div className="flex flex-wrap gap-1">
                                {[
                                    'customer_name',
                                    'total_payment',
                                    'total_due',
                                    'account_number',
                                    'customer_mobile',
                                    'customer_email',
                                    'total_cradit',
                                    'security_deposit',
                                ].map((variable) => (
                                    <button
                                        key={variable}
                                        type="button"
                                        onClick={() => {
                                            const textarea =
                                                document.getElementById(
                                                    'custom-message',
                                                ) as HTMLTextAreaElement;
                                            const cursorPos =
                                                textarea.selectionStart;
                                            const textBefore =
                                                customMessage.substring(
                                                    0,
                                                    cursorPos,
                                                );
                                            const textAfter =
                                                customMessage.substring(
                                                    cursorPos,
                                                );
                                            const newText =
                                                textBefore +
                                                `{{${variable}}}` +
                                                textAfter;
                                            setCustomMessage(newText);
                                            setTimeout(() => {
                                                textarea.focus();
                                                textarea.setSelectionRange(
                                                    cursorPos +
                                                        variable.length +
                                                        4,
                                                    cursorPos +
                                                        variable.length +
                                                        4,
                                                );
                                            }, 0);
                                        }}
                                        className="cursor-pointer rounded bg-blue-100 px-2 py-1 text-xs text-blue-800 transition-colors hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800"
                                    >
                                        {variable}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                )}
            </FormModal>
        </AppLayout>
    );
}
