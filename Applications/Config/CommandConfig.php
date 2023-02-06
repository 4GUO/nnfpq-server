<?php

namespace GW\Config;

use GW\Command\TestCommand;


class CommandConfig
{
    public static function map()
    {
        return [
            'test' => TestCommand::class,
        ];
    }
}