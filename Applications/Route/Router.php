<?php

namespace GW\Route;

use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

class Router
{

    public static function dispatcher()
    {
        return simpleDispatcher(function (RouteCollector $r) {
            /* $r->addRoute('POST', '/', 'GW\Controller\xxController@xx');*/
        });

    }
}