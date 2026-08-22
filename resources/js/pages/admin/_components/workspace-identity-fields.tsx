import { useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { toSlug } from '@/lib/slug';

/**
 * What the workspace is called and where it lives.
 *
 * The mirroring rule owns the only state on this form, which is why it lives here
 * rather than in the sheet: the slug tracks the name until it is edited by hand, at
 * which point it stops for good. A slug is part of every URL the workspace will ever
 * have, so silently rewriting a deliberate choice is worse than a little duplication.
 */
export function WorkspaceIdentityFields({
    errors,
}: {
    errors: Record<string, string | undefined>;
}) {
    const { t } = useTranslation();
    const [name, setName] = useState('');
    const [slug, setSlug] = useState('');
    const [slugEdited, setSlugEdited] = useState(false);

    return (
        <>
            <div className="space-y-2">
                <Label htmlFor="name">{t('console.create.name')}</Label>
                <Input
                    id="name"
                    name="name"
                    value={name}
                    onChange={(event) => {
                        setName(event.target.value);

                        if (!slugEdited) {
                            setSlug(toSlug(event.target.value));
                        }
                    }}
                    placeholder={t('console.create.name_placeholder')}
                    autoFocus
                    aria-invalid={Boolean(errors.name)}
                />
                <InputError role="alert" message={errors.name} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="slug">{t('console.create.slug')}</Label>
                {/* The leading slash is chrome, not content — it sits inside the
                    field's border so the address reads as one thing. */}
                <div className="flex items-center rounded-md border border-input focus-within:ring-[3px] focus-within:ring-ring/50">
                    <span className="pl-3 text-muted-foreground text-sm">
                        /
                    </span>
                    <Input
                        id="slug"
                        name="slug"
                        value={slug}
                        onChange={(event) => {
                            setSlugEdited(true);
                            setSlug(event.target.value);
                        }}
                        placeholder={t('console.create.slug_placeholder')}
                        autoCapitalize="none"
                        spellCheck={false}
                        aria-invalid={Boolean(errors.slug)}
                        className="border-0 pl-1 shadow-none focus-visible:ring-0"
                    />
                </div>
                <p className="text-muted-foreground text-xs">
                    {t('console.create.slug_hint')}
                </p>
                <InputError role="alert" message={errors.slug} />
            </div>
        </>
    );
}
