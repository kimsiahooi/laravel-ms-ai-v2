<?php

declare(strict_types=1);

/*
| 产品 —— 这个工作区卖的东西。与其他列表共用的界面文案（搜索、分页、取消、编辑、
| 删除）放在 common.php；单位名称放在 units.php。
*/

return [
    'title' => '产品',
    'subtitle' => '你卖的东西，以及它们如何归类。',

    'search_placeholder' => '搜索名称、SKU 或条码…',

    'column' => [
        'name' => '产品',
        'sku' => 'SKU',
        'category' => '分类',
        'supplier' => '供应商',
        'created' => '添加时间',
        'creator' => '添加者',
        'view_category' => '在分类中查看 :name',
        'view_supplier' => '在供应商中查看 :name',
    ],

    'empty' => [
        'title' => '暂无产品',
        'description' => '先添加第一个，之后就可以销售、盘点，并用原材料把它做出来。',
    ],

    'no_match' => [
        'title' => '没有匹配的产品',
        'description' => '没有内容与“:term”匹配。',
    ],

    'create' => [
        'trigger' => '新建产品',
        'title' => '新建产品',
        'description' => '一个用来指代它的编号，以及你销售时使用的单位。',
        'submit' => '创建产品',
        'submitting' => '正在创建…',
    ],

    'edit' => [
        'title' => '编辑产品',
        'description' => '修改会同步到所有引用该产品的位置。',
        'submit' => '保存更改',
        'submitting' => '正在保存…',
    ],

    'group' => [
        'identity' => '这是什么',
        'filing' => '如何归类',
        'filing_hint' => '两项都是选填 —— 它们用于在列表和报表中分组，可以之后再定。',
    ],

    'field' => [
        'name' => '名称',
        'name_placeholder' => '例如：折叠脚凳',
        'sku' => 'SKU',
        'sku_placeholder' => '例如：P-001',
        'sku_hint' => '你自己给这个产品定的编号。它会出现在销售单和发票上，任意两个产品不能重复。',
        'barcode' => '条码',
        'barcode_placeholder' => '扫描或输入条码',
        'barcode_hint' => '在盘点、出入库和调拨时扫描它来找到这个产品。',
        'unit' => '单位',
        'unit_placeholder' => '选择单位',
        'unit_hint' => '你销售时使用的单位。该产品记录的每一个数量都是这个单位的数量。',
        'description' => '描述',
        'description_placeholder' => '用一两句话说明这是什么',
        'image' => '图片',
        'image_hint' => 'JPG、PNG 或 WebP，最大 2 MB。它会显示在每个列表中产品名称的旁边。',
        'image_remove' => '移除图片',
        'image_alt' => '产品图片',
        'category' => '分类',
        'category_placeholder' => '选择分类',
        'category_search' => '搜索分类…',
        'category_empty' => '没有匹配的分类。',
        'supplier' => '供应商',
        'supplier_placeholder' => '选择供应商',
        'supplier_search' => '搜索供应商…',
        'supplier_empty' => '没有匹配的供应商。',
    ],

    'confirm' => [
        'delete_title' => '删除 :name？',
        'delete_description' => '已经为该产品下过的单据会保留记录 —— 只是无法再为新的单据选择它。',
        'delete_submit' => '删除产品',
        'delete_submitting' => '正在删除…',
    ],

    'toast' => [
        'created' => '已创建 :name。',
        'updated' => '已更新 :name。',
        'deleted' => '已删除 :name。',
    ],
];
