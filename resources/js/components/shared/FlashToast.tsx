import { cn } from '@/lib/utils';
import { CheckCircle2, X, XCircle } from 'lucide-react';
import { AnimatePresence, motion } from 'motion/react';
import { useEffect, useState } from 'react';

/**
 * Notifikasi flash mengambang (toast) yang beranimasi masuk/keluar dan menghilang
 * sendiri setelah beberapa detik — menggantikan banner statis.
 *
 * Toast di-remount lewat `key={message}` sehingga pesan baru selalu tampil ulang;
 * efeknya hanya menjadwalkan penghilangan (setState di dalam timer, bukan sinkron
 * di dalam effect).
 */
export default function FlashToast({
    flash,
}: {
    flash: { success: string | null; error: string | null };
}) {
    const message = flash.success ?? flash.error;
    const isError = flash.error !== null && flash.error !== undefined;

    return (
        <div className="pointer-events-none fixed top-4 right-4 z-50 flex w-full max-w-sm flex-col gap-2">
            <AnimatePresence>
                {message && <Toast key={message} message={message} isError={isError} />}
            </AnimatePresence>
        </div>
    );
}

function Toast({ message, isError }: { message: string; isError: boolean }) {
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        const timer = setTimeout(() => setVisible(false), 4000);

        return () => clearTimeout(timer);
    }, []);

    return (
        <AnimatePresence>
            {visible && (
                <motion.div
                    initial={{ opacity: 0, y: -16, scale: 0.96 }}
                    animate={{ opacity: 1, y: 0, scale: 1 }}
                    exit={{ opacity: 0, x: 24, scale: 0.96 }}
                    transition={{ type: 'spring', stiffness: 400, damping: 30 }}
                    className={cn(
                        'pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg',
                        isError
                            ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
                    )}
                >
                    {isError ? (
                        <XCircle className="mt-0.5 size-5 shrink-0" aria-hidden />
                    ) : (
                        <CheckCircle2 className="mt-0.5 size-5 shrink-0" aria-hidden />
                    )}
                    <p className="flex-1">{message}</p>
                    <button
                        type="button"
                        onClick={() => setVisible(false)}
                        className="shrink-0 rounded p-0.5 opacity-60 transition-opacity hover:opacity-100"
                        aria-label="Tutup"
                    >
                        <X className="size-4" aria-hidden />
                    </button>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
