<?php

declare(strict_types=1);

/*
| 采购退货与销售退货共用的措辞。每个措辞的取舍见 `en` 文件。
*/

return [
    // App\Enums\ReturnStatus.
    'status' => [
        'pending' => '待处理',
        'completed' => '已完成',
        'cancelled' => '已取消',
    ],

    // App\Enums\ReturnReason.
    'reason' => [
        'damaged' => '货品损坏',
        'wrong_item' => '发错货品',
        'quality' => '不符合规格',
        'surplus' => '数量多余',
        'other' => '其他',
    ],
];
