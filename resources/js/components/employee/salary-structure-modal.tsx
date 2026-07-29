import type { SalaryStructureInput } from '@/components/employee/salary-preview-table';
import { SalaryPreviewTable } from '@/components/employee/salary-preview-table';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useState } from 'react';

interface SalaryStructureModalProps {
    open: boolean;
    value: SalaryStructureInput;
    errors?: Record<string, string | undefined>;
    onOpenChange: (open: boolean) => void;
    onSave: (value: SalaryStructureInput) => void;
}

const percentageFields = [
    'home_rent_percent',
    'medical_percent',
    'conveyance_percent',
] as const;

export function SalaryStructureModal({
    open,
    value,
    errors = {},
    onOpenChange,
    onSave,
}: SalaryStructureModalProps) {
    const [draft, setDraft] = useState<SalaryStructureInput>(value);
    const [localErrors, setLocalErrors] = useState<
        Partial<Record<keyof SalaryStructureInput, string>>
    >({});

    const update = (field: keyof SalaryStructureInput, nextValue: string) => {
        setDraft((current) => ({ ...current, [field]: nextValue }));
        setLocalErrors((current) => ({ ...current, [field]: undefined }));
    };

    const handleSave = () => {
        const nextErrors: Partial<Record<keyof SalaryStructureInput, string>> =
            {};
        const basicSalary = Number.parseFloat(draft.basic_salary);

        if (!Number.isFinite(basicSalary) || basicSalary <= 0) {
            nextErrors.basic_salary = 'Basic Salary must be greater than zero.';
        }

        percentageFields.forEach((field) => {
            const percentage = Number.parseFloat(draft[field] || '0');

            if (
                !Number.isFinite(percentage) ||
                percentage < 0 ||
                percentage > 100
            ) {
                nextErrors[field] = 'Percentage must be between 0 and 100.';
            }
        });

        (['other_allowances', 'deductions'] as const).forEach((field) => {
            const amount = Number.parseFloat(draft[field] || '0');

            if (!Number.isFinite(amount) || amount < 0) {
                nextErrors[field] = 'Amount cannot be negative.';
            }
        });

        if (Object.keys(nextErrors).length > 0) {
            setLocalErrors(nextErrors);
            return;
        }

        onSave(draft);
        onOpenChange(false);
    };

    const fieldError = (field: keyof SalaryStructureInput) =>
        localErrors[field] ?? errors[`salary_structure.${field}`];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Salary Structure</DialogTitle>
                    <DialogDescription className="sr-only">
                        Configure employee salary components.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="basic_salary">Basic Salary *</Label>
                        <Input
                            id="basic_salary"
                            type="number"
                            min="0.01"
                            step="0.01"
                            value={draft.basic_salary}
                            onChange={(event) =>
                                update('basic_salary', event.target.value)
                            }
                            error={Boolean(fieldError('basic_salary'))}
                        />
                        <InputError message={fieldError('basic_salary')} />
                    </div>

                    <div>
                        <Label htmlFor="home_rent_percent">Home Rent (%)</Label>
                        <Input
                            id="home_rent_percent"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            value={draft.home_rent_percent}
                            onChange={(event) =>
                                update('home_rent_percent', event.target.value)
                            }
                            error={Boolean(fieldError('home_rent_percent'))}
                        />
                        <InputError message={fieldError('home_rent_percent')} />
                    </div>

                    <div>
                        <Label htmlFor="medical_percent">
                            Medical Allowance (%)
                        </Label>
                        <Input
                            id="medical_percent"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            value={draft.medical_percent}
                            onChange={(event) =>
                                update('medical_percent', event.target.value)
                            }
                            error={Boolean(fieldError('medical_percent'))}
                        />
                        <InputError message={fieldError('medical_percent')} />
                    </div>

                    <div>
                        <Label htmlFor="conveyance_percent">
                            Conveyance (%)
                        </Label>
                        <Input
                            id="conveyance_percent"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            value={draft.conveyance_percent}
                            onChange={(event) =>
                                update('conveyance_percent', event.target.value)
                            }
                            error={Boolean(fieldError('conveyance_percent'))}
                        />
                        <InputError
                            message={fieldError('conveyance_percent')}
                        />
                    </div>

                    <div>
                        <Label htmlFor="other_allowances">
                            Other Allowances
                        </Label>
                        <Input
                            id="other_allowances"
                            type="number"
                            min="0"
                            step="0.01"
                            value={draft.other_allowances}
                            onChange={(event) =>
                                update('other_allowances', event.target.value)
                            }
                            error={Boolean(fieldError('other_allowances'))}
                        />
                        <InputError message={fieldError('other_allowances')} />
                    </div>

                    <div>
                        <Label htmlFor="deductions">Deductions</Label>
                        <Input
                            id="deductions"
                            type="number"
                            min="0"
                            step="0.01"
                            value={draft.deductions}
                            onChange={(event) =>
                                update('deductions', event.target.value)
                            }
                            error={Boolean(fieldError('deductions'))}
                        />
                        <InputError message={fieldError('deductions')} />
                    </div>
                </div>

                <SalaryPreviewTable structure={draft} />

                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button type="button" onClick={handleSave}>
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
