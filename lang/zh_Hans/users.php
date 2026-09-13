<?php

declare(strict_types=1);

/*
| 用户 —— 完整说明见 lang/en/users.php。
*/

return [
    'title' => '用户',
    'subtitle' => '谁可以登录这个工作区，以及决定其访问范围的角色。',
    'search_placeholder' => '搜索姓名或邮箱…',

    'column' => [
        'name' => '姓名',
        'email' => '邮箱',
        'role' => '角色',
        'status' => '状态',
        'created' => '添加时间',
    ],

    'status' => [
        'active' => '正常',
        'deactivated' => '已停用',
        'temporary_password' => '临时密码',
    ],

    'filter' => [
        'status' => '状态',
        'active' => '正常',
        'deactivated' => '已停用',
        'all' => '全部',
    ],

    'action' => [
        'new' => '添加成员',
        'edit' => '编辑',
        'deactivate' => '停用',
        'restore' => '重新启用',
    ],

    'create' => [
        'title' => '添加成员',
        'description' => '对方使用你在此设置的密码登录，并会在首次登录时被要求更换。',
        'submit' => '添加用户',
        'submitting' => '添加中…',
    ],

    'edit' => [
        'title' => '编辑 :name',
        'description' => '密码留空即保持原密码不变。填写新密码会再次要求对方更换。',
        'submit' => '保存更改',
        'submitting' => '保存中…',
    ],

    'field' => [
        'name' => '姓名',
        'name_placeholder' => '对方的全名',
        'email' => '邮箱',
        'email_placeholder' => 'name@company.com',
        'email_hint' => '这就是对方登录时使用的账号。',
        'role' => '角色',
        'role_placeholder' => '可访问的范围',
        'role_search' => '搜索角色…',
        'role_empty' => '没有匹配的角色。',
        'password' => '临时密码',
        'password_placeholder' => '至少 8 个字符',
        'password_hint' => '你需要把这个密码告诉对方。首次登录时会要求其更换。',
        'password_optional_hint' => '留空即保持原密码不变。',
        'password_confirmation' => '确认密码',
    ],

    'dialog' => [
        'deactivate' => [
            'title' => '停用 :name？',
            'description' => '对方将无法再登录。其已完成的操作仍然记在其名下，你可以随时重新启用。',
            'submit' => '停用',
            'submitting' => '停用中…',
        ],
    ],

    'empty' => [
        'title' => '目前只有你',
        'description' => '添加同事，并给他们一个只覆盖所需范围的角色。',
    ],

    'no_match' => [
        'title' => '没有匹配的人员',
        'description' => '没有与“:term”匹配的内容。',
    ],

    'toast' => [
        'created' => ':name 现在可以登录了。',
        'updated' => ':name 已更新。',
        'deactivated' => ':name 已无法登录。',
        'restored' => ':name 可以重新登录了。',
    ],

    'error' => [
        'last_administrator' => '工作区至少需要一名管理员，而这是最后一名。',
        'last_administrator_self' => '你是唯一的管理员。请先把 Administrator 角色给另一个人，再删除自己的账号，否则这个工作区将无人能够处理任何事情。',
        'not_yourself' => '不能停用自己的账号。',
        'must_change_password' => '请先设置你自己的密码，然后再继续。',
    ],

    'validation' => [
        'email_deactivated' => '该邮箱属于一位已停用的成员。请重新启用他们，而不是新建账号。',
        'last_administrator' => '这是唯一的管理员。请先把该角色给另一个人。',
    ],
];
