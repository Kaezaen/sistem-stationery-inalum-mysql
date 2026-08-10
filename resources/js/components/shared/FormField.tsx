import { cn } from '@/lib/utils';
import type { PropsWithChildren } from 'react';

interface Props {
    label: string;
    htmlFor?: string;
    error?: string;
    required?: boolean;
    hint?: string;
    className?: string;
}

export default function FormField({
    label,
    htmlFor,
    error,
    required,
    hint,
    className,
    children,
}: PropsWithChildren<Props>) {
    return (
        <div className={cn('space-y-1.5', className)}>
            <label htmlFor={htmlFor} className="block text-sm font-medium">
                {label}
                {required && <span className="ml-0.5 text-destructive">*</span>}
            </label>
            {children}
            {hint && !error && <p className="text-xs text-muted-foreground">{hint}</p>}
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
