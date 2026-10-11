import { describe, expect, it } from 'vitest';
import { humanizeStatus, statusTone } from '@/lib/status';

describe('statusTone', () => {
    it('maps terminal success states to success', () => {
        expect(statusTone('succeeded')).toBe('success');
    });

    it('maps in-flight states to warning/info', () => {
        expect(statusTone('pending')).toBe('warning');
        expect(statusTone('reserved')).toBe('warning');
        expect(statusTone('processing')).toBe('info');
    });

    it('maps failure states to danger', () => {
        expect(statusTone('failed')).toBe('danger');
        expect(statusTone('quarantined')).toBe('danger');
    });

    it('falls back to neutral for unknown states', () => {
        expect(statusTone('something_new')).toBe('neutral');
    });
});

describe('humanizeStatus', () => {
    it('title-cases single words', () => {
        expect(humanizeStatus('succeeded')).toBe('Succeeded');
    });

    it('splits snake_case into words', () => {
        expect(humanizeStatus('awaiting_settlement')).toBe('Awaiting Settlement');
    });
});
