<?php

namespace GW\Utils;

class DataFormatter
{
    public static function redisEncodeData($data)
    {
        return serialize($data);
    }

    public static function redisDecodeData($data)
    {
        return unserialize($data);
    }


}