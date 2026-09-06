<?php

declare(strict_types=1);

/*
| 关于本模块的说明，请参阅 lang/en/warehouses.php。
*/

return [
    'title' => '仓库',
    'subtitle' => '存放库存的建筑。每个仓库都隶属于一个站点。',

    'search_placeholder' => '搜索名称、代码或地址…',

    'filter' => [
        'site' => '站点',
        'all_sites' => '任何站点',
        'sites_selected' => ':count 个站点中的任一个',
        'site_hint' => '[0,1] 勾选多项可扩大范围——仓库只需位于其中一个站点即可。|[2,*] 正在显示位于这 :count 个站点中任一个的仓库，而非同时位于全部站点的仓库。',
        'site_search' => '搜索站点…',
        'site_empty' => '没有匹配的站点。',
    ],

    'column' => [
        'name' => '仓库',
        'code' => '代码',
        'site' => '站点',
        'address' => '地址',
        'created' => '添加时间',
        'creator' => '添加人',
        'view_site' => '在站点中查看:name',
    ],

    'empty' => [
        'title' => '还没有仓库',
        'description' => '仓库是库存实际存放的地方。添加第一个之后，就可以把库存移入其中了。',
    ],

    'no_match' => [
        'title' => '没有匹配的仓库',
        'description' => '没有内容匹配“:term”。',
    ],

    'no_sites' => [
        'title' => '请先添加站点',
        'description' => '每个仓库都隶属于一个站点，目前还没有可以挂靠的站点。',
        'action' => '前往站点',
    ],

    'create' => [
        'trigger' => '新建仓库',
        'title' => '新建仓库',
        'description' => '存放库存的地方，以及它所在的站点。',
        'submit' => '创建仓库',
        'submitting' => '创建中…',
    ],

    'edit' => [
        'title' => '编辑仓库',
        'description' => '修改会应用到所有使用该仓库的地方。',
        'submit' => '保存更改',
        'submitting' => '保存中…',
    ],

    'field' => [
        'site' => '站点',
        'site_placeholder' => '选择站点',
        'site_search' => '搜索站点…',
        'site_empty' => '没有匹配的站点。',
        'site_hint' => '该建筑所在的位置。把仓库改挂到别的站点，其库存也会随之转移。',
        'name' => '名称',
        'name_placeholder' => '例如：主仓',
        'code' => '代码',
        'code_placeholder' => '例如：PEN-A',
        'code_hint' => '你自己为该仓库设定的简短代码。它会出现在调拨单和报表上，且不能与其他仓库重复。',
        'address' => '地址',
        'address_placeholder' => '街道、城市、邮编',
    ],

    /*
    | 详情页 —— 某个仓库现有什么，以及每个项目何时需要补货。页面上唯一可修改的
    | 就是补货水平。
    */
    'detail' => [
        'search_placeholder' => '搜索项目或 SKU…',
        'view_movements' => '查看出入库',

        'in_stock' => '有库存的项目',
        'in_stock_hint' => '此刻货架上还有的',
        'needs_reorder' => '需要补货',
        'needs_reorder_hint' => '已达到或低于这里设定的水平',

        'column' => [
            'item' => '项目',
            'sku' => 'SKU',
            'type' => '类型',
            'on_hand' => '现有',
            'level' => '补货水平',
        ],

        'badge' => '补货',

        'level_for' => ':name 的补货水平',
        'level_placeholder' => '未设定',
        'level_hint' => '在此输入该项目需要补货的水平。留空则不发出提醒。',

        'filter' => [
            'show' => '显示',
            'stocked' => '本仓库内',
            'attention' => '需要补货',
            'all' => '全部项目',
        ],

        'empty' => [
            'title' => '目录里还没有东西',
            'description' => '先添加产品或原材料 —— 之后才能把它移入这里，并说明何时需要补货。',
            'action' => '前往产品',
        ],

        'no_stock' => [
            'title' => '本仓库还是空的',
            'description' => '把库存移入后就会出现在这里。你也可以在东西到货前，先为它设定补货水平。',
            'action' => '记录出入库',
            'action_all' => '显示全部项目',
        ],

        'no_match' => [
            'title' => '没有匹配的项目',
            'description' => '本仓库里没有与“:term”匹配的内容。把「显示」切换为全部项目即可搜索整个目录。',
        ],
    ],

    'confirm' => [
        'delete_title' => '删除:name？',
        'delete_description' => '已通过该仓库记录的出入库仍保留其历史——你只是无法再为新的单据选择它。',
        'delete_submit' => '删除仓库',
        'delete_submitting' => '删除中…',
    ],

    'toast' => [
        'created' => ':name已创建。',
        'updated' => ':name已更新。',
        'deleted' => ':name已删除。',
    ],
];
