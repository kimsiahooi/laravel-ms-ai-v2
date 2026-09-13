<?php

declare(strict_types=1);

/*
| 销售订单 —— 完整说明见 lang/en/sales-orders.php。
*/

return [
    'title' => '销售订单',
    'subtitle' => '客户订了什么，金额是多少。发货会把货品从仓库中扣除。',
    'search_placeholder' => '搜索订单号、客户或备注…',

    'column' => [
        'number' => '订单',
        'customer' => '客户',
        'status' => '状态',
        'total' => '合计',
        'expected' => '承诺交期',
        'created' => '下单',
    ],

    'status' => [
        'pending' => '待处理',
        'fulfilled' => '已发货',
        'cancelled' => '已取消',
    ],

    'action' => [
        'new' => '新建销售订单',
        'edit' => '编辑订单',
        'fulfil' => '发货',
        'cancel' => '取消订单',
    ],

    'filter' => [
        'status' => '状态',
        'all_statuses' => '任意状态',
        'customer' => '客户',
        'all_customers' => '任意客户',
        'customer_search' => '搜索客户…',
        'customer_empty' => '没有匹配的客户。',
    ],

    'create' => [
        'title' => '新建销售订单',
        'crumb' => '新建订单',
        'subtitle' => '卖给谁、卖什么、以及商定的价格。订单号在保存时自动生成。',
        'submit' => '保存订单',
        'submitting' => '保存中…',
    ],

    'edit' => [
        'title' => '编辑 :number',
        'crumb' => '编辑',
    ],

    'lines' => [
        'heading' => '销售内容',
    ],

    'field' => [
        'customer' => '客户',
        'customer_placeholder' => '卖给谁',
        'customer_search' => '搜索客户…',
        'customer_empty' => '没有匹配的客户。',
        'currency' => '货币',
        'currency_placeholder' => '选择货币',
        'exchange_rate' => '汇率',
        'exchange_rate_placeholder' => '例如 4.35',
        'exchange_rate_hint' => '订单商定当日，一单位订单货币折合本位币的金额。',
        'expected_date' => '承诺交货日',
        'expected_date_hint' => '客户期望收货的日期，若已商定则含时间。仅用于计划——当天不会自动发生任何事。',
        'notes' => '备注',
        'notes_placeholder' => '条款、客户采购单号，或任何值得记录的信息',
    ],

    'line' => [
        'item' => '项目',
        'item_placeholder' => '选择产品',
        'quantity' => '数量',
        'unit_price' => '单价',
        'discount' => '折扣',
        'total' => '行合计',
    ],

    'summary' => [
        'customer' => '客户',
        'currency' => '货币',
        'rate' => '汇率 :rate',
        'expected' => '承诺交货日',
        'raised_by' => '下单人',
        'fulfilled_by' => '发货人',
        'fulfilled_at' => '发货时间',
        'fulfilled_from' => '发货仓库',
        'notes' => '备注',
    ],

    'fulfil' => [
        'heading' => '发货',
        'description' => '发货会把订单的每一行从某一个仓库中扣除，并关闭订单。请选择货品实际是从哪里发出的。',
        'warehouse' => '发货仓库',
        'warehouse_placeholder' => '选择仓库',
        'warehouse_search' => '搜索仓库…',
        'warehouse_empty' => '没有匹配的仓库。',
        'no_warehouses' => '目前还没有可以发货的仓库。',
        'no_warehouses_action' => '设置仓库',
    ],

    'dialog' => [
        'fulfil' => [
            'title' => '确认为此订单发货？',
            'description' => '{1}该行将从 :warehouse 扣除，订单随即关闭。确认后库存立即变动，且无法撤销。|[2,*]全部 :count 行都会从 :warehouse 扣除，订单随即关闭。确认后库存立即变动，且无法撤销。',
            'submit' => '确认发货',
            'submitting' => '发货中…',
        ],
        'cancel' => [
            'title' => '取消此订单？',
            'description' => '订单将关闭，不会移动任何库存。已取消的订单无法重新打开，也不能再发货。',
            'submit' => '取消订单',
            'submitting' => '取消中…',
        ],
    ],

    'empty' => [
        'title' => '还没有销售订单',
        'description' => '新建一张，记录客户订了什么以及商定的价格。',
    ],

    'no_match' => [
        'title' => '没有匹配的订单',
        'description' => '没有与“:term”匹配的内容。',
    ],

    'no_setup' => [
        'title' => '请先添加客户',
        'description' => '订单总要有下单方，目前还没有客户可选。',
        'action' => '前往客户',
    ],

    'toast' => [
        'created' => '销售订单已创建。',
        'updated' => '销售订单已更新。',
        'fulfilled' => '订单已发货，库存已更新。',
        'cancelled' => '销售订单已取消。',
        'deleted' => '销售订单已删除。',
    ],

    'error' => [
        'not_pending' => '该订单已发货或已取消。',
        'fulfilled_locked' => '已发货的订单不能修改或删除。',
        'short' => '{1}库存不足：该仓库只有 :available 个:item，而订单需要 :required 个。|[2,*]该仓库有 :count 种产品库存不足，下方面板已标出。',
        'short_raced' => '发货过程中有人移动了这批库存。没有扣减任何库存——请核对数字后重试。',
    ],
];
