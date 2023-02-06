<?php

namespace GW\Utils;

use Workerman\Protocols\Http\Response as ResObj;

class Response
{
    public static function succ($data = [], $msg = "", $type = "normal")
    {
        return self::base(1, $msg, $type, $data);
    }

    public static function err($data = [], $msg = "", $type = "normal")
    {

        return self::base(0, $msg, $type, $data);
    }

    public static function base($succ = 1, $msg = "", $type = "normal", $data = [])
    {
        $res = [
            'code' => $succ ?: 0,
            'type' => $type,
            'data' => (object)$data,
            'msg'  => $msg
        ];
        return json_encode($res, 320);
    }
}