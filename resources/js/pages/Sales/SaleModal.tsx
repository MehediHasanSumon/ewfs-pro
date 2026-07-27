import { Button } from '@/components/ui/button';
import { FormModal } from '@/components/ui/form-modal';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Combobox } from '@/components/ui/combobox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { router } from '@inertiajs/react';
import { Edit, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

export interface Sale {
    id: number;
    sale_date: string;
    invoice_no: string;
    customer: string;
    vehicle_no: string;
    product_id: number;
    shift: { name: string };
    quantity: number;
    total_amount: number;
    paid_amount: number;
    due_amount: number;
    remarks: string;
    created_at: string;
    batch_code?: string;
    transaction?: {
        payment_type: string;
    };
}

interface Account {
    id: number;
    name: string;
    ac_number: string;
}

interface Product {
    id: number;
    product_name: string;
    product_code: string;
    unit?: { name: string };
    sales_price: number;
    stock?: {
        current_stock: number;
        available_stock?: number;
    };
}

interface Vehicle {
    id: number;
    vehicle_number: string;
    customer_id: number;
    products?: {
        id: number;
        product_name: string;
    }[];
    customer?: {
        id: number;
        name: string;
    } | null;
}

interface Shift {
    id: number;
    name: string;
}

interface ClosedShift {
    close_date: string;
    shift_id: number;
}

interface SalesHistory {
    vehicle_no: string;
    customer: string;
    product_id: number;
}

interface SaleModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSuccess?: () => void;
    editingSale: Sale | null;
    accounts: Account[];
    groupedAccounts: Record<string, Account[]>;
    products: Product[];
    vehicles: Vehicle[];
    salesHistory?: SalesHistory[];
    shifts: Shift[];
    closedShifts: ClosedShift[];
    uniqueCustomers?: string[];
    uniqueVehicles?: string[];
    initialSaleDate?: string;
    initialShiftId?: string;
}

export function SaleModal({
    isOpen,
    onClose,
    onSuccess,
    editingSale,
    accounts,
    groupedAccounts,
    products,
    vehicles,
    salesHistory = [],
    shifts,
    closedShifts,
    uniqueCustomers = [],
    uniqueVehicles = [],
    initialSaleDate,
    initialShiftId,
}: SaleModalProps) {
    const buildInitialState = () => ({
        sale_date: initialSaleDate || '',
        shift_id: initialShiftId || '',
        products: [
            {
                product_id: '',
                customer: '',
                vehicle_no: '',
                memo_no: '',
                quantity: '',
                amount: '',
                discount_type: 'Fixed',
                discount: '',
                payment_type: 'Cash',
                to_account_id: '',
                paid_amount: '',
                due_amount: '',
                bank_type: '',
                bank_name: '',
                cheque_no: '',
                cheque_date: '',
                branch_name: '',
                account_no: '',
                mobile_bank: '',
                mobile_number: '',
                remarks: '',
            }
        ],
    });

    const [data, setDataState] = useState(buildInitialState());

    const [processing, setProcessing] = useState(false);
    const [availableShifts, setAvailableShifts] = useState<Shift[]>(shifts);

    useEffect(() => {
        if (editingSale && isOpen) {
            loadEditData();
        } else if (isOpen) {
            const initialState = buildInitialState();
            setDataState(initialState);
            setAvailableShifts(getAvailableShifts(initialState.sale_date));
        } else if (!isOpen) {
            reset();
        }
    }, [editingSale, isOpen]);

    const loadEditData = async () => {
        if (!editingSale) return;
        try {
            const response = await fetch(`/sales/${editingSale.id}/edit`);
            const data = await response.json();
            const saleData = data.sale;
            
            let paymentType = 'Cash';
            const dbPaymentType = saleData.transaction?.payment_type?.toLowerCase();
            if (dbPaymentType === 'cash') paymentType = 'Cash';
            else if (dbPaymentType === 'bank') paymentType = 'Bank';
            else if (dbPaymentType === 'mobile bank' || dbPaymentType === 'mobile_bank') paymentType = 'Mobile Bank';
            
            setDataState({
                sale_date: saleData.sale_date.split('T')[0],
                shift_id: saleData.shift_id?.toString() || '',
                products: [
                    {
                        product_id: saleData.product_id?.toString() || '',
                        customer: saleData.customer || '',
                        vehicle_no: saleData.vehicle_no || '',
                        memo_no: saleData.memo_no || '',
                        quantity: saleData.quantity?.toString() || '',
                        amount: saleData.amount?.toString() || '',
                        discount_type: 'Fixed',
                        discount: saleData.discount?.toString() || '',
                        payment_type: paymentType,
                        to_account_id: saleData.transaction?.ac_number ? accounts.find(a => a.ac_number === saleData.transaction.ac_number)?.id.toString() || '' : '',
                        paid_amount: saleData.paid_amount?.toString() || '',
                        due_amount: saleData.due_amount?.toString() || '',
                        bank_type: saleData.transaction?.cheque_type || '',
                        bank_name: saleData.transaction?.bank_name || '',
                        cheque_no: saleData.transaction?.cheque_no || '',
                        cheque_date: saleData.transaction?.cheque_date || '',
                        branch_name: saleData.transaction?.branch_name || '',
                        account_no: saleData.transaction?.account_number || '',
                        mobile_bank: saleData.transaction?.mobile_bank_name || '',
                        mobile_number: saleData.transaction?.mobile_number || '',
                        remarks: saleData.remarks || '',
                    }
                ],
            });
        } catch (error) {
            console.error('Error loading sale:', error);
        }
    };

    const reset = () => {
        const initialState = buildInitialState();
        setDataState(initialState);
        setAvailableShifts(getAvailableShifts(initialState.sale_date));
    };

    const setData = (key: string, value: string) => {
        setDataState(prev => ({ ...prev, [key]: value }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const validProducts = data.products.filter(p => p.product_id && p.customer && p.vehicle_no && p.quantity && p.amount);
        if (validProducts.length === 0) {
            alert('Please add at least one product to cart');
            return;
        }
        
        if (editingSale) {
            const updateData = {
                sale_date: data.sale_date,
                shift_id: data.shift_id,
                product_id: validProducts[0].product_id,
                customer: validProducts[0].customer,
                vehicle_no: validProducts[0].vehicle_no,
                memo_no: validProducts[0].memo_no,
                quantity: validProducts[0].quantity,
                amount: validProducts[0].amount,
                discount: validProducts[0].discount || 0,
                payment_type: validProducts[0].payment_type,
                to_account_id: validProducts[0].to_account_id,
                paid_amount: validProducts[0].paid_amount,
                bank_type: validProducts[0].bank_type,
                bank_name: validProducts[0].bank_name,
                cheque_no: validProducts[0].cheque_no,
                cheque_date: validProducts[0].cheque_date,
                branch_name: validProducts[0].branch_name,
                account_no: validProducts[0].account_no,
                mobile_bank: validProducts[0].mobile_bank,
                mobile_number: validProducts[0].mobile_number,
                remarks: validProducts[0].remarks,
                invoice_no: editingSale.invoice_no,
            };
            
            router.put(`/sales/${editingSale.id}`, updateData, {
                onSuccess: () => {
                    onClose();
                    reset();
                    onSuccess?.();
                },
            });
        } else {
            setProcessing(true);
            router.post('/sales', {
                sale_date: data.sale_date,
                shift_id: data.shift_id,
                products: validProducts
            }, {
                onSuccess: () => {
                    onClose();
                    reset();
                    setProcessing(false);
                    onSuccess?.();
                },
                onError: () => {
                    setProcessing(false);
                },
            });
        }
    };

    const updateProduct = (index: number, field: string, value: string) => {
        setDataState((prevData) => {
            const newProducts = [...prevData.products];
            newProducts[index] = { ...newProducts[index], [field]: value };

            if (field === 'product_id' && value) {
                const selectedProduct = products.find(p => p.id.toString() === value);
                if (selectedProduct && selectedProduct.sales_price) {
                    const quantity = parseFloat(newProducts[index].quantity) || 0;
                    const amount = selectedProduct.sales_price * quantity;
                    const discount = parseFloat(newProducts[index].discount) || 0;
                    newProducts[index].amount = amount.toString();
                    newProducts[index].paid_amount = (amount - discount).toFixed(2);
                    newProducts[index].due_amount = '0.00';
                }
            }
            
            if (field === 'quantity' && value) {
                const selectedProduct = products.find(p => p.id.toString() === newProducts[index].product_id);
                if (selectedProduct && selectedProduct.sales_price) {
                    const quantity = parseFloat(value) || 0;
                    const amount = selectedProduct.sales_price * quantity;
                    const discount = parseFloat(newProducts[index].discount) || 0;
                    newProducts[index].amount = amount.toString();
                    newProducts[index].paid_amount = (amount - discount).toFixed(2);
                    newProducts[index].due_amount = '0.00';
                }
            }
            
            if (field === 'amount' && value) {
                const selectedProduct = products.find(p => p.id.toString() === newProducts[index].product_id);
                if (selectedProduct && selectedProduct.sales_price && selectedProduct.sales_price > 0) {
                    const amount = parseFloat(value) || 0;
                    const discount = parseFloat(newProducts[index].discount) || 0;
                    newProducts[index].quantity = (amount / selectedProduct.sales_price).toFixed(2);
                    newProducts[index].paid_amount = (amount - discount).toFixed(2);
                    newProducts[index].due_amount = '0.00';
                }
            }
            
            if (field === 'discount' && value !== undefined) {
                const amount = parseFloat(newProducts[index].amount) || 0;
                const discount = parseFloat(value) || 0;
                newProducts[index].paid_amount = (amount - discount).toFixed(2);
                newProducts[index].due_amount = '0.00';
            }
            
            if (field === 'to_account_id' && value) {
                const selectedAccount = accounts.find(a => a.id.toString() === value);
                if (selectedAccount) {
                    newProducts[index].account_no = selectedAccount.ac_number;
                }
            }
            
            return {
                ...prevData,
                products: newProducts
            };
        });
    };

    const addProduct = () => {
        const firstProduct = data.products[0];
        if (!firstProduct.product_id || !firstProduct.customer || !firstProduct.vehicle_no || !firstProduct.quantity || !firstProduct.amount || !firstProduct.to_account_id || !firstProduct.paid_amount) {
            alert('Please fill all required fields');
            return;
        }

        const newProducts = [
            {
                product_id: '',
                customer: '',
                vehicle_no: '',
                memo_no: '',
                quantity: '',
                amount: '',
                discount_type: 'Fixed',
                discount: '',
                payment_type: 'Cash',
                to_account_id: '',
                paid_amount: '',
                due_amount: '',
                bank_type: '',
                bank_name: '',
                cheque_no: '',
                cheque_date: '',
                branch_name: '',
                account_no: '',
                mobile_bank: '',
                mobile_number: '',
                remarks: '',
            },
            ...data.products
        ];

        setDataState({
            ...data,
            products: newProducts
        });
    };

    const removeProduct = (index: number) => {
        const newProducts = data.products.filter((_, i) => i !== index);
        setDataState(prev => ({ ...prev, products: newProducts }));
    };

    const getFilteredAccounts = (paymentType: string) => {
        if (paymentType === 'Cash') {
            return groupedAccounts['Cash in hand'] || groupedAccounts['Cash'] || [];
        } else if (paymentType === 'Bank') {
            return groupedAccounts['Bank Account'] || groupedAccounts['Bank'] || [];
        } else if (paymentType === 'Mobile Bank') {
            return groupedAccounts['Mobile Bank'] || [];
        }
        return [];
    };

    const getAvailableShifts = (selectedDate: string) => {
        if (!selectedDate) return shifts;
        
        const closedShiftIds = closedShifts
            .filter(cs => cs.close_date === selectedDate)
            .map(cs => cs.shift_id);
        
        return shifts.filter(shift => !closedShiftIds.includes(shift.id));
    };

    const handleVehicleBlur = (index: number, value: string) => {
        if (value) {
            const vehicle = vehicles.find(v => v.vehicle_number === value);
            if (vehicle) {
                setDataState((prevData) => {
                    const newProducts = [...prevData.products];
                    newProducts[index].customer = vehicle.customer?.name || '';
                    if (vehicle.products && vehicle.products.length > 0) {
                        const firstProduct = vehicle.products[0];
                        newProducts[index].product_id = firstProduct.id.toString();
                        const selectedProduct = products.find(p => p.id === firstProduct.id);
                        if (selectedProduct && selectedProduct.sales_price) {
                            const quantity = parseFloat(newProducts[index].quantity) || 0;
                            newProducts[index].amount = (selectedProduct.sales_price * quantity).toString();
                        }
                    }
                    return { ...prevData, products: newProducts };
                });
            } else {
                const saleHistory = salesHistory.find(s => s.vehicle_no === value);
                if (saleHistory) {
                    setDataState((prevData) => {
                        const newProducts = [...prevData.products];
                        newProducts[index].customer = saleHistory.customer || '';
                        if (saleHistory.product_id) {
                            newProducts[index].product_id = saleHistory.product_id.toString();
                            const selectedProduct = products.find(p => p.id === saleHistory.product_id);
                            if (selectedProduct && selectedProduct.sales_price) {
                                const quantity = parseFloat(newProducts[index].quantity) || 0;
                                newProducts[index].amount = (selectedProduct.sales_price * quantity).toString();
                            }
                        }
                        return { ...prevData, products: newProducts };
                    });
                }
            }
        }
    };

    return (
        <FormModal
            isOpen={isOpen}
            onClose={onClose}
            title={editingSale ? "Update Sale" : "Create Sale"}
            onSubmit={handleSubmit}
            processing={processing}
            submitText={editingSale ? "Update Sale" : "Create Sale"}
            className="max-w-[65vw]"
        >
            <div className="space-y-4">
                <div className="grid grid-cols-5 gap-4">
                    <div>
                        <Label className="text-sm font-medium dark:text-gray-200">
                            Sale Date <span className="text-red-500">*</span>
                        </Label>
                        <Input
                            type="date"
                            value={data.sale_date}
                            onChange={(e) => {
                                setData('sale_date', e.target.value);
                                setAvailableShifts(getAvailableShifts(e.target.value));
                                setData('shift_id', '');
                            }}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                    <div>
                        <Label className="text-sm font-medium dark:text-gray-200">
                            Shift <span className="text-red-500">*</span>
                        </Label>
                        <Select value={data.shift_id} onValueChange={(value) => setData('shift_id', value)} disabled={!data.sale_date}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select shift" />
                            </SelectTrigger>
                            <SelectContent>
                                {availableShifts.map((shift) => (
                                    <SelectItem key={shift.id} value={shift.id.toString()}>
                                        {shift.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label className="text-sm font-medium dark:text-gray-200">
                            Memo No <span className="text-red-500">*</span>
                        </Label>
                        <Input
                            value={data.products[0]?.memo_no || ''}
                            onChange={(e) => updateProduct(0, 'memo_no', e.target.value)}
                            placeholder="Enter memo number"
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                    <div>
                        <Label className="text-sm font-medium dark:text-gray-200">
                            Customer <span className="text-red-500">*</span>
                        </Label>
                        <Combobox
                            options={Array.from(new Set([
                                ...vehicles.filter(v => v.customer).map(v => v.customer!.name),
                                ...uniqueCustomers
                            ])).sort()}
                            value={data.products[0]?.customer || ''}
                            onValueChange={(value) => updateProduct(0, 'customer', value)}
                            placeholder="Type customer name"
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                    <div>
                        <Label className="text-sm font-medium dark:text-gray-200">
                            Vehicle <span className="text-red-500">*</span>
                        </Label>
                        <Combobox
                            options={Array.from(new Set([
                                ...vehicles.map(v => v.vehicle_number),
                                ...uniqueVehicles
                            ])).sort()}
                            value={data.products[0]?.vehicle_no || ''}
                            onValueChange={(value) => {
                                updateProduct(0, 'vehicle_no', value);
                                handleVehicleBlur(0, value);
                            }}
                            placeholder="Type vehicle number"
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                </div>

                <div className="grid grid-cols-5 gap-4">
                    <div>
                        <Label className="text-sm font-medium dark:text-gray-200">
                            Mobile Number
                        </Label>
                        <Input
                            value={data.products[0]?.mobile_number || ''}
                            onChange={(e) => updateProduct(0, 'mobile_number', e.target.value)}
                            placeholder="Enter mobile number"
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                    <div>
                        <Label className="text-sm font-medium dark:text-gray-200">Product</Label>
                        <Select value={data.products[0]?.product_id || ''} onValueChange={(value) => updateProduct(0, 'product_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select product" />
                            </SelectTrigger>
                            <SelectContent>
                                {products.map((product) => (
                                    <SelectItem key={product.id} value={product.id.toString()}>
                                        {product.product_name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label className="text-sm font-medium dark:text-gray-200">Sales Price</Label>
                        <Input
                            type="number"
                            step="0.01"
                            value={products.find(p => p.id.toString() === data.products[0]?.product_id)?.sales_price || ''}
                            readOnly
                            className="bg-gray-100 dark:border-gray-600 dark:bg-gray-600 dark:text-white"
                        />
                    </div>
                    <div>
                        <Label className="text-sm font-medium dark:text-gray-200">Quantity</Label>
                        <Input
                            type="number"
                            step="0.01"
                            value={data.products[0]?.quantity || ''}
                            onChange={(e) => updateProduct(0, 'quantity', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                    <div>
                        <Label className="text-sm font-medium dark:text-gray-200">Amount</Label>
                        <Input
                            type="number"
                            step="0.01"
                            value={data.products[0]?.amount || ''}
                            onChange={(e) => updateProduct(0, 'amount', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                </div>

                <div className="grid gap-4" style={{ gridTemplateColumns: `repeat(${data.products[0]?.payment_type === 'Bank' ? (data.products[0]?.bank_type === 'Cheque' ? 6 : 5) : data.products[0]?.payment_type === 'Mobile Bank' ? 5 : 3}, minmax(0, 1fr))` }}>
                    <div>
                        <Label className="text-sm font-medium dark:text-gray-200">Payment Method</Label>
                        <Select 
                            value={data.products[0]?.payment_type || 'Cash'} 
                            onValueChange={(value) => {
                                updateProduct(0, 'payment_type', value);
                                updateProduct(0, 'to_account_id', '');
                            }}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select payment method" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="Cash">Cash</SelectItem>
                                <SelectItem value="Bank">Bank</SelectItem>
                                <SelectItem value="Mobile Bank">Mobile Bank</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label className="text-sm font-medium dark:text-gray-200">To Account</Label>
                        <Select value={data.products[0]?.to_account_id || ''} onValueChange={(value) => updateProduct(0, 'to_account_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select payment account" />
                            </SelectTrigger>
                            <SelectContent>
                                {getFilteredAccounts(data.products[0]?.payment_type || 'Cash').map((account) => (
                                    <SelectItem key={account.id} value={account.id.toString()}>
                                        {account.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {data.products[0]?.payment_type === 'Bank' && (
                        <>
                            <div>
                                <Label className="text-sm font-medium dark:text-gray-200">Bank Type</Label>
                                <Select value={data.products[0]?.bank_type || ''} onValueChange={(value) => updateProduct(0, 'bank_type', value)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Cheque">Cheque</SelectItem>
                                        <SelectItem value="Cash Deposit">Cash Deposit</SelectItem>
                                        <SelectItem value="Online">Online</SelectItem>
                                        <SelectItem value="CHT">CHT</SelectItem>
                                        <SelectItem value="RTGS">RTGS</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="text-sm font-medium dark:text-gray-200">Bank Name</Label>
                                <Input
                                    value={data.products[0]?.bank_name || ''}
                                    onChange={(e) => updateProduct(0, 'bank_name', e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            {data.products[0]?.bank_type === 'Cheque' && (
                                <div>
                                    <Label className="text-sm font-medium dark:text-gray-200">Cheque No</Label>
                                    <Input
                                        value={data.products[0]?.cheque_no || ''}
                                        onChange={(e) => updateProduct(0, 'cheque_no', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                            )}
                        </>
                    )}
                    {data.products[0]?.payment_type === 'Mobile Bank' && (
                        <>
                            <div>
                                <Label className="text-sm font-medium dark:text-gray-200">Mobile Bank</Label>
                                <Select value={data.products[0]?.mobile_bank || ''} onValueChange={(value) => updateProduct(0, 'mobile_bank', value)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select mobile bank" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="bKash">bKash</SelectItem>
                                        <SelectItem value="Nagad">Nagad</SelectItem>
                                        <SelectItem value="Rocket">Rocket</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="text-sm font-medium dark:text-gray-200">Mobile Number</Label>
                                <Input
                                    value={data.products[0]?.mobile_number || ''}
                                    onChange={(e) => updateProduct(0, 'mobile_number', e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                        </>
                    )}
                    <div>
                        <Label className="text-sm font-medium dark:text-gray-200">
                            Paid Amount <span className="text-red-500">*</span>
                        </Label>
                        <Input
                            type="number"
                            step="0.01"
                            value={data.products[0]?.paid_amount || ''}
                            onChange={(e) => updateProduct(0, 'paid_amount', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                </div>

                <div className="grid grid-cols-12 gap-4">
                    <div className={editingSale ? "col-span-12" : "col-span-10"}>
                        <Label className="text-sm font-medium dark:text-gray-200">Remarks</Label>
                        <Input
                            value={data.products[0]?.remarks || ''}
                            onChange={(e) => updateProduct(0, 'remarks', e.target.value)}
                            placeholder="Enter any remarks"
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                    {!editingSale && (
                        <div className="col-span-2 flex flex-col justify-end">
                            <Button
                                type="button"
                                onClick={addProduct}
                                className="bg-blue-600 hover:bg-blue-700"
                            >
                                <Plus className="h-4 w-4 mr-1" />
                                Add to Cart
                            </Button>
                        </div>
                    )}
                </div>

                {!editingSale && (
                    <div className="mt-6">
                        <table className="w-full border border-gray-300 dark:border-gray-600">
                            <thead className="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th className="p-2 text-left text-sm font-medium dark:text-gray-200">SL</th>
                                    <th className="p-2 text-left text-sm font-medium dark:text-gray-200">Customer</th>
                                    <th className="p-2 text-left text-sm font-medium dark:text-gray-200">Vehicle</th>
                                    <th className="p-2 text-left text-sm font-medium dark:text-gray-200">Product Name</th>
                                    <th className="p-2 text-left text-sm font-medium dark:text-gray-200">Quantity</th>
                                    <th className="p-2 text-left text-sm font-medium dark:text-gray-200">Total</th>
                                    <th className="p-2 text-left text-sm font-medium dark:text-gray-200">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.products.slice(1).filter(p => p.product_id).map((product, index) => {
                                    const selectedProduct = products.find(p => p.id.toString() === product.product_id);
                                    const actualIndex = index + 1;
                                    const hasError = !product.product_id || !product.customer || !product.vehicle_no || !product.quantity || !product.amount || !product.to_account_id || !product.paid_amount;
                                    return (
                                        <tr key={actualIndex} className={`border-t dark:border-gray-600 ${hasError ? 'border-red-500 bg-red-50 dark:bg-red-900/20' : ''}`}>
                                            <td className="p-2 text-sm dark:text-white">{index + 1}</td>
                                            <td className="p-2 text-sm dark:text-white">{product.customer || '-'}</td>
                                            <td className="p-2 text-sm dark:text-white">{product.vehicle_no || '-'}</td>
                                            <td className="p-2 text-sm dark:text-white">{selectedProduct?.product_name}</td>
                                            <td className="p-2 text-sm dark:text-white">{product.quantity}</td>
                                            <td className="p-2 text-sm dark:text-white">{product.paid_amount || '0'}</td>
                                            <td className="p-2">
                                                <div className="flex gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            const editProduct = data.products[actualIndex];
                                                            const newProducts = data.products.filter((_, i) => i !== actualIndex);
                                                            newProducts[0] = editProduct;
                                                            setDataState(prev => ({ ...prev, products: newProducts }));
                                                        }}
                                                        className="text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() => removeProduct(actualIndex)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </FormModal>
    );
}
