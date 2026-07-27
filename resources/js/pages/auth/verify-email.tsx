import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import { Form, Head, Link } from '@inertiajs/react';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Verify Email" />
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
                                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                        </svg>
                                    </div>
                                    <h1 className="mb-4 text-4xl font-bold">
                                        Check Your Email
                                    </h1>
                                    <p className="mb-8 text-lg opacity-90">
                                        We've sent you a verification link
                                    </p>
                                </div>
                            </div>

                            <div className="p-12 dark:bg-gray-800">
                                <div className="mx-auto max-w-sm">
                                    <Card className="border-0 bg-transparent shadow-none">
                                        <CardHeader className="mb-5 text-center">
                                            <CardTitle className="text-3xl dark:text-white">
                                                Verify Email
                                            </CardTitle>
                                            <CardDescription className="dark:text-gray-300">
                                                Please verify your email address by clicking on the link we just emailed to you
                                            </CardDescription>
                                        </CardHeader>

                                        <CardContent>
                                            {status === 'verification-link-sent' && (
                                                <div className="mb-4 text-center text-sm font-medium text-green-600 dark:text-green-400">
                                                    A new verification link has been sent to your email address
                                                </div>
                                            )}

                                            <Form {...send.form()} className="space-y-6">
                                                {({ processing }) => (
                                                    <>
                                                        <Button
                                                            type="submit"
                                                            disabled={processing}
                                                            className="w-full"
                                                            size="lg"
                                                            variant="secondary"
                                                        >
                                                            {processing ? (
                                                                <>
                                                                    <svg className="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                                                    </svg>
                                                                    Sending...
                                                                </>
                                                            ) : (
                                                                'Resend Verification Email'
                                                            )}
                                                        </Button>

                                                        <div className="text-center">
                                                            <Link
                                                                href={logout()}
                                                                className="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 hover:underline"
                                                            >
                                                                Log out
                                                            </Link>
                                                        </div>
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