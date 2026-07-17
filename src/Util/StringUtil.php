<?php

namespace Odnavi\Core\Util;

/** Преобразования регистра имён (snake_case ↔ camelCase), используемые ORM. */
final class StringUtil
{
    public const FORMAT_SNAKE_CASE = 'snake_case';
    public const FORMAT_CAMEL_CASE = 'camelCase';

    /** Преобразует строку в camelCase (или PascalCase при $ucfirst). */
    public static function toCamelCase(string $key, bool $ucfirst = false): string
    {
        $key = ucwords(str_replace(['-', '_'], ' ', $key));
        $key = str_replace(' ', '', $key);
        return $ucfirst ? ucfirst($key) : lcfirst($key);
    }

    /** Преобразует строку в snake_case. */
    public static function toSnakeCase(string $key): string
    {
        return ltrim(strtolower(
            preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $key)
        ), '_');
    }

    /** Приводит имя к указанному формату (по умолчанию camelCase). */
    public static function formatCase(string $value, string $case = self::FORMAT_CAMEL_CASE): string
    {
        return match ($case) {
            self::FORMAT_CAMEL_CASE => self::toCamelCase($value),
            self::FORMAT_SNAKE_CASE => self::toSnakeCase($value),
            default                 => $value,
        };
    }
}