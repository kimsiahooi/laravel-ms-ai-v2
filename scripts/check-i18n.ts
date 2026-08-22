/**
 * Localization gate — the counterpart to check-validation-parity.ts.
 *
 * A missing translation does not crash: `t()` falls back to the base locale's text,
 * so an untranslated string ships silently as English and nobody notices until a user
 * complains. That is exactly the class of bug a type checker cannot see, so it gets a
 * gate instead.
 *
 * Fails when:
 *   1. the committed artifacts are stale — `lang/**.php` changed and
 *      `bun run lang:export` was not re-run (checked against a temp export, so this
 *      never edits your working tree);
 *   2. a non-base locale is missing a key the base locale has;
 *   3. a locale carries a key the base locale no longer has (a stale leftover);
 *   4. a `t()` / `tChoice()` call names a key that does not exist.
 *
 * Run: `bun run check:i18n`
 */

import {
    mkdtempSync,
    readdirSync,
    readFileSync,
    rmSync,
    statSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const BASE = 'en';
const BUNDLE_DIR = 'resources/js/lang';
const TYPES_FILE = 'resources/js/types/lang.d.ts';
const SOURCE = join(process.cwd(), 'resources/js');

type Bundle = Record<string, string>;

const problems: string[] = [];

function read(path: string): string {
    try {
        return readFileSync(path, 'utf8');
    } catch {
        return '';
    }
}

// --- 1. the committed artifacts match lang/ ----------------------------------
//
// Exported to a temp directory and compared, never written over the real ones: a
// gate that edits the working tree turns one failure into a second, unrelated
// failure on the next run, and buries the problem you were actually chasing.

const scratch = mkdtempSync(join(tmpdir(), 'i18n-'));

const exported = Bun.spawnSync([
    'php',
    'artisan',
    'lang:export',
    `--output=${scratch}`,
]);

if (exported.exitCode !== 0) {
    rmSync(scratch, { force: true, recursive: true });
    console.error('✗ php artisan lang:export failed:\n');
    console.error(exported.stderr.toString() || exported.stdout.toString());
    process.exit(1);
}

const generated = [
    TYPES_FILE,
    ...readdirSync(join(scratch, BUNDLE_DIR)).map((f) => `${BUNDLE_DIR}/${f}`),
];

const stale = generated.filter(
    (relative) =>
        read(join(process.cwd(), relative)) !== read(join(scratch, relative)),
);

if (stale.length > 0) {
    problems.push(
        `these are out of date with lang/ — run \`bun run lang:export\` and commit: ${stale.join(', ')}`,
    );
}

// --- 2 + 3. every locale agrees with the base --------------------------------
//
// Read from the FRESH export, not the committed copy, so a stale artifact cannot
// mask (or invent) a missing-translation problem.

const locales = readdirSync(join(scratch, BUNDLE_DIR))
    .filter((file) => file.endsWith('.json'))
    .map((file) => file.replace('.json', ''));

const bundles = new Map<string, Bundle>(
    locales.map((locale) => [
        locale,
        JSON.parse(read(join(scratch, BUNDLE_DIR, `${locale}.json`))) as Bundle,
    ]),
);

rmSync(scratch, { force: true, recursive: true });

const baseBundle = bundles.get(BASE);

if (!baseBundle) {
    console.error(
        `✗ no base locale bundle for ${BASE} — is lang/${BASE}/ missing?`,
    );
    process.exit(1);
}

const baseKeys = Object.keys(baseBundle);

for (const [locale, bundle] of bundles) {
    if (locale === BASE) {
        continue;
    }

    const keys = Object.keys(bundle);
    const missing = baseKeys.filter((key) => !(key in bundle));
    const extra = keys.filter((key) => !(key in baseBundle));

    if (missing.length > 0) {
        problems.push(
            `${locale} is missing ${missing.length} key(s) that ${BASE} has — they would ` +
                `silently render in ${BASE}: ${preview(missing)}`,
        );
    }

    if (extra.length > 0) {
        problems.push(
            `${locale} has ${extra.length} key(s) ${BASE} does not — stale after a rename: ${preview(extra)}`,
        );
    }
}

// --- 4. every t() call names a real key ---------------------------------------

const CALL = /\b(?:t|tChoice)\(\s*'([a-z0-9_]+(?:\.[a-z0-9_]+)+)'/g;

for (const file of sourceFiles(SOURCE)) {
    const contents = readFileSync(file, 'utf8');

    for (const [, key] of contents.matchAll(CALL)) {
        if (!(key in baseBundle)) {
            problems.push(
                `${file.replace(`${process.cwd()}/`, '')}: t('${key}') — no such key in ${BASE}.`,
            );
        }
    }
}

function sourceFiles(dir: string): string[] {
    const found: string[] = [];

    for (const entry of readdirSync(dir)) {
        // Generated trees carry no authored t() calls.
        if (['lang', 'routes', 'actions', 'wayfinder'].includes(entry)) {
            continue;
        }

        const path = join(dir, entry);

        if (statSync(path).isDirectory()) {
            found.push(...sourceFiles(path));
        } else if (path.endsWith('.ts') || path.endsWith('.tsx')) {
            found.push(path);
        }
    }

    return found;
}

function preview(keys: string[]): string {
    return keys.slice(0, 5).join(', ') + (keys.length > 5 ? ', …' : '');
}

// --- report -------------------------------------------------------------------

if (problems.length > 0) {
    console.error('✗ i18n check failed:\n');
    for (const problem of problems) {
        console.error(`  • ${problem}`);
    }
    process.exit(1);
}

console.log(
    `✓ i18n: ${baseKeys.length} keys × ${bundles.size} locales agree, every t() key exists.`,
);
