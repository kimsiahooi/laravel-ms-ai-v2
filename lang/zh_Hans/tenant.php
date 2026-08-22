<?php

declare(strict_types=1);

/*
| The workspace shell — chrome a signed-in tenant user sees on every screen. Module
| strings live in that module's own file.
*/

return [
    // Fallback for the workspace's own name, used only before one is known.
    'name' => '工作区',

    'nav' => [
        'dashboard' => '仪表板',
        'settings' => '账户设置',
        'sign_out' => '退出登录',
    ],
];
