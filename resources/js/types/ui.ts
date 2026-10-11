/** Shared UI-layer types (kept out of `.vue` files so they are importable). */

export interface TableColumn {
    key: string;
    label: string;
    align?: 'left' | 'right' | 'center';
    class?: string;
}

export interface SelectOption {
    value: string;
    label: string;
}

export interface DescriptionItem {
    label: string;
    value: string;
}

export interface TimelineItem {
    status: string;
    label?: string;
    at?: string | null;
}
