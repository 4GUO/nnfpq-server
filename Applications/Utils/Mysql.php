<?php

namespace GW\Utils;

use Workerman\MySQL\Connection;
use GW\Config\MysqlConfig;

class Mysql {

    /** @var Connection $instance */
    static $instance = null;

    /**
     * @return Connection
     * @throws \Exception
     */
    public static function go() {
        if (is_null(self::$instance)) {
            try {
                $mysql          = new Connection(MysqlConfig::HOST, MysqlConfig::PORT, MysqlConfig::USERNAME, MysqlConfig::PASSWORD, MysqlConfig::DATABASE, MysqlConfig::CHARSET);
                self::$instance = $mysql;

            } catch (\Error | \Exception $e) {
                throw new \Exception('mysql connect error' . $e->getMessage());
            }
        }
        return (new static());
    }

    public function __call($method, $parameters) {
        return self::$instance->{$method}(...$parameters);
    }
}