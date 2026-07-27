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
import { router } from '@inertiajs/react';
import { Edit, Plus, Trash2 } from 'lucide-react';
import { useCallback, useState } from 'react';

export interface PaymentVoucher {
    id: number;
    voucher_type: string;
    date: string;
    from_account: { id: number; name: string };
    to_account: { id: number; name: string };
    shift?: { id: number; name: string };
    transaction?: { amount: number; payment_type: string };
    voucher_category?: { id: number; name: string };
    payment_sub_type?: { id: number; name: string };
    description?: string;
    remarks: string;
    created_at: string;
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

interface Account {
    id: number;
    name: string;
    ac_number: string;
}

interface Shift {
    id: number;
    name: string;
}

interface PaymentVoucherModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSuccess?: () => void;
    editingVoucher: PaymentVoucher | null;
    accounts: Account[];
    groupedAccounts: Record<string, Account[]>;
    shifts: Shift[];
    closedShifts: Array<{close_date: string; shift_id: number}>;
    voucherCategories: VoucherCategory[];
    paymentSubTypes: PaymentSubType[];
    initialDate?: string;
    initialShiftId?: string;
}

const buildEmptyVoucher = () => ({
    voucher_category_id: '',
    payment_sub_type_id: '',
    from_account_id: '',
    to_account_id: '',
    amount: '',
    payment_method: 'Cash',
    bank_type: '',
    bank_name: '',
    cheque_no: '',
    cheque_date: '',
    account_no: '',
    branch_name: '',
    mobile_bank: '',
    mobile_number: '',
    description: '',
    remarks: '',
});

export function PaymentVoucherModal({
    isOpen,
    onClose,
    onSuccess,
    editingVoucher,
    accounts,
    groupedAccounts,
    shifts,
    closedShifts,
    voucherCategories,
    paymentSubTypes,
    initialDate,
    initialShiftId,
}: PaymentVoucherModalProps) {
    const buildInitialState = () => ({
        date: initialDate || '',
        shift_id: initialShiftId || '',
        vouchers: [buildEmptyVoucher()],
    });

    const getInitialData = () => {
        if (!editingVoucher) return buildInitialState();
        return {
            date: editingVoucher.date?.split('T')[0] || '',
            shift_id: editingVoucher.shift?.id?.toString() || '',
            vouchers: [
                {
                    voucher_category_id:
                        editingVoucher.voucher_category?.id?.toString() || '',
                    payment_sub_type_id:
                        editingVoucher.payment_sub_type?.id?.toString() || '',
                    from_account_id:
                        editingVoucher.from_account?.id?.toString() || '',
                    to_account_id:
                        editingVoucher.to_account?.id?.toString() || '',
                    amount: editingVoucher.transaction?.amount?.toString() || '',
                    payment_method: editingVoucher.transaction?.payment_type
                        ? editingVoucher.transaction.payment_type
                              .charAt(0)
                              .toUpperCase() +
                          editingVoucher.transaction.payment_type.slice(1)
                        : 'Cash',
                    description: editingVoucher.description || '',
                    bank_type: '',
                    bank_name: '',
                    cheque_no: '',
                    cheque_date: '',
                    account_no: '',
                    branch_name: '',
                    mobile_bank: '',
                    mobile_number: '',
                    remarks: editingVoucher.remarks || '',
                },
            ],
        };
    };

    const [data, setData] = useState(getInitialData);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const reset = () => {
        setData(buildInitialState());
        setErrors({});
    };

    const setField = (key: 'date' | 'shift_id', value: string) => {
        setData((prev) => ({ ...prev, [key]: value }));
    };

    const getFilteredAccounts = useCallback(
        (paymentMethod: string) => {
            const groupName =
                paymentMethod === 'Cash'
                    ? 'Cash in hand'
                    : paymentMethod === 'Bank'
                      ? 'Bank Account'
                      : paymentMethod === 'Mobile Bank'
                        ? 'Mobile Bank'
                        : 'Other';
            return groupedAccounts[groupName] || [];
        },
        [groupedAccounts],
    );

    const getAvailableShifts = useCallback(() => {
        if (!data.date) return [];

        const selectedDate = data.date;
        const closedShiftIds = closedShifts
            .filter((cs) => cs.close_date === selectedDate)
            .map((cs) => cs.shift_id);

        return shifts.filter((shift) => !closedShiftIds.includes(shift.id));
    }, [data.date, shifts, closedShifts]);

    const updateVoucher = (index: number, field: string, value: string) => {
        setData((prev) => {
            const vouchers = [...prev.vouchers];
            vouchers[index] = { ...vouchers[index], [field]: value };
            if (field === 'payment_method') {
                vouchers[index].from_account_id = '';
            }
            return { ...prev, vouchers };
        });
    };

    const addVoucher = () => {
        const firstVoucher = data.vouchers[0];
        if (
            !firstVoucher.voucher_category_id ||
            !firstVoucher.payment_sub_type_id ||
            !firstVoucher.from_account_id ||
            !firstVoucher.to_account_id ||
            !firstVoucher.amount
        ) {
            alert('Please fill required fields before adding.');
            return;
        }

        setData((prev) => ({
            ...prev,
            vouchers: [buildEmptyVoucher(), ...prev.vouchers],
        }));
    };

    const removeVoucher = (index: number) => {
        setData((prev) => ({
            ...prev,
            vouchers: prev.vouchers.filter((_, i) => i != index),
        }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setErrors({});

        if (editingVoucher) {
            const firstVoucher = data.vouchers[0];
            const payload = {
                date: data.date,
                shift_id: data.shift_id,
                ...firstVoucher,
            };

            setProcessing(true);
            router.put(`/vouchers/payment/${editingVoucher.id}`, payload, {
                onSuccess: () => {
                    onClose();
                    reset();
                    onSuccess?.();
                },
                onError: (errs) => {
                    setErrors(errs as Record<string, string>);
                },
                onFinish: () => {
                    setProcessing(false);
                },
            });
            return;
        }

        const validVouchers = data.vouchers.filter(
            (v) =>
                v.voucher_category_id &&
                v.payment_sub_type_id &&
                v.from_account_id &&
                v.to_account_id &&
                v.amount,
        );

        if (validVouchers.length === 0) {
            alert('Please add at least one voucher.');
            return;
        }

        const payload = {
            date: data.date,
            shift_id: data.shift_id,
            vouchers: validVouchers,
        };

        setProcessing(true);
        router.post('/vouchers/payment', payload, {
            onSuccess: () => {
                onClose();
                reset();
                onSuccess?.();
            },
            onError: (errs) => {
                setErrors(errs as Record<string, string>);
            },
            onFinish: () => {
                setProcessing(false);
            },
        });
    };

    const currentVoucher = data.vouchers[0];

    if (!isOpen) {
        return null;
    }

    return (
        <FormModal
            isOpen={isOpen}
            onClose={onClose}
            title={editingVoucher ? 'Edit Payment Voucher' : 'Create Payment Voucher'}
            onSubmit={handleSubmit}
            processing={processing}
            submitText={editingVoucher ? 'Update' : 'Create'}
            className="max-w-[65vw]"
        >
            <div className="grid grid-cols-4 gap-4">
                <div>
                    <Label htmlFor="date" className="dark:text-gray-200">
                        Date
                    </Label>
                    <Input
                        id="date"
                        type="date"
                        value={data.date}
                        onChange={(e) => setField('date', e.target.value)}
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                    {errors.date && (
                        <span className="text-sm text-red-500">{errors.date}</span>
                    )}
                </div>
                <div>
                    <Label htmlFor="shift_id" className="dark:text-gray-200">
                        Shift
                    </Label>
                    <Select
                        value={data.shift_id}
                        onValueChange={(value) => setField('shift_id', value)}
                        disabled={!data.date}
                    >
                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <SelectValue placeholder="Choose shift" />
                        </SelectTrigger>
                        <SelectContent>
                            {getAvailableShifts().map((shift) => (
                                <SelectItem key={shift.id} value={shift.id.toString()}>
                                    {shift.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {errors.shift_id && (
                        <span className="text-sm text-red-500">{errors.shift_id}</span>
                    )}
                </div>
                <div>
                    <Label className="dark:text-gray-200">Category</Label>
                    <Select
                        value={currentVoucher.voucher_category_id}
                        onValueChange={(value) => {
                            updateVoucher(0, 'voucher_category_id', value);
                            updateVoucher(0, 'payment_sub_type_id', '');
                        }}
                    >
                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <SelectValue placeholder="Choose category" />
                        </SelectTrigger>
                        <SelectContent>
                            {voucherCategories.map((category) => (
                                <SelectItem key={category.id} value={category.id.toString()}>
                                    {category.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {errors['vouchers.0.voucher_category_id'] && (
                        <span className="text-sm text-red-500">{errors['vouchers.0.voucher_category_id']}</span>
                    )}
                </div>
                <div>
                    <Label htmlFor="payment_sub_type_id" className="dark:text-gray-200">
                        Payment Sub Type
                    </Label>
                    <Select
                        value={currentVoucher.payment_sub_type_id}
                        onValueChange={(value) => updateVoucher(0, 'payment_sub_type_id', value)}
                        disabled={!currentVoucher.voucher_category_id}
                    >
                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <SelectValue placeholder="Choose sub type" />
                        </SelectTrigger>
                        <SelectContent>
                            {paymentSubTypes
                                .filter(
                                    (subType) =>
                                        subType.voucher_category_id.toString() ===
                                        currentVoucher.voucher_category_id,
                                )
                                .map((subType) => (
                                    <SelectItem key={subType.id} value={subType.id.toString()}>
                                        {subType.name}
                                    </SelectItem>
                                ))}
                        </SelectContent>
                    </Select>
                    {errors['vouchers.0.payment_sub_type_id'] && (
                        <span className="text-sm text-red-500">{errors['vouchers.0.payment_sub_type_id']}</span>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-4 gap-4">
                <div>
                    <Label htmlFor="payment_method" className="dark:text-gray-200">
                        Payment Method
                    </Label>
                    <Select
                        value={currentVoucher.payment_method}
                        onValueChange={(value) => updateVoucher(0, 'payment_method', value)}
                    >
                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <SelectValue placeholder="Choose payment method" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="Cash">Cash</SelectItem>
                            <SelectItem value="Bank">Bank</SelectItem>
                            <SelectItem value="Mobile Bank">Mobile Bank</SelectItem>
                        </SelectContent>
                    </Select>
                    {errors['vouchers.0.payment_method'] && (
                        <span className="text-sm text-red-500">{errors['vouchers.0.payment_method']}</span>
                    )}
                </div>
                <div>
                    <Label htmlFor="from_account_id" className="dark:text-gray-200">
                        From Account
                    </Label>
                    <Select
                        value={currentVoucher.from_account_id}
                        onValueChange={(value) => updateVoucher(0, 'from_account_id', value)}
                    >
                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <SelectValue placeholder="Choose source account" />
                        </SelectTrigger>
                        <SelectContent>
                            {getFilteredAccounts(currentVoucher.payment_method).map((account) => (
                                <SelectItem key={account.id} value={account.id.toString()}>
                                    {account.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {errors['vouchers.0.from_account_id'] && (
                        <span className="text-sm text-red-500">{errors['vouchers.0.from_account_id']}</span>
                    )}
                </div>
                <div>
                    <Label htmlFor="to_account_id" className="dark:text-gray-200">
                        To Account
                    </Label>
                    <Select
                        value={currentVoucher.to_account_id}
                        onValueChange={(value) => updateVoucher(0, 'to_account_id', value)}
                    >
                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <SelectValue placeholder="Choose destination account" />
                        </SelectTrigger>
                        <SelectContent>
                            {accounts.map((account) => (
                                <SelectItem key={account.id} value={account.id.toString()}>
                                    {account.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {errors['vouchers.0.to_account_id'] && (
                        <span className="text-sm text-red-500">{errors['vouchers.0.to_account_id']}</span>
                    )}
                </div>
                <div>
                    <Label htmlFor="amount" className="dark:text-gray-200">
                        Amount
                    </Label>
                    <Input
                        id="amount"
                        type="number"
                        step="0.01"
                        placeholder="Enter amount"
                        value={currentVoucher.amount}
                        onChange={(e) => updateVoucher(0, 'amount', e.target.value)}
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                    {errors['vouchers.0.amount'] && (
                        <span className="text-sm text-red-500">{errors['vouchers.0.amount']}</span>
                    )}
                </div>
            </div>

            {currentVoucher.payment_method === 'Bank' && (
                <div className="grid grid-cols-4 gap-4">
                    <div>
                        <Label htmlFor="bank_type" className="dark:text-gray-200">
                            Bank Type
                        </Label>
                        <Select
                            value={currentVoucher.bank_type}
                            onValueChange={(value) => updateVoucher(0, 'bank_type', value)}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
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
                        <Label htmlFor="bank_name" className="dark:text-gray-200">
                            Bank Name
                        </Label>
                        <Input
                            id="bank_name"
                            value={currentVoucher.bank_name}
                            onChange={(e) => updateVoucher(0, 'bank_name', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                    <div>
                        <Label htmlFor="cheque_no" className="dark:text-gray-200">
                            Cheque Number
                        </Label>
                        <Input
                            id="cheque_no"
                            value={currentVoucher.cheque_no}
                            onChange={(e) => updateVoucher(0, 'cheque_no', e.target.value)}
                            disabled={currentVoucher.bank_type !== 'Cheque'}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                    <div>
                        <Label htmlFor="cheque_date" className="dark:text-gray-200">
                            Cheque Date
                        </Label>
                        <Input
                            id="cheque_date"
                            type="date"
                            value={currentVoucher.cheque_date}
                            onChange={(e) => updateVoucher(0, 'cheque_date', e.target.value)}
                            disabled={currentVoucher.bank_type !== 'Cheque'}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                </div>
            )}

            {currentVoucher.payment_method === 'Mobile Bank' ? (
                <div className="grid grid-cols-4 gap-4">
                    <div>
                        <Label htmlFor="mobile_bank" className="dark:text-gray-200">
                            Mobile Bank
                        </Label>
                        <Select
                            value={currentVoucher.mobile_bank}
                            onValueChange={(value) => updateVoucher(0, 'mobile_bank', value)}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
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
                        <Label htmlFor="mobile_number" className="dark:text-gray-200">
                            Mobile Number
                        </Label>
                        <Input
                            id="mobile_number"
                            value={currentVoucher.mobile_number}
                            onChange={(e) => updateVoucher(0, 'mobile_number', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                    <div>
                        <Label htmlFor="remarks" className="dark:text-gray-200">
                            Remarks
                        </Label>
                        <Input
                            id="remarks"
                            placeholder="Enter remarks (optional)"
                            value={currentVoucher.remarks}
                            onChange={(e) => updateVoucher(0, 'remarks', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                    <div className="flex items-end">
                        {!editingVoucher && (
                            <Button
                                type="button"
                                onClick={addVoucher}
                                className="w-full bg-blue-600 hover:bg-blue-700"
                            >
                                <Plus className="mr-1 h-4 w-4" />
                                Add Voucher
                            </Button>
                        )}
                    </div>
                </div>
            ) : (
                <div className="grid grid-cols-4 gap-4">
                    <div
                        className={
                            currentVoucher.payment_method === 'Cash' ||
                            currentVoucher.payment_method === 'Bank'
                                ? 'col-span-3'
                                : undefined
                        }
                    >
                        <Label htmlFor="remarks" className="dark:text-gray-200">
                            Remarks
                        </Label>
                        <Input
                            id="remarks"
                            placeholder="Enter remarks (optional)"
                            value={currentVoucher.remarks}
                            onChange={(e) => updateVoucher(0, 'remarks', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                    <div className="flex items-end">
                        {!editingVoucher && (
                            <Button
                                type="button"
                                onClick={addVoucher}
                                className="w-full bg-blue-600 hover:bg-blue-700"
                            >
                                <Plus className="mr-1 h-4 w-4" />
                                Add Voucher
                            </Button>
                        )}
                    </div>
                    {currentVoucher.payment_method !== 'Cash' &&
                        currentVoucher.payment_method !== 'Bank' && (
                            <div className="col-span-2" />
                        )}
                </div>
            )}

            {!editingVoucher && (
                <div className="mt-6">
                    <table className="w-full border border-gray-300 dark:border-gray-600">
                        <thead className="bg-gray-100 dark:bg-gray-700">
                            <tr>
                                <th className="p-2 text-left text-sm font-medium dark:text-gray-200">
                                    SL
                                </th>
                                <th className="p-2 text-left text-sm font-medium dark:text-gray-200">
                                    Category
                                </th>
                                <th className="p-2 text-left text-sm font-medium dark:text-gray-200">
                                    From
                                </th>
                                <th className="p-2 text-left text-sm font-medium dark:text-gray-200">
                                    To
                                </th>
                                <th className="p-2 text-left text-sm font-medium dark:text-gray-200">
                                    Amount
                                </th>
                                <th className="p-2 text-left text-sm font-medium dark:text-gray-200">
                                    Method
                                </th>
                                <th className="p-2 text-left text-sm font-medium dark:text-gray-200">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.vouchers
                                .slice(1)
                                .filter((v) => v.voucher_category_id)
                                .map((voucher, index) => {
                                    const actualIndex = index + 1;
                                    const category = voucherCategories.find(
                                        (c) => c.id.toString() === voucher.voucher_category_id,
                                    );
                                    const fromAccount = accounts.find(
                                        (a) => a.id.toString() === voucher.from_account_id,
                                    );
                                    const toAccount = accounts.find(
                                        (a) => a.id.toString() === voucher.to_account_id,
                                    );
                                    return (
                                        <tr key={actualIndex} className="border-t dark:border-gray-600">
                                            <td className="p-2 text-sm dark:text-white">{index + 1}</td>
                                            <td className="p-2 text-sm dark:text-white">{category?.name || '-'}</td>
                                            <td className="p-2 text-sm dark:text-white">{fromAccount?.name || '-'}</td>
                                            <td className="p-2 text-sm dark:text-white">{toAccount?.name || '-'}</td>
                                            <td className="p-2 text-sm dark:text-white">{voucher.amount || '0'}</td>
                                            <td className="p-2 text-sm dark:text-white">{voucher.payment_method}</td>
                                            <td className="p-2">
                                                <div className="flex gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            const editVoucher = data.vouchers[actualIndex];
                                                            const newVouchers = data.vouchers.filter((_, i) => i !== actualIndex);
                                                            newVouchers[0] = editVoucher;
                                                            setData((prev) => ({ ...prev, vouchers: newVouchers }));
                                                        }}
                                                        className="text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => removeVoucher(actualIndex)}
                                                        className="text-red-600 hover:text-red-800"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                        </tbody>
                    </table>
                </div>
            )}
        </FormModal>
    );
}
