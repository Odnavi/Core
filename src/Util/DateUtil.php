<?php

namespace Odnavi\Core\Util;

use Odnavi\Core\Trait\InstanceTrait;
use DateTimeImmutable;
use DateTimeZone;

class DateUtil
{
    use InstanceTrait;

    /** @var DateTimeZone[] Тайм зоны */
    private array $timezones = [];

    /**
     * @param string $timezone
     *
     * @return DateTimeZone
     */
    public static function getTimeZone(string $timezone): DateTimeZone
    {
        $instance = self::getInstance();
        empty($instance->timezones[$timezone]) && $instance->timezones[$timezone] = new DateTimeZone($timezone);

        return $instance->timezones[$timezone];
    }

    /**
     * @param int $timestamp
     *
     * @return DateTimeImmutable
     */
    public static function getDate(int $timestamp = CURRENT_TIME): DateTimeImmutable
    {
       return (new DateTimeImmutable('@' . $timestamp))
           ->setTimezone(self::getTimeZone('UTC'));
    }
}