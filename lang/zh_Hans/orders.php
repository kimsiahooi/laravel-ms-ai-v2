<?php

declare(strict_types=1);

/*
| 关于本模块的说明，请参阅 lang/en/orders.php。
*/

return [
    'line' => [
        'item' => '项目',
        'item_placeholder' => '选择产品或原材料',
        'item_search' => '按名称或 SKU 搜索…',
        'item_empty' => '没有匹配项。',
        'quantity' => '数量',
        'quantity_placeholder' => '例如：12',
        'unit_price' => '单价',
        'unit_price_placeholder' => '例如：9.50',
        'unit_cost' => '单位成本',
        'unit_cost_placeholder' => '例如 9.50',
        'discount' => '折扣',
        'discount_value' => '折扣值',
        'taxable' => '计税',
        'amount' => '金额',
        'remove' => '删除第 :number 行',
    ],

    'discount_type' => [
        'none' => '无',
        'percent' => '百分比',
        'amount' => '固定金额',
    ],

    'lines' => [
        'add' => '添加行',
        'empty' => '还没有任何行。添加一行来说明订购的内容。',
    ],

    'totals' => [
        'subtotal' => '小计',
        'discount' => '折扣',
        'tax' => '税额（:rate%）',
        'total' => '合计',
        'estimate' => '这是实时估算。保存订单时，最终金额由服务器根据这些行重新计算并存储。',
    ],
];
