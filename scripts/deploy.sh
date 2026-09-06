#!/usr/bin/env bash
#
# Production deploy for the multi-tenant ERP.
# Run from the project root as the SITE user (not root):
#
#     bun run deploy            # normal release — the site stays up throughout
#     bun run deploy:fresh      # wipe vendor/ too, behind a maintenance page
#
# The whole body lives in main() and is only invoked on the last line, so a
# `git pull` that rewrites this very file mid-run can't corrupt the shell: bash
# has already parsed the function into memory before it executes.
#
#   -e            abort the instant any command fails (non-zero exit)
#   -u            treat use of an unset variable as an error (catches typos)
#   -o pipefail   a pipeline fails if ANY stage fails, not just the last one
set -euo pipefail

# The pm2 process name. Deliberately not the bare "inertia-ssr": this box is
# expected to host a second Laravel app, and a name that says which app it is
# saves somebody restarting the wrong one at 2am.
readonly SSR_PROCESS="kimsiah-ssr"

# Set by --fresh. See step 2.
FRESH=0

main() {
    # Always operate from the project root, wherever we were invoked from.
    cd "$(dirname "$0")/.."

    parse_args "$@"

    check_env

    if [ "$FRESH" -eq 1 ]; then
        echo "▶ [0/12] Fresh mode: taking the site down for the installs…"
        # --retry tells well-behaved clients when to come back; --secret gives you a
        # URL that bypasses the maintenance page so you can watch the deploy yourself.
        php artisan down --retry=60 --secret="deploy-$(date +%s)"
        # Bring the site back even if a step below fails, so a broken deploy is a
        # broken site rather than a permanently dark one.
        trap 'php artisan up || true' EXIT
    fi

    echo "▶ [1/12] Pulling latest code…"
    # --ff-only: only fast-forward; abort (rather than make a merge commit) if the
    # server branch has diverged from origin, keeping the box an exact mirror.
    git pull --ff-only

    echo "▶ [2/12] Clearing node_modules for a clean install…"
    # node_modules is safe to delete under a live site, and vendor/ is not — which is
    # why only one of them is here.
    #
    # node_modules is read at BUILD time only. The SSR bundle this app serves from is
    # self-contained: `bootstrap/ssr/app.js` plus `bootstrap/ssr/assets/` carry every
    # dependency inlined, with no bare package imports, so the running node process
    # never reads node_modules at all. Wiping it costs nothing and removes any question
    # about how thoroughly bun prunes a package you dropped from package.json.
    #
    # vendor/ is read by php-fpm on EVERY request, so deleting it is a live 500 for as
    # long as `composer install` takes. It also does not need deleting: composer tracks
    # vendor/composer/installed.json and uninstalls anything no longer in the lockfile,
    # printing "Removing x/y" as it goes. Use --fresh on the rare day you want to see
    # that with your own eyes; it wipes vendor too, behind the maintenance page above.
    rm -rf node_modules
    if [ "$FRESH" -eq 1 ]; then
        rm -rf vendor
    fi

    echo "▶ [3/12] Installing PHP dependencies (production only, no dev tools)…"
    #   --no-interaction      never prompt; use defaults (required for an unattended run)
    #   --prefer-dist         fetch zipped release archives instead of cloning git sources
    #   --optimize-autoloader build a static class-map so class loading needs no fs lookup
    #   --no-dev              skip require-dev (Pint/PHPStan/Pail) — keep prod lean.
    #                         Safe here: bootstrap/providers.php guards the TypeScript
    #                         transformer provider behind class_exists() for exactly this.
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

    echo "▶ [4/12] Installing JS dependencies…"
    # --frozen-lockfile: install exactly what bun.lock pins; fail if it's stale, so the
    # server never silently resolves different versions (bun's equivalent of `npm ci`).
    bun install --frozen-lockfile

    echo "▶ [5/12] Clearing stale caches (so the new config/routes take effect)…"
    php artisan optimize:clear

    echo "▶ [6/12] Migrating the central database…"
    # --force: run without the interactive "you're in production, sure?" prompt
    # (Laravel blocks prod migrations otherwise).
    php artisan migrate --force

    echo "▶ [7/12] Migrating every workspace database…"
    # One migration run per tenant database. New workspaces are migrated when they are
    # provisioned; this is what carries a schema change to the ones that already exist.
    php artisan tenants:migrate --force

    echo "▶ [8/12] Ensuring the public storage symlink exists…"
    # `|| true`: storage:link exits non-zero when the symlink already exists, so this
    # keeps the step idempotent under `set -e`. Note it links public/storage only —
    # uploaded media lives on the private `assets` disk and is never symlinked.
    php artisan storage:link || true

    echo "▶ [9/12] Removing any stale Vite dev-server marker…"
    # If public/hot exists, Laravel believes a Vite dev server is running: it points
    # every asset at that dead address AND posts SSR to it instead of the SSR bundle.
    # The page still returns 200, silently client-rendered, with nothing in laravel.log.
    # It is gitignored so it should never arrive here — this is the cheap insurance.
    rm -f public/hot

    echo "▶ [10/12] Building client + SSR bundles…"
    # build:ssr, never a bare `build`: `build` produces no SSR bundle, and the failure
    # is the silent one above rather than an error.
    bun run build:ssr

    echo "▶ [11/12] Verifying the build (Biome + TypeScript)…"
    # These run AFTER the build on purpose. resources/js/{routes,actions,wayfinder} are
    # gitignored and generated by the wayfinder plugin DURING `vite build`, so `tsc` on a
    # fresh checkout would fail on modules that do not exist yet.
    #
    # They still run before the pm2 restart, which is the point: `set -euo pipefail`
    # means a failure aborts HERE, leaving the currently-running SSR process untouched
    # and serving.
    #
    # No test suite is run because this project deliberately has none — see CLAUDE.md.
    # check:deploy gates on errors only, so advisory warnings don't fail a release.
    bun run check:deploy
    bun run types:check

    echo "▶ [12/12] Re-caching config / routes / views, and restarting SSR…"
    php artisan optimize

    # Restart only OUR process — never `pm2 restart all`, which on a shared box would
    # touch every app on it. The restart re-runs the artisan command, which loads the
    # freshly built bundle.
    #   --update-env     re-read the current env so new .env values take effect
    #   >/dev/null 2>&1  silence both streams; only the exit code is used
    if pm2 restart "$SSR_PROCESS" --update-env >/dev/null 2>&1; then
        echo "   restarted existing $SSR_PROCESS"
    else
        pm2 start "php artisan inertia:start-ssr" --name "$SSR_PROCESS"
    fi
    # Persist the process list so pm2 resurrects it on reboot. Needs `pm2 startup` to
    # have been run once for this user — see docs/DEPLOYMENT.md.
    pm2 save

    if [ "$FRESH" -eq 1 ]; then
        trap - EXIT
        php artisan up
    fi

    echo "✅ Deploy complete."
}

parse_args() {
    for arg in "$@"; do
        case "$arg" in
            --fresh) FRESH=1 ;;
            *)
                echo "Unknown option: $arg" >&2
                echo "Usage: bash scripts/deploy.sh [--fresh]" >&2
                exit 1
                ;;
        esac
    done
}

# Sanity-check .env before anything expensive runs.
#
# A dotenv value containing unquoted whitespace makes the WHOLE file unparseable, and
# nothing says so: the first thing to notice is `package:discover`, run by composer's
# post-autoload-dump hook, aborting with "Failed to parse dotenv file" in the middle of
# step 3. That reads like a composer problem and is not one.
#
# Deliberately plain grep, not php: on a first deploy vendor/ does not exist yet, so
# anything needing Laravel booted cannot run here.
check_env() {
    echo "▶ [0/12] Checking .env…"

    if [ ! -f .env ]; then
        echo "✗ No .env in $(pwd). Copy .env.production.example and fill it in." >&2
        exit 1
    fi

    # KEY=value where the value is unquoted and contains a space or tab.
    if unquoted=$(grep -nE "^[A-Za-z_][A-Za-z0-9_]*=[^\"'#]*[[:space:]]" .env); then
        echo "✗ .env has unquoted whitespace in a value, so dotenv cannot parse the file:" >&2
        echo "$unquoted" >&2
        echo "  Quote the value (KEY=\"two words\") or remove the spaces." >&2
        exit 1
    fi

    # Placeholders from .env.production.example that were never filled in. Anchored to an
    # assignment so the template's own comments explaining CHANGEME do not match.
    if leftover=$(grep -nE "^[A-Za-z_][A-Za-z0-9_]*=.*CHANGEME" .env); then
        echo "✗ .env still has placeholders:" >&2
        echo "$leftover" >&2
        exit 1
    fi
}

main "$@"
