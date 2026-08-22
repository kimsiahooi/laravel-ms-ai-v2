/**
 * `Acme Trading Sdn Bhd` → `acme-trading-sdn-bhd`.
 *
 * Deliberately lossy and ASCII-only, because the result has to satisfy the same
 * pattern the server enforces and the `{tenant}` route matches: lowercase kebab, and
 * nothing else. Anything outside that — accents, CJK, punctuation — collapses to a
 * separator and drops out, which is exactly why the field it fills stays editable
 * rather than being derived and locked.
 *
 * The 50-character cap is not cosmetic: `<db prefix><slug>` has to fit MySQL's
 * 64-character database-name limit, and the FormRequest caps it at the same number.
 */
export function toSlug(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 50);
}
