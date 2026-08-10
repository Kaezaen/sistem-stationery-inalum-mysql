import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import Button from '@/components/shared/Button';
import SelectInput from '@/components/shared/SelectInput';
import TextInput from '@/components/shared/TextInput';
import { Head, router, usePage } from '@inertiajs/react';
import { Download, Printer } from 'lucide-react';
import { useState } from 'react';

interface ReportColumn {
    key: string;
    label: string;
    align?: 'left' | 'right' | 'center';
    numeric?: boolean;
    format?: string;
}

interface ReportMeta {
    label: string;
    value: number | string;
}

interface FilterSchema {
    period?: 'month' | 'year' | 'range' | null;
    category?: boolean;
    department?: boolean;
    search?: boolean;
}

interface ReportFilters {
    year: number;
    month: number;
    from: string;
    until: string;
    category_id: number | null;
    department_id: number | null;
    search: string;
}

interface ReportData {
    key: string;
    title: string;
    subtitle: string | null;
    columns: ReportColumn[];
    rows: Record<string, string | number | null>[];
    filterSchema: FilterSchema;
    filters: ReportFilters;
    meta: ReportMeta[];
}

interface Props {
    report: ReportData;
    options: {
        categories: { id: number; name: string }[];
        departments: { id: number; name: string }[];
    };
    can: { export: boolean };
}

const MONTHS = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

export default function Show({ report, options, can }: Props) {
    const { filterSchema, filters } = report;
    const { ziggy } = usePage().props;
    const path = new URL(ziggy.location).pathname;

    const [year, setYear] = useState(filters.year);
    const [month, setMonth] = useState(filters.month);
    const [from, setFrom] = useState(filters.from);
    const [until, setUntil] = useState(filters.until);
    const [categoryId, setCategoryId] = useState<string>(
        filters.category_id ? String(filters.category_id) : '',
    );
    const [departmentId, setDepartmentId] = useState<string>(
        filters.department_id ? String(filters.department_id) : '',
    );
    const [search, setSearch] = useState(filters.search);

    const currentParams = () => ({
        year,
        month,
        from,
        until,
        category_id: categoryId,
        department_id: departmentId,
        search,
    });

    const apply = (overrides: Record<string, string | number> = {}) => {
        router.get(
            path,
            { ...currentParams(), ...overrides },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    };

    const exportUrl = () => {
        const params = new URLSearchParams();
        Object.entries({ ...currentParams(), export: 'xlsx' }).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined) {
                params.set(key, String(value));
            }
        });
        return `${path}?${params.toString()}`;
    };

    const formatCell = (value: string | number | null | undefined) => {
        if (value === null || value === undefined || value === '') {
            return <span className="text-muted-foreground">—</span>;
        }
        return value;
    };

    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">{report.title}</h1>}>
            <Head title={report.title} />

            <div className="mb-4 flex flex-wrap items-end justify-between gap-3 print:hidden">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        apply();
                    }}
                    className="flex flex-wrap items-end gap-3"
                >
                    {filterSchema.period === 'month' && (
                        <>
                            <Field label="Tahun">
                                <TextInput
                                    type="number"
                                    value={year}
                                    className="w-28"
                                    onChange={(e) => setYear(Number(e.target.value))}
                                />
                            </Field>
                            <Field label="Bulan">
                                <SelectInput
                                    value={month}
                                    className="w-40"
                                    onChange={(e) => {
                                        const value = Number(e.target.value);
                                        setMonth(value);
                                        apply({ month: value });
                                    }}
                                >
                                    {MONTHS.map((name, index) => (
                                        <option key={name} value={index + 1}>
                                            {name}
                                        </option>
                                    ))}
                                </SelectInput>
                            </Field>
                        </>
                    )}

                    {filterSchema.period === 'year' && (
                        <Field label="Tahun">
                            <TextInput
                                type="number"
                                value={year}
                                className="w-28"
                                onChange={(e) => setYear(Number(e.target.value))}
                            />
                        </Field>
                    )}

                    {filterSchema.period === 'range' && (
                        <>
                            <Field label="Dari">
                                <TextInput
                                    type="date"
                                    value={from}
                                    className="w-40"
                                    onChange={(e) => {
                                        setFrom(e.target.value);
                                        apply({ from: e.target.value });
                                    }}
                                />
                            </Field>
                            <Field label="Sampai">
                                <TextInput
                                    type="date"
                                    value={until}
                                    className="w-40"
                                    onChange={(e) => {
                                        setUntil(e.target.value);
                                        apply({ until: e.target.value });
                                    }}
                                />
                            </Field>
                        </>
                    )}

                    {filterSchema.category && (
                        <Field label="Kategori">
                            <SelectInput
                                value={categoryId}
                                className="w-48"
                                onChange={(e) => {
                                    setCategoryId(e.target.value);
                                    apply({ category_id: e.target.value });
                                }}
                            >
                                <option value="">Semua Kategori</option>
                                {options.categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </SelectInput>
                        </Field>
                    )}

                    {filterSchema.department && (
                        <Field label="Departemen">
                            <SelectInput
                                value={departmentId}
                                className="w-48"
                                onChange={(e) => {
                                    setDepartmentId(e.target.value);
                                    apply({ department_id: e.target.value });
                                }}
                            >
                                <option value="">Semua Departemen</option>
                                {options.departments.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name}
                                    </option>
                                ))}
                            </SelectInput>
                        </Field>
                    )}

                    {filterSchema.search && (
                        <Field label="Cari Item">
                            <TextInput
                                placeholder="Kode / nama item"
                                value={search}
                                className="w-56"
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </Field>
                    )}

                    <Button type="submit" variant="secondary">
                        Terapkan
                    </Button>
                </form>

                <div className="flex items-center gap-2">
                    {can.export && (
                        <a
                            href={exportUrl()}
                            className="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-4 text-sm font-medium transition-colors hover:bg-secondary"
                        >
                            <Download className="size-4" aria-hidden />
                            Excel
                        </a>
                    )}
                    <Button type="button" variant="secondary" onClick={() => window.print()}>
                        <Printer className="size-4" aria-hidden />
                        Cetak / PDF
                    </Button>
                </div>
            </div>

            {/* Judul untuk hasil cetak — tersembunyi di layar, tampil saat print. */}
            <div className="mb-4 hidden print:block">
                <h1 className="text-lg font-bold">{report.title}</h1>
                {report.subtitle && <p className="text-sm">{report.subtitle}</p>}
            </div>

            {report.subtitle && (
                <p className="mb-3 text-sm text-muted-foreground print:hidden">{report.subtitle}</p>
            )}

            {report.meta.length > 0 && (
                <div className="mb-4 flex flex-wrap gap-3">
                    {report.meta.map((m) => (
                        <div key={m.label} className="rounded-lg border bg-card px-4 py-2">
                            <p className="text-xs text-muted-foreground">{m.label}</p>
                            <p className="text-lg font-semibold tabular-nums">{m.value}</p>
                        </div>
                    ))}
                </div>
            )}

            <div className="overflow-x-auto rounded-lg border bg-card print:border-0">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            {report.columns.map((column) => (
                                <th
                                    key={column.key}
                                    className={
                                        column.align === 'right'
                                            ? 'px-4 py-3 text-right'
                                            : 'px-4 py-3'
                                    }
                                >
                                    {column.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {report.rows.map((row, rowIndex) => (
                            <tr key={rowIndex} className="border-b last:border-0">
                                {report.columns.map((column) => (
                                    <td
                                        key={column.key}
                                        className={
                                            column.numeric
                                                ? 'px-4 py-2.5 text-right tabular-nums'
                                                : 'px-4 py-2.5'
                                        }
                                    >
                                        {formatCell(row[column.key])}
                                    </td>
                                ))}
                            </tr>
                        ))}

                        {report.rows.length === 0 && (
                            <tr>
                                <td
                                    colSpan={report.columns.length}
                                    className="px-4 py-10 text-center text-muted-foreground"
                                >
                                    Tidak ada data untuk filter ini.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <p className="mt-3 text-sm text-muted-foreground print:hidden">
                {report.rows.length} baris
            </p>
        </AuthenticatedLayout>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <label className="flex flex-col gap-1">
            <span className="text-xs font-medium text-muted-foreground">{label}</span>
            {children}
        </label>
    );
}
