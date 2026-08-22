/**
 * Validation parity gate — the replacement for the deleted parity test.
 *
 * The zod schemas exist to refuse, in the browser, exactly what the server refuses.
 * That only holds while the two lists of fields agree, so this reads the real PHP
 * FormRequests and checks that every field the server validates is one the browser
 * knows about too.
 *
 * Deliberately field-level, not rule-level: pinning every rule string would break on
 * harmless rewording, while a *missing field* is the failure that actually matters —
 * it means a value reaches the server unchecked.
 *
 * Schemas are discovered by convention, so adding a resource needs no edit here:
 *
 *     app/Http/Requests/Tenant/CategoryRequest.php
 *       → resources/js/lib/validation/schemas/category.ts   (export `categorySchema`)
 *
 * A schema exported as a factory (it needs runtime options, e.g. the allowed
 * currencies) is listed in FACTORY_ARGS below with the arguments to build it with.
 *
 * Run: `bun run check:validation`
 */

import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const REQUESTS = join(process.cwd(), 'app/Http/Requests/Tenant');
const SCHEMAS = join(process.cwd(), 'resources/js/lib/validation/schemas');

/** Requests with no hand-written schema, and why that is correct. */
const EXEMPT = new Set([
    // Declares no rules of its own — it is the shared base class.
    'TenantFormRequest',
    // Validated from the payload each builds, not from a page's fields.
    'StockTakePostRequest',
    'SettingsUpdateRequest',
    // Read-only lookups behind the item picker and the barcode scanner. They take
    // query parameters, not a form anyone fills in.
    'StockOnHandRequest',
    'StockResolveItemRequest',
]);

/** Schemas exported as factories, with the arguments to build one for inspection. */
const FACTORY_ARGS: Record<string, unknown[]> = {
    CustomerRequest: [['MY', 'SG']],
    UserRequest: [false],
    PurchaseOrderRequest: [['MYR']],
    SalesOrderRequest: [['MYR']],
};

/** `WarehouseReorderLevelRequest` → `warehouse-reorder-level`. */
function schemaModule(request: string): string {
    return request
        .replace(/Request$/, '')
        .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
        .toLowerCase();
}

/** The top-level field names a FormRequest's `rules()` returns. */
function laravelFields(request: string): string[] {
    const php = readFileSync(join(REQUESTS, `${request}.php`), 'utf8');
    const body = php.slice(php.indexOf('public function rules'));
    const keys = [...body.matchAll(/^\s{12}'([^']+)'\s*=>/gm)].map((m) => m[1]);

    // `items.*.quantity` is part of `items`, which is already its own key.
    return [...new Set(keys.filter((key) => !key.includes('.')))];
}

/** The keys a zod object schema knows, unwrapping any effects wrapped around it. */
function zodFields(schema: any): string[] {
    let current = schema;

    // `.refine()` / `.superRefine()` wrap the object; the shape is underneath.
    while (current && !current.shape && 'def' in current) {
        const inner = current.def?.innerType;
        if (!inner) break;
        current = inner;
    }

    return current?.shape ? Object.keys(current.shape) : [];
}

const problems: string[] = [];

if (!existsSync(REQUESTS)) {
    console.log(
        '✓ validation parity: no tenant FormRequests yet — nothing to check.',
    );
    process.exit(0);
}

const requests = readdirSync(REQUESTS)
    .filter((file) => file.endsWith('Request.php'))
    .map((file) => file.replace('.php', ''))
    .filter((name) => !EXEMPT.has(name))
    .sort();

let checked = 0;

for (const request of requests) {
    const module = schemaModule(request);
    const path = join(SCHEMAS, `${module}.ts`);

    if (!existsSync(path)) {
        problems.push(
            `${request}: no schema at resources/js/lib/validation/schemas/${module}.ts ` +
                `— every server-validated form needs a browser-side one (or an EXEMPT entry saying why not).`,
        );
        continue;
    }

    const exports = await import(path);
    const key = Object.keys(exports).find((name) => name.endsWith('Schema'));

    if (!key) {
        problems.push(
            `${request}: ${module}.ts exports nothing ending in "Schema".`,
        );
        continue;
    }

    let schema = exports[key];
    if (typeof schema === 'function') {
        schema = schema(...(FACTORY_ARGS[request] ?? []));
    }

    const known = zodFields(schema);
    const missing = laravelFields(request).filter((f) => !known.includes(f));

    if (missing.length > 0) {
        problems.push(
            `${request}: validated by the server but unchecked in the browser — ${missing.join(', ')}`,
        );
    }

    checked += 1;
}

if (problems.length > 0) {
    console.error('✗ validation parity failed:\n');
    for (const problem of problems) console.error(`  • ${problem}`);
    console.error(
        `\n${problems.length} problem(s) across ${requests.length} request(s).`,
    );
    process.exit(1);
}

console.log(`✓ validation parity: ${checked} request/schema pair(s) agree.`);
