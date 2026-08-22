import type { FormComponentRef } from '@inertiajs/core';
import { useCallback, useRef } from 'react';
import type { ZodType } from 'zod';
import { useTranslation } from '@/hooks/use-translation';
import { runGate } from '@/lib/validation/gate';

/**
 * Wire a schema into an Inertia `<Form>`: spread the result onto the element.
 *
 *     const gate = useZodGate(categorySchema);
 *     <Form {...gate} noValidate action={…} method="post">…</Form>
 *
 * The values checked come from the form's own `getData()` — the exact object
 * Inertia is about to send — so the check and the request can never drift apart.
 * `noValidate` matters: without it the browser's own bubble fires first and this
 * never runs.
 *
 * With no schema the form submits as it always did, so forms can be converted one
 * at a time.
 *
 * The translator is picked up here rather than passed in, so a form gets messages in
 * the user's language without knowing that translation is involved at all — which is
 * what keeps it from being the step someone forgets on the twenty-second form.
 */
export function useZodGate(schema?: ZodType) {
    const ref = useRef<FormComponentRef | null>(null);
    const { t } = useTranslation();

    const onBefore = useCallback(
        () =>
            schema
                ? runGate(schema, ref.current?.getData(), ref.current, t)
                : true,
        [schema, t],
    );

    return { ref, onBefore };
}
