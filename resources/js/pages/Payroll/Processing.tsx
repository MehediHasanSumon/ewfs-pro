import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FormModal } from '@/components/ui/form-modal';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Calculator, Plus, Receipt, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

interface EmployeeOption {
    id: number;
    employee_code: string | null;
    employee_name: string;
    department: string | null;
    designation: string | null;
    monthly_salary: number;
    payment_method: string | null;
    payment_account: { id: number; name: string; ac_number: string } | null;
    deductions?: Deduction[];
    extras?: Extra[];
}

interface Deduction {
    amount: string;
    reason: string;
}

interface Extra {
    voucher_transaction_type_id: string;
    amount: string;
    remarks: string;
}

interface Draft {
    deductions: Deduction[];
    extras: Extra[];
}

interface ExtraType {
    id: number;
    code: string;
    name: string;
}

interface Item {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_code: string | null;
    department: string | null;
    designation: string | null;
    monthly_salary: number;
    total_deduction: number;
    total_bonus: number;
    advance_applied: number;
    net_salary: number;
    net_payable: number;
    payment_method: string | null;
    payment_account: { name: string; ac_number: string } | null;
    status: string;
    deductions?: { amount: number; reason: string }[];
    extras?: { amount: number; remarks: string | null; voucher_transaction_type: { name: string; code: string } | null }[];
}

interface Period {
    id: number;
    payroll_code: string | null;
    label: string;
    status: string;
    remarks: string | null;
}

const amount = (value: number) =>
    Number(value ?? 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

export default function Processing({
    period,
    employees,
    items,
    extraTypes,
    canGenerate,
}: {
    period: Period;
    employees: EmployeeOption[];
    items: Item[];
    extraTypes: ExtraType[];
    canGenerate: boolean;
}) {
    const revisingGenerated = period.status === 'generated';
    const [selectedIds, setSelectedIds] = useState<number[]>(
        revisingGenerated ? employees.map((employee) => employee.id) : [],
    );
    const [generationOpen, setGenerationOpen] = useState(false);
    const [bonusOpen, setBonusOpen] = useState(false);
    const [bonusEmployeeId, setBonusEmployeeId] = useState<number | null>(null);
    const [drafts, setDrafts] = useState<Record<number, Draft>>(() =>
        Object.fromEntries(
            employees.map((employee) => [
                employee.id,
                {
                    deductions: employee.deductions ?? [],
                    extras: employee.extras ?? [],
                },
            ]),
        ),
    );
    const generation = useForm({ employees: [] as { employee_id: number; deductions: Deduction[]; extras: Extra[] }[] });
    const [bonus, setBonus] = useState<Extra>({
        voucher_transaction_type_id: '',
        amount: '',
        remarks: '',
    });

    const selectedEmployees = useMemo(
        () => employees.filter((employee) => selectedIds.includes(employee.id)),
        [employees, selectedIds],
    );
    const allSelected = employees.length > 0 && selectedIds.length === employees.length;

    const ensureDraft = (employeeId: number): Draft =>
        drafts[employeeId] ?? { deductions: [], extras: [] };

    const updateDraft = (employeeId: number, draft: Draft) => {
        setDrafts((current) => ({ ...current, [employeeId]: draft }));
    };

    const openGeneration = () => {
        setDrafts((current) => {
            const next = { ...current };
            selectedIds.forEach((id) => {
                next[id] ??= { deductions: [], extras: [] };
            });
            return next;
        });
        setGenerationOpen(true);
    };

    const submitGeneration = (event: React.FormEvent) => {
        event.preventDefault();
        const payload = selectedEmployees.map((employee) => ({
            employee_id: employee.id,
            deductions: ensureDraft(employee.id).deductions,
            extras: ensureDraft(employee.id).extras,
        }));
        generation.transform(() => ({ employees: payload }));
        generation.post(`/payroll/${period.id}/generate`, {
            preserveScroll: true,
            onSuccess: () => {
                setGenerationOpen(false);
                setSelectedIds([]);
            },
        });
    };

    const addBonus = () => {
        if (!bonusEmployeeId || !bonus.voucher_transaction_type_id || Number(bonus.amount) <= 0) {
            return;
        }
        const draft = ensureDraft(bonusEmployeeId);
        updateDraft(bonusEmployeeId, {
            ...draft,
            extras: [...draft.extras, bonus],
        });
        setBonus({
            voucher_transaction_type_id: '',
            amount: '',
            remarks: '',
        });
        setBonusOpen(false);
    };

    const renderDraftRows = (employee: EmployeeOption) => {
        const draft = ensureDraft(employee.id);
        const deductionTotal = draft.deductions.reduce((sum, row) => sum + Number(row.amount || 0), 0);
        const bonusTotal = draft.extras.reduce((sum, row) => sum + Number(row.amount || 0), 0);

        return (
            <div key={employee.id} className="space-y-3 rounded-md border p-3 dark:border-gray-700">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p className="font-medium dark:text-white">{employee.employee_name}</p>
                        <p className="text-xs text-gray-500">{employee.employee_code} · Monthly Salary {amount(employee.monthly_salary)}</p>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => {
                            setBonusEmployeeId(employee.id);
                            setBonusOpen(true);
                        }}
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        Add Bonus / Extra
                    </Button>
                </div>
                {draft.deductions.map((deduction, index) => (
                    <div key={index} className="grid gap-2 md:grid-cols-[160px_1fr_auto]">
                        <Input
                            type="number"
                            min="0.01"
                            step="0.01"
                            value={deduction.amount}
                            onChange={(event) => {
                                const next = [...draft.deductions];
                                next[index] = { ...deduction, amount: event.target.value };
                                updateDraft(employee.id, { ...draft, deductions: next });
                            }}
                            aria-label={`Deduction amount for ${employee.employee_name}`}
                        />
                        <Input
                            value={deduction.reason}
                            onChange={(event) => {
                                const next = [...draft.deductions];
                                next[index] = { ...deduction, reason: event.target.value };
                                updateDraft(employee.id, { ...draft, deductions: next });
                            }}
                            placeholder="Deduction reason"
                            aria-label={`Deduction reason for ${employee.employee_name}`}
                        />
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            onClick={() => updateDraft(employee.id, {
                                ...draft,
                                deductions: draft.deductions.filter((_, rowIndex) => rowIndex !== index),
                            })}
                            aria-label="Remove deduction"
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                ))}
                {draft.extras.length > 0 && (
                    <div className="space-y-1 text-sm text-gray-600 dark:text-gray-300">
                        {draft.extras.map((extra, index) => (
                            <div key={index} className="flex items-center justify-between gap-2">
                                <span>
                                    {extraTypes.find((type) => type.id.toString() === extra.voucher_transaction_type_id)?.name ?? 'Extra'}: {amount(Number(extra.amount))}
                                </span>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    onClick={() => updateDraft(employee.id, {
                                        ...draft,
                                        extras: draft.extras.filter((_, rowIndex) => rowIndex !== index),
                                    })}
                                    aria-label="Remove bonus"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        ))}
                    </div>
                )}
                <div className="flex flex-wrap items-center justify-between gap-2 border-t pt-2 text-sm dark:border-gray-700">
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => updateDraft(employee.id, {
                            ...draft,
                            deductions: [...draft.deductions, { amount: '', reason: '' }],
                        })}
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        Add Deduction
                    </Button>
                    <span className="text-gray-600 dark:text-gray-300">
                        Deduction {amount(deductionTotal)} · Bonus {amount(bonusTotal)} · Net {amount(employee.monthly_salary - deductionTotal + bonusTotal)}
                    </span>
                </div>
            </div>
        );
    };

    return (
        <AppLayout>
            <Head title={`Payroll - ${period.label}`} />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold dark:text-white">{period.label}</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {period.payroll_code} · Status: <span className="capitalize">{period.status}</span>
                        </p>
                    </div>
                    <Button type="button" variant="outline" onClick={() => router.get('/payroll')}>
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Payrolls
                    </Button>
                </div>

                {canGenerate && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {revisingGenerated ? 'Revise Generated Payroll' : 'Select Employees'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[980px]">
                                    <thead>
                                        <tr className="border-b text-left text-sm text-gray-500 dark:border-gray-700">
                                            <th className="w-12 p-4">
                                                <Checkbox
                                                    checked={allSelected}
                                                    disabled={revisingGenerated}
                                                    onCheckedChange={() => setSelectedIds(allSelected ? [] : employees.map((employee) => employee.id))}
                                                    aria-label="Select all employees"
                                                />
                                            </th>
                                            <th className="p-4">Employee</th>
                                            <th className="p-4">Department</th>
                                            <th className="p-4">Designation</th>
                                            <th className="p-4">Payment Method</th>
                                            <th className="p-4">Account</th>
                                            <th className="p-4 text-right">Monthly Salary</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {employees.map((employee) => (
                                            <tr key={employee.id} className="border-b dark:border-gray-700">
                                                <td className="p-4">
                                                    <Checkbox
                                                        checked={selectedIds.includes(employee.id)}
                                                        disabled={revisingGenerated}
                                                        onCheckedChange={() => setSelectedIds((current) => current.includes(employee.id) ? current.filter((id) => id !== employee.id) : [...current, employee.id])}
                                                        aria-label={`Select ${employee.employee_name}`}
                                                    />
                                                </td>
                                                <td className="p-4 dark:text-white">
                                                    <div className="font-medium">{employee.employee_name}</div>
                                                    <div className="text-xs text-gray-500">{employee.employee_code}</div>
                                                </td>
                                                <td className="p-4 text-sm dark:text-gray-300">{employee.department ?? 'N/A'}</td>
                                                <td className="p-4 text-sm dark:text-gray-300">{employee.designation ?? 'N/A'}</td>
                                                <td className="p-4 text-sm dark:text-gray-300">{employee.payment_method ?? 'Not configured'}</td>
                                                <td className="p-4 text-sm dark:text-gray-300">{employee.payment_account?.name ?? 'Not configured'}</td>
                                                <td className="p-4 text-right tabular-nums dark:text-white">{amount(employee.monthly_salary)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="flex justify-end p-4">
                                <Button type="button" disabled={!canGenerate || selectedIds.length === 0} onClick={openGeneration}>
                                    <Calculator className="mr-2 h-4 w-4" />
                                    {revisingGenerated ? 'Save Payroll Revision' : `Generate Payroll (${selectedIds.length})`}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {period.status !== 'draft' && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2"><Receipt className="h-5 w-5" />Generated Payroll Details</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1380px]">
                                    <thead>
                                        <tr className="border-b text-left text-sm text-gray-500 dark:border-gray-700">
                                            <th className="p-4">Employee</th>
                                            <th className="p-4">Department</th>
                                            <th className="p-4 text-right">Monthly Salary</th>
                                            <th className="p-4 text-right">Deduction</th>
                                            <th className="p-4 text-right">Advance Adjustment</th>
                                            <th className="p-4 text-right">Bonus</th>
                                            <th className="p-4 text-right">Net Salary</th>
                                            <th className="p-4">Payment Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {items.map((item) => (
                                            <tr key={item.id} className="border-b dark:border-gray-700">
                                                <td className="p-4 dark:text-white"><div className="font-medium">{item.employee_name}</div><div className="text-xs text-gray-500">{item.employee_code}</div></td>
                                                <td className="p-4 text-sm dark:text-gray-300">{item.department ?? 'N/A'}</td>
                                                <td className="p-4 text-right tabular-nums dark:text-gray-300">{amount(item.monthly_salary)}</td>
                                                <td className="p-4 text-right tabular-nums dark:text-gray-300">{amount(item.total_deduction)}</td>
                                                <td className="p-4 text-right tabular-nums dark:text-gray-300">{amount(item.advance_applied)}</td>
                                                <td className="p-4 text-right tabular-nums dark:text-gray-300">{amount(item.total_bonus)}</td>
                                                <td className="p-4 text-right font-semibold tabular-nums dark:text-white">{amount(item.net_payable)}</td>
                                                <td className="p-4"><Badge variant={item.status === 'paid' ? 'success' : 'warning'}>{item.status}</Badge></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            <FormModal
                isOpen={generationOpen}
                onClose={() => setGenerationOpen(false)}
                title="Generate Payroll"
                description="Review deductions and add separate bonus or extra payment lines for the selected employees."
                onSubmit={submitGeneration}
                processing={generation.processing}
                submitText="Save / Generate"
                wide
                errors={generation.errors as Record<string, string>}
                submitDisabled={selectedEmployees.length === 0}
            >
                <div className="space-y-4">
                    {selectedEmployees.map(renderDraftRows)}
                </div>
            </FormModal>

            <FormModal
                isOpen={bonusOpen}
                onClose={() => setBonusOpen(false)}
                title="Add Bonus / Extra Payment"
                onSubmit={(event) => {
                    event.preventDefault();
                    addBonus();
                }}
                processing={false}
                submitText="Add"
                submitDisabled={!bonus.voucher_transaction_type_id || Number(bonus.amount) <= 0}
            >
                <div className="space-y-4">
                    <div>
                        <Label>Voucher Transaction Type</Label>
                        <Select value={bonus.voucher_transaction_type_id} onValueChange={(value) => setBonus((current) => ({ ...current, voucher_transaction_type_id: value }))}>
                            <SelectTrigger><SelectValue placeholder="Choose transaction type" /></SelectTrigger>
                            <SelectContent>
                                {extraTypes.map((type) => <SelectItem key={type.id} value={type.id.toString()}>{type.name} ({type.code})</SelectItem>)}
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>Amount</Label>
                        <Input type="number" min="0.01" step="0.01" value={bonus.amount} onChange={(event) => setBonus((current) => ({ ...current, amount: event.target.value }))} />
                    </div>
                    <div>
                        <Label>Remarks</Label>
                        <Textarea value={bonus.remarks} onChange={(event) => setBonus((current) => ({ ...current, remarks: event.target.value }))} />
                    </div>
                </div>
            </FormModal>
        </AppLayout>
    );
}
