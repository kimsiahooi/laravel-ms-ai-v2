<?php

declare(strict_types=1);

/*
| 供应商 —— 这个工作区从谁那里采购。与其他列表共用的界面文案（搜索、分页、取消、
| 编辑、删除）放在 common.php。
*/

return [
    'title' => '供应商',
    'subtitle' => '你向谁采购，以及如何联系他们。',

    'search_placeholder' => '搜索名称、联系人姓名、电话、邮箱或备注…',

    'column' => [
        'name' => '供应商',
        'email' => '电子邮箱',
        'phone' => '电话',
        'created' => '添加时间',
        'creator' => '添加者',
    ],

    'empty' => [
        'title' => '暂无供应商',
        'description' => '先添加第一个，创建采购单时即可直接选用。',
    ],

    'no_match' => [
        'title' => '没有匹配的供应商',
        'description' => '没有内容与“:term”匹配。',
    ],

    'create' => [
        'trigger' => '新建供应商',
        'title' => '新建供应商',
        'description' => '只有名称是必填的 —— 其余信息可以之后再补。',
        'submit' => '创建供应商',
        'submitting' => '正在创建…',
    ],

    'edit' => [
        'title' => '编辑供应商',
        'description' => '修改会同步到所有引用该供应商的位置。',
        'submit' => '保存更改',
        'submitting' => '正在保存…',
    ],

    'field' => [
        'name' => '公司名称',
        'name_placeholder' => '例如：Acme Steel Sdn Bhd',
        'contact_person' => '联系人',
        'contact_person_placeholder' => '你平时对接的人',
        'email' => '电子邮箱',
        'email_placeholder' => 'orders@example.com',
        'phone' => '电话',
        'phone_placeholder' => '+60 3 1234 5678',
        'tax_id' => '税号',
        'tax_id_placeholder' => '注册号或 SST 号',
        'address' => '地址',
        'address_placeholder' => '收货和开票地址',
        'notes' => '备注',
        'notes_placeholder' => '付款条件、交货周期，以及任何值得记下的信息',
    ],

    'confirm' => [
        'delete_title' => '删除 :name？',
        'delete_description' => '已经向该供应商下过的采购单会保留记录 —— 只是无法再为新的采购单选择它。',
        'delete_submit' => '删除供应商',
        'delete_submitting' => '正在删除…',
    ],

    'toast' => [
        'created' => '已创建 :name。',
        'updated' => '已更新 :name。',
        'deleted' => '已删除 :name。',
    ],
];
