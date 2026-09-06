<?php

declare(strict_types=1);

/*
| 关于本模块的说明，请参阅 lang/en/purchase-orders.php。
*/

return [
    'title' => '采购订单',
    'subtitle' => '已经向供应商订购了什么、要付多少钱。收货时，货物会入到某个仓库。',
    'search_placeholder' => '搜索订单号、供应商或备注…',

    'column' => [
        'number' => '订单',
        'supplier' => '供应商',
        'status' => '状态',
        'total' => '合计',
        'expected' => '预计到货',
        'created' => '创建时间',
    ],

    'status' => [
        'pending' => '待收货',
        'received' => '已收货',
        'cancelled' => '已取消',
    ],

    'action' => [
        'new' => '新建采购订单',
        'edit' => '编辑订单',
        'receive' => '收货',
        'cancel' => '取消订单',
    ],

    'filter' => [
        'status' => '状态',
        'all_statuses' => '任意状态',
        'supplier' => '供应商',
        'all_suppliers' => '任意供应商',
        'supplier_search' => '搜索供应商…',
        'supplier_empty' => '没有匹配的供应商。',
    ],

    'create' => [
        'title' => '新建采购订单',
        'crumb' => '新建订单',
        'subtitle' => '向谁采购、采购什么、议定的价格是多少。订单号在保存时自动分配。',
        'submit' => '保存订单',
        'submitting' => '正在保存…',
    ],

    'edit' => [
        'title' => '编辑 :number',
        'crumb' => '编辑',
    ],

    'lines' => [
        'heading' => '订购内容',
    ],

    'field' => [
        'supplier' => '供应商',
        'supplier_placeholder' => '向谁采购',
        'supplier_search' => '搜索供应商…',
        'supplier_empty' => '没有匹配的供应商。',
        'currency' => '币种',
        'currency_placeholder' => '选择币种',
        'exchange_rate' => '汇率',
        'exchange_rate_placeholder' => '例如：4.35',
        'exchange_rate_hint' => '订单议定当天，一单位订单币种折合多少本位币。',
        'expected_date' => '预计到货日期',
        'expected_date_hint' => '货物应当到达的日期。仅用于计划，当天不会自动发生任何事。',
        'notes' => '备注',
        'notes_placeholder' => '条款、报价单号，或任何值得记下的信息',
    ],

    'line' => [
        'item' => '物料',
        'quantity' => '数量',
        'unit_cost' => '单位成本',
        'discount' => '折扣',
        'total' => '行合计',
    ],

    'summary' => [
        'supplier' => '供应商',
        'currency' => '币种',
        'rate' => '汇率 :rate',
        'expected' => '预计到货日期',
        'raised_by' => '创建人',
        'received_by' => '收货人',
        'received_at' => '收货时间',
        'received_into' => '入库仓库',
        'notes' => '备注',
    ],

    'receive' => [
        'heading' => '收货',
        'description' => '收货会把每一行都入到同一个仓库，并关闭该订单。请选择货物实际到达的地点。',
        'warehouse' => '收货仓库',
        'warehouse_placeholder' => '选择仓库',
        'warehouse_search' => '搜索仓库…',
        'warehouse_empty' => '没有匹配的仓库。',
        'no_warehouses' => '目前还没有可以收货的仓库。',
        'no_warehouses_action' => '去设置仓库',
    ],

    'dialog' => [
        'receive' => [
            'title' => '要为该订单收货吗？',
            'description' => '全部 :lines 行都会入到 :warehouse，订单随之关闭。确认后库存立即变动，此操作无法撤销。',
            'submit' => '确认收货',
            'submitting' => '正在收货…',
        ],
        'cancel' => [
            'title' => '要取消这个订单吗？',
            'description' => '订单会被关闭，库存不会有任何变动。已取消的订单无法重新打开，也不能再收货。',
            'submit' => '取消订单',
            'submitting' => '正在取消…',
        ],
    ],

    'empty' => [
        'title' => '还没有采购订单',
        'description' => '新建一个，记录你订购了什么、议定了什么价格。',
    ],

    'no_match' => [
        'title' => '没有匹配的订单',
        'description' => '没有与“:term”匹配的内容。',
    ],

    'no_setup' => [
        'title' => '请先添加供应商',
        'description' => '订单总是向某个供应商下的，而目前还没有供应商。',
        'action' => '前往供应商',
    ],

    'toast' => [
        'created' => '采购订单已创建。',
        'updated' => '采购订单已更新。',
        'received' => '订单已收货，库存已更新。',
        'cancelled' => '采购订单已取消。',
        'deleted' => '采购订单已删除。',
    ],

    'error' => [
        'not_pending' => '该订单已收货或已取消。',
        'insufficient' => '只有 :available 可用，而这次要动 :requested。',
        'received_locked' => '已收货的订单不能修改或删除。',
        'insufficient' => '仅有 :available，而这次收货要入 :requested。',
    ],
];
