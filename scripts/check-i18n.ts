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
 *   4. a `t()` / `tChoice()` call names a key that does not exist;
 *   5. a FormRequest uses a validation rule that has no translated message;
 *   6. a component renders a hard-coded string instead of calling t().
 *
 * Check 6 is the one that closes the gap the other five could not see. Checks 1-5 all
 * verify that keys which ARE referenced resolve — by construction none of them can fail
 * on a literal, so a file with no `t()` at all was invisible. Nine auth and settings
 * files sat untranslated behind a green tick until it existed.
 *
 * That last one exists because of a bug this gate was written after. Laravel falls
 * back to its own English `validation.php` for any rule `lang/{locale}/validation.php`
 * does not carry, and it fills `:attribute` from a translation regardless — so a Malay
 * form was refusing an address with "The e-mel pentadbir field must be a valid email
 * address.": half translated, and reported by nobody. A missing rule message is
 * invisible in exactly the way a missing UI string is not.
 *
 * Run: `bun run check:i18n`
 */

import { spawnSync } from 'node:child_process';
import {
    existsSync,
    mkdtempSync,
    readdirSync,
    readFileSync,
    rmSync,
    statSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import ts from 'typescript';

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

// `node:child_process` rather than `Bun.spawnSync`: identical here, and it keeps this
// file typed by @types/node alone, so `bun run types:check` covers the gates too.
const exported = spawnSync('php', [
    'artisan',
    'lang:export',
    `--output=${scratch}`,
]);

if (exported.status !== 0) {
    rmSync(scratch, { force: true, recursive: true });
    console.error('✗ php artisan lang:export failed:\n');
    console.error(
        exported.stderr?.toString() || exported.stdout?.toString() || '',
    );
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

// --- 5. every validation rule in use has a translated message -----------------
//
// Laravel's fallback hides this: an untranslated rule renders in English rather than
// failing, so nothing short of reading every message in every language would catch it.

/** Rules that never produce a message — they only decide whether others run. */
const SILENT = new Set([
    'bail',
    'exclude',
    'exclude_if',
    'exclude_unless',
    'exclude_with',
    'exclude_without',
    'nullable',
    'sometimes',
]);

const REQUEST_DIRS = ['app/Http/Requests'];

for (const file of phpFiles(REQUEST_DIRS)) {
    const php = readFileSync(file, 'utf8');
    const overridden = customMessages(php);
    const where = file.replace(`${process.cwd()}/`, '');

    for (const [field, rule] of rulesInUse(php)) {
        if (SILENT.has(rule) || overridden.has(`${field}.${rule}`)) {
            continue;
        }

        const covered = baseKeys.some(
            (key) =>
                key === `validation.${rule}` ||
                key.startsWith(`validation.${rule}.`),
        );

        if (!covered) {
            problems.push(
                `${where}: rule '${rule}' on '${field}' has no message in lang/${BASE}/validation.php — ` +
                    `it would fall back to the framework's English in ms and zh_Hans.`,
            );
        }
    }
}

/** Every `field => rule` pair a FormRequest's `rules()` declares. */
function rulesInUse(php: string): Array<[string, string]> {
    const start = php.indexOf('public function rules');

    if (start === -1) {
        return [];
    }

    // Stop at the next method, so `messages()` and `attributes()` are not read as rules.
    const rest = php.slice(start);
    const end = rest.indexOf('\n    public function', 1);
    const body = end === -1 ? rest : rest.slice(0, end);

    const fields = [...body.matchAll(/^\s{12}'([^'.]+)'\s*=>/gm)];

    return fields.flatMap((match, index) => {
        const from = (match.index ?? 0) + match[0].length;
        const to = fields[index + 1]?.index ?? body.length;
        const block = body.slice(from, to);
        const field = match[1];

        // `Rule::notIn(…)` is the `not_in` rule wearing a builder.
        const builders = [...block.matchAll(/Rule::(\w+)\s*\(/g)].map((m) =>
            m[1].replace(/([a-z0-9])([A-Z])/g, '$1_$2').toLowerCase(),
        );
        // Their arguments are table and column names, not rules — drop them before
        // reading the plain-string rules, or `Rule::unique('tenants', 'id')` reports
        // two rules called `tenants` and `id`.
        const strings = [
            ...block
                .replace(/Rule::\w+\s*\([^)]*\)/g, '')
                .matchAll(/'([a-z_]+)(?::[^']*)?'/g),
        ].map((m) => m[1]);

        return [...new Set([...strings, ...builders])].map(
            (rule) => [field, rule] as [string, string],
        );
    });
}

/** `'slug.regex' => __('console.validation.slug_regex')` — rules with their own wording. */
function customMessages(php: string): Set<string> {
    const start = php.indexOf('public function messages');

    if (start === -1) {
        return new Set();
    }

    return new Set(
        [...php.slice(start).matchAll(/^\s{12}'([^']+\.[^']+)'\s*=>/gm)].map(
            (m) => m[1],
        ),
    );
}

function phpFiles(dirs: string[]): string[] {
    const found: string[] = [];

    for (const dir of dirs) {
        const root = join(process.cwd(), dir);

        if (!existsSync(root)) {
            continue;
        }

        for (const entry of readdirSync(root)) {
            const path = join(root, entry);

            if (statSync(path).isDirectory()) {
                found.push(...phpFiles([join(dir, entry)]));
            } else if (path.endsWith('Request.php')) {
                found.push(path);
            }
        }
    }

    return found;
}

// --- 6. no hard-coded user-facing strings -------------------------------------
//
// Parsed rather than grepped, and that is not fastidiousness: a regex pass over the
// same files reported `Promise` out of `() => Promise<void>` and every translation key
// already being passed as a `label`. A gate that cries wolf gets switched off.
//
// What counts as user-facing: JSX text, a string literal given to a prop a person reads
// (not className/id/variant/href), and a literal inside a rendered expression such as
// `{busy ? 'Saving…' : 'Save'}`. What does not: a literal that is an operand of a
// comparison — in `status === 'verification-link-sent' && <p/>` the string is a sentinel,
// never text. `i18n-allow` on the line opts a genuine non-string out (a product name, an
// example slug).

const UI_ROOTS = [
    'resources/js/pages',
    'resources/js/components',
    'resources/js/layouts',
];

// Props whose value someone reads. Everything else is machinery.
const HUMAN_PROPS = new Set([
    'title',
    'placeholder',
    'label',
    'description',
    'alt',
    'aria-label',
    'loadingLabel',
    'separator',
    'busyLabel',
    'confirmLabel',
    'searchPlaceholder',
    'emptyMessage',
]);

// A value that is already a translation key, passed through to a component that resolves
// it — ColumnHeader takes one as `label`, ResourceFormDialog takes four. Not a sentence.
//
// Applied wherever a literal could be one: a plain attribute value, and a literal inside
// a rendered expression. The tradeoff is that a genuine sentence shaped like a key would
// slip through — but lowercase, dotted and spaceless is not a shape English comes in, and
// one rendered as text would read as `categories.edit.title` on screen.
const KEY_SHAPE = /^[a-z0-9_]+(?:\.[a-z0-9_]+)+$/;

function tsxFiles(dir: string): string[] {
    const found: string[] = [];

    for (const entry of readdirSync(dir)) {
        // Vendored shadcn. Not ours to translate, and not ours to edit.
        if (entry === 'ui') {
            continue;
        }

        const path = join(dir, entry);

        if (statSync(path).isDirectory()) {
            found.push(...tsxFiles(path));
        } else if (path.endsWith('.tsx')) {
            found.push(path);
        }
    }

    return found;
}

for (const root of UI_ROOTS) {
    for (const file of tsxFiles(root)) {
        const contents = readFileSync(file, 'utf8');
        const lines = contents.split('\n');
        const source = ts.createSourceFile(
            file,
            contents,
            ts.ScriptTarget.Latest,
            true,
            ts.ScriptKind.TSX,
        );

        const lineOf = (node: ts.Node): number =>
            source.getLineAndCharacterOfPosition(node.getStart(source)).line;

        const report = (node: ts.Node, what: string): void => {
            const line = lineOf(node);

            if (lines[line]?.includes('i18n-allow')) {
                return;
            }

            problems.push(
                `${file}:${line + 1}: ${what} — use t() (or mark the line 'i18n-allow').`,
            );
        };

        // A JSX expression renders text only as a CHILD of an element, or as the value of
        // a prop a person reads. As an ordinary attribute it is machinery.
        const isRendered = (node: ts.JsxExpression): boolean => {
            const parent = node.parent;

            if (ts.isJsxElement(parent) || ts.isJsxFragment(parent)) {
                return true;
            }

            return ts.isJsxAttribute(parent)
                ? HUMAN_PROPS.has(parent.name.getText(source))
                : false;
        };

        const visit = (node: ts.Node): void => {
            if (ts.isJsxText(node) && /[A-Za-z]{2}/.test(node.text)) {
                report(
                    node,
                    `hard-coded text ${JSON.stringify(node.text.trim())}`,
                );
            }

            if (
                ts.isJsxAttribute(node) &&
                node.initializer &&
                ts.isStringLiteral(node.initializer) &&
                HUMAN_PROPS.has(node.name.getText(source)) &&
                /[A-Za-z]{2}/.test(node.initializer.text) &&
                !KEY_SHAPE.test(node.initializer.text)
            ) {
                report(
                    node,
                    `hard-coded ${node.name.getText(source)}=${JSON.stringify(node.initializer.text)}`,
                );
            }

            if (
                ts.isJsxExpression(node) &&
                node.expression &&
                isRendered(node)
            ) {
                const literals: ts.StringLiteral[] = [];

                const collect = (expression: ts.Node): void => {
                    if (ts.isStringLiteral(expression)) {
                        literals.push(expression);

                        return;
                    }

                    if (ts.isParenthesizedExpression(expression)) {
                        collect(expression.expression);

                        return;
                    }

                    // Only the branches that render — never the condition.
                    if (ts.isConditionalExpression(expression)) {
                        collect(expression.whenTrue);
                        collect(expression.whenFalse);

                        return;
                    }

                    if (ts.isBinaryExpression(expression)) {
                        const operator = expression.operatorToken.kind;

                        if (
                            operator ===
                                ts.SyntaxKind.AmpersandAmpersandToken ||
                            operator === ts.SyntaxKind.BarBarToken ||
                            operator === ts.SyntaxKind.QuestionQuestionToken
                        ) {
                            collect(expression.right);
                        }
                    }
                };

                collect(node.expression);

                for (const literal of literals) {
                    // KEY_SHAPE applies here exactly as it does to a plain attribute
                    // value. `title={editing ? 'x.edit' : 'x.create'}` is a key chosen
                    // between, not a sentence — the component it goes to resolves it.
                    if (
                        /[A-Za-z]{2}/.test(literal.text) &&
                        !KEY_SHAPE.test(literal.text)
                    ) {
                        report(
                            literal,
                            `hard-coded ${JSON.stringify(literal.text)}`,
                        );
                    }
                }
            }

            ts.forEachChild(node, visit);
        };

        visit(source);
    }
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
    `✓ i18n: ${baseKeys.length} keys × ${bundles.size} locales agree, every t() key exists, ` +
        `every validation rule in use is translated, no hard-coded strings.`,
);
