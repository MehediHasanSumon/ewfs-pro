import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/two-factor/login';
import { Form, Head } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useMemo, useState } from 'react';

const OTP_MAX_LENGTH = 6;

export default function TwoFactorChallenge() {
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');

    const authConfigContent = useMemo<{
        title: string;
        description: string;
        toggleText: string;
    }>(() => {
        if (showRecoveryInput) {
            return {
                title: 'Recovery Code',
                description: 'Please confirm access to your account by entering one of your emergency recovery codes',
                toggleText: 'Use authentication code instead',
            };
        }

        return {
            title: 'Authentication Code',
            description: 'Enter the authentication code provided by your authenticator application',
            toggleText: 'Use recovery code instead',
        };
    }, [showRecoveryInput]);

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    return (
        <>
            <Head title="Two-Factor Authentication" />
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
                                                d="M18 8a6 6 0 01-7.743 5.743L10 14l-4 4-4-4 4-4 .257-.257A6 6 0 1118 8zm-6-2a1 1 0 11-2 0 1 1 0 012 0z"
                                                clipRule="evenodd"
                                            />
                                        </svg>
                                    </div>
                                    <h1 className="mb-4 text-4xl font-bold">
                                        Secure Access
                                    </h1>
                                    <p className="mb-8 text-lg opacity-90">
                                        Additional security verification required
                                    </p>
                                </div>
                            </div>

                            <div className="p-12 dark:bg-gray-800">
                                <div className="mx-auto max-w-sm">
                                    <Card className="border-0 bg-transparent shadow-none">
                                        <CardHeader className="mb-5 text-center">
                                            <CardTitle className="text-3xl dark:text-white">
                                                {authConfigContent.title}
                                            </CardTitle>
                                            <CardDescription className="dark:text-gray-300">
                                                {authConfigContent.description}
                                            </CardDescription>
                                        </CardHeader>

                                        <CardContent>
                                            <Form
                                                {...store.form()}
                                                className="space-y-6"
                                                resetOnError
                                                resetOnSuccess={!showRecoveryInput}
                                            >
                                                {({ errors, processing, clearErrors }) => (
                                                    <>
                                                        {showRecoveryInput ? (
                                                            <div className="space-y-2">
                                                                <Label htmlFor="recovery_code" className="dark:text-gray-200">
                                                                    Recovery Code
                                                                </Label>
                                                                <Input
                                                                    id="recovery_code"
                                                                    name="recovery_code"
                                                                    type="text"
                                                                    placeholder="Enter recovery code"
                                                                    error={!!errors.recovery_code}
                                                                    autoFocus={showRecoveryInput}
                                                                    required
                                                                />
                                                                {errors.recovery_code && (
                                                                    <span className="text-xs text-red-500">
                                                                        {errors.recovery_code}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <div className="space-y-2">
                                                                <Label className="text-center block dark:text-gray-200">
                                                                    Authentication Code
                                                                </Label>
                                                                <div className="flex justify-center">
                                                                    <InputOTP
                                                                        name="code"
                                                                        maxLength={OTP_MAX_LENGTH}
                                                                        value={code}
                                                                        onChange={(value) => setCode(value)}
                                                                        disabled={processing}
                                                                        pattern={REGEXP_ONLY_DIGITS}
                                                                    >
                                                                        <InputOTPGroup>
                                                                            {Array.from(
                                                                                { length: OTP_MAX_LENGTH },
                                                                                (_, index) => (
                                                                                    <InputOTPSlot
                                                                                        key={index}
                                                                                        index={index}
                                                                                    />
                                                                                ),
                                                                            )}
                                                                        </InputOTPGroup>
                                                                    </InputOTP>
                                                                </div>
                                                                {errors.code && (
                                                                    <span className="text-xs text-red-500 text-center block">
                                                                        {errors.code}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        )}

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
                                                                    Verifying...
                                                                </>
                                                            ) : (
                                                                'Continue'
                                                            )}
                                                        </Button>

                                                        <div className="text-center">
                                                            <button
                                                                type="button"
                                                                className="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 hover:underline"
                                                                onClick={() => toggleRecoveryMode(clearErrors)}
                                                            >
                                                                {authConfigContent.toggleText}
                                                            </button>
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