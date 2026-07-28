import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Car } from 'lucide-react';

interface VehicleDetails {
    id: number;
    vehicle_type?: string;
    vehicle_name?: string;
    vehicle_number: string;
    reg_date?: string;
    status: boolean;
    customer?: {
        id: number;
        name: string;
        proprietor_name?: string;
        mobile?: string;
    };
    products: Array<{
        id: number;
        product_name: string;
        sort_order: number;
    }>;
    created_at?: string;
}

export default function VehicleShow({ vehicle }: { vehicle: VehicleDetails }) {
    return (
        <AppLayout>
            <Head title={`Vehicle - ${vehicle.vehicle_number}`} />

            <div className="space-y-6 p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            {vehicle.vehicle_name || vehicle.vehicle_number}
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Vehicle details and assigned products
                        </p>
                    </div>
                    <Button
                        variant="secondary"
                        onClick={() => router.get('/vehicles')}
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to List
                    </Button>
                </div>

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 dark:text-white">
                            <Car className="h-5 w-5" />
                            Vehicle Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-6 md:grid-cols-2">
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    Customer
                                </dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {vehicle.customer?.name || 'N/A'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    Proprietor Name
                                </dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {vehicle.customer?.proprietor_name || 'N/A'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    Vehicle Number
                                </dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {vehicle.vehicle_number}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    Vehicle Name
                                </dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {vehicle.vehicle_name || 'N/A'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    Vehicle Type
                                </dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {vehicle.vehicle_type || 'N/A'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    Registration Date
                                </dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {vehicle.reg_date
                                        ? new Date(
                                              `${vehicle.reg_date}T00:00:00`,
                                          ).toLocaleDateString('en-GB')
                                        : 'N/A'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    Status
                                </dt>
                                <dd className="mt-1">
                                    <Badge
                                        variant={
                                            vehicle.status
                                                ? 'success'
                                                : 'destructive'
                                        }
                                    >
                                        {vehicle.status ? 'Active' : 'Inactive'}
                                    </Badge>
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="dark:text-white">
                            Assigned Products
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {vehicle.products.length > 0 ? (
                            <ol className="divide-y border dark:divide-gray-700 dark:border-gray-700">
                                {vehicle.products.map((product, index) => (
                                    <li
                                        key={product.id}
                                        className="flex items-center gap-3 px-4 py-3 text-sm text-gray-900 dark:text-gray-100"
                                    >
                                        <span className="w-6 text-right text-gray-500 dark:text-gray-400">
                                            {index + 1}.
                                        </span>
                                        {product.product_name}
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No products assigned.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
