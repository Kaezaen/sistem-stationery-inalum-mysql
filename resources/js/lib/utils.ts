import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Menggabungkan class Tailwind dengan penyelesaian konflik.
 * Dipakai seluruh komponen shadcn/ui.
 */
export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}
