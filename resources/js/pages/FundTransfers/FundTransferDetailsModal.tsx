import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { ArrowRight, FileText, X } from 'lucide-react';

export interface FundTransferDetail {
    id: number;
    transfer_no: string;
    transfer_date: string;
    from_account_id: number;
    to_account_id: number;
    from_account: {
        id: number;
        name: string;
        ac_number?: string;
        group?: { name: string; code?: string };
    };
    to_account: {
        id: number;
        name: string;
        ac_number?: string;
        group?: { name: string; code?: string };
    };
    amount: number | string;
    transfer_fee: number | string;
    fee_account_id?: number | null;
    fee_account?: {
        id: number;
        name: string;
        ac_number?: string;
    } | null;
    reference_no?: string | null;
    remarks?: string | null;
    status: 'draft' | 'posted' | 'cancelled';
    created_by?: { id: number; name: string } | null;
    posted_by?: { id: number; name: string } | null;
    created_at: string;
    posted_at?: string | null;
}

interface FundTransferDetailsModalProps {
    isOpen: boolean;
    onClose: () => void;
    transfer: FundTransferDetail | null;
}

export function FundTransferDetailsModal({
    isOpen,
    onClose,
    transfer,
}: FundTransferDetailsModalProps) {
    if (!transfer) return null;

    const amount = Number(transfer.amount) || 0;
    const fee = Number(transfer.transfer_fee) || 0;
    const totalOut = amount + fee;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl dark:bg-gray-800">
                <DialogHeader className="border-b pb-4 dark:border-gray-700">
                    <div className="flex items-center justify-between pr-6">
                        <DialogTitle className="flex items-center gap-2 text-xl font-bold dark:text-white">
                            <FileText className="h-5 w-5 text-primary" />
                            Fund Transfer Details
                        </DialogTitle>
                        <div>
                            {transfer.status === 'posted' && (
                                <span className="rounded bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                                    Posted
                                </span>
                            )}
                            {transfer.status === 'cancelled' && (
                                <span className="rounded bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800 dark:bg-red-900 dark:text-red-200">
                                    Cancelled
                                </span>
                            )}
                            {transfer.status === 'draft' && (
                                <span className="rounded bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                    Draft
                                </span>
                            )}
                        </div>
                    </div>
                </DialogHeader>

                <div className="space-y-6 py-4">
                    {/* Header Summary Card */}
                    <div className="grid grid-cols-1 gap-4 rounded-lg border bg-gray-50 p-4 sm:grid-cols-2 dark:border-gray-700 dark:bg-gray-750">
                        <div>
                            <span className="text-xs font-medium text-gray-500 dark:text-gray-400">Transfer Number</span>
                            <p className="text-base font-bold text-gray-900 dark:text-white">{transfer.transfer_no}</p>
                        </div>
                        <div>
                            <span className="text-xs font-medium text-gray-500 dark:text-gray-400">Transfer Date</span>
                            <p className="text-base font-semibold text-gray-900 dark:text-white">
                                {new Date(transfer.transfer_date).toLocaleDateString()}
                            </p>
                        </div>
                    </div>

                    {/* Transfer Flow Visual */}
                    <div className="grid grid-cols-1 items-center gap-4 rounded-lg border p-4 sm:grid-cols-5 dark:border-gray-700">
                        <div className="sm:col-span-2">
                            <span className="text-xs font-medium text-amber-600 dark:text-amber-400">From Account (Source)</span>
                            <p className="font-semibold text-gray-900 dark:text-white">{transfer.from_account?.name}</p>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                {transfer.from_account?.ac_number || transfer.from_account?.group?.name}
                            </p>
                        </div>

                        <div className="flex justify-center sm:col-span-1">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300">
                                <ArrowRight className="h-4 w-4" />
                            </div>
                        </div>

                        <div className="sm:col-span-2">
                            <span className="text-xs font-medium text-blue-600 dark:text-blue-400">To Account (Destination)</span>
                            <p className="font-semibold text-gray-900 dark:text-white">{transfer.to_account?.name}</p>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                {transfer.to_account?.ac_number || transfer.to_account?.group?.name}
                            </p>
                        </div>
                    </div>

                    {/* Financial Breakdown Table */}
                    <div className="overflow-hidden rounded-lg border dark:border-gray-700">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 dark:bg-gray-700">
                                <tr className="border-b dark:border-gray-600">
                                    <th className="p-3 text-left font-medium dark:text-gray-300">Description</th>
                                    <th className="p-3 text-right font-medium dark:text-gray-300">Amount (BDT)</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y dark:divide-gray-700">
                                <tr>
                                    <td className="p-3 dark:text-gray-300">Transfer Inflow (To Destination)</td>
                                    <td className="p-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">
                                        {amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                    </td>
                                </tr>
                                {fee > 0 && (
                                    <tr>
                                        <td className="p-3 dark:text-gray-300">
                                            Transfer Fee / Bank Charge
                                            {transfer.fee_account && (
                                                <span className="ml-1 text-xs text-gray-500 dark:text-gray-400">
                                                    ({transfer.fee_account.name})
                                                </span>
                                            )}
                                        </td>
                                        <td className="p-3 text-right font-semibold text-amber-600 dark:text-amber-400">
                                            {fee.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                        </td>
                                    </tr>
                                )}
                                <tr className="bg-gray-50/80 font-bold dark:bg-gray-750">
                                    <td className="p-3 text-gray-900 dark:text-white">Total Source Deduction (From Account)</td>
                                    <td className="p-3 text-right text-gray-900 dark:text-white">
                                        {totalOut.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {/* Additional Metadata */}
                    <div className="grid grid-cols-1 gap-4 text-xs sm:grid-cols-2">
                        {transfer.reference_no && (
                            <div>
                                <span className="font-medium text-gray-500 dark:text-gray-400">Reference / Memo No:</span>
                                <p className="font-semibold text-gray-800 dark:text-gray-200">{transfer.reference_no}</p>
                            </div>
                        )}
                        {transfer.created_by && (
                            <div>
                                <span className="font-medium text-gray-500 dark:text-gray-400">Created By:</span>
                                <p className="font-semibold text-gray-800 dark:text-gray-200">{transfer.created_by.name}</p>
                            </div>
                        )}
                        {transfer.remarks && (
                            <div className="sm:col-span-2">
                                <span className="font-medium text-gray-500 dark:text-gray-400">Remarks:</span>
                                <p className="text-gray-800 dark:text-gray-200">{transfer.remarks}</p>
                            </div>
                        )}
                    </div>
                </div>

                <div className="flex justify-end border-t pt-4 dark:border-gray-700">
                    <Button variant="outline" onClick={onClose}>
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
