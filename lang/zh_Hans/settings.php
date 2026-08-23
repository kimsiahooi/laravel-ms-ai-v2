<?php

declare(strict_types=1);

return [
    'nav' => [
        'profile' => '个人资料',
        'security' => '安全',
        'appearance' => '外观',
    ],

    'heading' => [
        'title' => '设置',
        'description' => '管理你的个人资料和账户设置',
        'nav_label' => '设置分区',
    ],

    'profile' => [
        'head' => '个人资料设置',
        'title' => '个人资料',
        'description' => '更新你的姓名和邮箱地址',
        'name' => '姓名',
        'name_placeholder' => '全名',
        'email' => '邮箱地址',
        'email_placeholder' => '邮箱地址',
        'unverified' => '你的邮箱地址尚未验证。',
        'resend' => '点击这里重新发送验证邮件。',
        'sent' => '新的验证链接已发送到你的邮箱地址。',
        'save' => '保存',
    ],

    'security' => [
        'head' => '安全设置',
        'title' => '更新密码',
        'description' => '使用长而随机的密码来保护你的账户',
        'current' => '当前密码',
        'new' => '新密码',
        'confirm' => '确认密码',
        'save' => '保存',
    ],

    'appearance' => [
        'head' => '外观设置',
        'title' => '外观',
        'description' => '选择本设备上的应用外观',
    ],

    'delete' => [
        'title' => '删除账户',
        'description' => '删除你的账户及其所有数据',
        'warning' => '警告',
        'warning_body' => '请谨慎操作，此操作无法撤销。',
        'button' => '删除账户',
        'confirm_title' => '确定要删除你的账户吗？',
        'confirm_body' => '账户删除后，其所有数据将一并永久删除。请输入密码以确认。',
    ],

    'two_factor' => [
        'title' => '两步验证',
        'description' => '为登录添加第二重验证',
        'enabled_body' => '登录时系统会要求输入验证码，可从手机上的验证器应用获取。',
        'disabled_body' => '启用后，登录时系统会要求输入验证码。验证码来自手机上的验证器应用。',
        'disable' => '停用',
        'continue_setup' => '继续设置',
        'enable' => '启用',
    ],

    'passkeys' => [
        'removing' => '正在移除…',
        'register' => '注册通行密钥',
        'registering' => '正在注册…',
        'title' => '通行密钥',
        'description' => '无需密码即可登录',
        'empty_title' => '还没有通行密钥',
        'empty_body' => '添加一个即可无需密码登录。',
        'add' => '添加通行密钥',
        'name' => '通行密钥名称',
        'name_placeholder' => '例如 MacBook Pro、iPhone',
        'name_hint' => '名称有助于你之后识别这个通行密钥。',
        'unsupported' => '此浏览器不支持通行密钥。',
        'remove' => '移除',
        'remove_title' => '移除通行密钥',
        'remove_body' => '移除“:name”通行密钥？你将无法再用它登录。',
        'added' => '添加于 :when',
        'last_used' => '最后使用 :when',
    ],

    'recovery' => [
        'title' => '恢复代码',
        'body' => '如果你丢失了验证器，恢复代码可以让你重新登录。请存放在密码管理器中。',
        'view_codes' => '查看恢复代码',
        'hide_codes' => '隐藏恢复代码',
        'regenerate' => '重新生成代码',
        'note' => '每个代码只能使用一次，用后即失效。用完后可在上方重新生成。',
    ],

    'setup' => [
        'manual' => '或手动输入代码',
        'back' => '返回',
        'confirm' => '确认',
    ],
    'password' => [
        'hide' => '隐藏密码',
        'show' => '显示密码',
    ],
];
