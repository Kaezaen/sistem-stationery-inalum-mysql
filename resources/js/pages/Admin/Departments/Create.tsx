import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import type { DepartmentOption } from '@/types';
import { Head } from '@inertiajs/react';
import DepartmentForm from './DepartmentForm';

export default function Create({ parents }: { parents: DepartmentOption[] }) {
    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Tambah Departemen</h1>}>
            <Head title="Tambah Departemen" />

            <DepartmentForm
                initial={{ code: '', name: '', account_code: '', parent_id: '', is_active: true }}
                parents={parents}
                action="/admin/departments"
                method="post"
                submitLabel="Simpan Departemen"
            />
        </AuthenticatedLayout>
    );
}
