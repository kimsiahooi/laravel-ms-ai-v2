<?php

declare(strict_types=1);

/*
| Authentication: the framework's own failure messages, and the copy on the screens
| that produce them.
|
| The three flat keys are Laravel's, quoted verbatim so English output is byte-identical
| to the framework default. Everything else is this app's, grouped by screen.
|
| One constraint worth knowing: a group here must never be named after a framework key.
| `auth.password` is a framework STRING, so a `password` group would turn it into an
| array and break `__('auth.password')` silently.
*/

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    'panel' => [
        'heading' => 'Your stock, your orders, one workspace.',
        'point_stock' => 'Every movement of every item, across every location.',
        'point_orders' => 'Purchase to production to sale, in one thread.',
        'footer' => 'Signing in to :workspace.',
    ],

    'fields' => [
        'email' => 'Email address',
        'email_placeholder' => 'you@example.com',
        'password' => 'Password',
        'password_placeholder' => 'Password',
    ],

    'login' => [
        'head' => 'Sign in',
        'title' => 'Sign in to your workspace',
        'description' => 'Enter your email and password to continue.',
        'forgot' => 'Forgot your password?',
        'remember' => 'Keep me signed in',
        'submit' => 'Sign in',
        'submitting' => 'Signing in…',
    ],

    'forgot' => [
        'head' => 'Forgot password',
        'title' => 'Forgot your password?',
        'description' => 'Enter your email and we will send you a reset link.',
        'submit' => 'Email password reset link',
        'submitting' => 'Sending…',
        'return' => 'Or return to',
        'login' => 'sign in',
    ],

    'reset' => [
        'head' => 'Reset password',
        'title' => 'Reset your password',
        'description' => 'Choose a new password for your account.',
        'new_password' => 'New password',
        'confirm_password' => 'Confirm password',
        'submit' => 'Reset password',
        'submitting' => 'Resetting…',
    ],

    'confirm' => [
        'head' => 'Confirm password',
        'title' => 'Confirm your password',
        'description' => 'This is a secure area. Please confirm your password before continuing.',
        'submit' => 'Confirm password',
        'submitting' => 'Confirming…',
        'with_passkey' => 'Confirm with passkey',
        'or_password' => 'Or confirm with password',
    ],

    'verify' => [
        'sent' => 'A new verification link has been sent to your email address.',
        'head' => 'Verify email',
        'title' => 'Verify your email address',
        'description' => 'Click the link we just emailed you. If it has not arrived, we can send another.',
        'resend' => 'Resend verification email',
        'resending' => 'Sending…',
        'log_out' => 'Sign out',
    ],

    'two_factor' => [
        'head' => 'Two-factor authentication',
        'code_title' => 'Authentication code',
        'code_description' => 'Enter the code from your authenticator app.',
        'code_toggle' => 'sign in using a recovery code',
        'recovery_title' => 'Recovery code',
        'recovery_description' => 'Enter one of your emergency recovery codes.',
        'recovery_toggle' => 'sign in using an authentication code',
        'recovery_placeholder' => 'Enter recovery code',
        'continue' => 'Continue',
        'or' => 'or you can',
    ],

    'passkey' => [
        'authenticating' => 'Authenticating…',
        'sign_in' => 'Sign in with a passkey',
        'or_email' => 'Or continue with email',
    ],
];
