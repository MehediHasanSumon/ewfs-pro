import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { FormModal } from '@/components/ui/form-modal';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue, } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FileText, } from 'lucide-react';
import { useEffect, useState } from 'react';
import { usePermission } from '@/hooks/usePermission';
import { CreditSaleModal } from '@/pages/CreditSales/CreditSaleModal';
import { SaleModal } from '@/pages/Sales/SaleModal';
import { ReceivedVoucherModal } from '@/pages/Vouchers/ReceivedVoucherModal';
import { PaymentVoucherModal } from '@/pages/Vouchers/PaymentVoucherModal';

interface DispenserReading {
    id: number;
    dispenser_id: number;
    product_id: number;
    item_rate: number;
    start_reading: number;
    end_reading: number;
    meter_test: number;
    net_reading: number;
    total_sale: number;
    dispenser?: {
        id: number;
        dispenser_name: string;
    };
    product?: {
        id: number;
        product_name: string;
        sales_price: number;
    };
}

interface Shift {
    id: number;
    name: string;
}

interface ClosedShift {
    close_date: string;
    shift_id: number;
}

interface ProductWiseData {
    [key: number]: {
        net_reading: number;
        total_sale: number;
        credit_sales: number;
        cash_sales: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Product', href: '#' },
    { title: 'Dispensers Reading', href: '/product/dispensers-reading' },
];

interface Product {
    id: number;
    product_name: string;
    product_code: string;
    sales_price: number;
    unit?: { name: string };
    stock?: { current_stock: number };
}

interface Customer {
    id: number;
    name: string;
}

interface Vehicle {
    id: number;
    vehicle_number: string;
    customer_id: number;
    product_id: number;
    products?: {
        id: number;
        product_name: string;
    }[];
    customer?: {
        id: number;
        name: string;
    } | null;
}

interface Account {
    id: number;
    name: string;
    ac_number: string;
}

interface Employee {
    id: number;
    employee_name: string;
}

interface VoucherCategory {
    id: number;
    name: string;
}

interface PaymentSubType {
    id: number;
    name: string;
    voucher_category_id: number;
}

interface DispenserReadingProps {
    dispenserReading: DispenserReading[];
    shifts: Shift[];
    closedShifts: ClosedShift[];
    products?: Product[];
    otherProducts?: Product[];
    customers?: Customer[];
    vehicles?: Vehicle[];
    accounts?: Account[];
    groupedAccounts?: Record<string, Account[]>;
    employees?: Employee[];
    uniqueCustomers?: string[];
    uniqueVehicles?: string[];
    voucherCategories?: VoucherCategory[];
    paymentSubTypes?: PaymentSubType[];
}

export default function DispenserReading({
    dispenserReading = [],
    shifts = [],
    closedShifts = [],
    products = [],
    otherProducts = [],
    customers = [],
    vehicles = [],
    accounts = [],
    groupedAccounts = {},
    employees = [],
    uniqueCustomers = [],
    uniqueVehicles = [],
    voucherCategories = [],
    paymentSubTypes = [],
}: DispenserReadingProps) {
    const { can } = usePermission();
    const canCreateCreditSales = can('create-credit-sale');
    const canCreateSale = can('create-sale');
    const canCreateVoucher = can('create-voucher');
    const canCreateOfficePayment = can('create-office-payment');
    const canCreateDispenserReading = can('create-dispenser-reading');

    const [productWiseData, setProductWiseData] = useState<ProductWiseData>({});
    const [totalSalesSum, setTotalSalesSum] = useState(0);
    const [availableShifts, setAvailableShifts] = useState<Shift[]>([]);
    const [isCreditSalesOpen, setIsCreditSalesOpen] = useState(false);
    const [isBankSalesOpen, setIsBankSalesOpen] = useState(false);
    const [isCashReceiveOpen, setIsCashReceiveOpen] = useState(false);
    const [isCashPaymentOpen, setIsCashPaymentOpen] = useState(false);
    const [isOfficePaymentOpen, setIsOfficePaymentOpen] = useState(false);
    const [officePaymentData, setOfficePaymentData] = useState({
        date: '',
        shift_id: '',
        to_account_id: '',
        amount: '',
        payment_type: 'Cash',
        remarks: '',
    });
    const [officePaymentProcessing, setOfficePaymentProcessing] =
        useState(false);
    const [otherProductsSales, setOtherProductsSales] = useState(
        otherProducts.map((product) => ({
            product_id: product.id,
            sell_quantity: 0,
            sell_by: '',
            total_sales: 0,
        }))
    );
    const [isValidationModalOpen, setIsValidationModalOpen] = useState(false);
    const [isConfirmModalOpen, setIsConfirmModalOpen] = useState(false);
    const [isSuccessModalOpen, setIsSuccessModalOpen] = useState(false);
    const [validationError, setValidationError] = useState('');
    const { data, setData, processing } = useForm({
        transaction_date: '',
        shift_id: '',
        credit_sales: '0',
        bank_sales: '0',
        cash_sales: '0',
        credit_sales_other: '0',
        bank_sales_other: '0',
        cash_sales_other: '0',
        cash_receive: '0',
        total_cash: '0',
        cash_payment: '0',
        office_payment: '0',
        final_due_amount: '0',
        dispenser_readings: dispenserReading.map((reading) => ({
            dispenser_id: reading.dispenser_id,
            product_id: reading.product_id,
            item_rate: reading.item_rate,
            start_reading: reading.start_reading,
            end_reading: reading.end_reading,
            meter_test: reading.meter_test || 0,
            reading_by: '',
            net_reading: 0,
            total_sale: 0,
        })),
    });

    const shiftOptions = availableShifts.map((shift) => ({
        value: shift.id.toString(),
        label: shift.name,
    }));

    const calculateOtherProductSale = (index: number, sellQuantity: number) => {
        const newSales = [...otherProductsSales];
        const product = otherProducts[index];
        const salesPrice = Number(product?.sales_price) || 0;
        const totalSales = sellQuantity * salesPrice;
        newSales[index] = {
            ...newSales[index],
            sell_quantity: sellQuantity,
            total_sales: totalSales,
        };
        setOtherProductsSales(newSales);
    };

    const calculateReading = (index: number, field: string, value: string) => {
        const newReadings = [...data.dispenser_readings];
        newReadings[index] = {
            ...newReadings[index],
            [field]: parseFloat(value) || 0,
        };
        const reading = newReadings[index];
        const netReading = reading.end_reading - reading.start_reading - reading.meter_test;
        const totalSale = netReading * reading.item_rate;
        newReadings[index].net_reading = netReading;
        newReadings[index].total_sale = totalSale;
        setData('dispenser_readings', newReadings);
        updateTotals(newReadings, data.office_payment, data.credit_sales);
    };

    const updateTotals = (
        readings = data.dispenser_readings,
        officePaymentValue?: string,
        creditSalesValue?: string,
    ) => {
        const totalSales = readings.reduce((sum, reading) => {
            const sale = Number.isFinite(reading.total_sale)
                ? reading.total_sale
                : 0;
            return sum + sale;
        }, 0);
        setTotalSalesSum(totalSales);

        const creditSales =
            parseFloat(
                creditSalesValue !== undefined
                    ? creditSalesValue
                    : data.credit_sales,
            ) || 0;
        const bankSales = parseFloat(data.bank_sales) || 0;
        const cashSales = totalSales - creditSales - bankSales;
        const cashReceive = parseFloat(data.cash_receive) || 0;
        const cashSalesOther = parseFloat(data.cash_sales_other) || 0;
        const totalCash = cashSales + cashSalesOther + cashReceive;
        const cashPayment = parseFloat(data.cash_payment) || 0;
        const officePayment =
            parseFloat(
                officePaymentValue !== undefined
                    ? officePaymentValue
                    : data.office_payment,
            ) || 0;
        const finalDueAmount = totalCash - cashPayment - officePayment;
        setData((prev) => ({
            ...prev,
            cash_sales: cashSales.toFixed(2),
            total_cash: totalCash.toFixed(2),
            final_due_amount: finalDueAmount.toFixed(2),
        }));
        setProductWiseData((prev) => {
            const aggregated: ProductWiseData = {};
            readings.forEach((reading) => {
                const pid = parseInt(reading.product_id?.toString() || '');
                if (!pid || !Number.isFinite(pid)) return;
                const net = Number.isFinite(reading.net_reading)
                    ? reading.net_reading
                    : 0;
                const total = Number.isFinite(reading.total_sale)
                    ? reading.total_sale
                    : 0;

                const current = aggregated[pid] || {
                    net_reading: 0,
                    total_sale: 0,
                    credit_sales: 0,
                    cash_sales: 0,
                };
                aggregated[pid] = {
                    net_reading: current.net_reading + net,
                    total_sale: current.total_sale + total,
                    credit_sales: 0,
                    cash_sales: 0,
                };
            });

            const next: ProductWiseData = { ...prev };
            Object.keys(next).forEach((pidStr) => {
                const pid = parseInt(pidStr);
                if (!aggregated[pid]) {
                    next[pid] = {
                        net_reading: 0,
                        total_sale: 0,
                        credit_sales: 0,
                        cash_sales: 0,
                    };
                }
            });
            Object.entries(aggregated).forEach(([pidStr, val]) => {
                const pid = parseInt(pidStr);
                next[pid] = {
                    net_reading: val.net_reading,
                    total_sale: val.total_sale,
                    credit_sales: next[pid]?.credit_sales || 0,
                    cash_sales: 0,
                };
            });
            return next;
        });
    };

    const fetchShiftData = async (shiftDate: string, shiftId: string) => {
        if (!shiftDate || !shiftId) return;
        try {
            const response = await fetch(
                `/product/get-shift-closing-data/${shiftDate}/${shiftId}`,
            );
            const result = await response.json();
            const summaryData = result?.getTotalSummeryReport?.[0] || {};
            const productWiseCreditSalesData =
                result?.getCreditSalesDetailsReport || [];
            const creditSalesByProduct: { [key: number]: number } = {};
            productWiseCreditSalesData.forEach((item: { product_id: string; product_wise_credit_sales: string }) => {
                const productId = parseInt(item.product_id);
                const creditSales =
                    parseFloat(item.product_wise_credit_sales) || 0;
                creditSalesByProduct[productId] =
                    (creditSalesByProduct[productId] || 0) + creditSales;
            });
            setProductWiseData((prev) => {
                const updated = { ...prev };
                Object.keys(creditSalesByProduct).forEach((productIdStr) => {
                    const productId = parseInt(productIdStr);
                    if (updated[productId]) {
                        updated[productId].credit_sales =
                            creditSalesByProduct[productId];
                    } else {
                        updated[productId] = {
                            net_reading: 0,
                            total_sale: 0,
                            credit_sales: creditSalesByProduct[productId],
                            cash_sales: 0,
                        };
                    }
                });
                return updated;
            });

            setData((prev) => {
                const newData = {
                    ...prev,
                    credit_sales: (
                        summaryData.total_credit_sales_amount || 0
                    ).toString(),
                    bank_sales: (
                        summaryData.total_bank_sale_amount || 0
                    ).toString(),
                    credit_sales_other: (
                        summaryData.total_credit_sales_other_amount || 0
                    ).toString(),
                    bank_sales_other: (
                        summaryData.total_bank_sales_other_amount || 0
                    ).toString(),
                    cash_receive: (
                        summaryData.total_cash_receive_amount || 0
                    ).toString(),
                    cash_payment: (
                        summaryData.total_cash_payment_amount || 0
                    ).toString(),
                    office_payment: (
                        summaryData.total_office_payment_amount || 0
                    ).toString(),
                };
                setTimeout(
                    () =>
                        updateTotals(
                            prev.dispenser_readings,
                            newData.office_payment,
                            newData.credit_sales,
                        ),
                    0,
                );
                return newData;
            });
        } catch (error) {
            console.error('Error fetching shift data:', error);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!data.transaction_date) {
            setValidationError('Transaction date is required');
            setIsValidationModalOpen(true);
            return;
        }
        if (!data.shift_id) {
            setValidationError('Shift selection is required');
            setIsValidationModalOpen(true);
            return;
        }

        const creditSales = parseFloat(data.credit_sales || '0');
        const bankSales = parseFloat(data.bank_sales || '0');
        const cashSales = parseFloat(data.cash_sales || '0');
        const creditSalesOther = parseFloat(data.credit_sales_other || '0');
        const bankSalesOther = parseFloat(data.bank_sales_other || '0');
        const cashSalesOther = parseFloat(data.cash_sales_other || '0');
        const cashReceive = parseFloat(data.cash_receive || '0');
        const totalCash = parseFloat(data.total_cash || '0');
        const cashPayment = parseFloat(data.cash_payment || '0');

        if (isNaN(creditSales)) {
            setValidationError('Credit sales must be a valid number');
            setIsValidationModalOpen(true);
            return;
        }
        if (isNaN(bankSales)) {
            setValidationError('Bank sales must be a valid number');
            setIsValidationModalOpen(true);
            return;
        }
        if (isNaN(cashSales)) {
            setValidationError('Cash sales must be a valid number');
            setIsValidationModalOpen(true);
            return;
        }
        if (isNaN(creditSalesOther)) {
            setValidationError('Credit sales (other) must be a valid number');
            setIsValidationModalOpen(true);
            return;
        }
        if (isNaN(bankSalesOther)) {
            setValidationError('Bank sales (other) must be a valid number');
            setIsValidationModalOpen(true);
            return;
        }
        if (isNaN(cashSalesOther)) {
            setValidationError('Cash sales (other) must be a valid number');
            setIsValidationModalOpen(true);
            return;
        }
        if (isNaN(cashReceive)) {
            setValidationError('Cash receive must be a valid number');
            setIsValidationModalOpen(true);
            return;
        }
        if (isNaN(totalCash)) {
            setValidationError('Total cash must be a valid number');
            setIsValidationModalOpen(true);
            return;
        }
        if (isNaN(cashPayment)) {
            setValidationError('Cash payment must be a valid number');
            setIsValidationModalOpen(true);
            return;
        }

        const totalSalesAmount = creditSales + bankSales + cashSales + creditSalesOther + bankSalesOther + cashSalesOther;
        if (totalSalesAmount === 0) {
            setValidationError('Cannot close shift with zero sales. Please add sales data first.');
            setIsValidationModalOpen(true);
            return;
        }

        setIsConfirmModalOpen(true);
    };

    const handleConfirmSubmit = () => {
        setIsConfirmModalOpen(false);
        const otherProductSalesData = otherProductsSales
            .filter(sale => sale.sell_quantity > 0)
            .map(sale => ({
                product_id: sale.product_id,
                quantity: sale.sell_quantity,
                unit_price: otherProducts.find(p => p.id === sale.product_id)?.sales_price || 0,
                employee_id: sale.sell_by,
                remarks: null
            }));

        const totalOtherProductsSales = otherProductsSales.reduce((sum, sale) => sum + (sale.total_sales || 0), 0);
        const creditSalesOther = parseFloat(data.credit_sales_other) || 0;
        const bankSalesOther = parseFloat(data.bank_sales_other) || 0;
        const cashSalesOther = totalOtherProductsSales - creditSalesOther - bankSalesOther;

        const submitData = {
            ...data,
            cash_sales_other: cashSalesOther.toFixed(2),
            other_product_sales: otherProductSalesData
        };

        router.post('/product/dispensers-reading', submitData, {
            onSuccess: () => {
                setIsSuccessModalOpen(true);
            },
            onError: () => {
                setValidationError('Failed to close shift.');
                setIsValidationModalOpen(true);
            },
        });
    };

    const getAvailableShifts = (selectedDate: string) => {
        if (!selectedDate) return [];

        const closedShiftIds = closedShifts
            .filter((cs) => cs.close_date === selectedDate)
            .map((cs) => cs.shift_id);

        return shifts.filter((shift) => !closedShiftIds.includes(shift.id));
    };

    const handleDateChange = async (date: string) => {
        setData('transaction_date', date);
        setData('shift_id', '');
        if (date) {
            setAvailableShifts(getAvailableShifts(date));
        } else {
            setAvailableShifts([]);
        }
    };

    const updateOtherProductsCashSales = () => {
        const totalOtherProductsSales = otherProductsSales.reduce((sum, sale) => sum + (sale.total_sales || 0), 0);
        const creditSalesOther = parseFloat(data.credit_sales_other) || 0;
        const bankSalesOther = parseFloat(data.bank_sales_other) || 0;
        const cashSalesOther = totalOtherProductsSales - creditSalesOther - bankSalesOther;

        const creditSales = parseFloat(data.credit_sales) || 0;
        const bankSales = parseFloat(data.bank_sales) || 0;
        const totalSales = data.dispenser_readings.reduce((sum, reading) => sum + (reading.total_sale || 0), 0);
        const cashSales = totalSales - creditSales - bankSales;
        const cashReceive = parseFloat(data.cash_receive) || 0;
        const totalCash = cashSales + cashSalesOther + cashReceive;
        const cashPayment = parseFloat(data.cash_payment) || 0;
        const officePayment = parseFloat(data.office_payment) || 0;
        const finalDueAmount = totalCash - cashPayment - officePayment;

        setData(prev => ({
            ...prev,
            cash_sales_other: cashSalesOther.toFixed(2),
            total_cash: totalCash.toFixed(2),
            final_due_amount: finalDueAmount.toFixed(2),
        }));
    };

    useEffect(() => {
        updateTotals();
    }, []);

    useEffect(() => {
        updateOtherProductsCashSales();
    }, [otherProductsSales, data.credit_sales_other, data.bank_sales_other]);

    useEffect(() => {
        if (data.transaction_date && data.shift_id) {
            fetchShiftData(data.transaction_date, data.shift_id);
        }
    }, [data.transaction_date, data.shift_id]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dispensers Reading" />
            <div className="mx-auto max-w-full px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
                        Dispensers Calculation
                    </h1>
                    <p className="mt-2 text-gray-600 dark:text-gray-400">
                        Manage dispenser readings and shift closing
                    </p>
                </div>
                <Card className="border border-gray-200 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <form onSubmit={handleSubmit}>
                        <CardHeader className="border-b border-gray-200 px-6 py-6 dark:border-gray-700">
                            <div className="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-6">
                                <div>
                                    <Label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Transaction Date{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        type="date"
                                        value={data.transaction_date}
                                        onChange={(e) =>
                                            handleDateChange(e.target.value)
                                        }
                                        className="w-full dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        required
                                    />
                                </div>
                                <div>
                                    <Label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Select Shift{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <SearchableSelect
                                        options={shiftOptions}
                                        value={data.shift_id}
                                        onValueChange={(value) =>
                                            setData('shift_id', value)
                                        }
                                        placeholder={
                                            data.transaction_date
                                                ? 'Select shift'
                                                : 'Select date first'
                                        }
                                        className="w-full"
                                    />
                                </div>
                                <div></div>
                                <div></div>
                                <div></div>
                                <div></div>
                            </div>
                            <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">

                                <div>
                                    <Label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Credit Sales
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            value={data.credit_sales}
                                            readOnly
                                            className="w-full bg-gray-50 pr-10 dark:border-gray-600 dark:bg-gray-600 dark:text-white"
                                        />
                                        {canCreateCreditSales && (
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                size="sm"
                                                className="absolute top-1/2 right-0.5 h-7 w-7 -translate-y-1/2 p-0"
                                                onClick={() => {
                                                    setIsCreditSalesOpen(true);
                                                }}
                                            >
                                                <FileText className="h-3.5 w-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <Label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Bank Sales
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            value={data.bank_sales}
                                            readOnly
                                            className="w-full bg-gray-50 pr-10 dark:border-gray-600 dark:bg-gray-600 dark:text-white"
                                        />
                                        {canCreateSale && (
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                size="sm"
                                                className="absolute top-1/2 right-0.5 h-7 w-7 -translate-y-1/2 p-0"
                                                onClick={() => {
                                                    setIsBankSalesOpen(true);
                                                }}
                                            >
                                                <FileText className="h-3.5 w-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <Label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Cash Sales
                                    </Label>
                                    <Input
                                        value={data.cash_sales}
                                        readOnly
                                        className="w-full bg-gray-50 dark:border-gray-600 dark:bg-gray-600 dark:text-white"
                                    />
                                </div>

                                <div>
                                    <Label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Credit Sales(Other Products)
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            value={data.credit_sales_other}
                                            readOnly
                                            className="w-full bg-gray-50 pr-10 dark:border-gray-600 dark:bg-gray-600 dark:text-white"
                                        />
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            className="absolute top-1/2 right-0.5 h-7 w-7 -translate-y-1/2 p-0"
                                            onClick={() => {
                                                setIsCreditSalesOpen(true);
                                            }}
                                        >
                                            <FileText className="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>
                                <div>
                                    <Label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Bank Sales(Other Products)
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            value={data.bank_sales_other}
                                            readOnly
                                            className="w-full bg-gray-50 pr-10 dark:border-gray-600 dark:bg-gray-600 dark:text-white"
                                        />
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            className="absolute top-1/2 right-0.5 h-7 w-7 -translate-y-1/2 p-0"
                                            onClick={() => {
                                                setIsBankSalesOpen(true);
                                            }}
                                        >
                                            <FileText className="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>
                                <div>
                                    <Label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Cash Sales(Other Products)
                                    </Label>
                                    <Input
                                        value={data.cash_sales_other}
                                        readOnly
                                        className="w-full bg-gray-50 dark:border-gray-600 dark:bg-gray-600 dark:text-white"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                <div>
                                    <Label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Cash Receive
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            value={data.cash_receive}
                                            readOnly
                                            className="w-full bg-gray-50 pr-10 dark:border-gray-600 dark:bg-gray-600 dark:text-white"
                                        />
                                        {canCreateVoucher && (
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                size="sm"
                                                className="absolute top-1/2 right-0.5 h-7 w-7 -translate-y-1/2 p-0"
                                                onClick={() => {
                                                    setIsCashReceiveOpen(true);
                                                }}
                                            >
                                                <FileText className="h-3.5 w-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <Label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Total Cash
                                    </Label>
                                    <Input
                                        value={data.total_cash}
                                        readOnly
                                        className="w-full bg-gray-50 dark:border-gray-600 dark:bg-gray-600 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Cash Payment
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            value={data.cash_payment}
                                            readOnly
                                            className="w-full bg-gray-50 pr-10 dark:border-gray-600 dark:bg-gray-600 dark:text-white"
                                        />
                                        {canCreateVoucher && (
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                size="sm"
                                                className="absolute top-1/2 right-0.5 h-7 w-7 -translate-y-1/2 p-0"
                                                onClick={() => {
                                                    setIsCashPaymentOpen(true);
                                                }}
                                            >
                                                <FileText className="h-3.5 w-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <Label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Office Payment
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            type="number"
                                            step="0.01"
                                            value={data.office_payment}
                                            onChange={(e) => {
                                                setData(
                                                    'office_payment',
                                                    e.target.value,
                                                );
                                                updateTotals(
                                                    data.dispenser_readings,
                                                    e.target.value,
                                                    data.credit_sales,
                                                );
                                            }}
                                            className="w-full pr-10 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        />
                                        {canCreateOfficePayment && (
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                size="sm"
                                                className="absolute top-1/2 right-0.5 h-7 w-7 -translate-y-1/2 p-0"
                                                onClick={() => {
                                                    setOfficePaymentData(
                                                        (prev) => ({
                                                            ...prev,
                                                            date: data.transaction_date,
                                                            shift_id: data.shift_id,
                                                        }),
                                                    );
                                                    setIsOfficePaymentOpen(true);
                                                }}
                                            >
                                                <FileText className="h-3.5 w-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <Label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Final Due Amount
                                    </Label>
                                    <Input
                                        value={data.final_due_amount}
                                        readOnly
                                        className="w-full bg-gray-50 dark:border-gray-600 dark:bg-gray-600 dark:text-white"
                                    />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="px-6 py-6">
                            <div className="mb-10 overflow-x-auto">
                                <table className="w-full border-collapse border border-gray-200 dark:border-gray-600">
                                    <thead>
                                        <tr className="bg-gray-50 dark:bg-gray-700">
                                            <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                SL
                                            </th>
                                            <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                Dispenser Name
                                            </th>
                                            <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                Product ID
                                            </th>
                                            <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                Product Name
                                            </th>
                                            <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                Item Rate
                                            </th>
                                            <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                Old Reading
                                            </th>
                                            <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                New Reading
                                            </th>
                                            <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                Meter Test
                                            </th>
                                            <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                Reading By
                                            </th>
                                            <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                Net Reading
                                            </th>
                                            <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                Total Sales
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.dispenser_readings.map(
                                            (reading, index) => {
                                                const dispenserInfo =
                                                    dispenserReading.find(
                                                        (d) =>
                                                            d.dispenser_id ===
                                                            reading.dispenser_id,
                                                    );
                                                return (
                                                    <tr
                                                        key={index}
                                                        className="hover:bg-gray-50 dark:hover:bg-gray-700"
                                                    >
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {index + 1}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-blue-600 dark:border-gray-600 dark:text-blue-400">
                                                            {dispenserInfo
                                                                ?.dispenser
                                                                ?.dispenser_name ||
                                                                'Unknown'}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {reading.product_id ||
                                                                'N/A'}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {dispenserInfo
                                                                ?.product
                                                                ?.product_name ||
                                                                'No Product Assigned'}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {reading.item_rate}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {
                                                                reading.start_reading
                                                            }
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 dark:border-gray-600">
                                                            <Input
                                                                type="number"
                                                                step="0.01"
                                                                value={
                                                                    reading.end_reading
                                                                }
                                                                onChange={(e) =>
                                                                    calculateReading(
                                                                        index,
                                                                        'end_reading',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                className="h-8 w-32 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                            />
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 dark:border-gray-600">
                                                            <Input
                                                                type="number"
                                                                step="0.01"
                                                                value={
                                                                    reading.meter_test
                                                                }
                                                                onChange={(e) =>
                                                                    calculateReading(
                                                                        index,
                                                                        'meter_test',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                className="h-8 w-20 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                            />
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 dark:border-gray-600">
                                                            <Select
                                                                value={
                                                                    reading.reading_by
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) => {
                                                                    const newReadings =
                                                                        [
                                                                            ...data.dispenser_readings,
                                                                        ];
                                                                    newReadings[
                                                                        index
                                                                    ] = {
                                                                        ...newReadings[
                                                                        index
                                                                        ],
                                                                        reading_by:
                                                                            value,
                                                                    };
                                                                    setData(
                                                                        'dispenser_readings',
                                                                        newReadings,
                                                                    );
                                                                }}
                                                            >
                                                                <SelectTrigger className="h-8 w-32 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                                    <SelectValue placeholder="Select" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {employees.map(
                                                                        (
                                                                            emp,
                                                                        ) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    emp.id
                                                                                }
                                                                                value={emp.id.toString()}
                                                                            >
                                                                                {
                                                                                    emp.employee_name
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {reading.net_reading.toFixed(
                                                                2,
                                                            )}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {reading.total_sale.toFixed(
                                                                2,
                                                            )}
                                                        </td>
                                                    </tr>
                                                );
                                            },
                                        )}
                                    </tbody>
                                    <tfoot>
                                        <tr className="bg-gray-50 font-semibold dark:bg-gray-700">
                                            <td
                                                colSpan={10}
                                                className="border border-gray-200 px-3 py-2 text-right text-sm text-gray-900 dark:border-gray-600 dark:text-white"
                                            >
                                                Total Sales:
                                            </td>
                                            <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                {totalSalesSum.toFixed(2)}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div className="mb-10">
                                <h3 className="mb-6 text-lg font-semibold text-gray-900 dark:text-white">
                                    Other Products Sales
                                </h3>
                                <div className="overflow-x-auto">
                                    <table className="w-full border-collapse border border-gray-200 dark:border-gray-600">
                                        <thead>
                                            <tr className="bg-gray-50 dark:bg-gray-700">
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    SL
                                                </th>
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    Product Name
                                                </th>
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    Product ID
                                                </th>
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    Unit
                                                </th>
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    Item Rate
                                                </th>
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    In Stock
                                                </th>
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    Sell
                                                </th>
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    New Stock
                                                </th>
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    Sell By
                                                </th>
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    Total Sales
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {otherProducts.map((product, index) => {
                                                const currentStock = Number(product.stock?.current_stock) || 0;
                                                const sellQuantity = otherProductsSales[index]?.sell_quantity || 0;
                                                const newStock = currentStock - sellQuantity;
                                                const totalSales = otherProductsSales[index]?.total_sales || 0;
                                                return (
                                                    <tr key={product.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {index + 1}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {product.product_name}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {product.product_code}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {product.unit?.name || 'N/A'}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {(Number(product.sales_price) || 0).toFixed(2)}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {currentStock}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 dark:border-gray-600">
                                                            <Input
                                                                type="number"
                                                                step="1"
                                                                value={sellQuantity}
                                                                onChange={(e) => {
                                                                    const quantity = parseInt(e.target.value) || 0;
                                                                    calculateOtherProductSale(index, quantity);
                                                                }}
                                                                className="h-8 w-20 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                            />
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {newStock}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 dark:border-gray-600">
                                                            <Select
                                                                value={otherProductsSales[index]?.sell_by || ''}
                                                                onValueChange={(value) => {
                                                                    const newSales = [...otherProductsSales];
                                                                    newSales[index] = {
                                                                        ...newSales[index],
                                                                        sell_by: value,
                                                                    };
                                                                    setOtherProductsSales(newSales);
                                                                }}
                                                            >
                                                                <SelectTrigger className="h-8 w-32 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                                    <SelectValue placeholder="Select" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {employees.map((emp) => (
                                                                        <SelectItem
                                                                            key={emp.id}
                                                                            value={emp.id.toString()}
                                                                        >
                                                                            {emp.employee_name}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {totalSales.toFixed(2)}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                        <tfoot>
                                            <tr className="bg-gray-50 font-semibold dark:bg-gray-700">
                                                <td
                                                    colSpan={9}
                                                    className="border border-gray-200 px-3 py-2 text-right text-sm text-gray-900 dark:border-gray-600 dark:text-white"
                                                >
                                                    Total Sales:
                                                </td>
                                                <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                    {otherProductsSales.reduce((sum, sale) => sum + (sale.total_sales || 0), 0).toFixed(2)}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div className="mb-10">
                                <h3 className="mb-6 text-lg font-semibold text-gray-900 dark:text-white">
                                    Product-wise Summary
                                </h3>
                                <div className="overflow-x-auto">
                                    <table className="w-full border-collapse border border-gray-200 dark:border-gray-600">
                                        <thead>
                                            <tr className="bg-gray-50 dark:bg-gray-700">
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    Product ID
                                                </th>
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    Product Name
                                                </th>
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    Rate
                                                </th>
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    Quantity
                                                </th>
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    Total Sale
                                                </th>
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    Credit Sales
                                                </th>
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    Bank Sales
                                                </th>
                                                <th className="border border-gray-200 px-3 py-2 text-left text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                    Cash Sales
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                            {Object.entries(productWiseData).filter(([productId, productData]) => {
                                                return productData.total_sale > 0 || productData.net_reading > 0;
                                            }).map(([productId, productData]) => {
                                                const productInfo = dispenserReading.find(
                                                    (d) => d.product_id?.toString() === productId,
                                                );
                                                const product = products?.find(
                                                    (p) => p.id.toString() === productId,
                                                );
                                                const name = product?.product_name ?? productInfo?.product?.product_name ?? 'No Product Assigned';
                                                const productCode = product?.product_code ?? productId;
                                                const rate = product?.sales_price ?? productInfo?.product?.sales_price ?? 0;
                                                const creditSales = productData.credit_sales || 0;
                                                const totalSale = productData.total_sale || 0;

                                                const totalAllOilSales = Object.values(productWiseData).reduce((sum, data) => sum + (data.total_sale || 0), 0);
                                                const bankSalesTotal = parseFloat(data.bank_sales) || 0;
                                                const proportion = totalAllOilSales > 0 ? totalSale / totalAllOilSales : 0;
                                                const productBankSales = bankSalesTotal * proportion;
                                                const cashSales = totalSale - creditSales - productBankSales;

                                                return (
                                                    <tr key={productId} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {productCode}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {name}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {rate}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {productData.net_reading.toFixed(2)}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {totalSale.toFixed(2)}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {creditSales.toFixed(2)}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {productBankSales.toFixed(2)}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {cashSales.toFixed(2)}
                                                        </td>
                                                    </tr>
                                                );
                                            })}

                                            {/* Other Products */}
                                            {otherProductsSales.filter(sale => sale.sell_quantity > 0).map((sale) => {
                                                const product = otherProducts.find(p => p.id === sale.product_id);
                                                if (!product) return null;

                                                const totalSale = sale.total_sales || 0;
                                                const creditSalesOther = parseFloat(data.credit_sales_other) || 0;
                                                const bankSalesOther = parseFloat(data.bank_sales_other) || 0;
                                                const totalOtherSales = otherProductsSales.reduce((sum, s) => sum + (s.total_sales || 0), 0);

                                                const proportion = totalOtherSales > 0 ? totalSale / totalOtherSales : 0;
                                                const productCreditSales = creditSalesOther * proportion;
                                                const productBankSales = bankSalesOther * proportion;
                                                const productCashSales = totalSale - productCreditSales - productBankSales;

                                                return (
                                                    <tr key={`other-${product.id}`} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {product.product_code}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {product.product_name}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {(Number(product.sales_price) || 0).toFixed(2)}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {sale.sell_quantity}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {totalSale.toFixed(2)}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {productCreditSales.toFixed(2)}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {productBankSales.toFixed(2)}
                                                        </td>
                                                        <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                            {productCashSales.toFixed(2)}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                        <tfoot>
                                            <tr className="bg-gray-50 font-semibold dark:bg-gray-700">
                                                <td
                                                    colSpan={4}
                                                    className="border border-gray-200 px-3 py-2 text-right text-sm text-gray-900 dark:border-gray-600 dark:text-white"
                                                >
                                                    Total:
                                                </td>
                                                <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                    {[
                                                        ...Object.entries(productWiseData).filter(([productId, productData]) => {
                                                            return productData.total_sale > 0 || productData.net_reading > 0;
                                                        }).map(([productId, productData]) => productData.total_sale || 0),
                                                        ...otherProductsSales.filter(sale => sale.sell_quantity > 0).map(sale => sale.total_sales || 0)
                                                    ].reduce((sum, sale) => sum + sale, 0).toFixed(2)}
                                                </td>
                                                <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                    {(parseFloat(data.credit_sales) + parseFloat(data.credit_sales_other)).toFixed(2)}
                                                </td>
                                                <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                    {(parseFloat(data.bank_sales) + parseFloat(data.bank_sales_other)).toFixed(2)}
                                                </td>
                                                <td className="border border-gray-200 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white">
                                                    {(parseFloat(data.cash_sales) + parseFloat(data.cash_sales_other)).toFixed(2)}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div className="flex justify-end border-t border-gray-200 pt-6 dark:border-gray-700">
                                {canCreateDispenserReading && (
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="rounded-md bg-blue-600 px-6 py-2 font-medium text-white hover:bg-blue-700"
                                    >
                                        {processing
                                            ? 'Processing...'
                                            : 'Close Shift'}
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </form>
                </Card>
                <SaleModal
                    isOpen={isBankSalesOpen}
                    onClose={() => setIsBankSalesOpen(false)}
                    onSuccess={() => {
                        if (data.transaction_date && data.shift_id) {
                            fetchShiftData(
                                data.transaction_date,
                                data.shift_id,
                            );
                        }
                    }}
                    editingSale={null}
                    accounts={accounts}
                    groupedAccounts={groupedAccounts}
                    products={products}
                    vehicles={vehicles}
                    salesHistory={[]}
                    shifts={shifts}
                    closedShifts={closedShifts}
                    uniqueCustomers={uniqueCustomers}
                    uniqueVehicles={uniqueVehicles}
                    initialSaleDate={data.transaction_date}
                    initialShiftId={data.shift_id}
                />
                <ReceivedVoucherModal
                    key={`receive-${isCashReceiveOpen}-${data.transaction_date}-${data.shift_id}`}
                    isOpen={isCashReceiveOpen}
                    onClose={() => setIsCashReceiveOpen(false)}
                    onSuccess={() => {
                        if (data.transaction_date && data.shift_id) {
                            fetchShiftData(
                                data.transaction_date,
                                data.shift_id,
                            );
                        }
                    }}
                    editingVoucher={null}
                    accounts={accounts}
                    groupedAccounts={groupedAccounts}
                    shifts={shifts}
                    closedShifts={closedShifts}
                    voucherCategories={voucherCategories}
                    paymentSubTypes={paymentSubTypes}
                    initialDate={data.transaction_date}
                    initialShiftId={data.shift_id}
                />

                <PaymentVoucherModal
                        key={`payment-${isCashPaymentOpen}-${data.transaction_date}-${data.shift_id}`}
                        isOpen={isCashPaymentOpen}
                        onClose={() => setIsCashPaymentOpen(false)}
                        onSuccess={() => {
                            if (data.transaction_date && data.shift_id) {
                                fetchShiftData(
                                    data.transaction_date,
                                    data.shift_id,
                                );
                            }
                        }}
                        editingVoucher={null}
                        accounts={accounts}
                        groupedAccounts={groupedAccounts}
                        shifts={shifts}
                        closedShifts={closedShifts}
                        voucherCategories={voucherCategories}
                        paymentSubTypes={paymentSubTypes}
                        initialDate={data.transaction_date}
                        initialShiftId={data.shift_id}
                    />

                <FormModal
                    isOpen={isOfficePaymentOpen}
                    onClose={() => {
                        setIsOfficePaymentOpen(false);
                        setOfficePaymentData({
                            date: '',
                            shift_id: '',
                            to_account_id: '',
                            amount: '',
                            payment_type: 'Cash',
                            remarks: '',
                        });
                    }}
                    title="Office Payment"
                    onSubmit={(e) => {
                        e.preventDefault();
                        setOfficePaymentProcessing(true);
                        router.post('/office-payments', officePaymentData, {
                            onSuccess: () => {
                                setIsOfficePaymentOpen(false);
                                setOfficePaymentData({
                                    date: '',
                                    shift_id: '',
                                    to_account_id: '',
                                    amount: '',
                                    payment_type: 'Cash',
                                    remarks: '',
                                });
                                setOfficePaymentProcessing(false);
                                if (data.transaction_date && data.shift_id) {
                                    fetchShiftData(
                                        data.transaction_date,
                                        data.shift_id,
                                    );
                                }
                            },
                            onError: () => setOfficePaymentProcessing(false),
                        });
                    }}
                    processing={officePaymentProcessing}
                    submitText="Create"
                    className="max-w-lg"
                >
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label
                                htmlFor="date"
                                className="dark:text-gray-200"
                            >
                                Date
                            </Label>
                            <Input
                                id="date"
                                type="date"
                                value={officePaymentData.date}
                                onChange={(e) => {
                                    setOfficePaymentData((prev) => ({
                                        ...prev,
                                        date: e.target.value,
                                    }));
                                    setAvailableShifts(
                                        getAvailableShifts(e.target.value),
                                    );
                                    setOfficePaymentData((prev) => ({
                                        ...prev,
                                        shift_id: '',
                                    }));
                                }}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <Label
                                htmlFor="shift_id"
                                className="dark:text-gray-200"
                            >
                                Shift
                            </Label>
                            <Select
                                value={officePaymentData.shift_id}
                                onValueChange={(value) =>
                                    setOfficePaymentData((prev) => ({
                                        ...prev,
                                        shift_id: value,
                                    }))
                                }
                                disabled={!officePaymentData.date}
                            >
                                <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <SelectValue placeholder="Select shift" />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableShifts.map((shift) => (
                                        <SelectItem
                                            key={shift.id}
                                            value={shift.id.toString()}
                                        >
                                            {shift.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div>
                        <Label
                            htmlFor="payment_type"
                            className="dark:text-gray-200"
                        >
                            Payment Type
                        </Label>
                        <Select
                            value={officePaymentData.payment_type}
                            onValueChange={(value) => {
                                setOfficePaymentData((prev) => ({
                                    ...prev,
                                    payment_type: value,
                                    to_account_id: '',
                                }));
                            }}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select payment type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="Cash">Cash</SelectItem>
                                <SelectItem value="Bank Account">
                                    Bank Account
                                </SelectItem>
                                <SelectItem value="Mobile Bank">
                                    Mobile Bank
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div>
                        <Label
                            htmlFor="to_account_id"
                            className="dark:text-gray-200"
                        >
                            To Account (Office)
                        </Label>
                        <Select
                            value={officePaymentData.to_account_id}
                            onValueChange={(value) =>
                                setOfficePaymentData((prev) => ({
                                    ...prev,
                                    to_account_id: value,
                                }))
                            }
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select office account" />
                            </SelectTrigger>
                            <SelectContent>
                                {(officePaymentData.payment_type === 'Cash'
                                    ? groupedAccounts['Cash in hand'] ||
                                    groupedAccounts['Cash'] ||
                                    []
                                    : officePaymentData.payment_type ===
                                        'Bank Account'
                                        ? groupedAccounts['Bank Account'] ||
                                        groupedAccounts['Bank'] ||
                                        []
                                        : officePaymentData.payment_type ===
                                            'Mobile Bank'
                                            ? groupedAccounts['Mobile Bank'] || []
                                            : []
                                ).map((account) => (
                                    <SelectItem
                                        key={account.id}
                                        value={account.id.toString()}
                                    >
                                        {account.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div>
                        <Label htmlFor="amount" className="dark:text-gray-200">
                            Amount
                        </Label>
                        <Input
                            id="amount"
                            type="number"
                            step="0.01"
                            value={officePaymentData.amount}
                            onChange={(e) =>
                                setOfficePaymentData((prev) => ({
                                    ...prev,
                                    amount: e.target.value,
                                }))
                            }
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>

                    <div>
                        <Label htmlFor="remarks" className="dark:text-gray-200">
                            Remarks
                        </Label>
                        <Input
                            id="remarks"
                            value={officePaymentData.remarks}
                            onChange={(e) =>
                                setOfficePaymentData((prev) => ({
                                    ...prev,
                                    remarks: e.target.value,
                                }))
                            }
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                </FormModal>

                <CreditSaleModal
                    isOpen={isCreditSalesOpen}
                    onClose={() => setIsCreditSalesOpen(false)}
                    onSuccess={() => {
                        if (data.transaction_date && data.shift_id) {
                            fetchShiftData(
                                data.transaction_date,
                                data.shift_id,
                            );
                        }
                    }}
                    editingSale={null}
                    products={products}
                    vehicles={vehicles}
                    customers={customers}
                    shifts={shifts}
                    closedShifts={closedShifts}
                    initialSaleDate={data.transaction_date}
                    initialShiftId={data.shift_id}
                />

                <Dialog open={isValidationModalOpen} onOpenChange={setIsValidationModalOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Validation Error</DialogTitle>
                            <DialogDescription>{validationError}</DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button onClick={() => setIsValidationModalOpen(false)}>OK</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog open={isConfirmModalOpen} onOpenChange={setIsConfirmModalOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Confirm Shift Close</DialogTitle>
                            <DialogDescription>Are you sure you want to close this shift?</DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setIsConfirmModalOpen(false)}>Cancel</Button>
                            <Button onClick={handleConfirmSubmit} disabled={processing}>
                                {processing ? 'Processing...' : 'Confirm'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog open={isSuccessModalOpen} onOpenChange={setIsSuccessModalOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Success</DialogTitle>
                            <DialogDescription>Shift closed successfully.</DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button onClick={() => window.location.reload()}>OK</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
