<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CentralUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password as promptPassword;
use function Laravel\Prompts\text;

/**
 * Creates a super-admin: someone who can sign in at /admin and provision workspaces.
 *
 * **This exists because seeding one cannot be made safe.** `DatabaseSeeder` creates
 * `admin@example.com` with a password printed in this repository, which is fine locally
 * and a back door in production — so it refuses to run there. That left a gap on the one
 * machine where creating the first account actually matters, and "paste this into
 * tinker" is a poor answer for a live box: no validation, a typo'd email you only find
 * out about at the sign-in screen, and a password typed into a REPL that keeps history.
 *
 * **The password is never an option.** Anything passed as an argument is visible in `ps`
 * while the command runs and lives in the shell history afterwards. Name and email are
 * options, because they are not secrets and typing them twice is a way to mistype them
 * once.
 *
 * With a terminal it is asked for and confirmed, hidden as you type. Without one — an
 * `ssh host "php artisan …"`, or a provisioning script — it is read from STDIN instead,
 * so `ADMIN_PW=… ; printf '%s' "$ADMIN_PW" | php artisan admin:create --name=… --email=…`
 * works and still keeps the secret out of the process list. Laravel Prompts throws an
 * unreadable exception in that situation otherwise, which is a poor way to find out.
 *
 * The rules are {@see Password::defaults()}, so this is held to whatever the app holds
 * everybody else to — 12 characters, mixed case, numbers, symbols and not in a breach
 * corpus once `APP_ENV=production`, and nothing much locally. One definition; a command
 * with its own idea of a good password is a command that eventually disagrees with the
 * sign-in form.
 *
 * Not first-run-only. A second admin is an ordinary thing to want, and this is how.
 */
final class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create
                            {--name= : The person\'s name}
                            {--email= : Their email address, which is also their sign-in}';

    protected $description = 'Create a super-admin who can sign in at /admin';

    public function handle(): int
    {
        // `isInteractive()` alone is not enough: it stays true for a piped STDIN, which
        // is exactly the case Prompts cannot handle.
        $interactive = $this->input->isInteractive() && stream_isatty(STDIN);

        $name = $this->stringOption('name');
        $email = $this->stringOption('email');

        if ($interactive) {
            $name = $name ?: text(label: 'Name', placeholder: 'Kim Siah', required: true);
            $email = $email ?: text(label: 'Email', placeholder: 'you@example.com', required: true);

            $password = promptPassword(
                label: 'Password',
                // Said before they type rather than after they fail.
                hint: 'At least 12 characters in production, with mixed case, a number and a symbol.',
                required: true,
            );

            // Only worth asking twice when it was typed blind. A piped secret cannot
            // have a typo that a second reading would catch.
            $confirmation = promptPassword(label: 'Confirm password', required: true);
        } else {
            if ($name === '' || $email === '') {
                $this->components->error('Without a terminal, --name and --email are required.');

                return self::FAILURE;
            }

            $password = $this->readPasswordFromStdin();
            $confirmation = $password;

            if ($password === '') {
                $this->components->error(
                    'No password on STDIN. Pipe one in, e.g. '
                    ."printf '%s' \"\$ADMIN_PW\" | php artisan admin:create --name=… --email=…",
                );

                return self::FAILURE;
            }
        }

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                // Unique through the model, so it checks the CENTRAL connection rather
                // than whatever `default` happens to be. Soft-deleted admins count: the
                // email index keeps their address reserved until they are restored or
                // force-deleted, so accepting it here would only fail at the insert.
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique(CentralUser::class)->withoutTrashed()],
                'password' => ['required', 'confirmed', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $admin = CentralUser::create([
            'name' => $name,
            'email' => $email,
            // Hashed by the model's `password` cast, not here — one place decides how.
            'password' => $password,
        ]);

        $this->components->info("Super-admin created: {$admin->email}");
        $this->components->bulletList([
            'Sign in at '.rtrim((string) config('app.url'), '/').'/admin',
            'Provision a workspace from there — there is no public sign-up.',
        ]);

        return self::SUCCESS;
    }

    /**
     * The first line of STDIN, without its newline.
     *
     * Deliberately one line: a password is a line, and reading the whole stream would
     * swallow a trailing newline into the secret and make it fail to match at sign-in.
     */
    private function readPasswordFromStdin(): string
    {
        $line = fgets(STDIN);

        return $line === false ? '' : rtrim($line, "\r\n");
    }

    /**
     * An option as a string, never an array.
     *
     * `--name` can legitimately be passed twice on a command line, and Symfony hands
     * back an array when it is; the string cast would then be a TypeError rather than a
     * message.
     */
    private function stringOption(string $key): string
    {
        $value = $this->option($key);

        return is_string($value) ? trim($value) : '';
    }
}
