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
