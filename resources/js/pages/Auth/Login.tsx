import Button from '@/components/shared/Button';
import FormField from '@/components/shared/FormField';
import TextInput from '@/components/shared/TextInput';
import GuestLayout from '@/components/layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Props {
    status?: string;
}

export default function Login({ status }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        login: '',
        password: '',
        remember: false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/login', { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout title="Masuk" description="Gunakan NIP, username, atau email korporat Anda.">
            <Head title="Masuk" />

            {status && (
                <div className="mb-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <FormField
                    label="NIP / Username / Email"
                    htmlFor="login"
                    error={errors.login}
                    required
                >
                    <TextInput
                        id="login"
                        name="login"
                        value={data.login}
                        autoComplete="username"
                        autoFocus
                        invalid={Boolean(errors.login)}
                        onChange={(e) => setData('login', e.target.value)}
                    />
                </FormField>

                <FormField label="Kata sandi" htmlFor="password" error={errors.password} required>
                    <TextInput
                        id="password"
                        name="password"
                        type="password"
                        value={data.password}
                        autoComplete="current-password"
                        invalid={Boolean(errors.password)}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </FormField>

                <div className="flex items-center justify-between">
                    <label className="flex items-center gap-2 text-sm text-muted-foreground">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="size-4 rounded border"
                        />
                        Ingat saya
                    </label>

                    <Link
                        href="/forgot-password"
                        className="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    >
                        Lupa kata sandi?
                    </Link>
                </div>

                <Button type="submit" disabled={processing} className="w-full">
                    {processing ? 'Memproses…' : 'Masuk'}
                </Button>
            </form>
        </GuestLayout>
    );
}
