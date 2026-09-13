<?php

declare(strict_types=1);

/*
| 角色——一组人能够访问哪些内容，以及决定这件事的界面。每个措辞的取舍见 `en` 文件。
|
| 角色本身的名称从不翻译：“Administrator” 来自播种器，而其余每个角色都是这个工作区自己输入的词。
*/

return [
    'title' => '角色',
    'subtitle' => '角色决定持有者能够进入哪些界面。请给出仍能让对方完成工作的最小范围。',

    'action' => [
        'new' => '新建角色',
    ],

    'card' => [
        'built_in' => '内置',
        'locked' => '始终拥有全部权限。它无法被编辑或删除，因此工作区不会把自己锁在门外。',
        'permissions' => '{0}不授予任何权限|{1}1 项权限|[2,*]:count 项权限',
        'holders' => '{0}暂无人持有|{1}1 人持有|[2,*]:count 人持有',
    ],

    'create' => [
        'title' => '新建角色',
        'crumb' => '新建角色',
        'subtitle' => '按职责命名，然后勾选这份职责需要访问的内容。',
        'submit' => '创建角色',
        'submitting' => '创建中…',
    ],

    'edit' => [
        'title' => '编辑 :name',
        'crumb' => '编辑',
        'subtitle' => '更改会在每个人下次加载页面时生效。',
        'holders' => '{0}暂时没有人持有此角色。|{1}有 1 人持有此角色。|[2,*]有 :count 人持有此角色。',
        'submit' => '保存更改',
        'submitting' => '保存中…',
    ],

    'field' => [
        'name' => '角色名称',
        'name_placeholder' => '例如：库存文员',
        'name_hint' => '写职责，而不是人名。用户界面在分配角色时显示的就是这个名称。',
    ],

    'matrix' => [
        'heading' => '此角色可访问的范围',
        'description' => '每一组对应一个界面。“查看”决定它是否出现在对方的侧边栏里——没有它，同组的其余权限就无从施展。',
        'select_all' => '全选',
        'clear_all' => '全部清除',
        'group_all' => '选中 :screen 的全部权限',
        'group_count' => ':selected / :total',
        'selected' => '已选 :selected / :total',
    ],

    'dialog' => [
        'delete' => [
            'title' => '删除 :name？',
            'description' => '该角色将被永久移除。目前无人持有，因此不会有人失去访问权——但这里勾选过的内容需要在新角色上重新勾选。',
            'submit' => '删除角色',
            'submitting' => '删除中…',
        ],
    ],

    'toast' => [
        'created' => '已创建 :name。',
        'updated' => '已更新 :name。',
        'deleted' => '已删除 :name。',
    ],

    'error' => [
        'in_use' => '{1}:name 仍有 1 人持有（含已停用的同事）。请先把他们改到其他角色。|[2,*]:name 仍有 :count 人持有（含已停用的同事）。请先把他们改到其他角色。',
    ],

    'validation' => [
        'permissions' => '请至少勾选一项此角色可以访问的内容。',
    ],
];
