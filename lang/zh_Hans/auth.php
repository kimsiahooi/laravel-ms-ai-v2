<?php

declare(strict_types=1);

return [
    'failed' => '凭据与我们的记录不匹配。',
    'password' => '提供的密码不正确。',
    'throttle' => '登录尝试次数过多。请在 :seconds 秒后重试。',

    'panel' => [
        'heading' => '你的库存，你的订单，一个工作区。',
        'point_stock' => '每个地点、每件物品的每一次移动。',
        'point_orders' => '从采购到生产到销售，串成一条线。',
        'footer' => '正在登录 :workspace。',
    ],

    'fields' => [
        'email' => '邮箱地址',
        'email_placeholder' => 'you@example.com',
        'password' => '密码',
        'password_placeholder' => '密码',
    ],

    'login' => [
        'head' => '登录',
        'title' => '登录你的工作区',
        'description' => '输入邮箱和密码以继续。',
        'forgot' => '忘记密码？',
        'remember' => '保持登录状态',
        'submit' => '登录',
        'submitting' => '正在登录…',
    ],

    'forgot' => [
        'head' => '忘记密码',
        'title' => '忘记密码了？',
        'description' => '输入你的邮箱，我们会发送重置链接。',
        'submit' => '发送密码重置链接',
        'submitting' => '正在发送…',
        'return' => '或返回',
        'login' => '登录',
    ],

    'reset' => [
        'head' => '重置密码',
        'title' => '重置你的密码',
        'description' => '为你的账户设置一个新密码。',
        'new_password' => '新密码',
        'confirm_password' => '确认密码',
        'submit' => '重置密码',
        'submitting' => '正在重置…',
    ],

    'confirm' => [
        'head' => '确认密码',
        'title' => '确认你的密码',
        'description' => '这是安全区域。请先确认密码再继续。',
        'submit' => '确认密码',
        'submitting' => '正在确认…',
        'with_passkey' => '使用通行密钥确认',
        'or_password' => '或使用密码确认',
    ],

    'verify' => [
        'sent' => '新的验证链接已发送到你的邮箱地址。',
        'head' => '验证邮箱',
        'title' => '验证你的邮箱地址',
        'description' => '请点击我们刚发送的链接。如果没有收到，我们可以再发一次。',
        'resend' => '重新发送验证邮件',
        'resending' => '正在发送…',
        'log_out' => '退出登录',
    ],

    'two_factor' => [
        'head' => '两步验证',
        'code_title' => '验证码',
        'code_description' => '输入验证器应用中的验证码。',
        'code_toggle' => '使用恢复代码登录',
        'recovery_title' => '恢复代码',
        'recovery_description' => '输入你的其中一个应急恢复代码。',
        'recovery_toggle' => '使用验证码登录',
        'recovery_placeholder' => '输入恢复代码',
        'continue' => '继续',
        'or' => '或者你可以',
    ],

    'passkey' => [
        'authenticating' => '正在验证…',
        'sign_in' => '使用通行密钥登录',
        'or_email' => '或使用邮箱继续',
    ],
];
