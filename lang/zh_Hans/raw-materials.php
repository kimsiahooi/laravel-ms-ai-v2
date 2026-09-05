<?php

declare(strict_types=1);

/*
| 原材料 —— 这个工作区采购进来、用于生产的东西。与其他列表共用的界面文案
| （搜索、分页、取消、编辑、删除）放在 common.php。
*/

return [
    'title' => '原材料',
    'subtitle' => '你采购进来、用于做出成品的东西。',

    'search_placeholder' => '搜索名称、SKU 或条码…',

    'column' => [
        'name' => '材料',
        'sku' => 'SKU',
        'unit' => '单位',
        'created' => '添加时间',
        'creator' => '添加者',
    ],

    'empty' => [
        'title' => '暂无原材料',
        'description' => '先添加第一个，之后就可以收货、盘点，并用它组成产品。',
    ],

    'no_match' => [
        'title' => '没有匹配的材料',
        'description' => '没有内容与“:term”匹配。',
    ],

    'create' => [
        'trigger' => '新建材料',
        'title' => '新建原材料',
        'description' => '一个用来指代它的编号，以及你清点它时使用的单位。',
        'submit' => '创建材料',
        'submitting' => '正在创建…',
    ],

    'edit' => [
        'title' => '编辑原材料',
        'description' => '修改会同步到所有引用该材料的位置。',
        'submit' => '保存更改',
        'submitting' => '正在保存…',
    ],

    'field' => [
        'name' => '名称',
        'name_placeholder' => '例如：12mm 钢筋',
        'sku' => 'SKU',
        'sku_placeholder' => '例如：RM-001',
        'sku_hint' => '你自己给这个材料定的编号。它会出现在采购单和库存清单上，任意两个材料不能重复。',
        'barcode' => '条码',
        'barcode_placeholder' => '扫描或输入条码',
        'barcode_hint' => '在盘点、出入库和调拨时扫描它来找到这个材料。',
        'unit' => '单位',
        'unit_placeholder' => '选择单位',
        'unit_hint' => '你清点它时用的单位。该材料记录的每一个数量都是这个单位的数量，所以请选择你采购和领用时使用的那个。',
    ],

    'confirm' => [
        'blocked_title' => '无法删除:name',
        'blocked_description' => '{1} 它被 :products 的物料清单使用。请先将它从该清单中移除，然后才能删除此原材料。|[2,*] 它被 :count 个产品的物料清单使用（:products）。请先将它从这些清单中移除，然后才能删除此原材料。',
        'delete_title' => '删除 :name？',
        'delete_description' => '已经记录在该材料上的库存会保留其历史 —— 只是无法再为新的单据选择它。',
        'delete_submit' => '删除材料',
        'delete_submitting' => '正在删除…',
    ],

    'toast' => [
        'in_use' => '{1} 无法删除:name — 它被 :products 的物料清单使用。|[2,*] 无法删除:name — 它被 :count 个产品的物料清单使用（:products）。',
        'created' => '已创建 :name。',
        'updated' => '已更新 :name。',
        'deleted' => '已删除 :name。',
    ],
];
