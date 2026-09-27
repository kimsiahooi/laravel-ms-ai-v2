<?php

declare(strict_types=1);

return [
    'title' => '销售退货',
    'subtitle' => '客户退回的货品，按当初向他们收取的价格入账。',
    'search_placeholder' => '搜索退货单号或订单号、客户或备注…',

    'column' => [
        'number' => '退货单',
        'order' => '对应',
        'customer' => '客户',
        'status' => '状态',
        'reason' => '原因',
        'total' => '贷记',
        'created' => '创建时间',
    ],

    'action' => [
        'new' => '新建销售退货',
        'edit' => '编辑退货单',
        'complete' => '完成退货',
        'cancel' => '取消退货',
    ],

    'filter' => [
        'status' => '状态',
        'all_statuses' => '任何状态',
        'reason' => '原因',
        'all_reasons' => '任何原因',
    ],

    'create' => [
        'title' => '针对 :number 的退货',
        'crumb' => '新建退货',
        'subtitle' => '填写每一行发出的货品退回了多少。价格取自订单，因此贷记金额与当初向客户收取的一致。',
        'submit' => '保存退货单',
        'submitting' => '正在保存…',
    ],

    'edit' => [
        'title' => '编辑 :number',
        'crumb' => '编辑',
        'subtitle' => '只有待处理的退货单可以修改。',
        'submit' => '保存修改',
        'submitting' => '正在保存…',
    ],

    'order' => [
        'heading' => '贷记对象',
        'number' => '销售订单',
        'customer' => '客户',
        'fulfilled' => '发货时间',
        'locked' => '一张退货单只对应一次发货，因此这里无法更改。要贷记另一张订单，请新建退货单。',
    ],

    'field' => [
        'reason' => '原因',
        'reason_placeholder' => '货品退回的原因',
        'notes' => '备注',
        'notes_placeholder' => '单据编号、客户的说法，或任何值得记录的事',
    ],

    'lines' => [
        'heading' => '退回了什么',
        'description' => '这次发货中仍有可退数量的每一行。留空数量即表示该行不列入本次退货。',
        'fill_all' => '全部退回',
        'empty' => '该订单上的货品都已退完。',
    ],

    'line' => [
        'item' => '产品',
        'sold' => '已发出',
        'returned' => '已退回',
        'remaining' => '剩余可退',
        'quantity' => '本次退回',
        'quantity_placeholder' => '例如 2',
        'unit_price' => '单价',
        'discount' => '折扣',
        'total' => '行贷记',
    ],

    'complete' => [
        'heading' => '把货品收回来',
        'description' => '完成退货会把每一行重新入库到某一个仓库并关闭这张单。请选择货品实际入到哪里。',
        'warehouse' => '入库仓库',
        'warehouse_placeholder' => '选择仓库',
        'warehouse_search' => '搜索仓库…',
        'warehouse_empty' => '没有匹配的仓库。',
        'no_warehouses' => '目前还没有可以入库的仓库。',
        'no_warehouses_action' => '去设置仓库',
    ],

    'summary' => [
        'order' => '销售订单',
        'customer' => '客户',
        'currency' => '货币',
        'rate' => '汇率 :rate',
        'reason' => '原因',
        'raised_by' => '创建人',
        'completed_by' => '完成人',
        'completed_at' => '完成时间',
        'warehouse' => '入库仓库',
        'notes' => '备注',
    ],

    'dialog' => [
        'complete' => [
            'title' => '完成这张退货单？',
            'description' => '{1}一行货品将入库到 :warehouse，退货单随即关闭。确认后库存立即变动，且无法撤销。|[2,*]全部 :count 行货品将入库到 :warehouse，退货单随即关闭。确认后库存立即变动，且无法撤销。',
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
            'description' => '退货单将被删除，单上的数量重新可退。目前尚未发生库存变动，因此没有任何操作需要撤销。',
            'submit' => '删除退货单',
            'submitting' => '正在删除…',
        ],
    ],

    'empty' => [
        'title' => '还没有销售退货',
        'description' => '退货单是针对一次发货创建的。打开货品所属的销售订单，从那里开始。',
        'action' => '查看已发货订单',
    ],

    'no_setup' => [
        'title' => '还没有发出过任何货品',
        'description' => '货品发出之后才能退回。先完成一张销售订单的发货，它就可以退货了。',
        'action' => '去销售订单',
    ],

    'no_match' => [
        'title' => '没有匹配的退货单',
        'description' => '没有内容匹配“:term”。',
    ],

    'toast' => [
        'created' => '销售退货已创建。',
        'updated' => '销售退货已更新。',
        'deleted' => '销售退货已删除。',
        'completed' => '销售退货已完成，库存已变动。',
        'cancelled' => '销售退货已取消。',
    ],

    'error' => [
        'not_pending' => '该退货单已完成或已取消。',
        'completed_locked' => '已完成的退货单不能修改或删除。',
        'no_order' => '该销售订单无法退货——它可能已被删除，或者从未发货。',
        'nothing_returnable' => '该订单上的货品都已退完。',
        'short_raced' => '这张退货单正在完成时，有人改动了库存。没有入库任何货品——请核对数字后重试。',
        'over_return_now' => '{1}自这张退货单创建以来，另一张退货单已经完成。:item 只剩 :remaining 可退，而这张单要退 :requested。请修改后重试。|[2,*]自这张退货单创建以来，另一张退货单已经完成，其中 :count 行已经超出这次发货可退的数量。请修改这张退货单后重试。',
    ],

    'validation' => [
        'over_return' => '该行只剩 :remaining 可以退回。',
    ],
];
