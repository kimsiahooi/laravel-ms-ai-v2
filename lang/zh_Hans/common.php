<?php

declare(strict_types=1);

return [
    'actions' => [
        'cancel' => '取消',
        'clear_search' => '清除搜索',
        'delete' => '删除',
        'edit' => '编辑',
        'row_actions' => ':name 的操作',
    ],

    // Marks a field the form will accept empty. Sits beside the label rather than
    // inside it, so the label stays the thing a screen reader announces.
    'field' => [
        'optional' => '（选填）',
    ],

    'confirm' => [
        'type_to_confirm' => '输入 :phrase 以确认',
    ],

    'password' => [
        'hide' => '隐藏密码',
        'show' => '显示密码',
    ],

    'errors' => [
        'generic' => '出了点问题。',
    ],

    'list' => [
        'no_matches' => '没有匹配项',
        'no_matches_hint' => '没有任何内容与“:search”匹配。',
        'page_empty' => '此页没有内容',
        'page_empty_hint' => '这些行已不存在 —— 自打开此页以来，列表可能变短了。',
        'back_to_first' => '返回第一页',
        'actions_column' => '操作',
        'rows_per_page' => '每页行数',
    ],

    'pagination' => [
        'label' => '分页',
        'page' => '第 :page 页',
        'no_results' => '无结果',
        'showing' => '显示第 :from–:to 项，共 :total 项',
        'page_of' => '第 :current 页，共 :last 页',
        'previous' => '上一页',
        'next' => '下一页',
    ],

    'language' => [
        'change' => '切换语言',
    ],

    'theme' => [
        'change' => '切换主题',
        'light' => '浅色',
        'dark' => '深色',
        'system' => '跟随系统',
    ],

    'time' => [
        'just_now' => '刚刚',
        'minutes_ago' => ':count 分钟前',
        'hours_ago' => ':count 小时前',
        'days_ago' => ':count 天前',
    ],
];
