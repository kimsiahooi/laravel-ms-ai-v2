<?php

declare(strict_types=1);

/*
| 关于本模块的说明，请参阅 lang/en/locations.php。
*/

return [
    'title' => '站点',
    'subtitle' => '你经营的场所。每个站点下设仓库，库存实际存放在仓库中。',

    'search_placeholder' => '搜索名称、代码或地址…',

    'column' => [
        'name' => '站点',
        'code' => '代码',
        'address' => '地址',
        'created' => '添加时间',
        'creator' => '添加人',
    ],

    'empty' => [
        'title' => '还没有站点',
        'description' => '站点可以是分店、门市或工厂。添加第一个之后，就可以为它设置仓库了。',
    ],

    'no_match' => [
        'title' => '没有匹配的站点',
        'description' => '没有内容匹配“:term”。',
    ],

    'create' => [
        'trigger' => '新建站点',
        'title' => '新建站点',
        'description' => '你经营的场所。只有名称是必填的。',
        'submit' => '创建站点',
        'submitting' => '创建中…',
    ],

    'edit' => [
        'title' => '编辑站点',
        'description' => '修改会应用到所有使用该站点的地方。',
        'submit' => '保存更改',
        'submitting' => '保存中…',
    ],

    'field' => [
        'name' => '名称',
        'name_placeholder' => '例如：槟城分店',
        'code' => '代码',
        'code_placeholder' => '例如：PEN',
        'code_hint' => '你自己为该站点设定的简短代码。它会出现在调拨单和报表上，且不能与其他站点重复。',
        'address' => '地址',
        'address_placeholder' => '街道、城市、邮编',
    ],

    'confirm' => [
        'blocked_title' => '无法删除:name',
        'blocked_description' => '{1} 该站点上仍有仓库：:warehouses。请先迁移或删除它，然后才能删除此站点。|[2,*] 该站点上仍有 :count 个仓库（:warehouses）。请先迁移或删除它们，然后才能删除此站点。',
        'blocked_link' => '{1} 查看该仓库|[2,*] 查看全部 :count 个仓库',
        'delete_title' => '删除:name？',
        'delete_description' => '已记录在该站点的出入库仍保留其历史——你只是无法再为新的单据选择它。',
        'delete_submit' => '删除站点',
        'delete_submitting' => '删除中…',
    ],

    'toast' => [
        'in_use' => '{1} 无法删除:name——该站点上仍有仓库：:warehouses。|[2,*] 无法删除:name——该站点上仍有 :count 个仓库（:warehouses）。',
        'created' => ':name已创建。',
        'updated' => ':name已更新。',
        'deleted' => ':name已删除。',
    ],
];
