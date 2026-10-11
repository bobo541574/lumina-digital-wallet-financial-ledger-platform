import { describe, expect, it } from 'vitest';
import { formatRelativeTime } from '@/lib/format/date';

const now = new Date('2026-10-11T12:00:00Z');

describe('formatRelativeTime', () => {
    it('returns an empty string for missing or invalid input', () => {
        expect(formatRelativeTime(null, now)).toBe('');
        expect(formatRelativeTime('not-a-date', now)).toBe('');
    });

    it('reports very recent timestamps as just now', () => {
        expect(formatRelativeTime('2026-10-11T11:59:30Z', now)).toBe('just now');
    });

    it('formats minutes, hours and days', () => {
        expect(formatRelativeTime('2026-10-11T11:57:00Z', now)).toBe('3m ago');
        expect(formatRelativeTime('2026-10-11T09:00:00Z', now)).toBe('3h ago');
        expect(formatRelativeTime('2026-10-09T12:00:00Z', now)).toBe('2d ago');
    });

    it('falls back to a locale date for older timestamps', () => {
        const old = '2026-09-01T12:00:00Z';

        expect(formatRelativeTime(old, now)).toBe(new Date(old).toLocaleDateString());
    });
});
