import { cn } from '@/lib/utils';
import type { SelectHTMLAttributes } from 'react';

export default function SelectInput({
    className,
    invalid,
    children,
    ...props
}: SelectHTMLAttributes<HTMLSelectElement> & { invalid?: boolean }) {
    return (
        <select
            {...props}
            className={cn(
                'flex h-9 w-full rounded-md border bg-background px-3 py-1 text-sm shadow-xs transition-colors',
                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                'disabled:cursor-not-allowed disabled:opacity-50',
                invalid && 'border-destructive focus-visible:ring-destructive',
                className,
            )}
        >
            {children}
        </select>
    );
}
