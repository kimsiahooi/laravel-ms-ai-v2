<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The locales this app ships. One list, read by the locale middleware, the language
 * switcher and the `lang:export` command, so a new locale is added in exactly one
 * place.
 *
 * The keys are Laravel's directory names (`zh_Hans`, with an underscore). `html()`
 * returns the BCP-47 form (`zh-Hans`) that the <html lang> attribute needs — the two
 * are not interchangeable and mixing them up silently breaks screen readers and
 * browser translation prompts.
 */
final class Locales
{
    public const BASE = 'en';

    /** @var array<string, string> locale => the language's own name for itself */
    public const SUPPORTED = [
        'en' => 'English',
        'ms' => 'Bahasa Malaysia',
        'zh_Hans' => '简体中文',
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::SUPPORTED);
    }

    public static function supports(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::SUPPORTED);
    }

    /** `zh_Hans` → `zh-Hans`, for the <html lang> attribute. */
    public static function html(string $locale): string
    {
        return str_replace('_', '-', $locale);
    }

    /**
     * Every locale with its own name, for a language picker.
     *
     * @return list<array{code: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (string $code): array => ['code' => $code, 'label' => self::SUPPORTED[$code]],
            self::codes(),
        );
    }
}
