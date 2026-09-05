<?php

declare(strict_types=1);

/**
 * 为什么这里只包含框架文件的一部分，请参阅 lang/en/validation.php。
 */

return [

    'array' => ':attribute必须是数组。',
    'boolean' => ':attribute必须是真或假。',
    'decimal' => ':attribute必须有 :decimal 位小数。',
    'distinct' => ':attribute有重复的值。',
    'email' => ':attribute必须是有效的电子邮件地址。',
    'enum' => '所选的:attribute无效。',
    'exists' => '所选的:attribute无效。',
    'gt' => [
        'numeric' => ':attribute必须大于 :value。',
    ],
    'image' => ':attribute必须是图片。',
    'max' => [
        'array' => ':attribute最多只能有 :max 项。',
        'file' => ':attribute不能大于 :max KB。',
        'numeric' => ':attribute不能大于 :max。',
        'string' => ':attribute不能大于 :max 个字符。',
    ],
    'mimes' => ':attribute必须是以下类型的文件：:values。',
    'min' => [
        'string' => ':attribute至少需要 :min 个字符。',
    ],
    'numeric' => ':attribute必须是数字。',
    'regex' => ':attribute格式不正确。',
    'required' => ':attribute不能为空。',
    'string' => ':attribute必须是字符串。',
    'unique' => ':attribute已被使用。',

    'attributes' => [
        'address' => '地址',
        'admin_email' => '管理员邮箱',
        'admin_name' => '管理员姓名',
        'admin_password' => '管理员密码',
        'barcode' => '条码',
        'category_id' => '分类',
        'city' => '城市',
        'contact_person' => '联系人',
        'country_code' => '国家',
        'description' => '描述',
        'email' => '电子邮箱',
        'image' => '图片',
        'items' => '物料',
        'items.*.quantity' => '数量',
        'items.*.raw_material_id' => '原材料',
        'name' => '名称',
        'notes' => '备注',
        'phone' => '电话',
        'postcode' => '邮编',
        'registration_no' => '注册号',
        'remove_image' => '移除图片',
        'sku' => 'SKU',
        'slug' => '地址',
        'sst_registration_no' => 'SST/GST 注册号',
        'state_code' => '州代码',
        'supplier_id' => '供应商',
        'tax_id' => '税号',
        'tin' => '税务识别号',
        'unit' => '单位',
    ],

];
