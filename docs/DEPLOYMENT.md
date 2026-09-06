# Deployment

One app, one VPS, one script. `bun run deploy` is the whole release process; everything
below is either the one-time setup it assumes, or an explanation of why a step is there.

The box is CloudPanel-managed, so nginx, php-fpm and MySQL are the panel's business. This
covers what the panel does not: the PHP extensions this app needs, the Node runtime that
serves SSR, the database privilege that multi-tenancy needs, and the process manager.

**The site is `kimsiah.site`, and a second Laravel app is expected on a subdomain later.**
Two things in here exist because of that — the SSR port and the tenant database prefix —
and both are called out where they matter.

---

## The shape of it

| | |
|---|---|
| Web | nginx + php-fpm (CloudPanel), docroot `public/` |
| PHP | 8.3+, **`bcmath` required** |
| Database | MySQL 8 — one central database, plus one per workspace |
| Node | serves `bootstrap/ssr/app.js` on `127.0.0.1:13714` |
| Bun | installs and builds on the box |
| pm2 | supervises exactly one process: the SSR server |
| Queue worker | **none** — see *Deferred* |
| Cron | **none** — see *Deferred* |

Sessions, cache and queues are all MySQL. There is no Redis.

---

## One-time server setup

### 1. PHP

8.3 or newer, with:

```
bcmath  pdo_mysql  gd  mbstring  openssl  tokenizer  xml  ctype  curl  fileinfo  dom  zip  pcntl
```

**`bcmath` is not optional and is easy to miss** — it is not a stock CloudPanel extension
and v1 did not need it. `StockService` does all of its arithmetic with `bcadd`, `bcsub`
and `bccomp`, so without it *every* stock operation is a fatal error rather than a
degraded one.

`pcntl` is optional but wanted: `inertia:start-ssr` uses it to shut its node child down
cleanly, and without it a pm2 restart can leave orphaned node processes holding the port.

`ext-intl` is **not** needed. All number and date formatting happens in the browser with
the locale and time zone pinned — see `resources/js/lib/format.ts`.

### 2. PHP upload limits

In CloudPanel's PHP settings **for this site**:

```
upload_max_filesize = 8M
post_max_size       = 8M
```

and in the site's nginx config, `client_max_body_size 8m;`.

The product photo rule is `max:2048` (2 MB). At PHP's default of 2M, a file *at* the limit
dies as a **413 at the web server before Laravel's validator ever runs** — so instead of
"The photo must not be larger than 2 MB" the user gets a broken request with nothing in
the log. The ceiling needs to be comfortably above the rule, not equal to it.

These are per-site in CloudPanel, so raising them here does not affect the other app.

### 3. MySQL — and the privilege CloudPanel does not grant

This app is multi-database multi-tenant: provisioning a workspace **creates a real MySQL
database**, `inv_tenant_{slug}`, through the app's own connection. CloudPanel's generated
per-site user is scoped to that site's single database and cannot do it.

Create the central database and its user in CloudPanel first. Then, **as root on the
server**:

```bash
clpctl db:show:master-credentials
```

It prints a small table: **Host, User Name, Password, Port**. Use exactly what it shows —
the user name is often `root`, but it is whatever is in that table, so read it rather than
assuming.

**Pass the Host it prints, with `-h`. This is the part that catches people:**

```bash
mysql -h 127.0.0.1 -P 3306 -u root -p     # <- values from the table above
```

Omitting `-h` does not mean "the same machine" to MySQL. Without it the client connects
over the unix socket, which MySQL authenticates as `user@localhost` — a *different account
row* from `user@127.0.0.1`, usually with a different password and, on Debian/Ubuntu, often
no password at all because `root@localhost` uses the `auth_socket` plugin. So
`mysql -u root -p` with the printed password is refused even though the password is
correct. `-h 127.0.0.1` forces TCP and matches the row those credentials belong to.

It then asks for a password; paste the one the table printed. Nothing is echoed while you
type — that is normal, just paste and press enter.

At the `mysql>` prompt. First find the user CloudPanel made for the central database, and
the host it is registered under — both matter, and neither is worth guessing:

```sql
SELECT user, host, db FROM mysql.db WHERE db LIKE 'ms%';
```

Then grant, substituting the user and host that query returned:

```sql
GRANT ALL PRIVILEGES ON `inv\_tenant\_%`.* TO 'youruser'@'localhost';
FLUSH PRIVILEGES;
SHOW GRANTS FOR 'youruser'@'localhost';
```

`SHOW GRANTS` echoing the line back is the confirmation. Then `exit`.

**One line, not two.** The obvious second line — `GRANT CREATE, DROP ON *.*` — is not
needed and should not be used. A database-level grant with a wildcard already covers
`CREATE DATABASE` for names matching the pattern, so the single line above lets the app
create, migrate and drop workspace databases while being unable to touch anything else on
the server. `ON *.*` would let this user drop **any** database on the box, including the
other site's. Verified rather than assumed — with only the line above granted:

| Attempt | Result |
|---|---|
| `CREATE DATABASE inv_tenant_acme` | allowed |
| `CREATE TABLE` inside it, `INSERT` | allowed |
| `DROP DATABASE inv_tenant_acme` | allowed |
| `CREATE DATABASE other_app` | **denied** |
| `DROP DATABASE` (another site's) | **denied** |

Three things that silently make this not work:

- **The escaped underscores are load-bearing.** In a grant pattern an unescaped `_` is a
  single-character wildcard, so `inv_tenant_%` would also match databases like
  `invXtenantY…` — a wider grant than intended. Written `` `inv\_tenant\_%` `` the
  backslashes make them literal underscores. (A hyphenated prefix would need no escaping,
  since only `_` and `%` are wildcards.)
- **The host must match how the app connects.** MySQL grants are per `user@host`, and the
  pair must already exist — granting to `'youruser'@'localhost'` when the account is
  registered as `'youruser'@'%'` fails with `ERROR 1410`, because to MySQL that is a
  different user it is being asked to invent. Check first:
  `SELECT user, host FROM mysql.user WHERE user = 'youruser';`
- **The pattern must match `TENANCY_DB_PREFIX`.** The grant above assumes
  `TENANCY_DB_PREFIX=inv_tenant_` in `.env`, giving `inv_tenant_{slug}`. Change one and you
  must change the other; the prefix is what keeps these databases distinct from the central
  database and from anything a second tenanted app on this box creates.

**The failure does not appear at deploy time.** Everything installs, the site comes up,
you sign in — and then creating your first workspace fails. Which is why provisioning a
throwaway workspace is on the day-one checklist below.

### 4. Node, Bun and pm2

Node runs the SSR bundle; Bun installs and builds. Then, **as the site user, not root**:

```bash
pm2 startup            # prints a command to run once as root
pm2 save
```

Without `pm2 startup`, SSR does not come back after a reboot — the site still serves, but
every page silently drops to client rendering. CloudPanel gives each site its own system
user, so pm2's process list is per-user and cannot collide with another app's.

### 5. HTTPS

Not optional. Passkeys refuse to register on an insecure origin, and `.env` sets
`SESSION_SECURE_COOKIE=true`, which means no session cookie is issued over plain HTTP at
all.

### 6. Permissions

Docroot points at `public/`. `storage/` and `bootstrap/cache/` must be writable by the
php-fpm user. `storage/media-library-temp/` is created on demand during an upload and must
be writable too.

---

## First deploy

```bash
git clone <repo> .                     # into the site directory
cp .env.production.example .env        # then fill in every <…>
php artisan key:generate               # once, and never again — see below
bun run deploy
```

Then create the first super-admin, and sign in at `https://kimsiah.site/admin`.

**Never re-run `php artisan key:generate` on a live box.** `PASSKEYS_USER_HANDLE_SECRET`
falls back to `APP_KEY` (`config/fortify.php`), so rotating it invalidates every passkey
anybody has registered, and every encrypted cookie already in a browser.

### The first super-admin

`php artisan db:seed` **refuses to run in production**, on purpose: it creates
`admin@example.com` with the password `password`, which is public knowledge in this
repository. Production's strong-password rules apply to a password somebody *types*, so
that account would still sign in — a back door nothing would warn about.

Make one deliberately instead:

```bash
php artisan admin:create
```

It asks for a name, an email and a password — hidden as you type, asked twice, and checked
against the same rules the app holds everyone else to (in production: 12 characters, mixed
case, a number, a symbol, and not found in a known breach). A duplicate or malformed email
is refused before anything is written.

**The password is never an option**, because an argument is visible in `ps` while the
command runs and stays in your shell history afterwards. If you have no terminal — running
it through `ssh host "php artisan …"`, or from a provisioning script — it reads the
password from STDIN instead, which keeps it out of the process list just the same:

```bash
printf '%s' "$ADMIN_PW" | php artisan admin:create --name='Your Name' --email='you@example.com'
```

Run it again whenever you want another super-admin; it is not a first-run-only command.

There is no public sign-up — this is a B2B app, and workspaces and their first users are
provisioned by a super-admin at `/admin`.

---

## Routine deploy

```bash
bun run deploy
```

Run it as the site user from the project root. **The site stays up throughout.** The steps
and why each is there are commented in `scripts/deploy.sh`; the two worth knowing:

- **`bun run build:ssr`, never a bare `bun run build`.** `build` produces no SSR bundle,
  and the result is not an error — it is a site that quietly stops server-rendering.
- **The checks run after the build, before the pm2 restart.** They have to come after
  because `resources/js/{routes,actions,wayfinder}` are generated *during* `vite build`;
  they come before the restart so a failure leaves the old SSR process serving.

### `bun run deploy:fresh`

Same thing, but it also deletes `vendor/` — behind `php artisan down`, because php-fpm
reads `vendor/` on every request and removing it under a live site is a 500 for as long as
the install takes.

You should not normally need it. `composer install` already uninstalls anything dropped
from the lockfile, printing `Removing x/y` as it goes. `deploy:fresh` exists for the day
you would rather see that with your own eyes than take composer's word for it.

`node_modules` is deleted on *every* deploy and costs nothing, because it is only read at
build time — the SSR bundle is self-contained.

---

## After a schema change

`bun run deploy` runs both `php artisan migrate --force` (central) and
`php artisan tenants:migrate --force` (every workspace). Nothing extra to do.

**After adding a permission** to `App\Support\TenantPermissions`, existing workspaces also
need the seeder, which is not part of the deploy:

```bash
php artisan tenants:seed
```

New workspaces get it at provision time.

---

## Troubleshooting

**The site works but is not server-rendered.**
Look for `data-server-rendered="true"` in view-source. If it is missing: `pm2 list` (is
the process up?), `ss -ltnp | grep 13714` (is anything listening?), and check
`public/hot` does not exist — its presence makes Laravel post SSR to a dead Vite address
and fall back to client rendering **with nothing written to `laravel.log`**. The deploy
removes it, but it is the first thing to check.

**A workspace renders as unstyled HTML.**
The asset URLs have been rewritten under `/tenancy/assets/`. `tenancy.filesystem.asset_helper_tenancy`
must stay `false` — see the comment in `config/tenancy.php`. This cannot be reproduced
with `bun run dev`; it only appears against built assets.

**Images 404 in production but work locally.**
nginx is serving the URL from the docroot instead of passing it to PHP. Media URLs are
deliberately extension-less (`/{workspace}/media/{id}`) so that `try_files $uri =404`
cannot match them — if a rule has been added that catches them anyway, that is the cause.

**Uploading a 2 MB photo fails with no message.** PHP's upload limits — section 2 above.

**`Failed to parse dotenv file. Encountered unexpected whitespace at [...]`, during
`composer install`.** Not a composer problem. A value in `.env` contains a space and is
not quoted, which makes the entire file unparseable; you see it here because composer's
`post-autoload-dump` hook runs `artisan package:discover`, and that is the first thing to
boot Laravel. Quote the value — `KEY="two words"` — or remove the spaces. `deploy` now
checks for this before it installs anything, so it should not reach composer again.

**Creating a workspace fails.** The MySQL user cannot `CREATE DATABASE` — section 3.

**`ERROR 1410: You are not allowed to create a user with GRANT`.** The user in your
`GRANT … TO 'x'@'host'` does not exist, and MySQL 8 will not create one for you. Almost
always this is the *database* name typed where the *user* name belongs — they are
different things, and CloudPanel asks for both when you add a database. Find the real one:

```sql
SELECT user, host, db FROM mysql.db WHERE db LIKE 'ms%';
```

**`mysql -u root -p` is refused with the right password.** You are on the unix socket, so
MySQL is checking `root@localhost` rather than the `root@127.0.0.1` those credentials
belong to. Add `-h 127.0.0.1` — section 3.

**Password reset emails never arrive.** `MAIL_MAILER` is still `log`.

**SSR stopped working after adding the second app.** Both bound port 13714. This app's port
is pinned in *two* files that must agree — `inertia({ ssr: { port } })` in `vite.config.ts`
(what the bundle binds, baked in at build time) and `INERTIA_SSR_URL` in `.env` (where
Laravel posts). The next app on this box takes **13715**.

---

## Deferred, deliberately

**No queue worker.** There are no `ShouldQueue` classes and nothing calls `dispatch()`.
Media conversions are generated inline because `QUEUE_CONVERSIONS_BY_DEFAULT=false` — with
no worker, a queued conversion is a thumbnail that never appears and an error nobody sees.
Flipping that flag means also running `php artisan queue:work` under pm2, and the
`QueueTenancyBootstrapper` (already enabled) is what would carry workspace context into
the job.

**No cron.** There are no scheduled tasks. Adding `* * * * * php artisan schedule:run`
costs nothing if you want it ready.

**No backups.** Nothing in this repository backs anything up. What matters, in order:
`storage/assets/` (every uploaded file, and it is outside `public/` and outside git), the
central database, and every `inv_tenant_*` database.

**No rollback.** The deploy is in-place, so recovering from a bad release means fixing
forward or `git reset --hard` to the previous commit and deploying again. Atomic releases
with a symlink swap would fix this properly and would also make `deploy:fresh` unnecessary.

**No CI deploy.** `.github/workflows/tests.yml` runs the static checks on push; it does not
deploy. Releases are hand-run.
