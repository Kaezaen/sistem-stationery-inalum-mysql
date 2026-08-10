import GuestLayout from '@/components/layouts/GuestLayout';
import Button from '@/components/shared/Button';
import FormField from '@/components/shared/FormField';
import TextInput from '@/components/shared/TextInput';
import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Props {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/reset-password', {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout title="Atur ulang kata sandi">
            <Head title="Atur ulang kata sandi" />

            <form onSubmit={submit} className="space-y-4">
                <FormField label="Email" htmlFor="email" error={errors.email} required>
                    <TextInput
                        id="email"
                        type="email"
                        value={data.email}
                        invalid={Boolean(errors.email)}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </FormField>

                <FormField
                    label="Kata sandi baru"
                    htmlFor="password"
                    error={errors.password}
                    required
                >
                    <TextInput
                        id="password"
                        type="password"
                        value={data.password}
                        autoComplete="new-password"
                        autoFocus
                        invalid={Boolean(errors.password)}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </FormField>

                <FormField
                    label="Ulangi kata sandi"
                    htmlFor="password_confirmation"
                    error={errors.password_confirmation}
                    required
                >
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                </FormField>

                <Button type="submit" disabled={processing} className="w-full">
                    {processing ? 'Menyimpan…' : 'Simpan kata sandi'}
                </Button>
            </form>
        </GuestLayout>
    );
}
