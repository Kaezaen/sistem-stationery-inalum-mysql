import Button from '@/components/shared/Button';
import FormField from '@/components/shared/FormField';
import SelectInput from '@/components/shared/SelectInput';
import TextInput from '@/components/shared/TextInput';
import type { DepartmentOption, ManagerOption, Option } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export interface UserFormData {
    employee_id: string;
    username: string;
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    department_id: string;
    position: string;
    manager_id: string;
    is_active: boolean;
    roles: string[];
    [key: string]: string | boolean | string[];
}

interface Props {
    initial: UserFormData;
    departments: DepartmentOption[];
    managers: ManagerOption[];
    positions: Option[];
    availableRoles: Option[];
    action: string;
    method: 'post' | 'put';
    submitLabel: string;
}

export default function UserForm({
    initial,
    departments,
    managers,
    positions,
    availableRoles,
    action,
    method,
    submitLabel,
}: Props) {
    const { data, setData, post, put, processing, errors } = useForm<UserFormData>(initial);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (method === 'post') {
            post(action);
        } else {
            put(action);
        }
    };

    const toggleRole = (role: string, checked: boolean) => {
        setData('roles', checked ? [...data.roles, role] : data.roles.filter((r) => r !== role));
    };

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-6">
            <div className="rounded-lg border bg-card p-6">
                <h2 className="mb-4 font-medium">Identitas</h2>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="NIP"
                        htmlFor="employee_id"
                        error={errors.employee_id}
                        required
                    >
                        <TextInput
                            id="employee_id"
                            value={data.employee_id}
                            invalid={Boolean(errors.employee_id)}
                            onChange={(e) => setData('employee_id', e.target.value)}
                        />
                    </FormField>

                    <FormField label="Username" htmlFor="username" error={errors.username} required>
                        <TextInput
                            id="username"
                            value={data.username}
                            invalid={Boolean(errors.username)}
                            onChange={(e) => setData('username', e.target.value)}
                        />
                    </FormField>

                    <FormField label="Nama lengkap" htmlFor="name" error={errors.name} required>
                        <TextInput
                            id="name"
                            value={data.name}
                            invalid={Boolean(errors.name)}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                    </FormField>

                    <FormField label="Email" htmlFor="email" error={errors.email} required>
                        <TextInput
                            id="email"
                            type="email"
                            value={data.email}
                            invalid={Boolean(errors.email)}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    </FormField>
                </div>
            </div>

            <div className="rounded-lg border bg-card p-6">
                <h2 className="mb-1 font-medium">Organisasi</h2>
                <p className="mb-4 text-sm text-muted-foreground">
                    Atasan langsung menentukan siapa yang menyetujui request user ini pada Level 1.
                    Kesalahan pemetaan di sini membuat approval salah sasaran.
                </p>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Departemen / Seksi"
                        htmlFor="department_id"
                        error={errors.department_id}
                        required
                    >
                        <SelectInput
                            id="department_id"
                            value={data.department_id}
                            invalid={Boolean(errors.department_id)}
                            onChange={(e) => setData('department_id', e.target.value)}
                        >
                            <option value="">Pilih departemen</option>
                            {departments.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.code} — {d.name}
                                </option>
                            ))}
                        </SelectInput>
                    </FormField>

                    <FormField label="Jabatan" htmlFor="position" error={errors.position}>
                        <SelectInput
                            id="position"
                            value={data.position}
                            onChange={(e) => setData('position', e.target.value)}
                        >
                            <option value="">Tidak ditentukan</option>
                            {positions.map((p) => (
                                <option key={p.value} value={p.value}>
                                    {p.label}
                                </option>
                            ))}
                        </SelectInput>
                    </FormField>

                    <FormField
                        label="Atasan langsung"
                        htmlFor="manager_id"
                        error={errors.manager_id}
                        hint="Kosongkan bila user ini berada di pucuk struktur."
                        className="sm:col-span-2"
                    >
                        <SelectInput
                            id="manager_id"
                            value={data.manager_id}
                            invalid={Boolean(errors.manager_id)}
                            onChange={(e) => setData('manager_id', e.target.value)}
                        >
                            <option value="">Tidak ada atasan</option>
                            {managers.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.name} ({m.employee_id})
                                </option>
                            ))}
                        </SelectInput>
                    </FormField>
                </div>
            </div>

            <div className="rounded-lg border bg-card p-6">
                <h2 className="mb-1 font-medium">Kewenangan</h2>
                <p className="mb-4 text-sm text-muted-foreground">
                    Role <code className="font-mono">requester</code> otomatis melekat pada setiap
                    user dan tidak perlu dipilih.
                </p>

                <div className="space-y-2">
                    {availableRoles
                        .filter((role) => role.value !== 'requester')
                        .map((role) => (
                            <label
                                key={role.value}
                                className="flex cursor-pointer items-start gap-3 rounded-md border p-3 hover:bg-secondary/50"
                            >
                                <input
                                    type="checkbox"
                                    checked={data.roles.includes(role.value)}
                                    onChange={(e) => toggleRole(role.value, e.target.checked)}
                                    className="mt-0.5 size-4 rounded border"
                                />
                                <span>
                                    <span className="block text-sm font-medium">{role.label}</span>
                                    <span className="block text-xs text-muted-foreground">
                                        {role.description}
                                    </span>
                                </span>
                            </label>
                        ))}
                </div>
            </div>

            <div className="rounded-lg border bg-card p-6">
                <h2 className="mb-4 font-medium">Akun</h2>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Kata sandi"
                        htmlFor="password"
                        error={errors.password}
                        required={method === 'post'}
                        hint={method === 'put' ? 'Kosongkan bila tidak ingin mengubah.' : undefined}
                    >
                        <TextInput
                            id="password"
                            type="password"
                            value={data.password}
                            autoComplete="new-password"
                            invalid={Boolean(errors.password)}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                    </FormField>

                    <FormField label="Ulangi kata sandi" htmlFor="password_confirmation">
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            autoComplete="new-password"
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                        />
                    </FormField>
                </div>

                <label className="mt-4 flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                        className="size-4 rounded border"
                    />
                    Akun aktif — user non-aktif tidak dapat masuk ke sistem
                </label>
            </div>

            <div className="flex justify-end gap-2">
                <Link href="/admin/users">
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
