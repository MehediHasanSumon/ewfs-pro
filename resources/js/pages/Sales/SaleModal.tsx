import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
    category?: { id: number; name: string } | null;
    unit?: { id?: number; name: string } | null;
    is_inventory_item?: boolean;
    sales_price: number | null;
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
    vehicles: {
        id: number;
        vehicle_number: string;
        vehicle_name?: string | null;
    }[];
}

interface SessionState {
    sale_date: string;
    shift_id: string;
}

interface SaleRowState {
    customer_id: string;
    customer_name: string;
    customer_mobile: string;
    vehicle_id: string;
    vehicle_no: string;
    product_id: string;
    quantity: string;
    unit_price: number;
    discount: string;
    payment_type: string;
    to_account_id: string;
    bank_type: string;
    bank_name: string;
    branch_name: string;
    account_no: string;
    cheque_no: string;
    cheque_date: string;
    mobile_bank: string;
    payment_mobile_number: string;
    remarks: string;
    memo_no: string;
}

interface CartRow extends SaleRowState {
    key: string;
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

const emptyRow = (): SaleRowState => ({
    customer_id: '',
    customer_name: '',
    customer_mobile: '',
    vehicle_id: '',
    vehicle_no: '',
    product_id: '',
    quantity: '',
    unit_price: 0,
    discount: '',
    payment_type: 'Cash',
    to_account_id: '',
    bank_type: '',
    bank_name: '',
    branch_name: '',
    account_no: '',
    cheque_no: '',
    cheque_date: '',
    mobile_bank: '',
    payment_mobile_number: '',
    remarks: '',
    memo_no: '',
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

const rowAmount = (row: SaleRowState) =>
    Math.max(
        0,
        row.unit_price * (parseFloat(row.quantity) || 0) -
            (parseFloat(row.discount) || 0),
    );

const rowKey = () => `${Date.now()}-${Math.random()}`;

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
    const initialSession = (): SessionState => ({
        sale_date: initialSaleDate || '',
        shift_id: initialShiftId || '',
    });
    const [session, setSession] = useState<SessionState>(initialSession);
    const [draft, setDraft] = useState<SaleRowState>(emptyRow);
    const [cart, setCart] = useState<CartRow[]>([]);
    const [editingRowKey, setEditingRowKey] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [availableShifts, setAvailableShifts] = useState<Shift[]>(shifts);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [lookupError, setLookupError] = useState('');
    const [lookupLoading, setLookupLoading] = useState(false);
    const latestLookupRef = useRef('');
    const resolvedLookupRef = useRef('');

    const productsById = useMemo(
        () =>
            new Map(
                products.map((product) => [product.id.toString(), product]),
            ),
        [products],
    );
    const productOptions = useMemo(
        () =>
            products.map((product) => ({
                value: product.id.toString(),
                label: product.product_name,
                subtitle: [product.product_code, product.category?.name]
                    .filter(Boolean)
                    .join(' - '),
            })),
        [products],
    );
    const selectedProduct = productsById.get(draft.product_id);
    const draftAmount = rowAmount({
        ...draft,
        unit_price: selectedProduct?.sales_price ?? draft.unit_price,
    });
    const grandTotal = useMemo(
        () => cart.reduce((total, row) => total + rowAmount(row), 0),
        [cart],
    );
    const vehicleNumbers = useMemo(
        () =>
            Array.from(
                new Set(vehicles.map((vehicle) => vehicle.vehicle_number)),
            ).sort(),
        [vehicles],
    );
    const firstRowErrorEntry = Object.entries(errors).find(([key]) =>
        key.startsWith('rows.'),
    );
    const firstRowError = firstRowErrorEntry
        ? `Cart row ${
              Number(firstRowErrorEntry[0].split('.')[1] || 0) + 1
          }: ${firstRowErrorEntry[1]}`
        : undefined;

    const getAvailableShifts = (selectedDate: string) => {
        if (!selectedDate) {
            return shifts;
        }

        const closedShiftIds = closedShifts
            .filter((closing) => closing.close_date === selectedDate)
            .map((closing) => closing.shift_id);

        return shifts.filter((shift) => !closedShiftIds.includes(shift.id));
    };

    const getFilteredAccounts = (paymentType: string) => {
        if (paymentType === 'Cash') {
            return (
                groupedAccounts['Cash in hand'] || groupedAccounts.Cash || []
            );
        }

        if (paymentType === 'Bank') {
            return (
                groupedAccounts['Bank Account'] || groupedAccounts.Bank || []
            );
        }

        if (paymentType === 'Mobile Bank') {
            return groupedAccounts['Mobile Bank'] || [];
        }

        return [];
    };

    const updateDraft = (changes: Partial<SaleRowState>) => {
        setDraft((current) => ({ ...current, ...changes }));
    };

    const handleMobileChange = (value: string) => {
        latestLookupRef.current = normalizeMobile(value);
        resolvedLookupRef.current = '';
        setLookupError('');
        setDraft((current) => ({
            ...current,
            customer_mobile: value,
            customer_id: '',
            customer_name: current.customer_id ? '' : current.customer_name,
            vehicle_id: '',
            vehicle_no: '',
        }));
    };

    const handleVehicleChange = (vehicleNumber: string) => {
        const vehicle = vehicles.find(
            (item) => item.vehicle_number === vehicleNumber,
        );

        updateDraft({
            vehicle_id: vehicle?.id.toString() || '',
            vehicle_no: vehicleNumber,
        });
    };

    const clearDraft = () => {
        setDraft(emptyRow());
        setEditingRowKey(null);
        setLookupError('');
        latestLookupRef.current = '';
        resolvedLookupRef.current = '';
    };

    const validateDraft = () => {
        const nextErrors: Record<string, string> = {};
        const product = productsById.get(draft.product_id);
        const quantity = parseFloat(draft.quantity);

        if (!draft.customer_id && !draft.customer_name.trim()) {
            nextErrors.draft_customer_name =
                'Customer name is required for a walk-in customer.';
        }

        if (!draft.customer_mobile.trim()) {
            nextErrors.draft_customer_mobile = 'Mobile number is required.';
        }

        if (!product) {
            nextErrors.draft_product_id = 'Select a product.';
        } else if (product.sales_price === null || product.sales_price <= 0) {
            nextErrors.draft_product_id =
                'The selected product has no active sales price.';
        }

        if (!Number.isFinite(quantity) || quantity <= 0) {
            nextErrors.draft_quantity = 'Quantity must be greater than zero.';
        }

        if (!draft.payment_type) {
            nextErrors.draft_payment_type = 'Select a payment method.';
        }

        if (!draft.to_account_id) {
            nextErrors.draft_to_account_id = 'Select a payment account.';
        }

        if (draft.payment_type === 'Bank') {
            if (!draft.bank_type) {
                nextErrors.draft_bank_type =
                    'Bank transaction type is required.';
            }
            if (!draft.bank_name.trim()) {
                nextErrors.draft_bank_name = 'Bank name is required.';
            }
            if (draft.bank_type === 'Cheque') {
                if (!draft.cheque_no.trim()) {
                    nextErrors.draft_cheque_no = 'Cheque number is required.';
                }
                if (!draft.cheque_date) {
                    nextErrors.draft_cheque_date = 'Cheque date is required.';
                }
            }
        }

        if (draft.payment_type === 'Mobile Bank' && !draft.mobile_bank) {
            nextErrors.draft_mobile_bank = 'Mobile bank name is required.';
        }

        setErrors(nextErrors);

        return Object.keys(nextErrors).length === 0 && product ? product : null;
    };

    const addOrUpdateCartRow = () => {
        const product = validateDraft();

        if (!product || product.sales_price === null) {
            return;
        }

        const nextRow: CartRow = {
            ...draft,
            key: editingRowKey || rowKey(),
            unit_price: product.sales_price,
        };

        setCart((current) => {
            let rows = editingRowKey
                ? current.map((row) =>
                      row.key === editingRowKey ? nextRow : row,
                  )
                : [...current, nextRow];

            if (editingSale) {
                const shared = {
                    customer_id: nextRow.customer_id,
                    customer_name: nextRow.customer_name,
                    customer_mobile: nextRow.customer_mobile,
                    vehicle_id: nextRow.vehicle_id,
                    vehicle_no: nextRow.vehicle_no,
                    payment_type: nextRow.payment_type,
                    to_account_id: nextRow.to_account_id,
                    bank_type: nextRow.bank_type,
                    bank_name: nextRow.bank_name,
                    branch_name: nextRow.branch_name,
                    account_no: nextRow.account_no,
                    cheque_no: nextRow.cheque_no,
                    cheque_date: nextRow.cheque_date,
                    mobile_bank: nextRow.mobile_bank,
                    payment_mobile_number: nextRow.payment_mobile_number,
                    remarks: nextRow.remarks,
                    memo_no: nextRow.memo_no,
                };
                rows = rows.map((row) => ({ ...row, ...shared }));
            }

            return rows;
        });
        clearDraft();
        setErrors({});
    };

    const editCartRow = (row: CartRow) => {
        setDraft({ ...row });
        setEditingRowKey(row.key);
        setErrors({});
        latestLookupRef.current = normalizeMobile(row.customer_mobile);
        resolvedLookupRef.current = latestLookupRef.current;
    };

    const removeCartRow = (key: string) => {
        setCart((current) => current.filter((row) => row.key !== key));

        if (editingRowKey === key) {
            clearDraft();
        }
    };

    const draftHasContent = () =>
        Boolean(
            draft.customer_name ||
            draft.customer_mobile ||
            draft.vehicle_no ||
            draft.product_id ||
            draft.quantity ||
            draft.to_account_id ||
            draft.remarks,
        );

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        setErrors({});

        if (!session.sale_date) {
            setErrors({ sale_date: 'Sale date is required.' });
            return;
        }

        if (!session.shift_id) {
            setErrors({ shift_id: 'Shift is required.' });
            return;
        }

        if (draftHasContent()) {
            setErrors({
                draft: 'Add or update the current sale row before submitting.',
            });
            return;
        }

        if (cart.length === 0) {
            setErrors({ rows: 'Add at least one sale row to the cart.' });
            return;
        }

        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onError: (validationErrors: Record<string, string>) =>
                setErrors(validationErrors),
            onFinish: () => setProcessing(false),
            onSuccess: () => {
                onClose();
                onSuccess?.();
            },
        };

        if (editingSale) {
            const row = cart[0];
            router.put(
                `/sales/${editingSale.id}`,
                {
                    ...session,
                    customer_id: row.customer_id || null,
                    customer_name: row.customer_name,
                    customer_mobile: row.customer_mobile,
                    vehicle_id: row.vehicle_id || null,
                    vehicle_no: row.vehicle_no || null,
                    memo_no: row.memo_no || null,
                    payment_type: row.payment_type,
                    to_account_id: row.to_account_id,
                    bank_type: row.bank_type || null,
                    bank_name: row.bank_name || null,
                    branch_name: row.branch_name || null,
                    account_no: row.account_no || null,
                    cheque_no: row.cheque_no || null,
                    cheque_date: row.cheque_date || null,
                    mobile_bank: row.mobile_bank || null,
                    payment_mobile_number: row.payment_mobile_number || null,
                    remarks: row.remarks || null,
                    items: cart.map((item) => ({
                        product_id: item.product_id,
                        quantity: item.quantity,
                        discount: item.discount || 0,
                        remarks: null,
                    })),
                },
                options,
            );
            return;
        }

        router.post(
            '/sales/batch',
            {
                ...session,
                rows: cart.map((row) => ({
                    customer_id: row.customer_id || null,
                    customer_name: row.customer_name,
                    customer_mobile: row.customer_mobile,
                    vehicle_id: row.vehicle_id || null,
                    vehicle_no: row.vehicle_no || null,
                    product_id: row.product_id,
                    quantity: row.quantity,
                    discount: row.discount || 0,
                    payment_type: row.payment_type,
                    to_account_id: row.to_account_id,
                    bank_type: row.bank_type || null,
                    bank_name: row.bank_name || null,
                    branch_name: row.branch_name || null,
                    account_no: row.account_no || null,
                    cheque_no: row.cheque_no || null,
                    cheque_date: row.cheque_date || null,
                    mobile_bank: row.mobile_bank || null,
                    payment_mobile_number: row.payment_mobile_number || null,
                    remarks: row.remarks || null,
                })),
            },
            options,
        );
    };

    useEffect(() => {
        if (!isOpen || !draft.customer_mobile.trim()) {
            return;
        }

        const controller = new AbortController();
        const mobile = draft.customer_mobile.trim();
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
                    setLookupError(
                        payload.errors?.mobile?.[0] ||
                            payload.message ||
                            'Unable to look up this mobile number.',
                    );
                    return;
                }

                const customer = payload.data as CustomerLookup | null;
                resolvedLookupRef.current = lookupKey;

                if (!customer) {
                    setDraft((current) =>
                        normalizeMobile(current.customer_mobile) === lookupKey
                            ? {
                                  ...current,
                                  customer_id: '',
                                  vehicle_id: '',
                                  vehicle_no: '',
                              }
                            : current,
                    );
                    return;
                }

                setDraft((current) => {
                    if (
                        normalizeMobile(current.customer_mobile) !== lookupKey
                    ) {
                        return current;
                    }

                    const firstVehicle = customer.vehicles[0];

                    return {
                        ...current,
                        customer_id: customer.id.toString(),
                        customer_name: customer.name,
                        customer_mobile: customer.mobile,
                        vehicle_id: firstVehicle?.id.toString() || '',
                        vehicle_no: firstVehicle?.vehicle_number || '',
                    };
                });
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
    }, [draft.customer_mobile, isOpen]);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        setErrors({});
        setDraft(emptyRow());
        setEditingRowKey(null);
        setCart([]);
        latestLookupRef.current = '';
        resolvedLookupRef.current = '';

        if (!editingSale) {
            const nextSession = initialSession();
            setSession(nextSession);
            setAvailableShifts(getAvailableShifts(nextSession.sale_date));
            return;
        }

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
                const paymentType = paymentLabel(payment.payment_type);
                const common: Omit<
                    SaleRowState,
                    'product_id' | 'quantity' | 'unit_price' | 'discount'
                > = {
                    customer_id: sale.customer_id?.toString() || '',
                    customer_name: sale.customer_name || '',
                    customer_mobile: sale.customer_mobile || '',
                    vehicle_id: sale.vehicle_id?.toString() || '',
                    vehicle_no: sale.vehicle_no || '',
                    payment_type: paymentType,
                    to_account_id: payment.to_account_id?.toString() || '',
                    bank_type: payment.bank_type || '',
                    bank_name: payment.bank_name || '',
                    branch_name: payment.branch_name || '',
                    account_no: payment.account_no || '',
                    cheque_no: payment.cheque_no || '',
                    cheque_date: payment.cheque_date || '',
                    mobile_bank: payment.mobile_bank || '',
                    payment_mobile_number: payment.payment_mobile_number || '',
                    remarks: sale.remarks || '',
                    memo_no: sale.memo_no || '',
                };
                const rows: CartRow[] = (sale.items || []).map(
                    (item: {
                        id: number;
                        product_id: number;
                        quantity: number;
                        unit_price: number;
                        discount: number;
                    }) => ({
                        ...common,
                        key: `sale-item-${item.id}`,
                        product_id: item.product_id.toString(),
                        quantity: item.quantity.toString(),
                        unit_price:
                            productsById.get(item.product_id.toString())
                                ?.sales_price ?? item.unit_price,
                        discount: item.discount ? item.discount.toString() : '',
                    }),
                );
                const nextSession = {
                    sale_date: sale.sale_date || '',
                    shift_id: sale.shift_id?.toString() || '',
                };

                setSession(nextSession);
                setAvailableShifts(getAvailableShifts(nextSession.sale_date));
                setCart(rows);
            })
            .catch((error) => {
                if ((error as Error).name !== 'AbortError') {
                    setErrors({ sale: (error as Error).message });
                }
            });

        return () => controller.abort();
        // Existing lookup collections are stable for the lifetime of the modal.
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
            className="w-[calc(100vw-2rem)] max-w-[90vw] max-md:max-w-[calc(100vw-2rem)]"
        >
            <div className="space-y-4">
                <InputError message={errors.sale || errors.rows} />
                <InputError message={errors.draft || firstRowError} />

                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <Label>
                            Sale Date <span className="text-red-500">*</span>
                        </Label>
                        <Input
                            name="sale_date"
                            type="date"
                            value={session.sale_date}
                            onChange={(event) => {
                                const saleDate = event.target.value;
                                setSession({
                                    sale_date: saleDate,
                                    shift_id: '',
                                });
                                setAvailableShifts(
                                    getAvailableShifts(saleDate),
                                );
                            }}
                        />
                        <InputError message={errors.sale_date} />
                    </div>

                    <div>
                        <Label>
                            Shift <span className="text-red-500">*</span>
                        </Label>
                        <Select
                            value={session.shift_id}
                            onValueChange={(shiftId) =>
                                setSession((current) => ({
                                    ...current,
                                    shift_id: shiftId,
                                }))
                            }
                            disabled={!session.sale_date}
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
                            value={
                                editingSale
                                    ? draft.memo_no || cart[0]?.memo_no || ''
                                    : 'Auto-generated on save'
                            }
                            readOnly
                            className="bg-gray-100 dark:bg-gray-600"
                        />
                    </div>

                    <div>
                        <Label>
                            Customer Name{' '}
                            <span className="text-red-500">*</span>
                        </Label>
                        <Input
                            name="customer_name"
                            value={draft.customer_name}
                            onChange={(event) =>
                                updateDraft({
                                    customer_name: event.target.value,
                                })
                            }
                            placeholder="Enter customer name"
                        />
                        <InputError message={errors.draft_customer_name} />
                    </div>

                    <div>
                        <Label>
                            Mobile Number{' '}
                            <span className="text-red-500">*</span>
                        </Label>
                        <div className="relative">
                            <Input
                                name="customer_mobile"
                                value={draft.customer_mobile}
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
                            message={
                                errors.draft_customer_mobile || lookupError
                            }
                        />
                    </div>

                    <div>
                        <Label>Vehicle</Label>
                        <Combobox
                            options={vehicleNumbers}
                            value={draft.vehicle_no}
                            onValueChange={handleVehicleChange}
                            placeholder="Type vehicle number"
                        />
                    </div>

                    <div>
                        <Label>
                            Product <span className="text-red-500">*</span>
                        </Label>
                        <SearchableSelect
                            options={productOptions}
                            value={draft.product_id}
                            onValueChange={(productId) => {
                                const product = productsById.get(productId);
                                updateDraft({
                                    product_id: productId,
                                    unit_price: product?.sales_price || 0,
                                });
                            }}
                            placeholder="Select product"
                            searchPlaceholder="Search products..."
                        />
                        <InputError message={errors.draft_product_id} />
                    </div>

                    <div>
                        <Label>Sales Price</Label>
                        <Input
                            value={
                                selectedProduct?.sales_price != null
                                    ? selectedProduct.sales_price.toFixed(2)
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
                            name="quantity"
                            type="number"
                            min="0"
                            step="0.01"
                            value={draft.quantity}
                            onChange={(event) =>
                                updateDraft({ quantity: event.target.value })
                            }
                        />
                        <InputError message={errors.draft_quantity} />
                    </div>

                    <div>
                        <Label>Amount</Label>
                        <Input
                            value={
                                draft.product_id && draft.quantity
                                    ? draftAmount.toFixed(2)
                                    : ''
                            }
                            readOnly
                            className="bg-gray-100 dark:bg-gray-600"
                        />
                    </div>

                    <div>
                        <Label>Payment Method</Label>
                        <Select
                            value={draft.payment_type}
                            onValueChange={(paymentType) =>
                                updateDraft({
                                    payment_type: paymentType,
                                    to_account_id: '',
                                    account_no: '',
                                    bank_type: '',
                                    bank_name: '',
                                    cheque_no: '',
                                    cheque_date: '',
                                    mobile_bank: '',
                                    payment_mobile_number: '',
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select payment method" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="Cash">Cash</SelectItem>
                                <SelectItem value="Bank">Bank</SelectItem>
                                <SelectItem value="Mobile Bank">
                                    Mobile Banking
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.draft_payment_type} />
                    </div>

                    <div>
                        <Label>To Account</Label>
                        <Select
                            value={draft.to_account_id}
                            onValueChange={(accountId) => {
                                const account = accounts.find(
                                    (item) => item.id.toString() === accountId,
                                );
                                updateDraft({
                                    to_account_id: accountId,
                                    account_no: account?.ac_number || '',
                                });
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select payment account" />
                            </SelectTrigger>
                            <SelectContent>
                                {getFilteredAccounts(draft.payment_type).map(
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
                        <InputError message={errors.draft_to_account_id} />
                    </div>
                </div>

                {draft.payment_type === 'Bank' && (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div>
                            <Label>Bank Type</Label>
                            <Select
                                value={draft.bank_type}
                                onValueChange={(bankType) =>
                                    updateDraft({
                                        bank_type: bankType,
                                        cheque_no: '',
                                        cheque_date: '',
                                    })
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
                                    <SelectItem value="RTGS">RTGS</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.draft_bank_type} />
                        </div>

                        <div>
                            <Label>Bank Name</Label>
                            <Input
                                value={draft.bank_name}
                                onChange={(event) =>
                                    updateDraft({
                                        bank_name: event.target.value,
                                    })
                                }
                            />
                            <InputError message={errors.draft_bank_name} />
                        </div>

                        {draft.bank_type === 'Cheque' && (
                            <>
                                <div>
                                    <Label>Cheque No</Label>
                                    <Input
                                        value={draft.cheque_no}
                                        onChange={(event) =>
                                            updateDraft({
                                                cheque_no: event.target.value,
                                            })
                                        }
                                    />
                                    <InputError
                                        message={errors.draft_cheque_no}
                                    />
                                </div>
                                <div>
                                    <Label>Cheque Date</Label>
                                    <Input
                                        type="date"
                                        value={draft.cheque_date}
                                        onChange={(event) =>
                                            updateDraft({
                                                cheque_date: event.target.value,
                                            })
                                        }
                                    />
                                    <InputError
                                        message={errors.draft_cheque_date}
                                    />
                                </div>
                            </>
                        )}
                    </div>
                )}

                {draft.payment_type === 'Mobile Bank' && (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div>
                            <Label>Mobile Bank</Label>
                            <Select
                                value={draft.mobile_bank}
                                onValueChange={(mobileBank) =>
                                    updateDraft({
                                        mobile_bank: mobileBank,
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select mobile bank" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="bKash">bKash</SelectItem>
                                    <SelectItem value="Nagad">Nagad</SelectItem>
                                    <SelectItem value="Rocket">
                                        Rocket
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.draft_mobile_bank} />
                        </div>

                        <div>
                            <Label>Payment Mobile Number</Label>
                            <Input
                                value={draft.payment_mobile_number}
                                onChange={(event) =>
                                    updateDraft({
                                        payment_mobile_number:
                                            event.target.value,
                                    })
                                }
                            />
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div className="md:col-span-3">
                        <Label>Remarks</Label>
                        <Input
                            value={draft.remarks}
                            onChange={(event) =>
                                updateDraft({ remarks: event.target.value })
                            }
                            placeholder="Enter any remarks"
                        />
                    </div>

                    <div className="flex flex-col justify-end">
                        <Button type="button" onClick={addOrUpdateCartRow}>
                            {editingRowKey ? (
                                <Edit className="h-4 w-4" />
                            ) : (
                                <Plus className="h-4 w-4" />
                            )}
                            {editingRowKey ? 'Update Cart Row' : 'Add to Cart'}
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full border border-gray-300 dark:border-gray-600">
                        <thead className="bg-gray-100 dark:bg-gray-700">
                            <tr>
                                {[
                                    'SL',
                                    'Memo No',
                                    'Customer',
                                    'Mobile',
                                    'Vehicle',
                                    'Product',
                                    'Qty',
                                    'Unit Price',
                                    'Amount',
                                    'Payment Method',
                                    'Account',
                                    'Remarks',
                                    'Action',
                                ].map((heading) => (
                                    <th
                                        key={heading}
                                        className="p-2 text-left text-sm font-medium whitespace-nowrap"
                                    >
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {cart.length > 0 ? (
                                cart.map((row, index) => {
                                    const product = productsById.get(
                                        row.product_id,
                                    );
                                    const account = accounts.find(
                                        (item) =>
                                            item.id.toString() ===
                                            row.to_account_id,
                                    );

                                    return (
                                        <tr
                                            key={row.key}
                                            className="border-t dark:border-gray-600"
                                        >
                                            <td className="p-2 text-sm">
                                                {index + 1}
                                            </td>
                                            <td className="p-2 text-sm whitespace-nowrap">
                                                {row.memo_no || 'Auto on save'}
                                            </td>
                                            <td className="p-2 text-sm">
                                                {row.customer_name ||
                                                    'Walk-in Customer'}
                                            </td>
                                            <td className="p-2 text-sm whitespace-nowrap">
                                                {row.customer_mobile}
                                            </td>
                                            <td className="p-2 text-sm">
                                                {row.vehicle_no || '-'}
                                            </td>
                                            <td className="p-2 text-sm">
                                                {product?.product_name || 'N/A'}
                                            </td>
                                            <td className="p-2 text-right text-sm">
                                                {row.quantity}
                                            </td>
                                            <td className="p-2 text-right text-sm">
                                                {row.unit_price.toFixed(2)}
                                            </td>
                                            <td className="p-2 text-right text-sm">
                                                {rowAmount(row).toFixed(2)}
                                            </td>
                                            <td className="p-2 text-sm whitespace-nowrap">
                                                {row.payment_type}
                                            </td>
                                            <td className="p-2 text-sm">
                                                {account?.name || 'N/A'}
                                            </td>
                                            <td className="max-w-48 truncate p-2 text-sm">
                                                {row.remarks || '-'}
                                            </td>
                                            <td className="p-2">
                                                <div className="flex gap-1">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            editCartRow(row)
                                                        }
                                                        aria-label={`Edit sale row ${index + 1}`}
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            removeCartRow(
                                                                row.key,
                                                            )
                                                        }
                                                        aria-label={`Delete sale row ${index + 1}`}
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
                                        colSpan={13}
                                        className="p-6 text-center text-sm text-gray-500"
                                    >
                                        No sale rows added
                                    </td>
                                </tr>
                            )}
                        </tbody>
                        <tfoot>
                            <tr className="border-t bg-gray-50 font-medium dark:border-gray-600 dark:bg-gray-700">
                                <td
                                    colSpan={8}
                                    className="p-2 text-right text-sm"
                                >
                                    Grand Total
                                </td>
                                <td className="p-2 text-right text-sm">
                                    {grandTotal.toFixed(2)}
                                </td>
                                <td colSpan={4} />
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </FormModal>
    );
}
