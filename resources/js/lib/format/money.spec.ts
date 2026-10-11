import { describe, expect, it } from 'vitest';
import {
    formatMinorPlain,
    formatMoney,
    isSupportedCurrency,
    minorUnitsFor,
    parseAmountToMinor,
} from '@/lib/format/money';

describe('minorUnitsFor', () => {
    it('returns the ISO-4217 exponent for supported currencies', () => {
        expect(minorUnitsFor('USD')).toBe(2);
        expect(minorUnitsFor('mmk')).toBe(2);
    });

    it('defaults to 2 for unknown currencies', () => {
        expect(minorUnitsFor('ZZZ')).toBe(2);
    });
});

describe('isSupportedCurrency', () => {
    it('is case-insensitive', () => {
        expect(isSupportedCurrency('usd')).toBe(true);
        expect(isSupportedCurrency('XYZ')).toBe(false);
    });
});

describe('formatMoney', () => {
    it('formats integer minor units as a currency string', () => {
        expect(formatMoney(500, 'USD')).toBe('$5.00');
        expect(formatMoney(123456, 'USD')).toBe('$1,234.56');
    });

    it('rejects non-integer amounts instead of silently rounding', () => {
        expect(() => formatMoney(12.5, 'USD')).toThrow(TypeError);
    });
});

describe('parseAmountToMinor', () => {
    it('parses a decimal string into minor units', () => {
        expect(parseAmountToMinor('5', 'USD')).toBe(500);
        expect(parseAmountToMinor('12.30', 'USD')).toBe(1230);
        expect(parseAmountToMinor('1,234.56', 'USD')).toBe(123456);
    });

    it('pads single-digit fractions correctly', () => {
        expect(parseAmountToMinor('0.5', 'USD')).toBe(50);
    });

    it('rejects over-precise input', () => {
        expect(() => parseAmountToMinor('1.234', 'USD')).toThrowError(/decimal place/);
    });

    it('rejects malformed input', () => {
        expect(() => parseAmountToMinor('abc', 'USD')).toThrowError(/valid amount/);
        expect(() => parseAmountToMinor('1.2.3', 'USD')).toThrow();
    });

    it('round-trips with formatMinorPlain', () => {
        expect(formatMinorPlain(parseAmountToMinor('12.34', 'USD'), 'USD')).toBe('12.34');
    });
});

describe('formatMinorPlain', () => {
    it('renders fixed decimal places without a symbol', () => {
        expect(formatMinorPlain(500, 'USD')).toBe('5.00');
        expect(formatMinorPlain(5, 'USD')).toBe('0.05');
        expect(formatMinorPlain(-1230, 'USD')).toBe('-12.30');
    });
});
