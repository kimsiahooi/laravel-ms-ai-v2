<?php

declare(strict_types=1);

/*
| 采购退货——把货品退回给供货商，以及由此产生的贷记。每个措辞的取舍见 `en` 文件。
|
| 用“剩余”而不是“可用”：一行的上限说的是这批到货还有多少没退回，与仓库里有多少无关。
| 仓库是另一个问题，在退货完成时才问。
*/

return [
    'title' => '采购退货',
    'subtitle' => '退回供货商的货品，按当初这批到货的价格贷记。',
    'search_placeholder' => '搜索退货单号、订单号、供货商或备注…',

    'column' => [
        'number' => '退货单',
        'order' => '对应订单',
        'supplier' => '供货商',
        'status' => '状态',
        'reason' => '原因',
        'total' => '贷记金额',
        'created' => '创建于',
    ],

    'action' => [
        'new' => '新建采购退货',
        'edit' => '编辑退货单',
        'complete' => '完成退货',
        'cancel' => '取消退货',
    ],

    'filter' => [
        'status' => '状态',
        'all_statuses' => '全部状态',
        'reason' => '原因',
        'all_reasons' => '全部原因',
    ],

    'create' => [
        'title' => '对 :number 退货',
        'crumb' => '新建退货',
        'subtitle' => '填写每一行要退回多少。价格取自订单，因此贷记金额与当初的收费一致。',
        'submit' => '保存退货单',
        'submitting' => '保存中…',
    ],

    'edit' => [
        'title' => '编辑 :number',
        'crumb' => '编辑',
        'subtitle' => '只有待处理的退货单可以修改。',
        'submit' => '保存更改',
        'submitting' => '保存中…',
    ],

    'order' => [
        'heading' => '贷记对象',
        'number' => '采购订单',
        'supplier' => '供货商',
        'received' => '收货时间',
        'locked' => '一张退货单只贷记一批到货，因此这里不能更改。要贷记其他订单，请另建一张退货单。',
    ],

    'field' => [
        'reason' => '原因',
        'reason_placeholder' => '货品为何退回',
        'notes' => '备注',
        'notes_placeholder' => '参考编号、供货商的说法，或任何值得记下的事',
    ],

    'lines' => [
        'heading' => '退回的内容',
        'description' => '这批到货中仍有剩余可退的每一行。数量留空即表示该行不在这张退货单上。',
        'fill_all' => '全部退回',
        'empty' => '这张订单上的货品都已退完。',
    ],

    'line' => [
        'item' => '原材料',
        'ordered' => '已到货',
        'returned' => '已退回',
        'remaining' => '剩余可退',
        'quantity' => '本次退回',
        'quantity_placeholder' => '例如：2',
        'unit_cost' => '单位成本',
        'discount' => '折扣',
        'total' => '本行贷记',
    ],

    'complete' => [
        'heading' => '把货品退回去',
        'description' => '完成退货会从某一个仓库扣减每一行并关闭这张单。请选择货品实际从哪里发出。',
        'warehouse' => '发出仓库',
        'warehouse_placeholder' => '选择仓库',
        'warehouse_search' => '搜索仓库…',
        'warehouse_empty' => '没有匹配的仓库。',
        'no_warehouses' => '目前还没有可以发货的仓库。',
        'no_warehouses_action' => '去设置仓库',
    ],

    'summary' => [
        'order' => '采购订单',
        'supplier' => '供货商',
        'currency' => '货币',
        'rate' => '汇率 :rate',
        'reason' => '原因',
        'raised_by' => '创建人',
        'completed_by' => '完成人',
        'completed_at' => '完成时间',
        'warehouse' => '发出仓库',
        'notes' => '备注',
    ],

    'dialog' => [
        'complete' => [
            'title' => '完成这张退货单？',
            'description' => '{1}一行货品将从 :warehouse 扣减，退货单随即关闭。确认后库存立即变动，且无法撤销。|[2,*]全部 :count 行货品将从 :warehouse 扣减，退货单随即关闭。确认后库存立即变动，且无法撤销。',
            'submit' => '完成退货',
            'submitting' => '正在完成…',
        ],
        'cancel' => [
            'title' => '取消这张退货单？',
            'description' => '退货单将关闭，库存不会变动，单上的数量重新可退。已取消的退货单无法重新打开。',
            'submit' => '取消退货',
            'submitting' => '正在取消…',
        ],
        'delete' => [
            'title' => '删除 :number？',
            'description' => '该退货单将被移除，上面的数量重新变为可退。目前尚未发生任何库存变动，因此没有什么需要冲回。',
            'submit' => '删除退货单',
            'submitting' => '删除中…',
        ],
    ],

    'empty' => [
        'title' => '暂无采购退货',
        'description' => '退货单是针对某一批到货创建的。打开货品所属的采购订单，从那里开始。',
        'action' => '查看已收货订单',
    ],

    'no_setup' => [
        'title' => '还没有收到任何货',
        'description' => '货品到货之后才能退回。先收一张采购订单，它就可以退货了。',
        'action' => '前往采购订单',
    ],

    'no_match' => [
        'title' => '没有匹配的退货单',
        'description' => '没有内容匹配“:term”。',
    ],

    'toast' => [
        'created' => '采购退货已创建。',
        'updated' => '采购退货已更新。',
        'deleted' => '采购退货已删除。',
        'completed' => '采购退货已完成，库存已变动。',
        'cancelled' => '采购退货已取消。',
    ],

    'error' => [
        'not_pending' => '该退货单已完成或已取消。',
        'completed_locked' => '已完成的退货单不能修改或删除。',
        'no_order' => '该采购订单无法退货——它可能已被删除，或者从未收货。',
        'nothing_returnable' => '该订单上的货品都已退完。',
        'short' => '{1}库存不足：该仓库只有 :available 个 :item，而这张退货单需要 :required。|[2,*]该仓库有 :count 项货品库存不足，下方面板列出了是哪几项。',
        'short_raced' => '这张退货单正在完成时，有人改动了库存。没有扣减任何货品——请核对数字后重试。',
        'over_return_now' => '{1}自这张退货单创建以来，另一张退货单已经完成。:item 只剩 :remaining 可退，而这张单要退 :requested。请修改后重试。|[2,*]自这张退货单创建以来，另一张退货单已经完成，其中 :count 行已经超出这批收货可退的数量。请修改这张退货单后重试。',
    ],

    'validation' => [
        'over_return' => '这一行最多还能退回 :remaining。',
    ],
];
