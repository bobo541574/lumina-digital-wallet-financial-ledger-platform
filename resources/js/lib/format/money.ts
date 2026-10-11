/**
 * Money formatting utilities.
 *
 * The backend is authoritative and transmits every amount as an INTEGER in the
 * currency's minor units (e.g. 500 = 5.00). The frontend never does float
 * arithmetic on money: it only formats minor units for display and parses user
 * input back into minor units. See docs/architecture/adr/ADR-0001.
 */

/** Currencies the platform supports, with their ISO-4217 exponent (minor digits). */
const CURRENCY_MINOR_UNITS: Record<string, number> = {
    USD: 2,
    MMK: 2,
};

const DEFAULT_LOCALE = 'en-US';

/** Currencies the platform can open wallets in (sorted ISO-4217 codes). */
export const SUPPORTED_CURRENCIES: readonly string[] = Object.keys(CURRENCY_MINOR_UNITS).sort();

export function minorUnitsFor(currency: string): number {
    return CURRENCY_MINOR_UNITS[currency.toUpperCase()] ?? 2;
}

export function isSupportedCurrency(currency: string): boolean {
    return currency.toUpperCase() in CURRENCY_MINOR_UNITS;
}

/**
 * Format an integer minor-unit amount as a localized currency string.
 *
 * @example formatMoney(123456, 'USD') // "$1,234.56"
 */
export function formatMoney(
    amountMinor: number,
    currency: string,
    locale = DEFAULT_LOCALE,
): string {
    if (!Number.isInteger(amountMinor)) {
        throw new TypeError(`Money must be an integer number of minor units, got ${amountMinor}.`);
    }

    const code = currency.toUpperCase();
    const exponent = minorUnitsFor(code);
    const major = amountMinor / 10 ** exponent;

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: code,
        minimumFractionDigits: exponent,
        maximumFractionDigits: exponent,
    }).format(major);
}

/**
 * Parse a user-entered decimal string into integer minor units.
 * Throws on invalid or over-precise input rather than silently rounding.
 *
 * @example parseAmountToMinor('12.30', 'USD') // 1230
 */
export function parseAmountToMinor(input: string, currency: string): number {
    const trimmed = input.trim().replace(/,/g, '');

    if (!/^\d+(\.\d+)?$/.test(trimmed)) {
        throw new Error('Enter a valid amount.');
    }

    const exponent = minorUnitsFor(currency);
    const [whole = '0', fraction = ''] = trimmed.split('.');

    if (fraction.length > exponent) {
        throw new Error(
            `Only ${exponent} decimal place(s) are allowed for ${currency.toUpperCase()}.`,
        );
    }

    const paddedFraction = fraction.padEnd(exponent, '0');

    return Number.parseInt(`${whole}${paddedFraction}`, 10);
}

/**
 * Format minor units as a plain decimal string without a currency symbol —
 * useful for form inputs and copy-to-clipboard.
 */
export function formatMinorPlain(amountMinor: number, currency: string): string {
    const exponent = minorUnitsFor(currency);
    const negative = amountMinor < 0;
    const digits = Math.abs(amountMinor)
        .toString()
        .padStart(exponent + 1, '0');
    const whole = digits.slice(0, digits.length - exponent);
    const fraction = digits.slice(digits.length - exponent);

    return `${negative ? '-' : ''}${whole}${exponent > 0 ? `.${fraction}` : ''}`;
}
