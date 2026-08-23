<?php

declare(strict_types=1);

/*
| Password-broker outcomes. Laravel hands these to the client as a `status` prop or a
| validation error, already resolved — which is why the pages render them raw and must
| NOT wrap them in t(). Translating them means translating them here.
|
| English is quoted verbatim from Laravel's own file.
*/

return [
    'reset' => 'Your password has been reset.',
    'sent' => 'We have emailed your password reset link.',
    'throttled' => 'Please wait before retrying.',
    'token' => 'This password reset token is invalid.',
    'user' => "We can't find a user with that email address.",
];
