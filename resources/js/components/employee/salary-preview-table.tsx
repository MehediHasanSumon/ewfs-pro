export interface SalaryStructureInput {
    basic_salary: string;
    home_rent_percent: string;
    medical_percent: string;
    conveyance_percent: string;
    other_allowances: string;
    deductions: string;
}

export interface CalculatedSalary {
    basicSalary: number;
    homeRentAmount: number;
    medicalAmount: number;
    conveyanceAmount: number;
    otherAllowances: number;
    deductions: number;
    grossSalary: number;
}

const number = (value: string): number => {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? parsed : 0;
};

export function calculateSalary(
    structure: SalaryStructureInput,
): CalculatedSalary {
    const basicSalary = number(structure.basic_salary);
    const homeRentAmount =
        (basicSalary * number(structure.home_rent_percent)) / 100;
    const medicalAmount =
        (basicSalary * number(structure.medical_percent)) / 100;
    const conveyanceAmount =
        (basicSalary * number(structure.conveyance_percent)) / 100;
    const otherAllowances = number(structure.other_allowances);
    const deductions = number(structure.deductions);

    return {
        basicSalary,
        homeRentAmount,
        medicalAmount,
        conveyanceAmount,
        otherAllowances,
        deductions,
        grossSalary:
            basicSalary +
            homeRentAmount +
            medicalAmount +
            conveyanceAmount +
            otherAllowances -
            deductions,
    };
}

const formatter = new Intl.NumberFormat('en-BD', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

interface SalaryPreviewTableProps {
    structure: SalaryStructureInput;
}

export function SalaryPreviewTable({ structure }: SalaryPreviewTableProps) {
    const calculated = calculateSalary(structure);
    const rows = [
        ['Basic Salary', null, calculated.basicSalary],
        [
            'Home Rent',
            structure.home_rent_percent || '0',
            calculated.homeRentAmount,
        ],
        ['Medical', structure.medical_percent || '0', calculated.medicalAmount],
        [
            'Conveyance',
            structure.conveyance_percent || '0',
            calculated.conveyanceAmount,
        ],
        ['Other Allowances', null, calculated.otherAllowances],
        ['Deductions', null, -calculated.deductions],
    ] as const;

    return (
        <div className="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
            <table className="w-full text-sm">
                <thead className="bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        <th className="px-3 py-2 text-left font-medium">
                            Component
                        </th>
                        <th className="px-3 py-2 text-right font-medium">
                            Percentage
                        </th>
                        <th className="px-3 py-2 text-right font-medium">
                            Amount
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                    {rows.map(([label, percentage, amount]) => (
                        <tr key={label}>
                            <td className="px-3 py-2">{label}</td>
                            <td className="px-3 py-2 text-right">
                                {percentage === null ? '-' : `${percentage}%`}
                            </td>
                            <td className="px-3 py-2 text-right tabular-nums">
                                {formatter.format(amount)}
                            </td>
                        </tr>
                    ))}
                </tbody>
                <tfoot className="bg-gray-50 font-semibold dark:bg-gray-900/40">
                    <tr>
                        <td className="px-3 py-2" colSpan={2}>
                            Gross Salary
                        </td>
                        <td className="px-3 py-2 text-right tabular-nums">
                            {formatter.format(calculated.grossSalary)}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}
