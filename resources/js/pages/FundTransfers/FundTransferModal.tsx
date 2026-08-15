import { Button } from '@/components/ui/button';
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
import { useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import type { FundTransferDetail } from './FundTransferDetailsModal';

interface Account {
    id: number;
    name: string;
    ac_number: string;
    group_id?: number;
    group?: { id: number; name: string; code?: string };
}

interface FundTransferModalProps {
    isOpen: boolean;
    onClose: () => void;
    editingTransfer: FundTransferDetail | null;
    accounts: Account[];
    expenseAccounts: Account[];
}

export function FundTransferModal({
    isOpen,
    onClose,
    editingTransfer,
    accounts = [],
    expenseAccounts = [],
}: FundTransferModalProps) {
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        date: new Date().toISOString().split('T')[0],
        from_account_id: '',
        to_account_id: '',
        amount: '',
        transfer_fee: '0',
        fee_account_id: '',
        reference_no: '',
        remarks: '',
    });

    useEffect(() => {
        if (!isOpen) {
            reset();
            clearErrors();
            return;
        }

        if (editingTransfer) {
            setData({
                date: editingTransfer.transfer_date ? editingTransfer.transfer_date.split('T')[0] : '',
                from_account_id: editingTransfer.from_account_id?.toString() || '',
                to_account_id: editingTransfer.to_account_id?.toString() || '',
                amount: editingTransfer.amount?.toString() || '',
                transfer_fee: editingTransfer.transfer_fee ? editingTransfer.transfer_fee.toString() : '0',
                fee_account_id: editingTransfer.fee_account_id ? editingTransfer.fee_account_id.toString() : '',
                reference_no: editingTransfer.reference_no || '',
                remarks: editingTransfer.remarks || '',
            });
        } else {
            setData({
                date: new Date().toISOString().split('T')[0],
                from_account_id: '',
                to_account_id: '',
                amount: '',
                transfer_fee: '0',
                fee_account_id: '',
                reference_no: '',
                remarks: '',
            });
        }
        clearErrors();
    }, [isOpen, editingTransfer]);

    const fromAccountOptions = useMemo(() => {
        return accounts.map((acc) => ({
            value: acc.id.toString(),
            label: acc.name,
            subtitle: `${acc.group?.name || 'Account'} ${acc.ac_number ? '(' + acc.ac_number + ')' : ''}`,
        }));
    }, [accounts]);

    const toAccountOptions = useMemo(() => {
        return accounts
            .filter((acc) => acc.id.toString() !== data.from_account_id)
            .map((acc) => ({
                value: acc.id.toString(),
                label: acc.name,
                subtitle: `${acc.group?.name || 'Account'} ${acc.ac_number ? '(' + acc.ac_number + ')' : ''}`,
            }));
    }, [accounts, data.from_account_id]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editingTransfer) {
            put(`/fund-transfers/${editingTransfer.id}`, {
                onSuccess: () => {
                    onClose();
                    reset();
                },
            });
        } else {
            post('/fund-transfers', {
                onSuccess: () => {
                    onClose();
                    reset();
                },
            });
        }
    };

    const transferAmount = Number(data.amount) || 0;
    const feeAmount = Number(data.transfer_fee) || 0;
    const totalDeduction = transferAmount + feeAmount;

    const fromAccountObj = accounts.find((a) => a.id.toString() === data.from_account_id);
    const toAccountObj = accounts.find((a) => a.id.toString() === data.to_account_id);

    const generatedRemarkPreview = useMemo(() => {
        if (!fromAccountObj || !toAccountObj) {
            return 'Auto-generated from accounts if left empty';
        }
        if (feeAmount > 0) {
            return `Fund transfer from ${fromAccountObj.name} to ${toAccountObj.name} with transfer fee of ${feeAmount}. (Auto-generated if empty)`;
        }
        return `Fund transfer from ${fromAccountObj.name} to ${toAccountObj.name}. (Auto-generated if empty)`;
    }, [fromAccountObj, toAccountObj, feeAmount]);

    return (
        <FormModal
            isOpen={isOpen}
            onClose={onClose}
            title={editingTransfer ? `Edit Fund Transfer (${editingTransfer.transfer_no})` : 'Add Fund Transfer'}
            onSubmit={handleSubmit}
            processing={processing}
            submitText={editingTransfer ? 'Update & Re-post' : 'Save & Post Transfer'}
            wide={false}
            errors={errors}
        >
            <div className="space-y-4">
                {/* Row 1: Date & Reference */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <Label htmlFor="transfer_date" className="dark:text-gray-200">
                            Transfer Date <span className="text-red-500">*</span>
                        </Label>
                        <Input
                            id="transfer_date"
                            type="date"
                            value={data.date}
                            onChange={(e) => setData('date', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            required
                        />
                        {errors.date && (
                            <span className="text-sm text-red-500">{errors.date}</span>
                        )}
                    </div>

                    <div>
                        <Label htmlFor="reference_no" className="dark:text-gray-200">
                            Reference / Memo No
                        </Label>
                        <Input
                            id="reference_no"
                            placeholder="e.g. Cheque #, TXN ID (optional)"
                            value={data.reference_no}
                            onChange={(e) => setData('reference_no', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.reference_no && (
                            <span className="text-sm text-red-500">{errors.reference_no}</span>
                        )}
                    </div>
                </div>

                {/* Row 2: From Account & To Account */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <Label className="dark:text-gray-200">
                            From Account (Source) <span className="text-red-500">*</span>
                        </Label>
                        <SearchableSelect
                            options={fromAccountOptions}
                            value={data.from_account_id}
                            onValueChange={(val) => {
                                setData('from_account_id', val);
                                if (data.to_account_id === val) {
                                    setData('to_account_id', '');
                                }
                            }}
                            placeholder="Select source account"
                            searchPlaceholder="Search accounts..."
                        />
                        {errors.from_account_id && (
                            <span className="text-sm text-red-500">{errors.from_account_id}</span>
                        )}
                    </div>

                    <div>
                        <Label className="dark:text-gray-200">
                            To Account (Destination) <span className="text-red-500">*</span>
                        </Label>
                        <SearchableSelect
                            options={toAccountOptions}
                            value={data.to_account_id}
                            onValueChange={(val) => setData('to_account_id', val)}
                            placeholder="Select destination account"
                            searchPlaceholder="Search accounts..."
                            disabled={!data.from_account_id}
                        />
                        {errors.to_account_id && (
                            <span className="text-sm text-red-500">{errors.to_account_id}</span>
                        )}
                    </div>
                </div>

                {/* Row 3: Amount, Transfer Fee, Fee Account */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <Label htmlFor="amount" className="dark:text-gray-200">
                            Transfer Amount <span className="text-red-500">*</span>
                        </Label>
                        <Input
                            id="amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            value={data.amount}
                            onChange={(e) => setData('amount', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            required
                        />
                        {errors.amount && (
                            <span className="text-sm text-red-500">{errors.amount}</span>
                        )}
                    </div>

                    <div>
                        <Label htmlFor="transfer_fee" className="dark:text-gray-200">
                            Transfer Fee / Charge
                        </Label>
                        <Input
                            id="transfer_fee"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            value={data.transfer_fee}
                            onChange={(e) => setData('transfer_fee', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.transfer_fee && (
                            <span className="text-sm text-red-500">{errors.transfer_fee}</span>
                        )}
                    </div>

                    <div>
                        <Label htmlFor="fee_account_id" className="dark:text-gray-200">
                            Fee Expense Account
                        </Label>
                        <Select
                            value={data.fee_account_id}
                            onValueChange={(val) => setData('fee_account_id', val)}
                            disabled={feeAmount <= 0}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Default (Bank Charges)" />
                            </SelectTrigger>
                            <SelectContent>
                                {expenseAccounts.map((acc) => (
                                    <SelectItem key={acc.id} value={acc.id.toString()}>
                                        {acc.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.fee_account_id && (
                            <span className="text-sm text-red-500">{errors.fee_account_id}</span>
                        )}
                    </div>
                </div>

                {/* Row 4: Remarks */}
                <div>
                    <Label htmlFor="remarks" className="dark:text-gray-200">
                        Remarks / Notes
                    </Label>
                    <Input
                        id="remarks"
                        placeholder={generatedRemarkPreview}
                        value={data.remarks}
                        onChange={(e) => setData('remarks', e.target.value)}
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                    {errors.remarks && (
                        <span className="text-sm text-red-500">{errors.remarks}</span>
                    )}
                </div>

                {/* Live Financial Summary Box */}
                {transferAmount > 0 && (
                    <div className="rounded-md border bg-gray-50 p-3 text-xs dark:border-gray-700 dark:bg-gray-750">
                        <div className="flex justify-between py-1">
                            <span className="text-gray-600 dark:text-gray-400">Destination Account Inflow:</span>
                            <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                +{transferAmount.toFixed(2)}
                            </span>
                        </div>
                        {feeAmount > 0 && (
                            <div className="flex justify-between py-1">
                                <span className="text-gray-600 dark:text-gray-400">Fee Expense Incurred:</span>
                                <span className="font-semibold text-amber-600 dark:text-amber-400">
                                    +{feeAmount.toFixed(2)}
                                </span>
                            </div>
                        )}
                        <div className="flex justify-between border-t pt-1 font-bold dark:border-gray-600">
                            <span className="text-gray-900 dark:text-white">Total Source Account Deduction:</span>
                            <span className="text-gray-900 dark:text-white">
                                {totalDeduction.toFixed(2)}
                            </span>
                        </div>
                    </div>
                )}
            </div>
        </FormModal>
    );
}
