<?php

declare(strict_types=1);

/**
 * 为什么这里只包含框架文件的一部分，请参阅 lang/en/validation.php。
 */

return [

    'email' => ':attribute必须是有效的电子邮件地址。',
    'enum' => '所选的:attribute无效。',
    'max' => [
        'string' => ':attribute不能大于 :max 个字符。',
    ],
    'min' => [
        'string' => ':attribute至少需要 :min 个字符。',
    ],
    'regex' => ':attribute格式不正确。',
    'required' => ':attribute不能为空。',
    'string' => ':attribute必须是字符串。',
    'unique' => ':attribute已被使用。',

    'attributes' => [
        'address' => '地址',
        'admin_email' => '管理员邮箱',
        'admin_name' => '管理员姓名',
        'admin_password' => '管理员密码',
        'city' => '城市',
        'contact_person' => '联系人',
        'country_code' => '国家',
        'description' => '描述',
        'email' => '电子邮箱',
        'name' => '名称',
        'notes' => '备注',
        'phone' => '电话',
        'postcode' => '邮编',
        'registration_no' => '注册号',
        'slug' => '地址',
        'sst_registration_no' => 'SST/GST 注册号',
        'state_code' => '州代码',
        'tax_id' => '税号',
        'tin' => '税务识别号',
    ],

];
