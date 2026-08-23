<?php

declare(strict_types=1);

/*
| 计量单位，按 App\Enums\Unit 中的代码和 App\Enums\Dimension 中的量纲编排。
|
| 代码及其换算系数放在枚举里，因为那是服务端校验、库存引擎参与运算的数据。文字放在
| 这里，因为单位名称和其他面向用户的文案一样。
*/

return [
    'dimension' => [
        'mass' => '重量',
        'volume' => '容量',
        'length' => '长度',
        'count' => '计件',
    ],

    'symbol' => [
        'g' => '克',
        'kg' => '千克',
        't' => '吨',
        'ml' => '毫升',
        'l' => '升',
        'mm' => '毫米',
        'cm' => '厘米',
        'm' => '米',
        'pcs' => '个',
        'box' => '箱',
        'roll' => '卷',
        'sheet' => '张',
        'pair' => '对',
        'set' => '套',
    ],

    'name' => [
        'g' => '克 (g)',
        'kg' => '千克 (kg)',
        't' => '吨 (t)',
        'ml' => '毫升 (ml)',
        'l' => '升 (L)',
        'mm' => '毫米 (mm)',
        'cm' => '厘米 (cm)',
        'm' => '米 (m)',
        'pcs' => '个',
        'box' => '箱',
        'roll' => '卷',
        'sheet' => '张',
        'pair' => '对',
        'set' => '套',
    ],
];
