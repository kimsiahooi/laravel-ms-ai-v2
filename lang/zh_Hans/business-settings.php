<?php

declare(strict_types=1);

return [
    'title' => '业务设置',
    'head' => '业务设置',
    'subtitle' => '此工作区以什么交易，以及单据如何命名。这些改动会影响这里的每一个人。',

    'money' => [
        'title' => '金额',
        'description' => '记账所用的货币、下单可用的货币，以及订单所收的税。',
    ],

    'documents' => [
        'title' => '单据编号',
        'description' => '采购订单、销售订单及其退货如何编号。',
    ],

    'field' => [
        'base_currency' => '本位币',
        'base_currency_placeholder' => '选择货币',
        'base_currency_hint' => '记账所用的货币。订单仍可用其他货币开立，并自带汇率。',

        'currencies' => '订单可用的货币',
        'currencies_hint' => '本位币始终可用，因此无法在此取消勾选。',

        'tax_rate' => '税率',
        'tax_rate_placeholder' => '例如 6',
        'tax_rate_hint' => '百分比，介于 0 与 100 之间。新订单以此为起点；已开立的订单沿用当时的税率。',

        'tax_label' => '税种名称',
        'tax_label_placeholder' => '例如 SST',
        'tax_label_hint' => '单据上该税的名称。存储而非翻译——它是法律术语，不随阅读者的语言变化。',

        'purchase_order_prefix' => '采购订单前缀',
        'purchase_return_prefix' => '采购退货前缀',
        'sales_order_prefix' => '销售订单前缀',
        'sales_return_prefix' => '销售退货前缀',
        'prefix_placeholder' => '例如 PO',
        'prefix_hint' => '仅限字母、数字和连字符。前缀为“PO”的采购订单读作 PO-2026-0001。',

        'number_reset' => '重新开始编号',
        'number_reset_placeholder' => '选择编号何时重新开始',
        'number_reset_hint' => '每年重新开始会把年份写进编号并从一重新计数。永不重置则一直往下计数，这通常是从其他系统迁移过来的企业所需要的。',

        'financial_year_start_month' => '财政年度开始于',
        'financial_year_start_month_placeholder' => '选择月份',
        'financial_year_start_month_hint' => '仅在编号每年重新开始时使用。年度以其开始的月份标注，因此 2025 年 4 月至 2026 年 3 月全程编号为 2025。',
    ],

    'number_reset' => [
        'yearly' => '每个财政年度',
        'never' => '永不',
    ],

    'currency' => [
        'myr' => 'MYR — 马来西亚令吉',
        'sgd' => 'SGD — 新加坡元',
        'usd' => 'USD — 美元',
        'eur' => 'EUR — 欧元',
        'cny' => 'CNY — 人民币',
    ],

    'month' => [
        'january' => '一月',
        'february' => '二月',
        'march' => '三月',
        'april' => '四月',
        'may' => '五月',
        'june' => '六月',
        'july' => '七月',
        'august' => '八月',
        'september' => '九月',
        'october' => '十月',
        'november' => '十一月',
        'december' => '十二月',
    ],

    'validation' => [
        'prefix' => '前缀只能使用字母、数字和连字符。',
    ],

    'action' => [
        'save' => '保存设置',
        'saving' => '保存中…',
    ],

    'toast' => [
        'saved' => '业务设置已保存。',
    ],
];
