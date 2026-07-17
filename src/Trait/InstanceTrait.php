<?php

namespace Odnavi\Core\Trait;

Trait InstanceTrait
{
    private static $instance;

    public static function getInstance(...$args): self
    {
        return static::$instance ?: static::$instance = new self(...$args);
    }
}