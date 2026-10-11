import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Merge conditional Tailwind class lists, resolving conflicting utilities so
 * component variants compose predictably (shadcn-vue convention).
 */
export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}
