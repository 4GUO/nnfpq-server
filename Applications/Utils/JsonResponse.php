<?php

namespace GW\Utils;

use Workerman\Protocols\Http\Response as ResObj;

class JsonResponse
{
    public static function succ($data = [], $msg = "", $type = "normal")
    {
        $json = Response::base(1, $msg, $type, $data);
        return self::jsonToResponse($json);
    }

    public static function err($data = [], $msg = "", $type = "normal")
    {

        $json = Response::base(0, $msg, $type, $data);
        return self::jsonToResponse($json);
    }

    private static function jsonToResponse($json)
    {
        $response = new ResObj(200, [
            'Content-Type' => 'application/json;charset=utf-8',
        ], $json);
        return $response;
    }
}