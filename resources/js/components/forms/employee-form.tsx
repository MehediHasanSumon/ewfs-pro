import {
    EmployeeImageUploader,
    EmployeeNidUploader,
    EmployeeSignatureUploader,
} from '@/components/employee/employee-file-uploader';
import type { SalaryStructureInput } from '@/components/employee/salary-preview-table';
import { calculateSalary } from '@/components/employee/salary-preview-table';
import { SalaryStructureModal } from '@/components/employee/salary-structure-modal';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFocusFirstError } from '@/hooks/use-focus-first-error';
import { router } from '@inertiajs/react';
import { Calculator, LoaderCircle, Save } from 'lucide-react';
import { type FormEvent, useRef, useState } from 'react';

export interface EmployeeOption {
    id: number;
    name: string;
}

export interface EmployeeUploadLimits {
    image_max_kb: number;
    nid_max_kb: number;
}

export interface EmployeeFormData {
    employee_name: string;
    email: string;
    mobile: string;
    mobile_two: string;
    department_id: string;
    designation_id: string;
    emp_type_id: string;
    dob: string;
    gender: string;
    blood_group: string;
    marital_status: string;
    religion: string;
    nid: string;
    emergency_contact_person: string;
    emergency_contact_number: string;
    father_name: string;
    mother_name: string;
    present_address: string;
    permanent_address: string;
    job_status: string;
    joining_date: string;
    highest_education: string;
    status: boolean;
    photo: File | null;
    signature: File | null;
    nid_document: File | null;
    remove_photo: boolean;
    remove_signature: boolean;
    remove_nid_document: boolean;
    salary_structure: SalaryStructureInput;
}

export interface ExistingEmployeeFiles {
    photo_path?: string | null;
    photo_url?: string | null;
    signature_path?: string | null;
    signature_url?: string | null;
    nid_document_path?: string | null;
    nid_document_url?: string | null;
}

interface EmployeeFormProps {
    data: EmployeeFormData;
    errors: Record<string, string | undefined>;
    processing: boolean;
    submitLabel: string;
    departments: EmployeeOption[];
    designations: EmployeeOption[];
    empTypes: EmployeeOption[];
    uploadLimits: EmployeeUploadLimits;
    existingFiles?: ExistingEmployeeFiles;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    setData: <Key extends keyof EmployeeFormData>(
        key: Key,
        value: EmployeeFormData[Key],
    ) => void;
}

const fieldClass = 'dark:border-gray-600 dark:bg-gray-700 dark:text-white';

export function EmployeeForm({
    data,
    errors,
    processing,
    submitLabel,
    departments,
    designations,
    empTypes,
    uploadLimits,
    existingFiles = {},
    onSubmit,
    setData,
}: EmployeeFormProps) {
    const formRef = useRef<HTMLFormElement>(null);
    const [salaryOpen, setSalaryOpen] = useState(false);
    const grossSalary = calculateSalary(data.salary_structure).grossSalary;

    useFocusFirstError(formRef, errors);

    const error = (field: string) => errors[field];
    const select = (
        id: keyof Pick<
            EmployeeFormData,
            | 'department_id'
            | 'designation_id'
            | 'emp_type_id'
            | 'gender'
            | 'blood_group'
            | 'marital_status'
        >,
        placeholder: string,
        items: Array<{ value: string; label: string }>,
    ) => (
        <>
            <Select
                value={data[id]}
                onValueChange={(value) => setData(id, value)}
            >
                <SelectTrigger className={fieldClass}>
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {items.map((item) => (
                        <SelectItem key={item.value} value={item.value}>
                            {item.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error(id)} />
        </>
    );

    return (
        <>
            <form
                ref={formRef}
                onSubmit={onSubmit}
                className="space-y-6"
                aria-busy={processing}
            >
                <div className="grid grid-cols-1 gap-6 md:grid-cols-4">
                    <div>
                        <Label htmlFor="employee_name">Full Name *</Label>
                        <Input
                            id="employee_name"
                            value={data.employee_name}
                            onChange={(event) =>
                                setData('employee_name', event.target.value)
                            }
                            className={fieldClass}
                            placeholder="Enter full name"
                            autoFocus
                            error={Boolean(error('employee_name'))}
                        />
                        <InputError message={error('employee_name')} />
                    </div>

                    <div>
                        <Label htmlFor="mobile">Mobile</Label>
                        <Input
                            id="mobile"
                            value={data.mobile}
                            onChange={(event) =>
                                setData('mobile', event.target.value)
                            }
                            className={fieldClass}
                            placeholder="Enter mobile number"
                        />
                        <InputError message={error('mobile')} />
                    </div>

                    <div>
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(event) =>
                                setData('email', event.target.value)
                            }
                            className={fieldClass}
                            placeholder="Enter email address"
                        />
                        <InputError message={error('email')} />
                    </div>

                    <div>
                        <Label htmlFor="department_id">Department</Label>
                        {select(
                            'department_id',
                            'Select department',
                            departments.map((item) => ({
                                value: item.id.toString(),
                                label: item.name,
                            })),
                        )}
                    </div>

                    <div>
                        <Label htmlFor="designation_id">Designation</Label>
                        {select(
                            'designation_id',
                            'Select designation',
                            designations.map((item) => ({
                                value: item.id.toString(),
                                label: item.name,
                            })),
                        )}
                    </div>

                    <div>
                        <Label htmlFor="emp_type_id">Employee Type</Label>
                        {select(
                            'emp_type_id',
                            'Select employee type',
                            empTypes.map((item) => ({
                                value: item.id.toString(),
                                label: item.name,
                            })),
                        )}
                    </div>

                    <div>
                        <Label htmlFor="joining_date">Joining Date</Label>
                        <Input
                            id="joining_date"
                            type="date"
                            value={data.joining_date}
                            onChange={(event) =>
                                setData('joining_date', event.target.value)
                            }
                            className={fieldClass}
                        />
                        <InputError message={error('joining_date')} />
                    </div>

                    <div>
                        <Label htmlFor="job_status">Job Status</Label>
                        <Input
                            id="job_status"
                            value={data.job_status}
                            onChange={(event) =>
                                setData('job_status', event.target.value)
                            }
                            className={fieldClass}
                            placeholder="Enter job status"
                        />
                        <InputError message={error('job_status')} />
                    </div>

                    <div>
                        <Label htmlFor="salary">Salary</Label>
                        <div className="flex gap-2">
                            <Input
                                id="salary"
                                value={
                                    grossSalary > 0
                                        ? grossSalary.toFixed(2)
                                        : ''
                                }
                                className={fieldClass}
                                placeholder="Configure salary"
                                readOnly
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                onClick={() => setSalaryOpen(true)}
                                title="Salary Structure"
                                aria-label="Open salary structure"
                                className="shrink-0"
                            >
                                <Calculator
                                    className="h-4 w-4"
                                    aria-hidden="true"
                                />
                            </Button>
                        </div>
                        <InputError
                            message={
                                error('salary_structure') ??
                                error('salary_structure.basic_salary') ??
                                error('salary_structure.deductions')
                            }
                        />
                    </div>

                    <div>
                        <Label htmlFor="dob">Date of Birth</Label>
                        <Input
                            id="dob"
                            type="date"
                            value={data.dob}
                            onChange={(event) =>
                                setData('dob', event.target.value)
                            }
                            className={fieldClass}
                        />
                        <InputError message={error('dob')} />
                    </div>

                    <div>
                        <Label htmlFor="gender">Gender</Label>
                        {select('gender', 'Select gender', [
                            { value: 'Male', label: 'Male' },
                            { value: 'Female', label: 'Female' },
                            { value: 'Other', label: 'Other' },
                        ])}
                    </div>

                    <div>
                        <Label htmlFor="mobile_two">Alternative Mobile</Label>
                        <Input
                            id="mobile_two"
                            value={data.mobile_two}
                            onChange={(event) =>
                                setData('mobile_two', event.target.value)
                            }
                            className={fieldClass}
                            placeholder="Enter alternative mobile"
                        />
                        <InputError message={error('mobile_two')} />
                    </div>

                    <div>
                        <Label htmlFor="blood_group">Blood Group</Label>
                        {select(
                            'blood_group',
                            'Select blood group',
                            [
                                'A+',
                                'A-',
                                'B+',
                                'B-',
                                'AB+',
                                'AB-',
                                'O+',
                                'O-',
                            ].map((value) => ({ value, label: value })),
                        )}
                    </div>

                    <div>
                        <Label htmlFor="marital_status">Marital Status</Label>
                        {select(
                            'marital_status',
                            'Select marital status',
                            ['Single', 'Married', 'Divorced', 'Widowed'].map(
                                (value) => ({ value, label: value }),
                            ),
                        )}
                    </div>

                    {(
                        [
                            ['religion', 'Religion', 'Enter religion'],
                            ['nid', 'NID Number', 'Enter NID number'],
                            [
                                'father_name',
                                "Father's Name",
                                "Enter father's name",
                            ],
                            [
                                'mother_name',
                                "Mother's Name",
                                "Enter mother's name",
                            ],
                            [
                                'emergency_contact_person',
                                'Emergency Contact Person',
                                'Enter emergency contact person',
                            ],
                            [
                                'emergency_contact_number',
                                'Emergency Contact Number',
                                'Enter emergency contact number',
                            ],
                            [
                                'highest_education',
                                'Highest Education',
                                'Enter highest education',
                            ],
                            [
                                'present_address',
                                'Present Address',
                                'Enter present address',
                            ],
                            [
                                'permanent_address',
                                'Permanent Address',
                                'Enter permanent address',
                            ],
                        ] as const
                    ).map(([field, label, placeholder]) => (
                        <div key={field}>
                            <Label htmlFor={field}>{label}</Label>
                            <Input
                                id={field}
                                value={data[field]}
                                onChange={(event) =>
                                    setData(field, event.target.value)
                                }
                                className={fieldClass}
                                placeholder={placeholder}
                            />
                            <InputError message={error(field)} />
                        </div>
                    ))}

                    <div>
                        <Label htmlFor="status">Status</Label>
                        <Select
                            value={data.status ? 'true' : 'false'}
                            onValueChange={(value) =>
                                setData('status', value === 'true')
                            }
                        >
                            <SelectTrigger className={fieldClass}>
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="true">Active</SelectItem>
                                <SelectItem value="false">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={error('status')} />
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <EmployeeImageUploader
                        id="photo"
                        label="Employee Image"
                        value={data.photo}
                        existingPath={existingFiles.photo_path}
                        existingUrl={existingFiles.photo_url}
                        removeExisting={data.remove_photo}
                        maxSizeKb={uploadLimits.image_max_kb}
                        error={error('photo')}
                        onChange={(file) => {
                            setData('photo', file);
                            if (file) {
                                setData('remove_photo', false);
                            }
                        }}
                        onRemove={() => {
                            setData('photo', null);
                            setData('remove_photo', true);
                        }}
                    />
                    <EmployeeSignatureUploader
                        id="signature"
                        label="Employee Signature"
                        value={data.signature}
                        existingPath={existingFiles.signature_path}
                        existingUrl={existingFiles.signature_url}
                        removeExisting={data.remove_signature}
                        maxSizeKb={uploadLimits.image_max_kb}
                        error={error('signature')}
                        onChange={(file) => {
                            setData('signature', file);
                            if (file) {
                                setData('remove_signature', false);
                            }
                        }}
                        onRemove={() => {
                            setData('signature', null);
                            setData('remove_signature', true);
                        }}
                    />
                    <EmployeeNidUploader
                        id="nid_document"
                        label="Employee NID Document"
                        value={data.nid_document}
                        existingPath={existingFiles.nid_document_path}
                        existingUrl={existingFiles.nid_document_url}
                        removeExisting={data.remove_nid_document}
                        maxSizeKb={uploadLimits.nid_max_kb}
                        error={error('nid_document')}
                        onChange={(file) => {
                            setData('nid_document', file);
                            if (file) {
                                setData('remove_nid_document', false);
                            }
                        }}
                        onRemove={() => {
                            setData('nid_document', null);
                            setData('remove_nid_document', true);
                        }}
                    />
                </div>

                <div className="flex justify-end gap-4">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => router.get('/employees')}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? (
                            <LoaderCircle
                                className="h-4 w-4 animate-spin"
                                aria-hidden="true"
                            />
                        ) : (
                            <Save className="h-4 w-4" aria-hidden="true" />
                        )}
                        {processing ? `${submitLabel}...` : submitLabel}
                    </Button>
                </div>
            </form>

            {salaryOpen && (
                <SalaryStructureModal
                    open
                    value={data.salary_structure}
                    errors={errors}
                    onOpenChange={setSalaryOpen}
                    onSave={(structure) =>
                        setData('salary_structure', structure)
                    }
                />
            )}
        </>
    );
}
