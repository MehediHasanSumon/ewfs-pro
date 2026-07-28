import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
import { FormModal } from '@/components/ui/form-modal';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { router } from '@inertiajs/react';
import { Edit, LoaderCircle, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

interface SaleItem {
    id: number;
    product_id: number;
    product_name_snapshot: string;
    quantity: number;
    unit_price: number;
    discount_amount: number;
    line_total: number;
    remarks?: string | null;
}

interface SalePaymentDetail {
    payment_method: string;
}

export interface Sale {
    id: number;
    sale_date: string;
    invoice_no: string;
    customer: string;
    mobile_number?: string | null;
    vehicle_no?: string | null;
    product_id?: number | null;
    shift?: { name: string } | null;
    quantity: number;
    total_amount: number;
    paid_amount: number;
    due_amount: number;
    remarks: string;
    created_at: string;
    items?: SaleItem[];
    payment_detail?: SalePaymentDetail | null;
    transaction?: {
        payment_type?: string | null;
    } | null;
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
    customer_id: number | null;
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

interface CustomerLookup {
    id: number;
    name: string;
    mobile: string;
    address?: string | null;
    previous_due: number;
    vehicles: {
        id: number;
        vehicle_number: string;
        vehicle_name?: string | null;
    }[];
}

interface CartLine {
    key: string;
    product_id: string;
    quantity: string;
    discount: string;
    remarks: string;
}

interface FormState {
    sale_date: string;
    shift_id: string;
    memo_no: string;
    customer_id: string;
    customer_name: string;
    customer_mobile: string;
    customer_address: string;
    save_customer: boolean;
    vehicle_id: string;
    vehicle_no: string;
    payment_type: string;
    to_account_id: string;
    paid_amount: string;
    bank_type: string;
    bank_name: string;
    branch_name: string;
    account_no: string;
    cheque_no: string;
    cheque_date: string;
    mobile_bank: string;
    payment_mobile_number: string;
    remarks: string;
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
    shifts: Shift[];
    closedShifts: ClosedShift[];
    initialSaleDate?: string;
    initialShiftId?: string;
}

const emptyLine = (): CartLine => ({
    key: `${Date.now()}-${Math.random()}`,
    product_id: '',
    quantity: '',
    discount: '',
    remarks: '',
});

const paymentLabel = (method?: string | null) => {
    switch (method?.toLowerCase()) {
        case 'bank':
        case 'cheque':
            return 'Bank';
        case 'mobile_bank':
        case 'mobile bank':
            return 'Mobile Bank';
        default:
            return 'Cash';
    }
};

const normalizeMobile = (mobile: string) =>
    mobile.trim().replace(/[\s\-()]+/g, '');

export function SaleModal({
    isOpen,
    onClose,
    onSuccess,
    editingSale,
    accounts,
    groupedAccounts,
    products,
    vehicles,
    shifts,
    closedShifts,
    initialSaleDate,
    initialShiftId,
}: SaleModalProps) {
    const initialState = (): FormState => ({
        sale_date: initialSaleDate || '',
        shift_id: initialShiftId || '',
        memo_no: '',
        customer_id: '',
        customer_name: '',
        customer_mobile: '',
        customer_address: '',
        save_customer: false,
        vehicle_id: '',
        vehicle_no: '',
        payment_type: 'Cash',
        to_account_id: '',
        paid_amount: '',
        bank_type: '',
        bank_name: '',
        branch_name: '',
        account_no: '',
        cheque_no: '',
        cheque_date: '',
        mobile_bank: '',
        payment_mobile_number: '',
        remarks: '',
    });

    const [data, setData] = useState<FormState>(initialState);
    const [draftLine, setDraftLine] = useState<CartLine>(emptyLine);
    const [cart, setCart] = useState<CartLine[]>([]);
    const [processing, setProcessing] = useState(false);
    const [availableShifts, setAvailableShifts] = useState<Shift[]>(shifts);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [lookupError, setLookupError] = useState('');
    const [lookupLoading, setLookupLoading] = useState(false);
    const [previousDue, setPreviousDue] = useState(0);
    const [lookupEnabled, setLookupEnabled] = useState(true);
    const latestLookupRef = useRef('');
    const resolvedLookupRef = useRef('');

    const productsById = useMemo(
        () => new Map(products.map((product) => [product.id.toString(), product])),
        [products],
    );
    const selectedDraftProduct = productsById.get(draftLine.product_id);
    const draftGross =
        (selectedDraftProduct?.sales_price || 0) *
        (parseFloat(draftLine.quantity) || 0);
    const draftDiscount = parseFloat(draftLine.discount) || 0;
    const draftTotal = Math.max(0, draftGross - draftDiscount);

    const lineTotal = (line: CartLine) => {
        const product = productsById.get(line.product_id);
        const gross =
            (product?.sales_price || 0) * (parseFloat(line.quantity) || 0);

        return Math.max(0, gross - (parseFloat(line.discount) || 0));
    };

    const cartTotal = cart.reduce((total, line) => total + lineTotal(line), 0);
    const nestedItemError = Object.entries(errors).find(([key]) =>
        key.startsWith('items.'),
    )?.[1];
    const vehicleNumbers = useMemo(
        () =>
            Array.from(
                new Set(vehicles.map((vehicle) => vehicle.vehicle_number)),
            ).sort(),
        [vehicles],
    );

    function getAvailableShifts(selectedDate: string) {
        if (!selectedDate) return shifts;

        const closedShiftIds = closedShifts
            .filter((closing) => closing.close_date === selectedDate)
            .map((closing) => closing.shift_id);

        return shifts.filter((shift) => !closedShiftIds.includes(shift.id));
    }

    const getFilteredAccounts = (paymentType: string) => {
        if (paymentType === 'Cash') {
            return (
                groupedAccounts['Cash in hand'] || groupedAccounts['Cash'] || []
            );
        }

        if (paymentType === 'Bank') {
            return (
                groupedAccounts['Bank Account'] || groupedAccounts['Bank'] || []
            );
        }

        if (paymentType === 'Mobile Bank') {
            return groupedAccounts['Mobile Bank'] || [];
        }

        return [];
    };

    const reset = () => {
        const state = initialState();
        latestLookupRef.current = '';
        resolvedLookupRef.current = '';
        setData(state);
        setDraftLine(emptyLine());
        setCart([]);
        setAvailableShifts(getAvailableShifts(state.sale_date));
        setErrors({});
        setLookupError('');
        setLookupLoading(false);
        setPreviousDue(0);
        setLookupEnabled(true);
    };

    const clearCartForCustomerChange = () => {
        setCart([]);
        setDraftLine(emptyLine());
        setData((current) => ({ ...current, paid_amount: '' }));
    };

    const confirmCustomerChange = () =>
        cart.length === 0 && !draftLine.product_id
            ? true
            : window.confirm(
                  'Changing customer will clear the current cart. Continue?',
              );

    const handleMobileChange = (value: string) => {
        if (
            value !== data.customer_mobile &&
            !confirmCustomerChange()
        ) {
            return;
        }

        if (value !== data.customer_mobile) {
            clearCartForCustomerChange();
        }

        latestLookupRef.current = normalizeMobile(value);
        resolvedLookupRef.current = '';
        setLookupEnabled(true);
        setData((current) => ({
            ...current,
            customer_mobile: value,
            customer_id: '',
            customer_name: current.customer_id ? '' : current.customer_name,
            customer_address: current.customer_id
                ? ''
                : current.customer_address,
            save_customer: false,
            vehicle_id: '',
            vehicle_no: '',
        }));
        setPreviousDue(0);
        setLookupError('');
    };

    const handleCustomerNameChange = (value: string) => {
        if (
            value !== data.customer_name &&
            !confirmCustomerChange()
        ) {
            return;
        }

        if (value !== data.customer_name) {
            clearCartForCustomerChange();
        }

        setData((current) => ({
            ...current,
            customer_id: '',
            customer_name: value,
            save_customer: false,
        }));
        setPreviousDue(0);
    };

    const handleVehicleChange = (vehicleNumber: string) => {
        const vehicle = vehicles.find(
            (item) => item.vehicle_number === vehicleNumber,
        );

        setData((current) => ({
            ...current,
            vehicle_id: vehicle?.id.toString() || '',
            vehicle_no: vehicleNumber,
        }));
    };

    const updateDraftLine = (changes: Partial<CartLine>) => {
        const nextLine = { ...draftLine, ...changes };
        const product = productsById.get(nextLine.product_id);
        const quantity = parseFloat(nextLine.quantity);
        const discount = parseFloat(nextLine.discount) || 0;
        const nextDraftTotal =
            product && Number.isFinite(quantity) && quantity > 0
                ? Math.max(0, product.sales_price * quantity - discount)
                : 0;

        setDraftLine(nextLine);
        setData((current) => ({
            ...current,
            paid_amount:
                cartTotal + nextDraftTotal > 0
                    ? (cartTotal + nextDraftTotal).toFixed(2)
                    : '',
        }));
    };

    const addToCart = () => {
        const product = productsById.get(draftLine.product_id);
        const quantity = parseFloat(draftLine.quantity);

        if (!product) {
            setErrors((current) => ({
                ...current,
                draft_product_id: 'Select a product.',
            }));
            return;
        }

        if (!Number.isFinite(quantity) || quantity <= 0) {
            setErrors((current) => ({
                ...current,
                draft_quantity: 'Quantity must be greater than zero.',
            }));
            return;
        }

        if (draftDiscount > draftGross) {
            setErrors((current) => ({
                ...current,
                draft_discount: 'Discount cannot exceed the product amount.',
            }));
            return;
        }

        if (cart.some((line) => line.product_id === draftLine.product_id)) {
            setErrors((current) => ({
                ...current,
                draft_product_id: 'This product is already in the cart.',
            }));
            return;
        }

        const nextCart = [...cart, { ...draftLine }];
        const nextTotal = nextCart.reduce(
            (total, line) => total + lineTotal(line),
            0,
        );
        setCart(nextCart);
        setDraftLine(emptyLine());
        setData((current) => ({
            ...current,
            paid_amount: nextTotal.toFixed(2),
        }));
        setErrors((current) => {
            const next = { ...current };
            delete next.draft_product_id;
            delete next.draft_quantity;
            delete next.draft_discount;

            return next;
        });
    };

    const editCartLine = (line: CartLine) => {
        const nextCart = cart.filter((item) => item.key !== line.key);
        const nextTotal = nextCart.reduce(
            (total, item) => total + lineTotal(item),
            0,
        );
        setCart(nextCart);
        setDraftLine(line);
        setData((current) => ({
            ...current,
            paid_amount: nextTotal > 0 ? nextTotal.toFixed(2) : '',
        }));
    };

    const removeCartLine = (key: string) => {
        const nextCart = cart.filter((line) => line.key !== key);
        const nextTotal = nextCart.reduce(
            (total, line) => total + lineTotal(line),
            0,
        );
        setCart(nextCart);
        setData((current) => ({
            ...current,
            paid_amount: nextTotal > 0 ? nextTotal.toFixed(2) : '',
        }));
    };

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        setErrors({});

        const items = [...cart];

        if (draftLine.product_id || draftLine.quantity) {
            const product = productsById.get(draftLine.product_id);
            const quantity = parseFloat(draftLine.quantity);

            if (!product || !Number.isFinite(quantity) || quantity <= 0) {
                setErrors({
                    items: 'Complete the current product before submitting.',
                });
                return;
            }

            if (items.some((line) => line.product_id === draftLine.product_id)) {
                setErrors({
                    items: 'The same product cannot be added more than once.',
                });
                return;
            }

            items.push(draftLine);
        }

        if (items.length === 0) {
            setErrors({ items: 'Add at least one product to the cart.' });
            return;
        }

        const total = items.reduce(
            (sum, line) => sum + lineTotal(line),
            0,
        );
        const paidAmount = parseFloat(data.paid_amount);

        if (
            !Number.isFinite(paidAmount) ||
            Math.abs(paidAmount - total) >= 0.005
        ) {
            setErrors({
                paid_amount:
                    'Paid amount must equal the sale total for a POS sale.',
            });
            return;
        }

        const payload = {
            ...data,
            paid_amount: total.toFixed(2),
            items: items.map((line) => ({
                product_id: line.product_id,
                quantity: line.quantity,
                discount: line.discount || 0,
                remarks: line.remarks || null,
            })),
        };
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onError: (validationErrors: Record<string, string>) => {
                setErrors(validationErrors);
            },
            onFinish: () => setProcessing(false),
            onSuccess: () => {
                if (editingSale) {
                    onClose();
                }

                reset();
                onSuccess?.();
            },
        };

        if (editingSale) {
            router.put(`/sales/${editingSale.id}`, payload, options);
        } else {
            router.post('/sales', payload, options);
        }
    };

    useEffect(() => {
        if (
            !isOpen ||
            !lookupEnabled ||
            !data.customer_mobile.trim()
        ) {
            return;
        }

        const controller = new AbortController();
        const mobile = data.customer_mobile.trim();
        const lookupKey = normalizeMobile(mobile);

        latestLookupRef.current = lookupKey;

        if (resolvedLookupRef.current === lookupKey) {
            return;
        }

        const timer = window.setTimeout(async () => {
            setLookupLoading(true);
            setLookupError('');

            try {
                const response = await fetch(
                    `/sales/customer-lookup?mobile=${encodeURIComponent(mobile)}`,
                    {
                        signal: controller.signal,
                        headers: { Accept: 'application/json' },
                    },
                );
                const payload = await response.json();

                if (latestLookupRef.current !== lookupKey) {
                    return;
                }

                if (!response.ok) {
                    const message =
                        payload.errors?.mobile?.[0] ||
                        payload.message ||
                        'Unable to look up this mobile number.';
                    setLookupError(message);
                    return;
                }

                const customer = payload.data as CustomerLookup | null;

                if (!customer) {
                    resolvedLookupRef.current = lookupKey;
                    setData((current) =>
                        current.customer_mobile.trim() === mobile &&
                        current.customer_id
                            ? {
                                  ...current,
                                  customer_id: '',
                                  customer_name: '',
                                  customer_address: '',
                                  vehicle_id: '',
                                  vehicle_no: '',
                              }
                            : current,
                    );
                    setPreviousDue(0);
                    return;
                }

                resolvedLookupRef.current = lookupKey;
                setData((current) => {
                    if (
                        normalizeMobile(current.customer_mobile) !==
                        lookupKey
                    ) {
                        return current;
                    }

                    const firstVehicle = customer.vehicles[0];

                    return {
                        ...current,
                        customer_id: customer.id.toString(),
                        customer_name: customer.name,
                        customer_mobile: customer.mobile,
                        customer_address: customer.address || '',
                        save_customer: false,
                        vehicle_id:
                            current.vehicle_id ||
                            firstVehicle?.id.toString() ||
                            '',
                        vehicle_no:
                            current.vehicle_no ||
                            firstVehicle?.vehicle_number ||
                            '',
                    };
                });
                if (latestLookupRef.current === lookupKey) {
                    setPreviousDue(customer.previous_due);
                }
            } catch (error) {
                if ((error as Error).name !== 'AbortError') {
                    setLookupError(
                        'Unable to look up this mobile number. Please retry.',
                    );
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLookupLoading(false);
                }
            }
        }, 500);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [data.customer_mobile, isOpen, lookupEnabled]);

    useEffect(() => {
        if (!isOpen) {
            reset();
            return;
        }

        if (!editingSale) {
            const state = initialState();
            latestLookupRef.current = '';
            resolvedLookupRef.current = '';
            setData(state);
            setDraftLine(emptyLine());
            setCart([]);
            setAvailableShifts(getAvailableShifts(state.sale_date));
            setLookupEnabled(true);
            return;
        }

        setLookupEnabled(false);
        const controller = new AbortController();

        void fetch(`/sales/${editingSale.id}/edit`, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then(async (response) => {
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(
                        payload.message || 'Unable to load the selected sale.',
                    );
                }

                const sale = payload.sale;
                const payment = sale.payment || {};
                const lines: CartLine[] = (sale.items || []).map(
                    (item: {
                        id: number;
                        product_id: number;
                        quantity: number;
                        discount: number;
                        remarks?: string | null;
                    }) => ({
                        key: `sale-item-${item.id}`,
                        product_id: item.product_id.toString(),
                        quantity: item.quantity.toString(),
                        discount: item.discount
                            ? item.discount.toString()
                            : '',
                        remarks: item.remarks || '',
                    }),
                );
                const state: FormState = {
                    sale_date: sale.sale_date || '',
                    shift_id: sale.shift_id?.toString() || '',
                    memo_no: sale.memo_no || '',
                    customer_id: sale.customer_id?.toString() || '',
                    customer_name: sale.customer_name || '',
                    customer_mobile: sale.customer_mobile || '',
                    customer_address: sale.customer_address || '',
                    save_customer: false,
                    vehicle_id: sale.vehicle_id?.toString() || '',
                    vehicle_no: sale.vehicle_no || '',
                    payment_type: paymentLabel(payment.payment_type),
                    to_account_id:
                        payment.to_account_id?.toString() || '',
                    paid_amount:
                        payment.paid_amount?.toString() ||
                        sale.grand_total?.toString() ||
                        '',
                    bank_type: payment.bank_type || '',
                    bank_name: payment.bank_name || '',
                    branch_name: payment.branch_name || '',
                    account_no: payment.account_no || '',
                    cheque_no: payment.cheque_no || '',
                    cheque_date: payment.cheque_date || '',
                    mobile_bank: payment.mobile_bank || '',
                    payment_mobile_number:
                        payment.payment_mobile_number || '',
                    remarks: sale.remarks || '',
                };

                setData(state);
                setCart(lines);
                setDraftLine(emptyLine());
                setAvailableShifts(getAvailableShifts(state.sale_date));
                latestLookupRef.current = normalizeMobile(
                    state.customer_mobile,
                );
                resolvedLookupRef.current = latestLookupRef.current;
            })
            .catch((error) => {
                if ((error as Error).name !== 'AbortError') {
                    setErrors({
                        sale: (error as Error).message,
                    });
                }
            });

        return () => controller.abort();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editingSale, isOpen]);

    return (
        <FormModal
            isOpen={isOpen}
            onClose={onClose}
            title={editingSale ? 'Update Sale' : 'Create Sale'}
            onSubmit={handleSubmit}
            processing={processing}
            submitText={editingSale ? 'Update Sale' : 'Create Sale'}
            errors={errors}
            className="w-[calc(100vw-2rem)] max-w-[65vw] max-md:max-w-[calc(100vw-2rem)]"
        >
            <div className="space-y-4">
                <InputError message={errors.sale} />

                <div className="grid grid-cols-1 gap-4 md:grid-cols-5">
                    <div>
                        <Label>
                            Sale Date <span className="text-red-500">*</span>
                        </Label>
                        <Input
                            name="sale_date"
                            type="date"
                            value={data.sale_date}
                            onChange={(event) => {
                                const value = event.target.value;
                                setData((current) => ({
                                    ...current,
                                    sale_date: value,
                                    shift_id: '',
                                }));
                                setAvailableShifts(getAvailableShifts(value));
                            }}
                        />
                        <InputError message={errors.sale_date} />
                    </div>

                    <div>
                        <Label>
                            Shift <span className="text-red-500">*</span>
                        </Label>
                        <Select
                            value={data.shift_id}
                            onValueChange={(value) =>
                                setData((current) => ({
                                    ...current,
                                    shift_id: value,
                                }))
                            }
                            disabled={!data.sale_date}
                        >
                            <SelectTrigger>
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
                        <InputError message={errors.shift_id} />
                    </div>

                    <div>
                        <Label>Memo No</Label>
                        <Input
                            value={data.memo_no}
                            onChange={(event) =>
                                setData((current) => ({
                                    ...current,
                                    memo_no: event.target.value,
                                }))
                            }
                            placeholder="Enter memo number"
                        />
                        <InputError message={errors.memo_no} />
                    </div>

                    <div>
                        <Label>
                            Mobile Number{' '}
                            <span className="text-red-500">*</span>
                        </Label>
                        <div className="relative">
                            <Input
                                name="customer_mobile"
                                value={data.customer_mobile}
                                onChange={(event) =>
                                    handleMobileChange(event.target.value)
                                }
                                placeholder="Enter mobile number"
                                className="pr-9"
                            />
                            {lookupLoading && (
                                <LoaderCircle className="absolute top-2.5 right-3 h-4 w-4 animate-spin text-gray-500" />
                            )}
                        </div>
                        <InputError
                            message={errors.customer_mobile || lookupError}
                        />
                    </div>

                    <div>
                        <Label>
                            Customer <span className="text-red-500">*</span>
                        </Label>
                        <Input
                            name="customer_name"
                            value={data.customer_name}
                            onChange={(event) =>
                                handleCustomerNameChange(event.target.value)
                            }
                            readOnly={Boolean(data.customer_id)}
                            placeholder="Enter customer name"
                            className={
                                data.customer_id
                                    ? 'bg-gray-100 dark:bg-gray-600'
                                    : ''
                            }
                        />
                        <InputError message={errors.customer_name} />
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-5">
                    <div className="md:col-span-2">
                        <Label>Address</Label>
                        <Input
                            value={data.customer_address}
                            onChange={(event) =>
                                setData((current) => ({
                                    ...current,
                                    customer_address: event.target.value,
                                }))
                            }
                            readOnly={Boolean(data.customer_id)}
                            placeholder="Enter customer address"
                            className={
                                data.customer_id
                                    ? 'bg-gray-100 dark:bg-gray-600'
                                    : ''
                            }
                        />
                        <InputError message={errors.customer_address} />
                    </div>

                    <div>
                        <Label>Previous Due</Label>
                        <Input
                            value={previousDue.toFixed(2)}
                            readOnly
                            className="bg-gray-100 dark:bg-gray-600"
                        />
                    </div>

                    <div>
                        <Label>Vehicle</Label>
                        <Combobox
                            options={vehicleNumbers}
                            value={data.vehicle_no}
                            onValueChange={handleVehicleChange}
                            placeholder="Type vehicle number"
                        />
                        <InputError
                            message={errors.vehicle_id || errors.vehicle_no}
                        />
                    </div>

                    <div className="flex items-end pb-2">
                        {!data.customer_id && (
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="save-customer"
                                    checked={data.save_customer}
                                    onCheckedChange={(checked) =>
                                        setData((current) => ({
                                            ...current,
                                            save_customer: checked === true,
                                        }))
                                    }
                                />
                                <Label htmlFor="save-customer">
                                    Save Customer
                                </Label>
                            </div>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-6">
                    <div>
                        <Label>
                            Product <span className="text-red-500">*</span>
                        </Label>
                        <SearchableSelect
                            options={products.map((product) => ({
                                value: product.id.toString(),
                                label: product.product_name,
                                subtitle: product.product_code,
                            }))}
                            value={draftLine.product_id}
                            onValueChange={(value) =>
                                updateDraftLine({ product_id: value })
                            }
                            placeholder="Select product"
                            searchPlaceholder="Search products..."
                        />
                        <InputError
                            message={
                                errors.draft_product_id ||
                                errors['items.0.product_id']
                            }
                        />
                    </div>

                    <div>
                        <Label>Sales Price</Label>
                        <Input
                            value={
                                selectedDraftProduct
                                    ? selectedDraftProduct.sales_price.toFixed(2)
                                    : ''
                            }
                            readOnly
                            className="bg-gray-100 dark:bg-gray-600"
                        />
                    </div>

                    <div>
                        <Label>
                            Quantity <span className="text-red-500">*</span>
                        </Label>
                        <Input
                            type="number"
                            min="0"
                            step="0.01"
                            value={draftLine.quantity}
                            onChange={(event) =>
                                updateDraftLine({
                                    quantity: event.target.value,
                                })
                            }
                        />
                        <InputError
                            message={
                                errors.draft_quantity ||
                                errors['items.0.quantity']
                            }
                        />
                    </div>

                    <div>
                        <Label>Discount</Label>
                        <Input
                            type="number"
                            min="0"
                            step="0.01"
                            value={draftLine.discount}
                            onChange={(event) =>
                                updateDraftLine({
                                    discount: event.target.value,
                                })
                            }
                        />
                        <InputError
                            message={
                                errors.draft_discount ||
                                errors['items.0.discount']
                            }
                        />
                    </div>

                    <div>
                        <Label>Amount</Label>
                        <Input
                            value={
                                draftLine.product_id && draftLine.quantity
                                    ? draftTotal.toFixed(2)
                                    : ''
                            }
                            readOnly
                            className="bg-gray-100 dark:bg-gray-600"
                        />
                    </div>

                    <div className="flex flex-col justify-end">
                        <Button type="button" onClick={addToCart}>
                            <Plus className="h-4 w-4" />
                            Add to Cart
                        </Button>
                    </div>
                </div>

                <InputError message={errors.items || nestedItemError} />

                <div
                    className="grid grid-cols-1 gap-4 md:grid-cols-3"
                    data-payment-method={data.payment_type}
                >
                    <div>
                        <Label>Payment Method</Label>
                        <Select
                            value={data.payment_type}
                            onValueChange={(value) =>
                                setData((current) => ({
                                    ...current,
                                    payment_type: value,
                                    to_account_id: '',
                                }))
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select payment method" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="Cash">Cash</SelectItem>
                                <SelectItem value="Bank">Bank</SelectItem>
                                <SelectItem value="Mobile Bank">
                                    Mobile Bank
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.payment_type} />
                    </div>

                    <div>
                        <Label>To Account</Label>
                        <Select
                            value={data.to_account_id}
                            onValueChange={(value) => {
                                const account = accounts.find(
                                    (item) => item.id.toString() === value,
                                );
                                setData((current) => ({
                                    ...current,
                                    to_account_id: value,
                                    account_no:
                                        account?.ac_number ||
                                        current.account_no,
                                }));
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select payment account" />
                            </SelectTrigger>
                            <SelectContent>
                                {getFilteredAccounts(data.payment_type).map(
                                    (account) => (
                                        <SelectItem
                                            key={account.id}
                                            value={account.id.toString()}
                                        >
                                            {account.name}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.to_account_id} />
                    </div>

                    <div>
                        <Label>
                            Paid Amount{' '}
                            <span className="text-red-500">*</span>
                        </Label>
                        <Input
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.paid_amount}
                            onChange={(event) =>
                                setData((current) => ({
                                    ...current,
                                    paid_amount: event.target.value,
                                }))
                            }
                            placeholder={cartTotal.toFixed(2)}
                        />
                        <InputError message={errors.paid_amount} />
                    </div>

                    {data.payment_type === 'Bank' && (
                        <>
                            <div>
                                <Label>Bank Type</Label>
                                <Select
                                    value={data.bank_type}
                                    onValueChange={(value) =>
                                        setData((current) => ({
                                            ...current,
                                            bank_type: value,
                                        }))
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Cheque">
                                            Cheque
                                        </SelectItem>
                                        <SelectItem value="Cash Deposit">
                                            Cash Deposit
                                        </SelectItem>
                                        <SelectItem value="Online">
                                            Online
                                        </SelectItem>
                                        <SelectItem value="CHT">CHT</SelectItem>
                                        <SelectItem value="RTGS">
                                            RTGS
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.bank_type} />
                            </div>

                            <div>
                                <Label>Bank Name</Label>
                                <Input
                                    value={data.bank_name}
                                    onChange={(event) =>
                                        setData((current) => ({
                                            ...current,
                                            bank_name: event.target.value,
                                        }))
                                    }
                                />
                                <InputError message={errors.bank_name} />
                            </div>

                            {data.bank_type === 'Cheque' && (
                                <>
                                    <div>
                                        <Label>Cheque No</Label>
                                        <Input
                                            value={data.cheque_no}
                                            onChange={(event) =>
                                                setData((current) => ({
                                                    ...current,
                                                    cheque_no:
                                                        event.target.value,
                                                }))
                                            }
                                        />
                                        <InputError
                                            message={errors.cheque_no}
                                        />
                                    </div>
                                    <div>
                                        <Label>Cheque Date</Label>
                                        <Input
                                            type="date"
                                            value={data.cheque_date}
                                            onChange={(event) =>
                                                setData((current) => ({
                                                    ...current,
                                                    cheque_date:
                                                        event.target.value,
                                                }))
                                            }
                                        />
                                        <InputError
                                            message={errors.cheque_date}
                                        />
                                    </div>
                                </>
                            )}
                        </>
                    )}

                    {data.payment_type === 'Mobile Bank' && (
                        <>
                            <div>
                                <Label>Mobile Bank</Label>
                                <Select
                                    value={data.mobile_bank}
                                    onValueChange={(value) =>
                                        setData((current) => ({
                                            ...current,
                                            mobile_bank: value,
                                        }))
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select mobile bank" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="bKash">
                                            bKash
                                        </SelectItem>
                                        <SelectItem value="Nagad">
                                            Nagad
                                        </SelectItem>
                                        <SelectItem value="Rocket">
                                            Rocket
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.mobile_bank} />
                            </div>

                            <div>
                                <Label>Payment Mobile Number</Label>
                                <Input
                                    value={data.payment_mobile_number}
                                    onChange={(event) =>
                                        setData((current) => ({
                                            ...current,
                                            payment_mobile_number:
                                                event.target.value,
                                        }))
                                    }
                                />
                                <InputError
                                    message={errors.payment_mobile_number}
                                />
                            </div>
                        </>
                    )}
                </div>

                <div>
                    <Label>Remarks</Label>
                    <Input
                        value={data.remarks}
                        onChange={(event) =>
                            setData((current) => ({
                                ...current,
                                remarks: event.target.value,
                            }))
                        }
                        placeholder="Enter any remarks"
                    />
                    <InputError message={errors.remarks} />
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full border border-gray-300 dark:border-gray-600">
                        <thead className="bg-gray-100 dark:bg-gray-700">
                            <tr>
                                <th className="p-2 text-left text-sm font-medium">
                                    SL
                                </th>
                                <th className="p-2 text-left text-sm font-medium">
                                    Product
                                </th>
                                <th className="p-2 text-right text-sm font-medium">
                                    Price
                                </th>
                                <th className="p-2 text-right text-sm font-medium">
                                    Quantity
                                </th>
                                <th className="p-2 text-right text-sm font-medium">
                                    Discount
                                </th>
                                <th className="p-2 text-right text-sm font-medium">
                                    Total
                                </th>
                                <th className="p-2 text-left text-sm font-medium">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {cart.length > 0 ? (
                                cart.map((line, index) => {
                                    const product = productsById.get(
                                        line.product_id,
                                    );

                                    return (
                                        <tr
                                            key={line.key}
                                            className="border-t dark:border-gray-600"
                                        >
                                            <td className="p-2 text-sm">
                                                {index + 1}
                                            </td>
                                            <td className="p-2 text-sm">
                                                {product?.product_name || 'N/A'}
                                            </td>
                                            <td className="p-2 text-right text-sm">
                                                {(
                                                    product?.sales_price || 0
                                                ).toFixed(2)}
                                            </td>
                                            <td className="p-2 text-right text-sm">
                                                {line.quantity}
                                            </td>
                                            <td className="p-2 text-right text-sm">
                                                {(
                                                    parseFloat(line.discount) ||
                                                    0
                                                ).toFixed(2)}
                                            </td>
                                            <td className="p-2 text-right text-sm">
                                                {lineTotal(line).toFixed(2)}
                                            </td>
                                            <td className="p-2">
                                                <div className="flex gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            editCartLine(line)
                                                        }
                                                        aria-label={`Edit ${product?.product_name || 'product'}`}
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            removeCartLine(
                                                                line.key,
                                                            )
                                                        }
                                                        aria-label={`Remove ${product?.product_name || 'product'}`}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            ) : (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="p-6 text-center text-sm text-gray-500"
                                    >
                                        No products added
                                    </td>
                                </tr>
                            )}
                        </tbody>
                        <tfoot>
                            <tr className="border-t bg-gray-50 font-medium dark:border-gray-600 dark:bg-gray-700">
                                <td
                                    colSpan={5}
                                    className="p-2 text-right text-sm"
                                >
                                    Grand Total
                                </td>
                                <td className="p-2 text-right text-sm">
                                    {cartTotal.toFixed(2)}
                                </td>
                                <td />
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </FormModal>
    );
}
