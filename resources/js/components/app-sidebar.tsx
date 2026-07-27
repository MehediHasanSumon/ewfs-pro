import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Building,
    Car,
    ChevronDown,
    Clock,
    CreditCard,
    Database,
    DollarSign,
    FileText,
    Fuel,
    HandCoins,
    LayoutGrid,
    MessageSquare,
    Package,
    Settings,
    Shield,
    ShoppingCart,
    Truck,
    UserCheck,
    Users,
    Warehouse,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { usePermission } from '@/hooks/usePermission';
import AppLogo from './app-logo';

const mainNavItems = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
        permission: 'view-dashboard',
    },
    {
        title: 'General Setting',
        icon: Settings,
        children: [
            {
                title: 'Company Setting',
                href: '/company-settings',
                icon: Building,
                permission: 'view-company-setting',
            },
            { title: 'Shift', href: '/shifts', icon: Clock, permission: 'view-shift' },
        ],
    },
    {
        title: 'Dispenser',
        icon: Fuel,
        children: [
            { title: 'Credit Sales', href: '/credit-sales', icon: CreditCard, permission: 'view-credit-sale' },
            {
                title: 'Dispensers Calculation',
                href: '/product/dispensers-reading',
                icon: BarChart3,
                permission: 'view-dispenser-reading',
            },
            { title: 'Dispensers Setting', href: '/dispensers', icon: Fuel, permission: 'view-dispenser' },
            { title: 'Shift Closed List', href: '/shift-closed-list', icon: FileText, permission: 'view-shift-closed-list' },
            { title: 'Monthly Dispenser Report', href: '/reports/monthly-dispenser-report', icon: BarChart3 },
        ],
    },
    {
        title: 'Customer',
        icon: Users,
        children: [
            { title: 'Customers', href: '/customers', icon: Users, permission: 'view-customer' },
            { title: 'Vehicles', href: '/vehicles', icon: Car, permission: 'view-vehicle' },
            {
                title: 'Customer Details Bill',
                href: '/customer-details-bill',
                icon: FileText,
                permission: 'view-customer',
            },
            {
                title: 'Customer Summary Bill',
                href: '/customer-summary-bill',
                icon: FileText,
                permission: 'view-customer',
            },
            {
                title: 'Daily Statement Report',
                href: '/daily-statement',
                icon: BarChart3,
                permission: 'view-customer',
            },
        ],
    },
    {
        title: 'Supplier',
        icon: Truck,
        children: [{ title: 'Suppliers', href: '/suppliers', icon: Truck, permission: 'view-supplier' }],
    },
    {
        title: 'Products',
        icon: Package,
        children: [
            { title: 'Products', href: '/products', icon: Package, permission: 'view-product' },
            {
                title: 'Product Rates',
                href: '/product-rates',
                icon: DollarSign,
                permission: 'view-product-rate',
            },
            { title: 'Categories', href: '/categories', icon: Package, permission: 'view-category' },
            { title: 'Units', href: '/units', icon: Package, permission: 'view-unit' },
        ],
    },
    {
        title: 'Product Stock',
        icon: Warehouse,
        children: [
            { title: 'Stocks', href: '/stocks', icon: Package, permission: 'view-stock' },
            {
                title: 'Today Stock Report',
                href: '/stock-report',
                icon: BarChart3,
                permission: 'view-stock',
            },
        ],
    },
    {
        title: 'Purchase',
        icon: ShoppingCart,
        children: [
            { title: 'Purchase', href: '/purchases', icon: ShoppingCart, permission: 'view-purchase' },
            {
                title: 'Purchase Report Details',
                href: '/purchase-report-details',
                icon: FileText,
                permission: 'view-purchase',
            },
        ],
    },
    {
        title: 'Sales',
        icon: DollarSign,
        children: [
            { title: 'Sales', href: '/sales', icon: DollarSign, permission: 'view-sale' },
            { title: 'White Sales', href: '/white-sales', icon: ShoppingCart, permission: 'view-white-sale' },
            {
                title: 'Customer Sales Reports',
                href: '/customer-wise-sales-reports',
                icon: BarChart3,
                permission: 'view-sale',
            },
        ],
    },
    {
        title: 'Accounts',
        icon: Database,
        children: [
            { title: 'Groups', href: '/groups', icon: Database, permission: 'view-group' },
            { title: 'Accounts', href: '/accounts', icon: Database, permission: 'view-account' },
            { title: 'Liability and Assets', href: '/liability-assets', icon: BarChart3, permission: 'view-account' },
            { title: 'Balance Sheet', href: '/balance-sheet', icon: BarChart3, permission: 'view-account' },
            {
                title: 'General Ledger',
                href: '/general-ledger',
                icon: BarChart3,
                permission: 'view-account',
            },
            {
                title: 'Cash Book Ledger',
                href: '/cash-book-ledger',
                icon: BarChart3,
                permission: 'view-account',
            },
            {
                title: 'Bank Book Ledger',
                href: '/bank-book-ledger',
                icon: BarChart3,
                permission: 'view-account',
            },
            {
                title: 'Received Voucher',
                href: '/vouchers/received',
                icon: FileText,
                permission: 'view-voucher',
            },
            {
                title: 'Payment Voucher',
                href: '/vouchers/payment',
                icon: FileText,
                permission: 'view-voucher',
            },
            {
                title: 'Office Payment',
                href: '/office-payments',
                icon: CreditCard,
                permission: 'view-office-payment',
            },
        ],
    },
    {
        title: 'Loans Payable',
        icon: HandCoins,
        children: [
            { title: 'Loan List', href: '/loans', icon: HandCoins, permission: 'view-loan' },
        ],
    },
    {
        title: 'Employee',
        icon: UserCheck,
        children: [
            { title: 'Employees', href: '/employees', icon: UserCheck, permission: 'view-employee' },
            { title: 'Employee Type', href: '/emp-types', icon: Users, permission: 'view-emp-type' },
            { title: 'Department', href: '/emp-departments', icon: Building, permission: 'view-emp-department' },
            {
                title: 'Designation',
                href: '/emp-designations',
                icon: UserCheck,
                permission: 'view-emp-designation',
            },
        ],
    },
    {
        title: 'User Management',
        icon: Shield,
        children: [
            { title: 'Users', href: '/users', icon: Users, permission: 'view-user' },
            { title: 'Roles', href: '/roles', icon: Shield, permission: 'view-role' },
            { title: 'Permissions', href: '/permissions', icon: Shield, permission: 'view-permission' },
        ],
    },
    {
        title: 'SMS Config',
        icon: MessageSquare,
        children: [
            { title: 'SMS Config', href: '/sms-configs', icon: Settings, permission: 'view-s-m-s-setting' },
            { title: 'SMS Template', href: '/sms-templates', icon: FileText, permission: 'view-s-m-s-template' },
            { title: 'SMS Logs', href: '/sms-logs', icon: BarChart3, permission: 'view-s-m-s-log' },
        ],
    },
];

export function AppSidebar() {
    const { can } = usePermission();
    const [openDropdowns, setOpenDropdowns] = useState<string[]>([]);
    const { url } = usePage();

    const toggleDropdown = (title: string) => {
        setOpenDropdowns((prev) =>
            prev.includes(title)
                ? prev.filter((item) => item !== title)
                : [title],
        );
    };

    const isActive = (href: string) => {
        if (href === '/' || href === '/dashboard')
            return url === '/' || url === '/dashboard';
        return url.startsWith(href);
    };

    const isParentActive = (children: { href: string }[]) => {
        return children.some((child) => isActive(child.href));
    };

    useEffect(() => {
        const activeParents = mainNavItems
            .filter((item) => item.children && isParentActive(item.children))
            .map((item) => item.title);
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setOpenDropdowns((prev) => [...new Set([...prev, ...activeParents])]);
    }, [url]);

    return (
        <Sidebar collapsible="icon" variant="inset" className="h-screen w-72">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent className="scrollbar-ultra-thin overflow-y-auto px-4">
                <nav className="space-y-1">
                    {mainNavItems.map((item, index) => {
                        if (item.children) {
                            const visibleChildren = item.children.filter((child) =>
                                child.permission ? can(child.permission) : true
                            );
                            if (visibleChildren.length === 0) return null;

                            return (
                                <div key={index}>
                                    <button
                                        onClick={() => toggleDropdown(item.title)}
                                        className={`flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-[13px] font-semibold transition-colors ${isParentActive(visibleChildren)
                                            ? 'bg-indigo-600 text-white'
                                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
                                            }`}
                                    >
                                        <item.icon className="h-5 w-5" />
                                        <span className="ml-1 flex-1 text-left">{item.title}</span>
                                        <ChevronDown
                                            className={`h-4 w-4 transition-transform ${openDropdowns.includes(item.title) ? 'rotate-180' : ''
                                                }`}
                                        />
                                    </button>
                                    <div
                                        className={`mt-1 ml-8 space-y-1 overflow-hidden transition-all duration-300 ease-in-out ${openDropdowns.includes(item.title)
                                            ? 'max-h-[500px] opacity-100'
                                            : 'max-h-0 opacity-0'
                                            }`}
                                    >
                                        {visibleChildren.map((child, childIndex) => (
                                            <Link
                                                key={childIndex}
                                                href={child.href}
                                                className={`relative flex items-center gap-3 rounded-lg px-3 py-2 text-[13px] transition-colors ${isActive(child.href)
                                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200'
                                                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100'
                                                    }`}
                                            >
                                                <span
                                                    className={`h-2 w-2 flex-shrink-0 rounded-full ${isActive(child.href)
                                                        ? 'bg-indigo-500'
                                                        : 'bg-gray-400 dark:bg-gray-500'
                                                        }`}
                                                ></span>
                                                <span>{child.title}</span>
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            );
                        } else {
                            if (!can(item.permission)) return null;

                            return (
                                <Link
                                    key={index}
                                    href={item.href || '#'}
                                    className={`flex items-center gap-3 rounded-lg px-3 py-2 text-[13px] font-semibold transition-colors ${isActive(item.href || '#')
                                        ? 'bg-indigo-600 text-white'
                                        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
                                        }`}
                                >
                                    <item.icon className="h-5 w-5" />
                                    <span className="ml-1">{item.title}</span>
                                </Link>
                            );
                        }
                    })}
                </nav>
            </SidebarContent>
        </Sidebar>
    );
}
