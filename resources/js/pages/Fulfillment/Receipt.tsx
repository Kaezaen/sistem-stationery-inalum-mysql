import Button from '@/components/shared/Button';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';

interface Line {
    id: number;
    item_code: string;
    item_name: string;
    uom: string | null;
    quantity_requested: number;
    quantity_approved: number | null;
    quantity_actual: number | null;
    remark: string | null;
}

interface Props {
    request: {
        id: number;
        request_number: string;
        requester: string | null;
        department: string | null;
        request_date: string | null;
        items: Line[];
    };
    issuedAt: string | null;
    company: string;
}

/**
 * Bukti serah terima — halaman siap cetak.
 *
 * Sengaja TIDAK memakai AuthenticatedLayout: sidebar dan header aplikasi tidak
 * boleh ikut tercetak. Kontrol layar disembunyikan lewat utility `print:hidden`,
 * sehingga satu halaman melayani tampilan layar sekaligus hasil cetaknya.
 */
export default function Receipt({ request, issuedAt, company }: Props) {
    return (
        <div className="min-h-full bg-muted/30 p-6 print:bg-white print:p-0">
            <Head title={`Bukti Serah Terima ${request.request_number}`} />

            <div className="mx-auto max-w-3xl">
                <div className="mb-4 flex items-center justify-between print:hidden">
                    <Link
                        href={`/handover/${request.id}`}
                        className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" aria-hidden />
                        Kembali
                    </Link>

                    <Button type="button" onClick={() => window.print()}>
                        <Printer className="size-4" aria-hidden />
                        Cetak
                    </Button>
                </div>

                <div className="rounded-lg border bg-white p-8 text-black shadow-sm print:rounded-none print:border-0 print:shadow-none">
                    <header className="mb-6 border-b border-black/20 pb-4 text-center">
                        <h1 className="text-lg font-bold uppercase">{company}</h1>
                        <p className="mt-1 text-sm">Bukti Serah Terima Alat Tulis Kantor</p>
                    </header>

                    <dl className="mb-6 grid grid-cols-2 gap-x-8 gap-y-2 text-sm">
                        <div className="flex gap-2">
                            <dt className="w-32 shrink-0 text-black/60">Nomor Request</dt>
                            <dd className="font-medium">: {request.request_number}</dd>
                        </div>
                        <div className="flex gap-2">
                            <dt className="w-32 shrink-0 text-black/60">Tanggal Request</dt>
                            <dd>: {request.request_date ?? '—'}</dd>
                        </div>
                        <div className="flex gap-2">
                            <dt className="w-32 shrink-0 text-black/60">Penerima</dt>
                            <dd className="font-medium">: {request.requester ?? '—'}</dd>
                        </div>
                        <div className="flex gap-2">
                            <dt className="w-32 shrink-0 text-black/60">Seksi</dt>
                            <dd>: {request.department ?? '—'}</dd>
                        </div>
                        <div className="flex gap-2">
                            <dt className="w-32 shrink-0 text-black/60">Waktu Serah Terima</dt>
                            <dd>: {issuedAt ?? '—'}</dd>
                        </div>
                    </dl>

                    <table className="w-full border-collapse text-sm">
                        <thead>
                            <tr className="border-y border-black/20 text-left">
                                <th className="py-2 pr-2">No</th>
                                <th className="py-2 pr-2">Item</th>
                                <th className="py-2 pr-2">UoM</th>
                                <th className="py-2 pr-2 text-right">Diminta</th>
                                <th className="py-2 pr-2 text-right">Disetujui</th>
                                <th className="py-2 text-right">Diserahkan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {request.items.map((line, index) => (
                                <tr key={line.id} className="border-b border-black/10">
                                    <td className="py-2 pr-2 align-top">{index + 1}</td>
                                    <td className="py-2 pr-2 align-top">
                                        <div>{line.item_name}</div>
                                        <div className="font-mono text-xs text-black/50">
                                            {line.item_code}
                                        </div>
                                        {line.remark && (
                                            <div className="text-xs text-black/50">
                                                {line.remark}
                                            </div>
                                        )}
                                    </td>
                                    <td className="py-2 pr-2 align-top">{line.uom ?? '—'}</td>
                                    <td className="py-2 pr-2 text-right align-top tabular-nums">
                                        {line.quantity_requested}
                                    </td>
                                    <td className="py-2 pr-2 text-right align-top tabular-nums">
                                        {line.quantity_approved ?? '—'}
                                    </td>
                                    <td className="py-2 text-right align-top font-medium tabular-nums">
                                        {line.quantity_actual ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    <div className="mt-12 grid grid-cols-2 gap-8 text-center text-sm">
                        <div>
                            <p className="text-black/60">Diserahkan oleh</p>
                            <div className="mt-16 border-t border-black/30 pt-1">PIC Gudang</div>
                        </div>
                        <div>
                            <p className="text-black/60">Diterima oleh</p>
                            <div className="mt-16 border-t border-black/30 pt-1">
                                {request.requester ?? '—'}
                            </div>
                        </div>
                    </div>

                    <p className="mt-8 text-center text-xs text-black/40">
                        Dokumen ini dicetak dari Sistem Stationery dan sah tanpa tanda tangan basah
                        bila disertai bukti elektronik pada sistem.
                    </p>
                </div>
            </div>
        </div>
    );
}
