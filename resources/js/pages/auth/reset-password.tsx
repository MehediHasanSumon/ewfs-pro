import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/password';
import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';

interface ResetPasswordProps {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const [showPassword, setShowPassword] = useState(false);
    const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);

    return (
        <>
            <Head title="Reset Password" />
            <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-indigo-600 via-purple-600 to-purple-700 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 p-4">
                <div className="absolute top-[10%] left-[10%] h-48 w-48 animate-pulse rounded-full bg-white/10" />
                <div className="absolute right-[10%] bottom-[10%] h-32 w-32 animate-bounce rounded-full bg-white/5" />
                <div className="absolute top-1/2 right-[20%] h-24 w-24 animate-pulse rounded-full bg-white/5" />

                <div className="mx-auto w-full max-w-6xl">
                    <div className="overflow-hidden rounded-2xl bg-white/95 dark:bg-gray-800/95 shadow-2xl backdrop-blur-lg">
                        <div className="grid md:grid-cols-2">
                            <div className="relative flex flex-col items-center justify-center overflow-hidden bg-gradient-to-br from-indigo-600 to-purple-700 p-12 text-white">
                                <div className="absolute inset-0 bg-black/10" />
                                <div className="relative z-10 text-center">
                                    <div className="mb-8 animate-bounce">
                                        <svg
                                            className="mx-auto mb-4 h-16 w-16"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fillRule="evenodd"
                                                d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                                clipRule="evenodd"
                                            />
                                        </svg>
                                    </div>
                                    <h1 className="mb-4 text-4xl font-bold">
                                        New Password
                                    </h1>
                                    <p className="mb-8 text-lg opacity-90">
                                        Create a strong password for your account
                                    </p>
                                </div>
                            </div>

                            <div className="p-12 dark:bg-gray-800">
                                <div className="mx-auto max-w-sm">
                                    <Card className="border-0 bg-transparent shadow-none">
                                        <CardHeader className="mb-5 text-center">
                                            <CardTitle className="text-3xl dark:text-white">
                                                Reset Password
                                            </CardTitle>
                                            <CardDescription className="dark:text-gray-300">
                                                Enter your new password below
                                            </CardDescription>
                                        </CardHeader>

                                        <CardContent>
                                            <Form
                                                {...update.form()}
                                                transform={(data) => ({ ...data, token, email })}
                                                resetOnSuccess={['password', 'password_confirmation']}
                                                className="space-y-6"
                                            >
                                                {({ processing, errors }) => (
                                                    <>
                                                        <div className="space-y-2">
                                                            <Label htmlFor="email" className="dark:text-gray-200">
                                                                Email Address
                                                            </Label>
                                                            <div className="relative">
                                                                <Input
                                                                    id="email"
                                                                    type="email"
                                                                    name="email"
                                                                    className="pl-10"
                                                                    value={email}
                                                                    readOnly
                                                                />
                                                                <svg
                                                                    className="absolute top-2.5 left-3 h-4 w-4 text-gray-400"
                                                                    fill="currentColor"
                                                                    viewBox="0 0 20 20"
                                                                >
                                                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                                                </svg>
                                                            </div>
                                                            {errors.email && (
                                                                <span className="text-xs text-red-500">
                                                                    {errors.email}
                                                                </span>
                                                            )}
                                                        </div>

                                                        <div className="space-y-2">
                                                            <Label htmlFor="password" className="dark:text-gray-200">
                                                                New Password
                                                            </Label>
                                                            <div className="relative">
                                                                <Input
                                                                    id="password"
                                                                    type={showPassword ? 'text' : 'password'}
                                                                    name="password"
                                                                    className="pr-10 pl-10"
                                                                    placeholder="••••••••"
                                                                    error={!!errors.password}
                                                                    required
                                                                    autoFocus
                                                                />
                                                                <svg
                                                                    className="absolute top-3 left-3 h-5 w-5 text-gray-400"
                                                                    fill="currentColor"
                                                                    viewBox="0 0 20 20"
                                                                >
                                                                    <path
                                                                        fillRule="evenodd"
                                                                        d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                                                        clipRule="evenodd"
                                                                    />
                                                                </svg>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="absolute top-1 right-1 h-8 w-8"
                                                                    onClick={() => setShowPassword(!showPassword)}
                                                                >
                                                                    {showPassword ? (
                                                                        <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                                            <path fillRule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clipRule="evenodd" />
                                                                            <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                                                                        </svg>
                                                                    ) : (
                                                                        <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                                            <path fillRule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clipRule="evenodd" />
                                                                        </svg>
                                                                    )}
                                                                </Button>
                                                            </div>
                                                            {errors.password && (
                                                                <span className="text-xs text-red-500">
                                                                    {errors.password}
                                                                </span>
                                                            )}
                                                        </div>

                                                        <div className="space-y-2">
                                                            <Label htmlFor="password_confirmation" className="dark:text-gray-200">
                                                                Confirm New Password
                                                            </Label>
                                                            <div className="relative">
                                                                <Input
                                                                    id="password_confirmation"
                                                                    type={showPasswordConfirmation ? 'text' : 'password'}
                                                                    name="password_confirmation"
                                                                    className="pr-10 pl-10"
                                                                    placeholder="••••••••"
                                                                    error={!!errors.password_confirmation}
                                                                    required
                                                                />
                                                                <svg
                                                                    className="absolute top-3 left-3 h-5 w-5 text-gray-400"
                                                                    fill="currentColor"
                                                                    viewBox="0 0 20 20"
                                                                >
                                                                    <path
                                                                        fillRule="evenodd"
                                                                        d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                                                        clipRule="evenodd"
                                                                    />
                                                                </svg>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="absolute top-1 right-1 h-8 w-8"
                                                                    onClick={() => setShowPasswordConfirmation(!showPasswordConfirmation)}
                                                                >
                                                                    {showPasswordConfirmation ? (
                                                                        <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                                            <path fillRule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clipRule="evenodd" />
                                                                            <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                                                                        </svg>
                                                                    ) : (
                                                                        <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                                            <path fillRule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clipRule="evenodd" />
                                                                        </svg>
                                                                    )}
                                                                </Button>
                                                            </div>
                                                            {errors.password_confirmation && (
                                                                <span className="text-xs text-red-500">
                                                                    {errors.password_confirmation}
                                                                </span>
                                                            )}
                                                        </div>

                                                        <Button
                                                            type="submit"
                                                            disabled={processing}
                                                            className="w-full"
                                                            size="lg"
                                                        >
                                                            {processing ? (
                                                                <>
                                                                    <svg className="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                                                    </svg>
                                                                    Resetting...
                                                                </>
                                                            ) : (
                                                                'Reset Password'
                                                            )}
                                                        </Button>
                                                    </>
                                                )}
                                            </Form>
                                        </CardContent>
                                    </Card>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}