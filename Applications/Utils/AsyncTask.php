<?php

namespace GW\Utils;

use Workerman\Connection\AsyncTcpConnection;
use GW\Config\AsyncTaskConfig;

class AsyncTask
{
    public static function async($class, $method, array $param, $callback = '')
    {
        $taskConnection = new AsyncTcpConnection(AsyncTaskConfig::SOCKET_CLIENT_ADDR);

        $data = [$class, $method, $param];

        $taskConnection->send(json_encode($data, 320));

        $taskConnection->onMessage = function ($taskConnection, $taskResult) use ($callback) {
            // 获得结果后记得关闭异步连接
            $taskConnection->close();
            if (is_callable($callback)) {
                if (is_array($taskResult)) {
                    return $callback(...$taskResult);
                }
                return $callback($taskResult);
            }
        };
        // 执行异步连接
        $taskConnection->connect();

    }
}