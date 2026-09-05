<?php

declare(strict_types=1);

/*
| 关于本模块的说明，请参阅 lang/en/stock-movements.php。
*/

return [
    'title' => '库存变动',
    'subtitle' => '所有出入库记录及其原因。这里的内容无法编辑——如有错误，请记录一笔相反的变动来更正。',

    'search_placeholder' => '搜索项目、仓库或备注…',

    'filter' => [
        'warehouse' => '仓库',
        'all_warehouses' => '任何仓库',
        'warehouses_selected' => ':count 个仓库中的任一个',
        'warehouse_hint' => '[0,1] 勾选多项可扩大范围——变动只需发生在其中一个仓库即可。|[2,*] 正在显示发生在这 :count 个仓库中任一个的变动，而非同时发生在全部仓库的变动。',
        'warehouse_search' => '搜索仓库…',
        'warehouse_empty' => '没有匹配的仓库。',
        'reason' => '原因',
        'all_reasons' => '任何原因',
    ],

    'column' => [
        'item' => '项目',
        'warehouse' => '仓库',
        'quantity' => '变动量',
        'reason' => '原因',
        'recorded' => '记录时间',
        'user' => '操作人',
        'notes' => '备注',
    ],

    'item_type' => [
        'product' => '产品',
        'raw_material' => '原材料',
    ],

    'reason' => [
        'adjustment' => '手动调整',
        'stock_take' => '盘点',
        'transfer_in' => '调拨入库',
        'transfer_out' => '调拨出库',
        'purchase_receipt' => '采购入库',
        'purchase_return' => '采购退货',
        'sales_fulfillment' => '销售出库',
        'sales_return' => '销售退货',
        'production_consume' => '生产领用',
        'production_output' => '生产入库',
    ],

    'empty' => [
        'title' => '还没有任何变动',
        'description' => '记录第一笔变动后，它会显示在这里；之后采购、销售和调拨产生的变动也会一并出现。',
    ],

    'no_match' => [
        'title' => '没有匹配的变动',
        'description' => '没有内容匹配“:term”。',
    ],

    'no_setup' => [
        'title' => '请先设置仓库',
        'description' => '库存需要经由仓库流转，目前还没有可以存放的地方。',
        'action' => '前往仓库',
    ],

    'no_items' => [
        'title' => '请先添加可以出入库的项目',
        'description' => '库存按产品和原材料计量，而目录目前是空的。',
        'action' => '前往产品',
    ],

    'create' => [
        'trigger' => '记录变动',
        'title' => '记录变动',
        'description' => '什么发生了变动、在哪里、数量多少。',
        'submit' => '记录变动',
        'submitting' => '记录中…',
    ],

    'field' => [
        'warehouse' => '仓库',
        'warehouse_placeholder' => '选择仓库',
        'warehouse_search' => '搜索仓库…',
        'warehouse_empty' => '没有匹配的仓库。',
        'item' => '项目',
        'item_placeholder' => '选择产品或原材料',
        'item_search' => '按名称或 SKU 搜索…',
        'item_empty' => '没有匹配项。',
        'item_group_product' => '产品',
        'item_group_raw_material' => '原材料',
        'type' => '发生了什么',
        'type_in' => '入库',
        'type_out' => '出库',
        'type_set' => '设定库存量',
        'type_hint_in' => '在现有数量上增加。',
        'type_hint_out' => '从现有数量中扣减。数量不足时会被拒绝。',
        'type_hint_set' => '不论现在是多少，直接替换为该数值——适用于盘点之后。',
        'quantity' => '数量',
        'quantity_placeholder' => '例如：12',
        'quantity_placeholder_set' => '例如：12 —— 新的总量',
        'notes' => '备注',
        'notes_placeholder' => '原因，或任何值得记录的信息',
        'on_hand' => '当前库存：:quantity',
        'on_hand_unknown' => '此处尚无记录。',
    ],

    'error' => [
        'insufficient' => '仅有 :available，而本次需要 :requested。',
    ],

    'toast' => [
        'recorded' => '变动已记录。',
    ],
];
