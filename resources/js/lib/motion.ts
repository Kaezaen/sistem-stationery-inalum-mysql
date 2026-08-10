import type { Transition, Variants } from 'motion/react';

/**
 * Variants animasi bersama agar seluruh layar memakai gerakan yang konsisten.
 *
 * Semua gerakan halus & cepat (200–400ms) dan hanya menganimasi transform/opacity
 * (murah untuk GPU). Pengguna dengan prefers-reduced-motion otomatis dihormati
 * karena aplikasi dibungkus <MotionConfig reducedMotion="user"> di app.tsx.
 */

const easeOut: Transition['ease'] = [0.22, 1, 0.36, 1];

/** Muncul dari bawah sambil memudar — untuk kartu & blok konten. */
export const fadeUp: Variants = {
    hidden: { opacity: 0, y: 12 },
    visible: { opacity: 1, y: 0, transition: { duration: 0.35, ease: easeOut } },
};

/** Muncul dengan skala kecil — untuk elemen yang menonjol (logo, dialog). */
export const scaleIn: Variants = {
    hidden: { opacity: 0, scale: 0.96 },
    visible: { opacity: 1, scale: 1, transition: { duration: 0.3, ease: easeOut } },
};

/** Induk yang menahan anak-anaknya lalu memunculkannya bergiliran (stagger). */
export const staggerContainer: Variants = {
    hidden: {},
    visible: { transition: { staggerChildren: 0.06, delayChildren: 0.04 } },
};

/** Batang chart tumbuh dari bawah. */
export const growBar: Variants = {
    hidden: { scaleY: 0 },
    visible: { scaleY: 1, transition: { duration: 0.5, ease: easeOut } },
};

/** Interaksi hover/tap ringan untuk kartu yang dapat diangkat. */
export const hoverLift = {
    whileHover: { y: -3, transition: { type: 'spring' as const, stiffness: 400, damping: 25 } },
    whileTap: { scale: 0.99 },
};
