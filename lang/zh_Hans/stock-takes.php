<?php

declare(strict_types=1);

/*
| 关于本模块的说明，请参阅 lang/en/stock-takes.php。
*/

return [
    'title' => '库存盘点',
    'subtitle' => '清点仓库里实际有多少，再把差异过账。',
    'search_placeholder' => '搜索仓库、地点或备注…',

    'column' => [
        'id' => '盘点单号',
        'warehouse' => '仓库',
        'status' => '状态',
        'progress' => '已清点',
        'variances' => '差异',
        'opened_by' => '发起人',
        'posted_by' => '过账人',
        'posted_at' => '过账时间',
        'created_at' => '开始时间',
    ],

    'status' => [
        'draft' => '进行中',
        'posted' => '已过账',
        'cancelled' => '已取消',
    ],

    'action' => [
        'new' => '新建盘点',
        'view' => '打开盘点单',
        'post' => '过账',
        'cancel' => '取消盘点',
        'delete' => '删除',
        'add_item' => '添加货架上找到的项目',
    ],

    'dialog' => [
        'create' => [
            'title' => '新建盘点',
            'description' => '选择要清点的仓库。它现有的每个项目都会自动列出，找到别的东西也可以自己添加。',
            'submit' => '开始清点',
            'submitting' => '正在开始…',
        ],
        'post' => [
            'title' => '要为这次盘点过账吗？',
            'description' => '现有库存会变成你清点到的数量，差异则写入流水账。:total 行中的 :counted 行会被应用。此操作无法撤销。',
            'submit' => '过账',
            'submitting' => '正在过账…',
        ],
        'cancel' => [
            'title' => '要取消这次盘点吗？',
            'description' => '清点结果会被丢弃，库存保持原样。已取消的盘点无法重新打开。',
            'submit' => '取消盘点',
            'submitting' => '正在取消…',
        ],
        'delete' => [
            'title' => '要删除这次盘点吗？',
            'description' => '盘点单会从列表中移除。只有尚未过账的盘点才能删除。',
            'submit' => '删除',
            'submitting' => '正在删除…',
        ],
        'add_item' => [
            'title' => '把项目加入这次盘点',
            'description' => '货架上有、但本仓库还没有记录的东西。它的账面数从零开始。',
            'submit' => '加入盘点',
            'submitting' => '正在添加…',
        ],
    ],

    'field' => [
        'warehouse' => '仓库',
        'notes' => '备注',
        'notes_placeholder' => '做这次盘点的原因',
        'item' => '项目',
        'item_placeholder' => '搜索产品或原材料',
        'item_search' => '按名称或 SKU 搜索',
        'item_empty' => '没有匹配项。',
        'warehouse_placeholder' => '选择仓库',
        'warehouse_search' => '搜索仓库…',
        'warehouse_empty' => '没有匹配的仓库。',
        'item_group_product' => '产品',
        'item_group_raw_material' => '原材料',
    ],

    'sheet' => [
        'heading' => '盘点单',
        'item' => '项目',
        'expected' => '账面数',
        'counted' => '实点数',
        'difference' => '差异',
        'applied' => '实际调整',
        'not_counted' => '未清点',
        'saved' => '已保存',
        'saving' => '正在保存…',
        'empty' => '本仓库目前是空的。在货架上找到什么，就添加什么。',
    ],

    'summary' => [
        'lines' => '盘点单上的项目',
        'counted' => '目前已清点',
        'variances' => '发现的差异',
        'notes' => '备注',
        'opened_by' => '发起人',
        'posted_by' => '过账人',
    ],

    'toast' => [
        'opened' => '盘点已开始。',
        'posted' => '盘点已过账，库存已更新。',
        'cancelled' => '盘点已取消。',
        'deleted' => '盘点已删除。',
        'item_added' => '项目已加入盘点。',
    ],

    'error' => [
        'insufficient' => '仅有 :available，而本次要调整 :requested。',
        'not_draft' => '这次盘点已经过账或已取消。',
        'duplicate_item' => '该项目已经在这张盘点单上了。',
        'posted_locked' => '已过账的盘点无法删除。',
    ],

    // 盘点过账时，会把这句话盖在它生成的每一行流水账上，这样一笔变动就能追回到
    // 造成它的那张盘点单。
    'movement' => [
        'notes' => '盘点单 #:id',
    ],

    'empty' => [
        'title' => '还没有盘点记录',
        'description' => '发起一次盘点，看看仓库里实际存着什么。',
    ],

    'filter' => [
        'status' => '状态',
        'all_statuses' => '任何状态',
    ],
];
