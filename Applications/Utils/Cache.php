<?php

namespace GW\Utils;

use http\Exception\BadMessageException;

class Cache
{
    const UN_SAVE_SIGN = 'nil';

    public static function key(...$param)
    {
        return 'gw:' . implode(':', $param);
    }

    public static function fetch($cacheKey, $callable, $cacheTime = 3600)
    {
        if (is_array($cacheKey)) {
            $cacheKey = self::key(...$cacheKey);
        }
        $redis = Redis::go();
        $data  = $cacheTime > 0 ? $redis->get($cacheKey) : false;
        if (!$data) {

            if ($redis->exists($cacheKey)) {
                return $data;
            }

            $data = $callable();
            if ($data === self::UN_SAVE_SIGN) {
                return $data;
            }

            $cacheTime > 0 && $redis->set($cacheKey, $data, $cacheTime);
        }
        if (is_numeric($data) && $data == -1) {
            return false;
        }
        return $data;
    }

    public static function release($cacheKey)
    {
        if (is_array($cacheKey)) {
            $cacheKey = self::key(...$cacheKey);
        }
        $redis = Redis::go();
        $redis->del($cacheKey);
    }

}