import { cn } from '@/lib/utils';
import { useEffect, useState } from 'react';

/**
 * Logo Inalum.
 *
 * Memakai BERKAS GAMBAR RESMI bila tersedia — cukup taruh logo di
 * `public/images/inalum-logo.png` (PNG transparan) dan seluruh pemakaian otomatis
 * memakainya, tanpa mengubah kode. Bila berkas tidak ada, dipakai fallback SVG
 * rekreasi yang wordmark-nya dikunci lebar-nya (textLength) sehingga selalu
 * dirender penuh, apa pun font/ukurannya.
 *
 * Ukuran diatur lewat tinggi: <InalumLogo className="h-8 w-auto" />.
 */
const LOGO_SRC = '/images/inalum-logo.png';

// Cek keberadaan berkas SEKALI per sesi agar tidak menembak 404 berulang dan
// tidak berkedip: semua instance ikut hasil probe pertama. setState hanya terjadi
// di callback async (onload/onerror), tak pernah sinkron di dalam effect.
let status: 'unknown' | 'ok' | 'missing' = 'unknown';
let started = false;
const waiters = new Set<(ok: boolean) => void>();

function startProbe(): void {
    if (started) {
        return;
    }
    started = true;

    const settle = (result: 'ok' | 'missing'): void => {
        status = result;
        waiters.forEach((notify) => notify(result === 'ok'));
        waiters.clear();
    };

    const img = new Image();
    img.onload = () => settle('ok');
    img.onerror = () => settle('missing');
    img.src = LOGO_SRC;
}

function useOfficialLogo(): boolean {
    const [ok, setOk] = useState(status === 'ok');

    useEffect(() => {
        // Hasil sudah diketahui: state awal sudah mencerminkannya, tak perlu setState.
        if (status !== 'unknown') {
            return;
        }

        waiters.add(setOk);
        startProbe();

        return () => {
            waiters.delete(setOk);
        };
    }, []);

    return ok;
}

export default function InalumLogo({ className }: { className?: string }) {
    const official = useOfficialLogo();

    if (official) {
        return <img src={LOGO_SRC} alt="Inalum" className={cn('h-8 w-auto', className)} />;
    }

    return <LogoSvg className={className} />;
}

/** Fallback rekreasi. Wordmark dikunci lebar (textLength) agar tak pernah terpotong. */
function LogoSvg({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 350 80"
            role="img"
            aria-label="Inalum"
            className={cn('h-8 w-auto', className)}
            xmlns="http://www.w3.org/2000/svg"
        >
            <title>Inalum</title>

            {/* Mark "A" — dua kaki ribbon bertemu di puncak + aksen lime */}
            <polygon points="40,6 56,6 24,74 6,74" fill="#1c9ad6" />
            <polygon points="56,6 78,6 100,74 76,74" fill="#ed1c24" />
            <polygon points="50,6 66,6 58,32" fill="#8dc63f" />

            {/* Wordmark — textLength mengunci lebar 156 unit, lengthAdjust menskalakan
                glyph agar pas; jadi tak pernah meluber keluar viewBox. */}
            <text
                x="112"
                y="54"
                textLength="156"
                lengthAdjust="spacingAndGlyphs"
                fontFamily="Poppins, ui-sans-serif, system-ui, sans-serif"
                fontSize="46"
                fontWeight="700"
                fill="#1c9ad6"
            >
                Inalum
            </text>

            {/* Cincin orbit — busur ~300° yang meluruh menjadi tiga titik */}
            <path
                d="M320.7 48.5 A17 17 0 1 1 320.7 31.5"
                fill="none"
                stroke="#ed1c24"
                strokeWidth="7"
                strokeLinecap="round"
            />
            <circle cx="322.9" cy="41.5" r="3.2" fill="#ed1c24" />
            <circle cx="322.8" cy="37.6" r="2.4" fill="#ed1c24" />
            <circle cx="322" cy="34.2" r="1.6" fill="#ed1c24" />
        </svg>
    );
}

/** Hanya mark "A" + orbit (tanpa wordmark) — untuk ruang sempit / dekorasi. */
export function InalumMark({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 106 80"
            role="img"
            aria-label="Inalum"
            className={cn('h-8 w-auto', className)}
            xmlns="http://www.w3.org/2000/svg"
        >
            <title>Inalum</title>
            <polygon points="40,6 56,6 24,74 6,74" fill="#1c9ad6" />
            <polygon points="56,6 78,6 100,74 76,74" fill="#ed1c24" />
            <polygon points="50,6 66,6 58,32" fill="#8dc63f" />
        </svg>
    );
}
