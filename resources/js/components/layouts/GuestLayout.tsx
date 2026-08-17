import InalumLogo, { InalumMark } from '@/components/shared/InalumLogo';
import { fadeUp, scaleIn, staggerContainer } from '@/lib/motion';
import { Check } from 'lucide-react';
import { motion } from 'motion/react';
import type { PropsWithChildren } from 'react';

const FEATURES = [
    'Approval berjenjang tiga level',
    'Stok & reservasi real-time',
    'Laporan, notifikasi, dan audit lengkap',
];

export default function GuestLayout({
    title,
    description,
    children,
}: PropsWithChildren<{ title: string; description?: string }>) {
    const year = new Date().getFullYear();

    return (
        <div className="flex min-h-screen">
            {/* Panel brand — hanya tampil di layar lebar */}
            <motion.aside
                className="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-gradient-to-br from-primary via-blue-700 to-blue-900 p-10 text-white lg:flex xl:w-[55%]"
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.4 }}
            >
                {/* Aksen dekoratif */}
                <div
                    className="pointer-events-none absolute -top-20 -left-16 size-72 rounded-full bg-white/10 blur-3xl"
                    aria-hidden
                />
                <div
                    className="pointer-events-none absolute top-1/3 right-8 size-56 rounded-full bg-tertiary/30 blur-3xl"
                    aria-hidden
                />
                <InalumMark
                    className="pointer-events-none absolute -right-12 -bottom-12 h-80 w-auto rotate-12 opacity-10"
                    aria-hidden
                />

                <motion.div
                    className="relative flex h-full flex-col justify-between"
                    variants={staggerContainer}
                    initial="hidden"
                    animate="visible"
                >
                    <motion.div
                        className="inline-flex w-fit rounded-2xl bg-white px-5 py-4 shadow-xl"
                        variants={fadeUp}
                    >
                        <InalumLogo className="h-9 w-auto" />
                    </motion.div>

                    <motion.div className="max-w-md" variants={fadeUp}>
                        <h2 className="text-3xl leading-tight font-semibold tracking-tight">
                            Sistem Manajemen Stationery
                        </h2>
                        <p className="mt-3 text-white/80">
                            Standarisasi pengajuan dan verifikasi pembelian Alat Tulis Kantor —
                            cepat, transparan, dan terekam.
                        </p>

                        <ul className="mt-8 space-y-3 text-sm text-white/90">
                            {FEATURES.map((feature) => (
                                <li key={feature} className="flex items-center gap-3">
                                    <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-white/20">
                                        <Check className="size-3" aria-hidden />
                                    </span>
                                    {feature}
                                </li>
                            ))}
                        </ul>
                    </motion.div>

                    <motion.p className="text-xs text-white/60" variants={fadeUp}>
                        © {year} PT Indonesia Asahan Aluminium
                    </motion.p>
                </motion.div>
            </motion.aside>

            {/* Panel form */}
            <div className="flex flex-1 items-center justify-center bg-muted/30 p-6">
                <motion.div
                    className="w-full max-w-sm"
                    variants={staggerContainer}
                    initial="hidden"
                    animate="visible"
                >
                    <motion.div
                        className="mb-8 flex flex-col items-center gap-3 lg:hidden"
                        variants={scaleIn}
                    >
                        <InalumLogo className="h-9 w-auto" />
                        <span className="text-sm font-medium tracking-tight text-muted-foreground">
                            Sistem Stationery
                        </span>
                    </motion.div>

                    <motion.div
                        className="rounded-xl border bg-card p-6 shadow-sm sm:p-8"
                        variants={fadeUp}
                    >
                        <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
                        {description && (
                            <p className="mt-1 text-sm text-muted-foreground">{description}</p>
                        )}

                        <div className="mt-6">{children}</div>
                    </motion.div>

                    <p className="mt-6 text-center text-xs text-muted-foreground lg:hidden">
                        PT Indonesia Asahan Aluminium
                    </p>
                </motion.div>
            </div>
        </div>
    );
}
