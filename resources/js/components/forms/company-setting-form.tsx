import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFocusFirstError } from '@/hooks/use-focus-first-error';
import { Link } from '@inertiajs/react';
import { LoaderCircle, Save } from 'lucide-react';
import { type FormEvent, useRef } from 'react';

export interface CompanySettingFormData {
    company_name: string;
    company_details: string;
    proprietor_name: string;
    company_address: string;
    factory_address: string;
    company_mobile: string;
    company_phone: string;
    company_email: string;
    trade_license: string;
    tin_no: string;
    bin_no: string;
    vat_no: string;
    vat_rate: string;
    currency: string;
    company_logo: File | null;
    pdf_watermark_image?: File | null;
    remove_pdf_watermark?: boolean;
    is_registration: boolean;
    status: number;
}

interface CompanySettingFormProps {
    data: CompanySettingFormData;
    errors: Partial<Record<keyof CompanySettingFormData, string>>;
    processing: boolean;
    submitLabel: string;
    currentLogo?: string;
    currentWatermark?: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    setData: <Key extends keyof CompanySettingFormData>(
        key: Key,
        value: CompanySettingFormData[Key],
    ) => void;
    setError: (key: keyof CompanySettingFormData, message: string) => void;
    clearErrors: (...keys: Array<keyof CompanySettingFormData>) => void;
}

const MAX_LOGO_SIZE = 5 * 1024 * 1024;

export function CompanySettingForm({
    data,
    errors,
    processing,
    submitLabel,
    currentLogo,
    currentWatermark,
    onSubmit,
    setData,
    setError,
    clearErrors,
}: CompanySettingFormProps) {
    const formRef = useRef<HTMLFormElement>(null);
    useFocusFirstError(formRef, errors);

    const errorProps = (field: keyof CompanySettingFormData) => ({
        'aria-invalid': Boolean(errors[field]),
        'aria-describedby': errors[field] ? `${field}-error` : undefined,
    });

    const handleLogoChange = (file: File | null) => {
        clearErrors('company_logo');

        if (!file) {
            setData('company_logo', null);
            return;
        }

        if (!file.type.startsWith('image/')) {
            setData('company_logo', null);
            setError('company_logo', 'Please select a valid image file.');
            return;
        }

        if (file.size > MAX_LOGO_SIZE) {
            setData('company_logo', null);
            setError('company_logo', 'The logo must not exceed 5 MB.');
            return;
        }

        setData('company_logo', file);
    };

    const handleWatermarkChange = (file: File | null) => {
        clearErrors('pdf_watermark_image');
        if (data.remove_pdf_watermark) {
            setData('remove_pdf_watermark', false);
        }

        if (!file) {
            setData('pdf_watermark_image', null);
            return;
        }

        if (!file.type.startsWith('image/')) {
            setData('pdf_watermark_image', null);
            setError('pdf_watermark_image', 'Please select a valid image file.');
            return;
        }

        if (file.size > MAX_LOGO_SIZE) {
            setData('pdf_watermark_image', null);
            setError('pdf_watermark_image', 'The watermark image must not exceed 5 MB.');
            return;
        }

        setData('pdf_watermark_image', file);
    };

    return (
        <form
            ref={formRef}
            onSubmit={onSubmit}
            className="space-y-6"
            aria-busy={processing}
        >
            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <Label htmlFor="company_name" className="dark:text-gray-200">
                        Company Name *
                    </Label>
                    <Input
                        id="company_name"
                        name="company_name"
                        value={data.company_name}
                        onChange={(event) =>
                            setData('company_name', event.target.value)
                        }
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        required
                        maxLength={255}
                        autoFocus
                        {...errorProps('company_name')}
                    />
                    <InputError
                        id="company_name-error"
                        message={errors.company_name}
                    />
                </div>

                <div>
                    <Label
                        htmlFor="company_details"
                        className="dark:text-gray-200"
                    >
                        Company Details
                    </Label>
                    <Input
                        id="company_details"
                        name="company_details"
                        value={data.company_details}
                        onChange={(event) =>
                            setData('company_details', event.target.value)
                        }
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        {...errorProps('company_details')}
                    />
                    <InputError
                        id="company_details-error"
                        message={errors.company_details}
                    />
                </div>

                <div>
                    <Label
                        htmlFor="proprietor_name"
                        className="dark:text-gray-200"
                    >
                        Proprietor Name
                    </Label>
                    <Input
                        id="proprietor_name"
                        name="proprietor_name"
                        value={data.proprietor_name}
                        onChange={(event) =>
                            setData('proprietor_name', event.target.value)
                        }
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        maxLength={255}
                        {...errorProps('proprietor_name')}
                    />
                    <InputError
                        id="proprietor_name-error"
                        message={errors.proprietor_name}
                    />
                </div>

                <div>
                    <Label
                        htmlFor="company_address"
                        className="dark:text-gray-200"
                    >
                        Company Address
                    </Label>
                    <Input
                        id="company_address"
                        name="company_address"
                        value={data.company_address}
                        onChange={(event) =>
                            setData('company_address', event.target.value)
                        }
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        {...errorProps('company_address')}
                    />
                    <InputError
                        id="company_address-error"
                        message={errors.company_address}
                    />
                </div>

                <div>
                    <Label
                        htmlFor="factory_address"
                        className="dark:text-gray-200"
                    >
                        Factory Address
                    </Label>
                    <Input
                        id="factory_address"
                        name="factory_address"
                        value={data.factory_address}
                        onChange={(event) =>
                            setData('factory_address', event.target.value)
                        }
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        {...errorProps('factory_address')}
                    />
                    <InputError
                        id="factory_address-error"
                        message={errors.factory_address}
                    />
                </div>

                <div>
                    <Label
                        htmlFor="company_mobile"
                        className="dark:text-gray-200"
                    >
                        Cell Number
                    </Label>
                    <Input
                        id="company_mobile"
                        name="company_mobile"
                        type="tel"
                        value={data.company_mobile}
                        onChange={(event) =>
                            setData('company_mobile', event.target.value)
                        }
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        maxLength={255}
                        autoComplete="tel"
                        {...errorProps('company_mobile')}
                    />
                    <InputError
                        id="company_mobile-error"
                        message={errors.company_mobile}
                    />
                </div>

                <div>
                    <Label
                        htmlFor="company_phone"
                        className="dark:text-gray-200"
                    >
                        Phone Number
                    </Label>
                    <Input
                        id="company_phone"
                        name="company_phone"
                        type="tel"
                        value={data.company_phone}
                        onChange={(event) =>
                            setData('company_phone', event.target.value)
                        }
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        maxLength={255}
                        autoComplete="tel"
                        {...errorProps('company_phone')}
                    />
                    <InputError
                        id="company_phone-error"
                        message={errors.company_phone}
                    />
                </div>

                <div>
                    <Label
                        htmlFor="company_email"
                        className="dark:text-gray-200"
                    >
                        E-mail
                    </Label>
                    <Input
                        id="company_email"
                        name="company_email"
                        type="email"
                        value={data.company_email}
                        onChange={(event) =>
                            setData('company_email', event.target.value)
                        }
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        maxLength={255}
                        autoComplete="email"
                        {...errorProps('company_email')}
                    />
                    <InputError
                        id="company_email-error"
                        message={errors.company_email}
                    />
                </div>

                <div>
                    <Label
                        htmlFor="trade_license"
                        className="dark:text-gray-200"
                    >
                        Trade License No
                    </Label>
                    <Input
                        id="trade_license"
                        name="trade_license"
                        value={data.trade_license}
                        onChange={(event) =>
                            setData('trade_license', event.target.value)
                        }
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        maxLength={255}
                        {...errorProps('trade_license')}
                    />
                    <InputError
                        id="trade_license-error"
                        message={errors.trade_license}
                    />
                </div>

                <div>
                    <Label htmlFor="tin_no" className="dark:text-gray-200">
                        e-TIN No
                    </Label>
                    <Input
                        id="tin_no"
                        name="tin_no"
                        value={data.tin_no}
                        onChange={(event) =>
                            setData('tin_no', event.target.value)
                        }
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        maxLength={255}
                        {...errorProps('tin_no')}
                    />
                    <InputError id="tin_no-error" message={errors.tin_no} />
                </div>

                <div>
                    <Label htmlFor="bin_no" className="dark:text-gray-200">
                        BIN No
                    </Label>
                    <Input
                        id="bin_no"
                        name="bin_no"
                        value={data.bin_no}
                        onChange={(event) =>
                            setData('bin_no', event.target.value)
                        }
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        maxLength={255}
                        {...errorProps('bin_no')}
                    />
                    <InputError id="bin_no-error" message={errors.bin_no} />
                </div>

                <div>
                    <Label htmlFor="vat_no" className="dark:text-gray-200">
                        VAT No
                    </Label>
                    <Input
                        id="vat_no"
                        name="vat_no"
                        value={data.vat_no}
                        onChange={(event) =>
                            setData('vat_no', event.target.value)
                        }
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        maxLength={255}
                        {...errorProps('vat_no')}
                    />
                    <InputError id="vat_no-error" message={errors.vat_no} />
                </div>

                <div>
                    <Label htmlFor="vat_rate" className="dark:text-gray-200">
                        VAT Rate
                    </Label>
                    <Input
                        id="vat_rate"
                        name="vat_rate"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        inputMode="decimal"
                        value={data.vat_rate}
                        onChange={(event) =>
                            setData('vat_rate', event.target.value)
                        }
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        {...errorProps('vat_rate')}
                    />
                    <InputError id="vat_rate-error" message={errors.vat_rate} />
                </div>

                <div>
                    <Label htmlFor="currency" className="dark:text-gray-200">
                        Currency
                    </Label>
                    <Input
                        id="currency"
                        name="currency"
                        value={data.currency}
                        onChange={(event) =>
                            setData('currency', event.target.value.toUpperCase())
                        }
                        className="uppercase dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        minLength={3}
                        maxLength={3}
                        pattern="[A-Za-z]{3}"
                        placeholder="BDT"
                        {...errorProps('currency')}
                    />
                    <InputError id="currency-error" message={errors.currency} />
                </div>

                <div>
                    <Label
                        htmlFor="is_registration"
                        className="dark:text-gray-200"
                    >
                        User Registration
                    </Label>
                    <select
                        id="is_registration"
                        name="is_registration"
                        value={data.is_registration ? 1 : 0}
                        onChange={(event) =>
                            setData(
                                'is_registration',
                                event.target.value === '1',
                            )
                        }
                        className="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                        <option value={1}>Enable</option>
                        <option value={0}>Disable</option>
                    </select>
                    <InputError
                        id="is_registration-error"
                        message={errors.is_registration}
                    />
                </div>

                <div>
                    <Label htmlFor="status" className="dark:text-gray-200">
                        Status
                    </Label>
                    <select
                        id="status"
                        name="status"
                        value={data.status}
                        onChange={(event) =>
                            setData('status', Number(event.target.value))
                        }
                        className="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                        <option value={1}>Active</option>
                        <option value={0}>Disable</option>
                    </select>
                    <InputError id="status-error" message={errors.status} />
                </div>

                <div>
                    <Label
                        htmlFor="company_logo"
                        className="dark:text-gray-200"
                    >
                        Company Logo
                    </Label>
                    <Input
                        id="company_logo"
                        name="company_logo"
                        type="file"
                        accept="image/*"
                        onChange={(event) =>
                            handleLogoChange(event.target.files?.[0] || null)
                        }
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        {...errorProps('company_logo')}
                    />
                    {currentLogo && (
                        <p className="mt-1 text-sm text-gray-500">
                            Current: {currentLogo.split('/').pop()}
                        </p>
                    )}
                    <InputError
                        id="company_logo-error"
                        message={errors.company_logo}
                    />
                </div>

                <div>
                    <Label
                        htmlFor="pdf_watermark_image"
                        className="dark:text-gray-200"
                    >
                        PDF Watermark Image
                    </Label>
                    <Input
                        id="pdf_watermark_image"
                        name="pdf_watermark_image"
                        type="file"
                        accept="image/*"
                        onChange={(event) =>
                            handleWatermarkChange(event.target.files?.[0] || null)
                        }
                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        {...errorProps('pdf_watermark_image')}
                    />
                    {currentWatermark && !data.remove_pdf_watermark && (
                        <div className="mt-1 flex items-center justify-between">
                            <p className="text-sm text-gray-500">
                                Current: {currentWatermark.split('/').pop()}
                            </p>
                            <button
                                type="button"
                                onClick={() => setData('remove_pdf_watermark', true)}
                                className="text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400"
                            >
                                Remove Watermark
                            </button>
                        </div>
                    )}
                    {data.remove_pdf_watermark && (
                        <p className="mt-1 text-xs text-amber-600 dark:text-amber-400">
                            Watermark will be removed upon saving.
                        </p>
                    )}
                    <InputError
                        id="pdf_watermark_image-error"
                        message={errors.pdf_watermark_image}
                    />
                </div>
            </div>

            <div className="flex flex-col-reverse justify-end gap-2 sm:flex-row">
                <Link
                    href="/company-settings"
                    onClick={(event) => {
                        if (processing) {
                            event.preventDefault();
                        }
                    }}
                >
                    <Button
                        type="button"
                        variant="secondary"
                        disabled={processing}
                        className="w-full sm:w-auto"
                    >
                        Cancel
                    </Button>
                </Link>
                <Button
                    type="submit"
                    disabled={processing}
                    className="w-full sm:w-auto"
                >
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
    );
}
