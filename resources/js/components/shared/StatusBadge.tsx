import { cn } from '@/lib/utils';

type Tone = 'neutral' | 'success' | 'warning' | 'danger';

const TONES: Record<Tone, string> = {
    neutral: 'bg-secondary text-secondary-foreground',
    success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    warning: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    danger: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
};

export default function StatusBadge({
    children,
    tone = 'neutral',
}: {
    children: string;
    tone?: Tone;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                TONES[tone],
            )}
        >
            {children}
        </span>
    );
}
