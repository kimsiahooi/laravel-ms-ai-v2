#!/usr/bin/env bash
#
# Structure gate — the mechanical half of docs/ARCHITECTURE.md.
#
# Folder conventions decay unless something checks them, and there is no test suite
# here. This is the counterpart to check-validation-parity.ts and check-i18n.ts: what a
# script can decide should not be a review comment.
#
# Errors (exit 1):
#   1. lib/ imports React — it is meant to be pure, readable-as-testable functions.
#   2. components/ imports from pages/ — dependencies point pages -> components -> ui.
#   3. one module reaches into another module's _components/ — those are private until
#      promoted, which is the whole point of the rule of three.
#   4. a dialog imports the vendored primitive instead of components/feedback/dialog,
#      whose close button is the only translated one.
#   5. a controller runs a raw query — DB::, ->join( or a SQL string belong in a Service.
#
# Warnings (exit 0): size caps. A page over 250 lines or a controller method over 30 is
# a signal that a piece wants extracting, not a rule — so it prompts rather than blocks.
#
# Run: bun run check:structure

set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

JS="resources/js"
PROBLEMS=0

fail() {
    printf '  \033[31m•\033[0m %s\n' "$1"
    PROBLEMS=$((PROBLEMS + 1))
}

warn() {
    printf '  \033[33m•\033[0m %s\n' "$1"
}

echo "▸ structure"

# --- 1. lib/ must not import React -------------------------------------------
# lib/ holds pure functions. The moment one reaches for a hook it stops being
# checkable by reading and starts needing a render to understand.
while IFS= read -r hit; do
    fail "${hit%%:*}: lib/ must not import React — move the hook to hooks/, keep the maths here."
done < <(grep -rn "from 'react'" "$JS/lib" 2>/dev/null)

# --- 2. components/ must not import from pages/ ------------------------------
# A shared component that knows about a page is not shared, it is misplaced.
while IFS= read -r hit; do
    fail "${hit%%:*}: components/ must not import from pages/ — dependencies run pages -> components -> ui."
done < <(grep -rn "from '@/pages/" "$JS/components" 2>/dev/null)

# --- 3. _components/ are private to their module -----------------------------
# `pages/a/_components/x` may be imported by pages/a only. A second module wanting it
# is the signal to promote it to components/, not to reach across.
while IFS= read -r file; do
    importer_module=$(printf '%s' "$file" | sed -E "s|$JS/pages/([^/]+)/.*|\1|")

    while IFS= read -r target; do
        owner_module=$(printf '%s' "$target" | sed -E 's|.*@/pages/([^/]+)/_components/.*|\1|')

        if [ "$owner_module" != "$importer_module" ]; then
            fail "$file: imports $owner_module's private _components/ — promote it to components/ instead."
        fi
    done < <(grep -o "@/pages/[^/]*/_components/[^']*" "$file" 2>/dev/null)
done < <(find "$JS/pages" -name '*.tsx' -o -name '*.ts' 2>/dev/null)

# --- 4. dialogs come from feedback/, not from the vendored primitive ---------
# The vendored DialogContent labels its dismiss button "Close" in hard-coded English,
# which no screen catches because the label is sr-only. components/feedback/dialog.tsx
# re-exports the whole primitive with that one button replaced, so importing from there
# is the only way a dialog is translated. The facade itself is the one exception.
while IFS= read -r hit; do
    file="${hit%%:*}"

    case "$file" in
        "$JS/components/ui/"* | "$JS/components/feedback/dialog.tsx") continue ;;
    esac

    fail "$file: imports the vendored dialog — use @/components/feedback/dialog, or its close button says \"Close\" in every language."
done < <(grep -rn "from '@/components/ui/dialog'" "$JS" 2>/dev/null)

# --- 5. controllers must not run raw queries ---------------------------------
# A controller resolves, delegates and responds. A join in one is a Service trying to
# get out.
while IFS= read -r hit; do
    fail "${hit%%:*}: raw query in a controller — that belongs in a Service or an Action."
done < <(grep -rn -E '(DB::|->join\(|->leftJoin\(|selectRaw\(|whereRaw\()' app/Http/Controllers 2>/dev/null)

# --- size caps, as warnings --------------------------------------------------
while IFS= read -r file; do
    lines=$(wc -l < "$file" | tr -d ' ')

    if [ "$lines" -gt 250 ]; then
        warn "$file is $lines lines — over the ~250 signal; something wants extracting to _components/."
    fi
done < <(find "$JS/pages" -name '*.tsx' 2>/dev/null)

if [ "$PROBLEMS" -gt 0 ]; then
    printf '\n\033[31m✗\033[0m structure: %d problem(s). See docs/ARCHITECTURE.md.\n' "$PROBLEMS"
    exit 1
fi

printf '\033[32m✓\033[0m structure: dependencies point one way, modules keep their components to themselves.\n'
