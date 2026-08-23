<?php

declare(strict_types=1);

/*
| 产品分类 —— 第一个目录模块。只有一个界面：列表、覆盖其上的对话框，以及一个确认框。
| 与其他列表共用的界面文案（搜索、分页、取消、编辑、删除）放在 common.php。
*/

return [
    'title' => '分类',
    'subtitle' => '为目录中的产品分组，让长列表也能轻松查找。',

    'search_placeholder' => '搜索名称或描述…',

    'column' => [
        'name' => '名称',
        'description' => '描述',
        'created' => '创建时间',
        'creator' => '创建者',
    ],

    'empty' => [
        'title' => '暂无分类',
        'description' => '分类用于归类产品。先创建第一个分类，添加产品时即可直接使用。',
    ],

    'no_match' => [
        'title' => '没有匹配的分类',
        'description' => '没有内容与“:term”匹配。',
    ],

    'create' => [
        'trigger' => '新建分类',
        'title' => '新建分类',
        'description' => '为产品所属的分组命名。',
        'submit' => '创建分类',
        'submitting' => '正在创建…',
    ],

    'edit' => [
        'title' => '编辑分类',
        'description' => '重命名分类后，所有引用它的位置都会同步更新。',
        'submit' => '保存更改',
        'submitting' => '正在保存…',
    ],

    'field' => [
        'name' => '名称',
        'name_placeholder' => '例如：紧固件',
        'description' => '描述',
        'description_placeholder' => '这个分类包含哪些内容',
    ],

    'confirm' => [
        'delete_title' => '删除 :name？',
        'delete_description' => '已归入此分类的产品会保留数据 —— 只是不再按它分组。不会删除其他任何内容。',
        'delete_submit' => '删除分类',
        'delete_submitting' => '正在删除…',
    ],

    'toast' => [
        'created' => '已创建 :name。',
        'updated' => '已更新 :name。',
        'deleted' => '已删除 :name。',
    ],
];
