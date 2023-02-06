<?php

namespace GW\Route;

use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

class Router
{

    public static function dispatcher()
    {
        return simpleDispatcher(function (RouteCollector $r) {
            // 星纵下发
            $r->addRoute('POST', '/downlink', 'GW\Controller\NodeSetXzController@XzDownlink');
        });

    }
}