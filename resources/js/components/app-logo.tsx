import { usePage } from '@inertiajs/react';
import AppLogoIcon from './app-logo-icon';

interface CompanyProps {
    company: {
        name: string;
        logo: string;
    };
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    [key: string]: any;
}

export default function AppLogo() {
    const { company } = usePage<CompanyProps>().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md">
                {company?.logo ? (
                    <img src={company.logo} alt="Logo" className="size-5 object-contain" />
                ) : (
                    <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
                )}
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {company?.name || 'East West Filling Station'}
                </span>
            </div>
        </>
    );
}