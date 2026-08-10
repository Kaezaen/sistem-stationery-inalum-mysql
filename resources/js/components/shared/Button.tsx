import { cn } from '@/lib/utils';
import type { ButtonHTMLAttributes } from 'react';

type Variant =
    'primary' | 'secondary' | 'tertiary' | 'inverted' | 'outline' | 'destructive' | 'ghost';

const VARIANTS: Record<Variant, string> = {
    primary: 'bg-primary text-primary-foreground shadow-sm shadow-primary/20 hover:bg-primary/90',
    secondary: 'border bg-background hover:bg-secondary',
    tertiary:
        'bg-tertiary text-tertiary-foreground shadow-sm shadow-tertiary/20 hover:bg-tertiary/90',
    inverted: 'bg-foreground text-background hover:bg-foreground/90',
    outline: 'border border-input bg-transparent hover:bg-accent hover:text-accent-foreground',
    destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
    ghost: 'hover:bg-secondary',
};

export default function Button({
    className,
    variant = 'primary',
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: Variant }) {
    return (
        <button
            {...props}
            className={cn(
                'inline-flex h-9 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium',
                'transition-[color,background-color,box-shadow,transform] active:scale-[0.98]',
                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                'disabled:pointer-events-none disabled:opacity-50',
                VARIANTS[variant],
                className,
            )}
        />
    );
}
