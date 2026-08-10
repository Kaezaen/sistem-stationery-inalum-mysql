import { cn } from '@/lib/utils';
import type { InputHTMLAttributes } from 'react';

export default function TextInput({
    className,
    invalid,
    ...props
}: InputHTMLAttributes<HTMLInputElement> & { invalid?: boolean }) {
    return (
        <input
            {...props}
            className={cn(
                'flex h-9 w-full rounded-md border bg-background px-3 py-1 text-sm shadow-xs transition-colors',
                'placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                'disabled:cursor-not-allowed disabled:opacity-50',
                invalid && 'border-destructive focus-visible:ring-destructive',
                className,
            )}
        />
    );
}
