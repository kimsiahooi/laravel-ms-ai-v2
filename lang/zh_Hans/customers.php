<?php

declare(strict_types=1);

/*
| 客户 —— 这个工作区向谁销售。字段比供应商多，因为发票必须开给一个法律主体。
*/

return [
    'title' => '客户',
    'subtitle' => '你向谁销售，以及开票所需的信息。',

    'search_placeholder' => '搜索名称、联系人姓名、电话、邮箱、税号或备注…',

    'column' => [
        'name' => '客户',
        'email' => '电子邮箱',
        'location' => '所在地',
        'created' => '添加时间',
        'creator' => '添加者',
    ],

    'empty' => [
        'title' => '暂无客户',
        'description' => '先添加第一个，创建销售单时即可直接选用。',
    ],

    'no_match' => [
        'title' => '没有匹配的客户',
        'description' => '没有内容与“:term”匹配。',
    ],

    'create' => [
        'trigger' => '新建客户',
        'title' => '新建客户',
        'description' => '只有名称是必填的。税务和地址信息用于开票，可以之后再补。',
        'submit' => '创建客户',
        'submitting' => '正在创建…',
    ],

    'edit' => [
        'title' => '编辑客户',
        'description' => '修改会同步到所有引用该客户的位置。',
        'submit' => '保存更改',
        'submitting' => '正在保存…',
    ],

    'group' => [
        'identity' => '基本信息',
        'tax' => '税务信息',
        'tax_hint' => '开具电子发票时必填，此处选填 —— 拿到后再补即可。',
        'address' => '开票地址',
    ],

    'field' => [
        'name' => '公司名称',
        'name_placeholder' => '例如：Meridian Engineering Sdn Bhd',
        'contact_person' => '联系人',
        'contact_person_placeholder' => '你平时对接的人',
        'email' => '电子邮箱',
        'email_placeholder' => 'accounts@example.com',
        'phone' => '电话',
        'phone_placeholder' => '+60 3 1234 5678',
        'tin' => '税务识别号',
        'tin_placeholder' => '纳税人识别号',
        'registration_no' => '注册号',
        'registration_no_placeholder' => 'SSM（马来西亚）或 UEN（新加坡）',
        'sst_registration_no' => 'SST / GST 号',
        'sst_registration_no_placeholder' => '如果对方已注册',
        'address' => '街道地址',
        'address_placeholder' => '楼宇、街道、单元',
        'city' => '城市',
        'city_placeholder' => '例如：Shah Alam',
        'postcode' => '邮编',
        'postcode_placeholder' => '例如：40150',
        'state_code' => '州代码',
        'state_code_placeholder' => '例如：10',
        'country_code' => '国家',
        'country_code_placeholder' => '选择国家',
        'notes' => '备注',
        'notes_placeholder' => '信用条件、送货说明，以及任何值得记下的信息',
    ],

    'confirm' => [
        'delete_title' => '删除 :name？',
        'delete_description' => '已为该客户开出的销售单和发票会保留记录 —— 只是无法再为新的单据选择它。',
        'delete_submit' => '删除客户',
        'delete_submitting' => '正在删除…',
    ],

    'toast' => [
        'created' => '已创建 :name。',
        'updated' => '已更新 :name。',
        'deleted' => '已删除 :name。',
    ],
];
