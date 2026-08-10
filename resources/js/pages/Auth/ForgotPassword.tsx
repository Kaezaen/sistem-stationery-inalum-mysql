import GuestLayout from '@/components/layouts/GuestLayout';
import Button from '@/components/shared/Button';
import FormField from '@/components/shared/FormField';
import TextInput from '@/components/shared/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <GuestLayout
            title="Lupa kata sandi"
            description="Masukkan email Anda. Kami akan mengirimkan tautan untuk mengatur ulang kata sandi."
        >
            <Head title="Lupa kata sandi" />

            {status && (
                <div className="mb-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <FormField label="Email" htmlFor="email" error={errors.email} required>
                    <TextInput
                        id="email"
                        type="email"
                        value={data.email}
                        autoFocus
                        invalid={Boolean(errors.email)}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </FormField>

                <Button type="submit" disabled={processing} className="w-full">
                    {processing ? 'Mengirim…' : 'Kirim tautan'}
                </Button>

                <Link
                    href="/login"
                    className="block text-center text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    Kembali ke halaman masuk
                </Link>
            </form>
        </GuestLayout>
    );
}
