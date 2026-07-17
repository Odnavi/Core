<?php

namespace Odnavi\Core;

use Odnavi\Core\Trait\InstanceTrait;

final class Profiler
{
    use InstanceTrait;

    private array $timers = [];

    public static function startTimer(string $name, array $params = []): void
    {
        $instance = self::getInstance();
        $instance->addTimer([
            'name'   => $name,
            'time'   => null,
            'start'  => microtime(true),
            'params' => $params,
            'timers' => [],
        ], $instance->timers);
    }

    public static function stopTimer(): void
    {
        $instance = self::getInstance();
        $instance->clearTimer($instance->timers);
    }

    private function addTimer(array $timer, array &$timers): void
    {
        $lastTimer = array_pop($timers);

        if (!$lastTimer) {
            $timers[] = $timer;
            return;
        }

        if (!is_null($lastTimer['time'])) {
            $timers[] = $lastTimer;
            $timers[] = $timer;
            return;
        }

        self::addTimer($timer, $lastTimer['timers']);
        $timers[] = $lastTimer;
    }

    private function clearTimer(array &$timers): bool
    {
        $lastTimer = array_pop($timers);
        if (!$lastTimer) {
            return false;
        }

        if ($this->clearTimer($lastTimer['timers'])) {
            $timers[] = $lastTimer;
            return true;
        }

        if (!is_null($lastTimer['time'])) {
            $timers[] = $lastTimer;
            return false;
        }

        $lastTimer['time'] = round(microtime(true) - $lastTimer['start'], 9);

        $timers[] = $lastTimer;
        return true;
    }

    private function getTimers(array $timers): array
    {
        $result = [];
        foreach ($timers as $timer) {
            $subTimers = $this->getTimers($timer['timers']);

            $i    = 0;
            $name = $timer['name'];
            while (!empty($result[$name])) {
                $i++;
                $name = "$timer[name] ($i)";
            }

            if (!$timer['params'] && !$subTimers) {
                $result[$name] = $timer['time'];
                continue;
            }

            $result[$name] = ['time' => $timer['time']];
            $timer['params'] && $result[$name]['params'] = $timer['params'];
            $subTimers && $result[$name]['timers'] = $subTimers;
        }

        return $result;
    }

    public static function gerProfiling(): array
    {
        $instance = self::getInstance();
        return $instance->getTimers($instance->timers);
    }
}