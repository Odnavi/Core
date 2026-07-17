<?php

namespace Odnavi\Core\Util;

use Odnavi\Orm\Service\QueryBuilder;

readonly class FilterUtil
{
    /**
     * @param int|int[] $value
     * @param QueryBuilder $query
     * @param string $alias
     */
    public static function applyDateFilter(int|array $value, QueryBuilder $query, string $column): void
    {
        $values = is_array($value) ? array_values($value) : [$value];

        $from = $values[0] ?? null;
        $to   = $values[1] ?? null;

        // Обработка from
        if ($from !== null && $from !== '') {
            $literalUtc = DateUtil::getDate($from)
                ->format('Y-m-d H:i:s');
            $query->addWhere("$column >= ?");
            $query->setArgument($literalUtc);
        }

        // Обработка to
        if ($to !== null && $to !== '') {
            $literalUtc = DateUtil::getDate($to)
                ->format('Y-m-d H:i:s');
            $query->addWhere("$column <= ?");
            $query->setArgument($literalUtc);
        }
    }
}