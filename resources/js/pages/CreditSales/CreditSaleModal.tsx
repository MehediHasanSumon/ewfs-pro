import { Button } from '@/components/ui/button';
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
import { SearchableSelect } from '@/components/ui/searchable-select';
import { router } from '@inertiajs/react';
import { Edit, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Product {
    id: number;
    product_name: string;
    product_code: string;
    sales_price: number;
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

interface Customer {
    id: number;
    name: string;
}

interface Shift {
    id: number;
    name: string;
}

interface ClosedShift {
    close_date: string;
    shift_id: number;
}

export interface CreditSale {
    id: number;
    sale_date: string;
    invoice_no: string;
    customer: { id: number; name: string };
    vehicle: { id: number; vehicle_number: string };
    product_id: number;
    shift: { name: string };
    quantity: number;
    total_amount: number;
    paid_amount: number;
    due_amount: number;
    remarks: string;
    created_at: string;
}

interface CreditSaleModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSuccess?: () => void;
    editingSale: CreditSale | null;
    products: Product[];
    vehicles: Vehicle[];
    customers: Customer[];
    shifts: Shift[];
    closedShifts: ClosedShift[];
    initialSaleDate?: string;
    initialShiftId?: string;
}

export function CreditSaleModal({
    isOpen,
    onClose,
    onSuccess,
    editingSale,
    products,
    vehicles,
    customers,
    shifts,
    closedShifts,
    initialSaleDate,
    initialShiftId,
}: CreditSaleModalProps) {
    const buildInitialState = () => ({
        sale_date: initialSaleDate || '',
        shift_id: initialShiftId || '',
        products: [
            {
                product_id: '',
                customer_id: '',
                vehicle_id: '',
                memo_no: '',
                quantity: '',
                amount: '',
                due_amount: '',
                remarks: '',
            }
        ],
    });

    const [data, setDataState] = useState(buildInitialState());

    const [processing, setProcessing] = useState(false);
    const [availableShifts, setAvailableShifts] = useState<Shift[]>(shifts);

    const getAvailableShifts = (selectedDate: string) => {
        if (!selectedDate) return shifts;

        const closedShiftIds = closedShifts
            .filter(cs => cs.close_date === selectedDate)
            .map(cs => cs.shift_id);

        return shifts.filter(shift => !closedShiftIds.includes(shift.id));
    };

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
            const response = await fetch(`/credit-sales/${editingSale.id}/edit`);
            const data = await response.json();
            const saleData = data.creditSale;
            
            setDataState({
                sale_date: saleData.sale_date.split('T')[0],
                shift_id: saleData.shift_id?.toString() || '',
                products: [
                    {
                        product_id: saleData.product_id?.toString() || '',
                        customer_id: saleData.customer_id?.toString() || '',
                        vehicle_id: saleData.vehicle_id?.toString() || '',
                        memo_no: saleData.memo_no || '',
                        quantity: saleData.quantity?.toString() || '',
                        amount: saleData.amount?.toString() || '',
                        due_amount: saleData.due_amount?.toString() || '',
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
        const validProducts = data.products.filter(p => p.product_id && p.customer_id && p.vehicle_id && p.quantity);
        if (validProducts.length === 0) {
            alert('Please add at least one product to cart');
            return;
        }
        
        if (editingSale) {
            const updateData = {
                sale_date: data.sale_date,
                shift_id: data.shift_id,
                product_id: validProducts[0].product_id,
                customer_id: validProducts[0].customer_id,
                vehicle_id: validProducts[0].vehicle_id,
                memo_no: validProducts[0].memo_no,
                quantity: validProducts[0].quantity,
                amount: validProducts[0].amount,
                due_amount: validProducts[0].due_amount,
                remarks: validProducts[0].remarks,
            };
            
            router.put(`/credit-sales/${editingSale.id}`, updateData, {
                onSuccess: () => {
                    onClose();
                    reset();
                    onSuccess?.();
                },
            });
        } else {
            setProcessing(true);
            router.post('/credit-sales', {
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

            if (field === 'vehicle_id' && value) {
                const selectedVehicle = vehicles.find(v => v.id.toString() === value);
                if (selectedVehicle) {
                    newProducts[index].customer_id = selectedVehicle.customer_id.toString();
                    if (selectedVehicle.products && selectedVehicle.products.length > 0) {
                        newProducts[index].product_id = selectedVehicle.products[0].id.toString();
                    }
                }
            }

            if (field === 'product_id' && value) {
                const selectedProduct = products.find(p => p.id.toString() === value);
                if (selectedProduct && selectedProduct.sales_price) {
                    const quantity = parseFloat(newProducts[index].quantity) || 0;
                    const amount = selectedProduct.sales_price * quantity;
                    newProducts[index].amount = amount.toString();
                    newProducts[index].due_amount = amount.toFixed(2);
                }
            }
            
            if (field === 'quantity' && value) {
                const selectedProduct = products.find(p => p.id.toString() === newProducts[index].product_id);
                if (selectedProduct && selectedProduct.sales_price) {
                    const quantity = parseFloat(value) || 0;
                    const amount = selectedProduct.sales_price * quantity;
                    newProducts[index].amount = amount.toString();
                    newProducts[index].due_amount = amount.toFixed(2);
                }
            }
            
            if (field === 'amount' && value) {
                const selectedProduct = products.find(p => p.id.toString() === newProducts[index].product_id);
                if (selectedProduct && selectedProduct.sales_price && selectedProduct.sales_price > 0) {
                    const amount = parseFloat(value) || 0;
                    newProducts[index].quantity = (amount / selectedProduct.sales_price).toFixed(2);
                    newProducts[index].due_amount = amount.toFixed(2);
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
        if (!firstProduct.product_id || !firstProduct.customer_id || !firstProduct.vehicle_id || !firstProduct.quantity) {
            alert('Please fill product, customer, vehicle and quantity');
            return;
        }

        const newProducts = [
            {
                product_id: '',
                customer_id: '',
                vehicle_id: '',
                memo_no: '',
                quantity: '',
                amount: '',
                due_amount: '',
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

    const getFilteredVehicles = (customerId: string) => {
        if (!customerId) return vehicles;
        return vehicles.filter(v => v.customer_id.toString() === customerId);
    };

    const getFilteredProducts = (vehicleId: string) => {
        if (!vehicleId) return products;
        const selectedVehicle = vehicles.find(v => v.id.toString() === vehicleId);
        if (!selectedVehicle || !selectedVehicle.products || selectedVehicle.products.length === 0) {
            return [];
        }
        return products.filter(p => selectedVehicle.products!.some(vp => vp.id === p.id));
    };

    return (
        <FormModal
            isOpen={isOpen}
            onClose={onClose}
            title={editingSale ? "Update Sale" : "Create Sale"}
            onSubmit={handleSubmit}
            processing={processing}
            submitText={editingSale ? "Update Sale" : "Create Sale"}
            className="max-w-[65vw] max-h-[90vh]"
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
                            Memo No
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
                        <SearchableSelect
                            options={customers.map(customer => ({
                                value: customer.id.toString(),
                                label: customer.name
                            }))}
                            value={data.products[0]?.customer_id || ''}
                            onValueChange={(value) => updateProduct(0, 'customer_id', value)}
                            placeholder="Select customer"
                            searchPlaceholder="Search customers..."
                        />
                    </div>
                    <div>
                        <Label className="text-sm font-medium dark:text-gray-200">
                            Vehicle <span className="text-red-500">*</span>
                        </Label>
                        <SearchableSelect
                            options={getFilteredVehicles(data.products[0]?.customer_id).map(vehicle => ({
                                value: vehicle.id.toString(),
                                label: vehicle.vehicle_number,
                                subtitle: vehicle.customer?.name
                            }))}
                            value={data.products[0]?.vehicle_id || ''}
                            onValueChange={(value) => updateProduct(0, 'vehicle_id', value)}
                            placeholder="Select vehicle"
                            searchPlaceholder="Search vehicles..."
                        />
                    </div>
                </div>

                <div className="grid grid-cols-5 gap-4">
                    <div>
                        <Label className="text-sm font-medium dark:text-gray-200">Product</Label>
                        <Select value={data.products[0]?.product_id || ''} onValueChange={(value) => updateProduct(0, 'product_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select product" />
                            </SelectTrigger>
                            <SelectContent>
                                {getFilteredProducts(data.products[0]?.vehicle_id).map((product) => (
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
                            value={getFilteredProducts(data.products[0]?.vehicle_id).find(p => p.id.toString() === data.products[0]?.product_id)?.sales_price || ''}
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
                    <div>
                        <Label className="text-sm font-medium dark:text-gray-200">
                            Total Amount
                        </Label>
                        <Input
                            type="number"
                            step="0.01"
                            value={data.products[0]?.due_amount || ''}
                            readOnly
                            className="bg-gray-100 dark:border-gray-600 dark:bg-gray-600 dark:text-white"
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
                                    const selectedCustomer = customers.find(c => c.id.toString() === product.customer_id);
                                    const selectedVehicle = vehicles.find(v => v.id.toString() === product.vehicle_id);
                                    const actualIndex = index + 1;
                                    return (
                                        <tr key={actualIndex} className="border-t dark:border-gray-600">
                                            <td className="p-2 text-sm dark:text-white">{index + 1}</td>
                                            <td className="p-2 text-sm dark:text-white">{selectedCustomer?.name || '-'}</td>
                                            <td className="p-2 text-sm dark:text-white">{selectedVehicle?.vehicle_number || '-'}</td>
                                            <td className="p-2 text-sm dark:text-white">{selectedProduct?.product_name}</td>
                                            <td className="p-2 text-sm dark:text-white">{product.quantity}</td>
                                            <td className="p-2 text-sm dark:text-white">{product.due_amount || '0'}</td>
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
                            <tfoot>
                                <tr className="border-t bg-gray-50 dark:border-gray-600 dark:bg-gray-700">
                                    <td colSpan={5} className="p-2 text-right text-sm font-medium dark:text-gray-200">
                                        Total
                                    </td>
                                    <td className="p-2 text-sm font-semibold dark:text-white">
                                        {data.products
                                            .slice(1)
                                            .filter((p) => p.product_id)
                                            .reduce((sum, p) => {
                                                const amount = parseFloat(p.due_amount || p.amount || '0') || 0;
                                                return sum + amount;
                                            }, 0)
                                            .toFixed(2)}
                                    </td>
                                    <td className="p-2" />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}
            </div>
        </FormModal>
    );
}
