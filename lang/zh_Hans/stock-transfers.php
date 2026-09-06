<?php

declare(strict_types=1);

return [
    'title' => '库存调拨',
    'subtitle' => '库存从一个仓库移到另一个仓库。和流水账一样，这里的记录不能修改——调错方向就再调回去。',

    'search_placeholder' => '搜索物料、仓库或备注…',

    'filter' => [
        'warehouse' => '仓库',
        'all_warehouses' => '任意仓库',
        'warehouses_selected' => ':count 个仓库中的任意一个',
        'warehouse_hint' => '[0,1] 两端都算——不论库存是从这里调出还是调入。|[2,*] 显示涉及这 :count 个仓库中任意一个的调拨，调出调入都算。',
        'warehouse_search' => '搜索仓库…',
        'warehouse_empty' => '没有匹配的仓库。',
    ],

    'column' => [
        'item' => '物料',
        'from' => '调出',
        'to' => '调入',
        'quantity' => '数量',
        'moved' => '调拨时间',
        'user' => '操作人',
        'notes' => '备注',
    ],

    'empty' => [
        'title' => '还没有调拨记录',
        'description' => '在两个仓库之间调拨库存后，记录会显示在这里。',
    ],

    'no_match' => [
        'title' => '没有匹配的调拨',
        'description' => '没有与“:term”匹配的内容。',
    ],

    'no_setup' => [
        'title' => '请先建第二个仓库',
        'description' => '调拨是在两个仓库之间移动库存，而目前只有一个仓库。',
        'action' => '前往仓库',
    ],

    'no_items' => [
        'title' => '先添加可调拨的物料',
        'description' => '库存以产品和原材料计数，而目录还是空的。',
        'action' => '前往产品',
    ],

    'create' => [
        'trigger' => '调拨库存',
        'title' => '调拨库存',
        'description' => '调拨什么、从哪里调出、调到哪里。',
        'submit' => '记录调拨',
        'submitting' => '正在记录…',
    ],

    'field' => [
        'item' => '物料',
        'item_placeholder' => '选择产品或原材料',
        'item_search' => '按名称或 SKU 搜索…',
        'item_empty' => '没有匹配项。',
        'item_group_product' => '产品',
        'item_group_raw_material' => '原材料',
        'from' => '调出仓库',
        'from_placeholder' => '库存目前所在的仓库',
        'to' => '调入仓库',
        'to_placeholder' => '要调往的仓库',
        'warehouse_search' => '搜索仓库…',
        'warehouse_empty' => '没有匹配的仓库。',
        'quantity' => '数量',
        'quantity_placeholder' => '例如 12',
        'notes' => '备注',
        'notes_placeholder' => '原因，或任何值得记下的信息',
    ],

    'error' => [
        'insufficient' => '调出仓库只有 :available，而这次要调 :requested。',
    ],

    'toast' => [
        'recorded' => '调拨已记录。',
    ],
];
