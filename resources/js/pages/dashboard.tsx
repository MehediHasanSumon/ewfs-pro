import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { usePermission } from '@/hooks/usePermission';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Banknote, TrendingUp, ShoppingCart, Receipt } from 'lucide-react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
} from 'chart.js';
import { Bar, Doughnut } from 'react-chartjs-2';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend
);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

interface DashboardProps {
    stats: {
        cashInHand: number;
        outstandingBalance: number;
        cashSale: number;
        officeExpenses: number;
    };
    chartData: {
        monthlySales: Array<{month: string; total: number}>;
        monthlyPurchases: Array<{month: string; total: number}>;
        stockData: Array<{product_name: string; current_stock: number}>;
        totalStock: number;
        outstandingCustomers: Array<{customer: string; mobile_number: string; balance: number}>;
    };
    products: Array<{id: number; product_name: string}>;
}

export default function Dashboard({ stats, chartData, products }: DashboardProps) {
    const { can, permissions } = usePermission();
    const [salesViewMode, setSalesViewMode] = useState('graph');
    const [purchaseViewMode, setPurchaseViewMode] = useState('graph');
    const [stockViewMode, setStockViewMode] = useState('graph');
    const [outstandingViewMode, setOutstandingViewMode] = useState('data');
    const [selectedSaleType, setSelectedSaleType] = useState('sale');
    const [selectedPurchaseType, setSelectedPurchaseType] = useState('purchase');
    const [selectedSaleProduct, setSelectedSaleProduct] = useState('all');
    const [selectedPurchaseProduct, setSelectedPurchaseProduct] = useState('all');
    const [salesData, setSalesData] = useState(chartData.monthlySales);
    const [purchasesData, setPurchasesData] = useState(chartData.monthlyPurchases);
    
    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-BD', {
            style: 'currency',
            currency: 'BDT',
            minimumFractionDigits: 2
        }).format(amount);
    };

    const fetchChartData = async (productId: string, type: 'sale' | 'purchase') => {
        try {
            const response = await axios.get('/dashboard/chart-data', {
                params: { product_id: productId, type }
            });
            
            if (type === 'sale') {
                setSalesData(response.data);
            } else {
                setPurchasesData(response.data);
            }
        } catch (error) {
            console.error('Error fetching chart data:', error);
        }
    };

    const handleSaleTypeChange = (value: string) => {
        setSelectedSaleType(value);
        setSelectedSaleProduct('all');
        fetchChartData('all', value as 'sale' | 'purchase');
    };

    const handlePurchaseTypeChange = (value: string) => {
        setSelectedPurchaseType(value);
        setSelectedPurchaseProduct('all');
        fetchChartData('all', value as 'sale' | 'purchase');
    };

    const handleSaleProductChange = (value: string) => {
        setSelectedSaleProduct(value);
        fetchChartData(value, selectedSaleType as 'sale' | 'purchase');
    };

    const handlePurchaseProductChange = (value: string) => {
        setSelectedPurchaseProduct(value);
        fetchChartData(value, selectedPurchaseType as 'sale' | 'purchase');
    };

    // Dynamic chart data from backend
    const salesChartData = {
        labels: salesData.map(item => item.month),
        datasets: [
            {
                label: selectedSaleType === 'sale' ? 'Sale' : 'Purchase',
                data: salesData.map(item => item.total),
                backgroundColor: selectedSaleType === 'sale' ? '#facc15' : '#60a5fa',
            },
        ],
    };

    const purchaseChartData = {
        labels: purchasesData.map(item => item.month),
        datasets: [
            {
                label: selectedPurchaseType === 'sale' ? 'Sale' : 'Purchase',
                data: purchasesData.map(item => item.total),
                backgroundColor: selectedPurchaseType === 'sale' ? '#facc15' : '#60a5fa',
            },
        ],
    };

    const stockChartData = {
        labels: chartData.stockData.map(item => item.product_name),
        datasets: [
            {
                data: chartData.stockData.map(item => item.current_stock),
                backgroundColor: ['#22c55e', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6'],
            },
        ],
    };

    const totalStock = chartData.totalStock;

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false,
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleColor: 'white',
                bodyColor: 'white',
                borderColor: 'rgba(255, 255, 255, 0.1)',
                borderWidth: 1,
            },
        },
        scales: {
            y: {
                display: false,
                grid: {
                    display: false,
                },
            },
            x: {
                display: false,
                grid: {
                    display: false,
                },
            },
        },
        elements: {
            bar: {
                borderRadius: 4,
                borderSkipped: false,
            },
        },
    };

    const doughnutOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false,
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleColor: 'white',
                bodyColor: 'white',
            },
        },
        cutout: '60%',
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-4">
                    <Card className="border-l-4 border-l-green-500 bg-gradient-to-r from-green-100 to-green-200 dark:from-green-900 dark:to-green-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-green-700 dark:text-green-300">Cash in Hand</CardTitle>
                            <Banknote className="h-4 w-4 text-green-600 dark:text-green-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-800 dark:text-green-200">{formatCurrency(stats?.cashInHand || 0)}</div>
                        </CardContent>
                    </Card>
                    
                    <Card className="border-l-4 border-l-blue-500 bg-gradient-to-r from-blue-100 to-blue-200 dark:from-blue-900 dark:to-blue-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-blue-700 dark:text-blue-300">Outstanding Balance</CardTitle>
                            <TrendingUp className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-800 dark:text-blue-200">{formatCurrency(stats?.outstandingBalance || 0)}</div>
                        </CardContent>
                    </Card>
                    
                    <Card className="border-l-4 border-l-purple-500 bg-gradient-to-r from-purple-100 to-purple-200 dark:from-purple-900 dark:to-purple-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-purple-700 dark:text-purple-300">Cash Sale</CardTitle>
                            <ShoppingCart className="h-4 w-4 text-purple-600 dark:text-purple-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-purple-800 dark:text-purple-200">{formatCurrency(stats?.cashSale || 0)}</div>
                        </CardContent>
                    </Card>
                    
                    <Card className="border-l-4 border-l-red-500 bg-gradient-to-r from-red-100 to-red-200 dark:from-red-900 dark:to-red-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-red-700 dark:text-red-300">Office Expenses</CardTitle>
                            <Receipt className="h-4 w-4 text-red-600 dark:text-red-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-800 dark:text-red-200">{formatCurrency(stats?.officeExpenses || 0)}</div>
                        </CardContent>
                    </Card>
                </div>
                
                <div className="grid auto-rows-min gap-6 md:grid-cols-2">
                    {/* Month Wise Sales Report */}
                    <Card className="shadow-sm border-0 bg-white dark:bg-gray-900">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4 border-b border-gray-100 dark:border-gray-800">
                            <div>
                                <div className="flex gap-2 mb-3">
                                    <Select value={selectedSaleType} onValueChange={handleSaleTypeChange}>
                                        <SelectTrigger className="w-20 h-8 border-gray-200 dark:border-gray-700">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="sale">Sale</SelectItem>
                                            <SelectItem value="purchase">Purchase</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Select value={selectedSaleProduct} onValueChange={handleSaleProductChange}>
                                        <SelectTrigger className="w-24 h-8 border-gray-200 dark:border-gray-700">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Items</SelectItem>
                                            {products.map(product => (
                                                <SelectItem key={product.id} value={product.id.toString()}>
                                                    {product.product_name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <CardTitle className="text-base font-semibold text-gray-800 dark:text-gray-200">
                                    Month Wise {selectedSaleType === 'sale' ? 'Sales' : 'Purchase'} Report
                                </CardTitle>
                            </div>
                            <div className="flex gap-2">
                                <button 
                                    onClick={() => setSalesViewMode('graph')}
                                    className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${
                                        salesViewMode === 'graph' 
                                            ? 'bg-blue-500 hover:bg-blue-600 text-white' 
                                            : 'border border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800'
                                    }`}
                                >
                                    Graph
                                </button>
                                <button 
                                    onClick={() => setSalesViewMode('data')}
                                    className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${
                                        salesViewMode === 'data' 
                                            ? 'bg-blue-500 hover:bg-blue-600 text-white' 
                                            : 'border border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800'
                                    }`}
                                >
                                    Data
                                </button>
                            </div>
                        </CardHeader>
                        <CardContent className="pt-6">
                            {salesViewMode === 'graph' ? (
                                <div className="h-52 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 rounded-lg p-4">
                                    <Bar data={salesChartData} options={chartOptions} />
                                </div>
                            ) : (
                                <div className="h-52 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 rounded-lg overflow-auto">
                                    <table className="w-full text-xs">
                                        <thead className="bg-gray-200 dark:bg-gray-700 sticky top-0">
                                            <tr>
                                                <th className="p-3 text-left font-semibold text-gray-700 dark:text-gray-300">Month</th>
                                                <th className="p-3 text-right font-semibold text-gray-700 dark:text-gray-300">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {salesData.map((item, index) => (
                                                <tr key={index} className="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                                    <td className="p-3 text-gray-800 dark:text-gray-200">{item.month}</td>
                                                    <td className="p-3 text-right font-medium text-gray-800 dark:text-gray-200">{formatCurrency(item.total)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            <div className="flex gap-4 mt-4 text-xs">
                                <div className="flex items-center gap-2">
                                    <div className={`w-3 h-3 rounded-sm ${selectedSaleType === 'sale' ? 'bg-yellow-400' : 'bg-blue-400'}`}></div>
                                    <span className="text-gray-600 dark:text-gray-400">{selectedSaleType === 'sale' ? 'Sale' : 'Purchase'}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    
                    {/* Available Stock */}
                    <Card className="shadow-sm border-0 bg-white dark:bg-gray-900">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4 border-b border-gray-100 dark:border-gray-800">
                            <CardTitle className="text-base font-semibold text-gray-800 dark:text-gray-200">Available Stock</CardTitle>
                            <div className="flex gap-2">
                                <button 
                                    onClick={() => setStockViewMode('graph')}
                                    className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${
                                        stockViewMode === 'graph' 
                                            ? 'bg-blue-500 hover:bg-blue-600 text-white' 
                                            : 'border border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800'
                                    }`}
                                >
                                    Graph
                                </button>
                                <button 
                                    onClick={() => setStockViewMode('data')}
                                    className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${
                                        stockViewMode === 'data' 
                                            ? 'bg-blue-500 hover:bg-blue-600 text-white' 
                                            : 'border border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800'
                                    }`}
                                >
                                    Data
                                </button>
                            </div>
                        </CardHeader>
                        <CardContent className="pt-6">
                            {stockViewMode === 'graph' ? (
                                <div className="h-52 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 rounded-lg p-4 flex items-center justify-center">
                                    <div className="w-36 h-36 relative">
                                        <Doughnut data={stockChartData} options={doughnutOptions} />
                                        <div className="absolute inset-0 flex items-center justify-center">
                                            <div className="text-center">
                                                <div className="text-lg font-bold text-gray-800 dark:text-gray-200">{totalStock.toLocaleString()}</div>
                                                <div className="text-xs text-gray-500 dark:text-gray-400">Total Stock</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="h-52 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 rounded-lg overflow-auto">
                                    <table className="w-full text-xs">
                                        <thead className="bg-gray-200 dark:bg-gray-700 sticky top-0">
                                            <tr>
                                                <th className="p-3 text-left font-semibold text-gray-700 dark:text-gray-300">Product</th>
                                                <th className="p-3 text-right font-semibold text-gray-700 dark:text-gray-300">Stock</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {chartData.stockData.map((item, index) => (
                                                <tr key={index} className="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                                    <td className="p-3 text-gray-800 dark:text-gray-200">{item.product_name}</td>
                                                    <td className="p-3 text-right font-medium text-gray-800 dark:text-gray-200">{item.current_stock.toLocaleString()}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            <div className="flex gap-4 mt-4 text-xs justify-center flex-wrap">
                                {chartData.stockData.slice(0, 5).map((item, index) => (
                                    <div key={item.product_name} className="flex items-center gap-2">
                                        <div className={`w-3 h-3 rounded-sm`} style={{backgroundColor: ['#22c55e', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6'][index]}}></div>
                                        <span className="text-gray-600 dark:text-gray-400">{item.product_name}</span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                    
                    {/* Year Wise Purchase Comparison */}
                    <Card className="shadow-sm border-0 bg-white dark:bg-gray-900">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4 border-b border-gray-100 dark:border-gray-800">
                            <div>
                                <div className="flex gap-2 mb-3">
                                    <Select value={selectedPurchaseType} onValueChange={handlePurchaseTypeChange}>
                                        <SelectTrigger className="w-24 h-8 border-gray-200 dark:border-gray-700">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="purchase">Purchase</SelectItem>
                                            <SelectItem value="sale">Sale</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Select value={selectedPurchaseProduct} onValueChange={handlePurchaseProductChange}>
                                        <SelectTrigger className="w-28 h-8 border-gray-200 dark:border-gray-700">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Items</SelectItem>
                                            {products.map(product => (
                                                <SelectItem key={product.id} value={product.id.toString()}>
                                                    {product.product_name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <CardTitle className="text-base font-semibold text-gray-800 dark:text-gray-200">
                                    Year Wise {selectedPurchaseType === 'sale' ? 'Sales' : 'Purchase'} Comparison
                                </CardTitle>
                            </div>
                            <div className="flex gap-2">
                                <button 
                                    onClick={() => setPurchaseViewMode('graph')}
                                    className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${
                                        purchaseViewMode === 'graph' 
                                            ? 'bg-blue-500 hover:bg-blue-600 text-white' 
                                            : 'border border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800'
                                    }`}
                                >
                                    Graph
                                </button>
                                <button 
                                    onClick={() => setPurchaseViewMode('data')}
                                    className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${
                                        purchaseViewMode === 'data' 
                                            ? 'bg-blue-500 hover:bg-blue-600 text-white' 
                                            : 'border border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800'
                                    }`}
                                >
                                    Data
                                </button>
                            </div>
                        </CardHeader>
                        <CardContent className="pt-6">
                            {purchaseViewMode === 'graph' ? (
                                <div className="h-52 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 rounded-lg p-4">
                                    <Bar data={purchaseChartData} options={chartOptions} />
                                </div>
                            ) : (
                                <div className="h-52 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 rounded-lg overflow-auto">
                                    <table className="w-full text-xs">
                                        <thead className="bg-gray-200 dark:bg-gray-700 sticky top-0">
                                            <tr>
                                                <th className="p-3 text-left font-semibold text-gray-700 dark:text-gray-300">Month</th>
                                                <th className="p-3 text-right font-semibold text-gray-700 dark:text-gray-300">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {purchasesData.map((item, index) => (
                                                <tr key={index} className="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                                    <td className="p-3 text-gray-800 dark:text-gray-200">{item.month}</td>
                                                    <td className="p-3 text-right font-medium text-gray-800 dark:text-gray-200">{formatCurrency(item.total)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            <div className="flex gap-4 mt-4 text-xs">
                                <div className="flex items-center gap-2">
                                    <div className={`w-3 h-3 rounded-sm ${selectedPurchaseType === 'sale' ? 'bg-yellow-400' : 'bg-blue-400'}`}></div>
                                    <span className="text-gray-600 dark:text-gray-400">{selectedPurchaseType === 'sale' ? 'Sale' : 'Purchase'}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    
                    {/* List of Outstanding */}
                    <Card className="shadow-sm border-0 bg-white dark:bg-gray-900">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4 border-b border-gray-100 dark:border-gray-800">
                            <CardTitle className="text-base font-semibold text-gray-800 dark:text-gray-200">List of Outstanding</CardTitle>
                        </CardHeader>
                        <CardContent className="pt-6">
                            <div className="h-52 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 rounded-lg overflow-hidden">
                                <table className="w-full text-xs">
                                    <thead className="bg-gray-200 dark:bg-gray-700 sticky top-0">
                                        <tr>
                                            <th className="p-3 text-left font-semibold text-gray-700 dark:text-gray-300">Party Name</th>
                                            <th className="p-3 text-left font-semibold text-gray-700 dark:text-gray-300">Mobile</th>
                                            <th className="p-3 text-right font-semibold text-gray-700 dark:text-gray-300">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {chartData.outstandingCustomers.map((customer, index) => (
                                            <tr key={index} className="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                                <td className="p-3 text-gray-800 dark:text-gray-200">{customer.customer}</td>
                                                <td className="p-3 text-gray-600 dark:text-gray-400">{customer.mobile_number || '-'}</td>
                                                <td className="p-3 text-right font-medium text-gray-800 dark:text-gray-200">{formatCurrency(customer.balance)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                
                <div className="relative min-h-[20vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </AppLayout>
    );
}
