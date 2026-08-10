import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import Button from '@/components/shared/Button';
import ItemPicker, { type PickableItem } from '@/components/shared/ItemPicker';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import type { FormEvent } from 'react';
import ApprovalTimeline, { type TimelineRow } from './ApprovalTimeline';

interface Line {
    item_id: number;
    item_code: string;
    item_name: string;
    category: string | null;
    quantity: number;
}

interface FormShape {
    notes: string;
    items: Line[];
    [key: string]: string | Line[];
}

interface RequestDetail {
    id: number;
    request_number: string;
    notes: string | null;
    revision_count: number;
    items: {
        item_id: number;
        item_code: string;
        item_name: string;
        category: string | null;
        quantity_requested: number;
    }[];
}

interface Props {
    request: RequestDetail;
    history: TimelineRow[];
    categories: { id: number; name: string }[];
}

/**
 * Revisi request setelah ditolak — Bab 3.6 blueprint.
 *
 * Catatan penolakan ditampilkan menonjol di atas form: itulah satu-satunya
 * petunjuk yang dimiliki requester tentang apa yang harus diperbaiki.
 */
export default function Revise({ request, history, categories }: Props) {
    const { data, setData, post, processing, errors } = useForm<FormShape>({
        notes: request.notes ?? '',
        items: request.items.map((line) => ({
            item_id: line.item_id,
            item_code: line.item_code,
            item_name: line.item_name,
            category: line.category,
            quantity: line.quantity_requested,
        })),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`/requests/${request.id}/revise`);
    };

    const addItem = (item: PickableItem) => {
        setData('items', [
            ...data.items,
            {
                item_id: item.id,
                item_code: item.item_code,
                item_name: item.item_name,
                category: item.category,
                quantity: 1,
            },
        ]);
    };

    const setQuantity = (itemId: number, quantity: number) => {
        setData(
            'items',
            data.items.map((line) =>
                line.item_id === itemId ? { ...line, quantity: Math.max(1, quantity) } : line,
            ),
        );
    };

    const lastRejection = [...history].reverse().find((row) => row.decision === 'REJECTED');
    const itemsError = (errors as Record<string, string | undefined>).items;

    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Revisi Request</h1>}>
            <Head title={`Revisi ${request.request_number}`} />

            {lastRejection && (
                <div className="mb-6 flex gap-3 rounded-lg border border-red-300 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950">
                    <AlertTriangle className="size-5 shrink-0 text-red-600" aria-hidden />
                    <div className="text-sm">
                        <p className="font-medium text-red-900 dark:text-red-200">
                            Ditolak oleh {lastRejection.approver ?? '—'}
                        </p>
                        <p className="mt-1 text-red-800 dark:text-red-300">{lastRejection.notes}</p>
                        <p className="mt-2 text-xs text-red-700 dark:text-red-400">
                            Perbaiki sesuai catatan di atas. Menyimpan perubahan akan langsung
                            mengajukan ulang request ini.
                        </p>
                    </div>
                </div>
            )}

            <form onSubmit={submit} className="space-y-6">
                <div>
                    <h2 className="mb-3 font-medium">Tambah Item</h2>
                    <ItemPicker
                        categories={categories}
                        excludeIds={data.items.map((line) => line.item_id)}
                        onPick={addItem}
                    />
                </div>

                <div className="rounded-lg border bg-card">
                    <div className="flex items-center justify-between border-b p-4">
                        <h2 className="font-medium">Item Direvisi</h2>
                        <span className="text-sm text-muted-foreground">
                            {data.items.length} item
                        </span>
                    </div>

                    {itemsError && (
                        <p className="border-b bg-red-50 px-4 py-2 text-sm text-red-800 dark:bg-red-950 dark:text-red-300">
                            {itemsError}
                        </p>
                    )}

                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th className="px-4 py-3">Item</th>
                                <th className="px-4 py-3 text-right">Quantity</th>
                                <th className="px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.items.map((line) => (
                                <tr key={line.item_id} className="border-b last:border-0">
                                    <td className="px-4 py-3">
                                        <div>{line.item_name}</div>
                                        <div className="font-mono text-xs text-muted-foreground">
                                            {line.item_code}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setQuantity(line.item_id, line.quantity - 1)
                                                }
                                                className="size-7 rounded border text-sm hover:bg-secondary"
                                                aria-label="Kurangi"
                                            >
                                                −
                                            </button>
                                            <input
                                                type="number"
                                                min={1}
                                                value={line.quantity}
                                                onChange={(e) =>
                                                    setQuantity(
                                                        line.item_id,
                                                        Number(e.target.value),
                                                    )
                                                }
                                                className="h-7 w-16 rounded border bg-background text-center text-sm"
                                            />
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setQuantity(line.item_id, line.quantity + 1)
                                                }
                                                className="size-7 rounded border text-sm hover:bg-secondary"
                                                aria-label="Tambah"
                                            >
                                                +
                                            </button>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setData(
                                                    'items',
                                                    data.items.filter(
                                                        (l) => l.item_id !== line.item_id,
                                                    ),
                                                )
                                            }
                                            className="rounded-md border px-3 py-1 text-xs hover:bg-secondary"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <ApprovalTimeline history={history} />

                <div className="flex justify-end gap-2">
                    <Link href={`/requests/${request.id}`}>
                        <Button type="button" variant="secondary">
                            Batal
                        </Button>
                    </Link>
                    <Button type="submit" disabled={processing || data.items.length === 0}>
                        {processing ? 'Mengirim…' : 'Ajukan Request'}
                    </Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
