<?php

namespace GW\Utils;

/**
 * 设计的本身意思其实是上下文变量缓存，由于常驻进程,这里的存储单位为woker进程，当起了多个woker进程的时候，这里的数据是不互通的
 * 如果需要互通可以走官方的globalData组件
 */
class ApplicationContext
{
    static $context = [];

    public static function get($key)
    {
        $contextItem = self::$context[$key] ?? null;
        if (is_null($contextItem)) {
            return null;
        }
        if ($contextItem['ed']) {
            // 过期了不要返回 并且unset掉
            if ($contextItem['ed'] + $contextItem['ct'] < time()) {
                unset(self::$context[$key]);
                return null;
            }
        }
        return $contextItem['v'];
    }

    public static function fetch($key, \Closure $callable, $duration)
    {
        $value = self::get($key);
        if ($value) {
            return $value;
        }
        $callAble = is_callable($callable);

        if ($callAble) {
            $value = $callable();
        }
        
        self::set($key, $value, $duration);

        return $value;
    }

    public static function set($key, &$value, $duration = 0)
    {
        self::$context[$key] = [
            'v'  => $value,
            'ct' => time(), // createTIme
            'ed' => $duration
        ];
    }


    public static function remove($key)
    {
        $contextItem = self::$context[$key] ?? null;
        if (is_null($contextItem)) {
            return true;
        }
        unset(self::$context[$key]);
        print_r("remove ok ~");
        return true;
    }

    public static function key()
    {
        $args = func_get_args();
        return implode(":", $args);
    }
}