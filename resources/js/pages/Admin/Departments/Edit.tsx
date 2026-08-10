import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import type { DepartmentOption } from '@/types';
import { Head } from '@inertiajs/react';
import DepartmentForm from './DepartmentForm';

interface EditableDepartment {
    id: number;
    code: string;
    name: string;
    account_code: string | null;
    parent_id: number | null;
    is_active: boolean;
}

interface Props {
    department: EditableDepartment;
    parents: DepartmentOption[];
}

export default function Edit({ department, parents }: Props) {
    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Ubah Departemen</h1>}>
            <Head title={`Ubah ${department.name}`} />

            <DepartmentForm
                initial={{
                    code: department.code,
                    name: department.name,
                    account_code: department.account_code ?? '',
                    parent_id: department.parent_id ? String(department.parent_id) : '',
                    is_active: department.is_active,
                }}
                parents={parents}
                action={`/admin/departments/${department.id}`}
                method="put"
                submitLabel="Simpan Perubahan"
            />
        </AuthenticatedLayout>
    );
}
