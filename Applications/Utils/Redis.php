<?php

namespace GW\Utils;

use GW\Config\RedisConfig;

class Redis
{
    /** @var \Redis|null $instance */
    static $instance = null;


    /**
     * @return self
     * @throws \Exception
     */

    private function __construct()
    {

        try {
            self::$instance = new \Redis();
            self::$instance->pconnect(RedisConfig::HOST, RedisConfig::PORT, RedisConfig::TIMEOUT);
            self::$instance->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
            self::$instance->auth(RedisConfig::AUTH);


        } catch (\Error | \Exception $e) {
            throw new \Exception('redis connect error' . $e->getMessage());
        }

    }


    /**
     * @return \Redis
     */
    public static function go()
    {
        if (!self::$instance) {
            new self();
        } else {
            try {
                self::$instance->ping();
                $error = error_get_last();
                if (isset($error['message']) && $error['message'] != 'flag')
                    throw new \Exception('Redis server went away');
            } catch (\Exception $e) {
                // 断线重连
                new self();
            }
        }
        return (new static());

    }


    public function __call($method, $parameters)
    {
        return self::$instance->{$method}(...$parameters);
    }
}
