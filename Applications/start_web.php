<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use GW\Utils\JsonResponse;
use Workerman\Worker;
use Workerman\Protocols\Http\Request;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Response;
use GW\Service\EmqxXzService;
use GW\Route\Router;
use GW\Utils\Container;
use GW\Controller\BaseController;
use GW\Exception\WebServiceLogicException;

require_once __DIR__ . '/../vendor/autoload.php';

// WebServer
$web = new Worker("http://0.0.0.0:55151");
// WebServer进程数量
$web->count = 1;

define('WEBROOT', __DIR__ . DIRECTORY_SEPARATOR . 'Web');


$web->onMessage = function (TcpConnection $connection, Request $request) {

    // controller 自动注册
    spl_autoload_register([Container::class, "loadClass"], true);

    $uri    = $request->uri();
    $method = $request->method();
    if (false !== $pos = strpos($uri, '?')) {
        $uri = substr($uri, 0, $pos);
    }
    $uri        = rawurldecode($uri);
    $dispatcher = Router::dispatcher();
    $routeInfo  = $dispatcher->dispatch($method, $uri);


    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            // ... 404 Not Found
            $connection->send(new Response(404, [], '<h3>404 Not Found</h3>'));
            return;

        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            $allowedMethods = $routeInfo[1];
            // ... 405 Method Not Allowed
            $connection->send(new Response(405, [], '<h3>405 Method Not Allowed</h3>'));
            return;

        case FastRoute\Dispatcher::FOUND:

            // 异常处理
            $handler = $routeInfo[1];
            // ... call $handler with $vars
            // 暂时不支持资源路由
            [$namespace, $func] = explode("@", $handler);
            if (!$namespace) {
                $connection->send(new Response(502, [], '<h3>502 Namespace Not Exist </h3>'));
            }
            $func = $func ?: "index";
            /** @var BaseController $controller */
            $controller = Container::make($namespace);

            // TODO 接入错误 日志 访问日志 ...
            try {
                $controller->init($connection, $request, $routeInfo);
                try {

                    if (method_exists($controller, '__ready')) {
                        // 给控制器增加一个前置方法
                        $controller->__ready();
                    }

                    // 接管逻辑错误统一处理
                    $controller->{$func}();

                } catch (WebServiceLogicException $logicException) {
                    return $connection->send(JsonResponse::err([], $logicException->getMessage()));
                }

            } catch (Throwable $e) {
                return $connection->send(new Response(502, [], '<h3>' . $e->getMessage() . '</h3>'));
            }
            return;
    }

};


// 如果不是在根目录启动，则运行runAll方法
if (!defined('GLOBAL_START')) {
    Worker::runAll();
}

