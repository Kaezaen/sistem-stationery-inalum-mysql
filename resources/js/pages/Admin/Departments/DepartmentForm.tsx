import Button from '@/components/shared/Button';
import FormField from '@/components/shared/FormField';
import SelectInput from '@/components/shared/SelectInput';
import TextInput from '@/components/shared/TextInput';
import type { DepartmentOption } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export interface DepartmentFormData {
    code: string;
    name: string;
    account_code: string;
    parent_id: string;
    is_active: boolean;
    [key: string]: string | boolean;
}

interface Props {
    initial: DepartmentFormData;
    parents: DepartmentOption[];
    action: string;
    method: 'post' | 'put';
    submitLabel: string;
}

export default function DepartmentForm({ initial, parents, action, method, submitLabel }: Props) {
    const { data, setData, post, put, processing, errors } = useForm<DepartmentFormData>(initial);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (method === 'post') {
            post(action);
        } else {
            put(action);
        }
    };

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <div className="rounded-lg border bg-card p-6">
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField label="Kode" htmlFor="code" error={errors.code} required>
                        <TextInput
                            id="code"
                            value={data.code}
                            invalid={Boolean(errors.code)}
                            onChange={(e) => setData('code', e.target.value.toUpperCase())}
                        />
                    </FormField>

                    <FormField label="Nama" htmlFor="name" error={errors.name} required>
                        <TextInput
                            id="name"
                            value={data.name}
                            invalid={Boolean(errors.name)}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        label="Kode akun"
                        htmlFor="account_code"
                        error={errors.account_code}
                        hint="Dipakai laporan Request by Account."
                    >
                        <TextInput
                            id="account_code"
                            value={data.account_code}
                            onChange={(e) => setData('account_code', e.target.value)}
                        />
                    </FormField>

                    <FormField label="Induk" htmlFor="parent_id" error={errors.parent_id}>
                        <SelectInput
                            id="parent_id"
                            value={data.parent_id}
                            invalid={Boolean(errors.parent_id)}
                            onChange={(e) => setData('parent_id', e.target.value)}
                        >
                            <option value="">Tidak ada induk</option>
                            {parents.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.code} — {p.name}
                                </option>
                            ))}
                        </SelectInput>
                    </FormField>
                </div>

                <label className="mt-4 flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                        className="size-4 rounded border"
                    />
                    Departemen aktif
                </label>
            </div>

            <div className="flex justify-end gap-2">
                <Link href="/admin/departments">
                    <Button type="button" variant="secondary">
                        Batal
                    </Button>
                </Link>
                <Button type="submit" disabled={processing}>
                    {processing ? 'Menyimpan…' : submitLabel}
                </Button>
            </div>
        </form>
    );
}
