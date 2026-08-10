import { animate, motion, useMotionValue, useReducedMotion, useTransform } from 'motion/react';
import { useEffect } from 'react';

/**
 * Angka yang menghitung naik dari 0 ke nilai target — untuk kartu statistik.
 *
 * Menghormati prefers-reduced-motion: bila aktif, angka langsung ditampilkan
 * tanpa animasi. Diformat gaya Indonesia (titik ribuan).
 */
export default function AnimatedNumber({
    value,
    className,
    duration = 0.8,
}: {
    value: number;
    className?: string;
    duration?: number;
}) {
    const shouldReduce = useReducedMotion();
    const count = useMotionValue(0);
    const text = useTransform(count, (latest) => Math.round(latest).toLocaleString('id-ID'));

    useEffect(() => {
        if (shouldReduce) {
            count.set(value);
            return;
        }

        const controls = animate(count, value, { duration, ease: [0.22, 1, 0.36, 1] });

        return () => controls.stop();
    }, [value, duration, shouldReduce, count]);

    return <motion.span className={className}>{text}</motion.span>;
}
