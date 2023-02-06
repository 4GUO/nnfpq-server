<?php

use GW\Utils\Container;
use GW\Config\CommandConfig;
use GW\Utils\Command\Console;

ini_set('display_errors', 'on');

require_once __DIR__ . '/vendor/autoload.php';

define('APP_ROOT', __DIR__ . DIRECTORY_SEPARATOR . 'Applications');

spl_autoload_register([Container::class, "loadClass"]);

$cmdSet      = CommandConfig::map();
$commandName = $argv[1] ?? '';

// 介绍所有脚本
if (!$commandName || !isset($cmdSet[$commandName])) {
    return introduce($cmdSet);
}

$class = $cmdSet[$commandName];
execute($argv, $class);


function execute($argv, $class)
{
    try {
        // 构造参数
        $params = $argv;
        $params = array_values(array_slice($params, 2));

        if (isset($params[0]) && $params[0] == '-h') {
            return introduceOne($class);
        }
        (new $class)->exec();

    } catch (Exception $e) {
        Console::log($e->getMessage(), 'LIGHT_RED');
    }


}


function introduceOne($class)
{
    $desc = (new $class)->desc();
    if (is_array($desc)) {
        foreach ($desc as $descItem) {
            Console::log(str_repeat(' ', 4) . $descItem);
        }
    } else {
        Console::log($desc);
    }
}


function introduce($cmdSet)
{

    try {
        foreach ($cmdSet as $key => $commandClass) {
            Console::log('【cmd 名称】' . $key);
            introduceOne($commandClass);
            // 让每个脚本之间的内容换行
            Console::log("");
        }
    } catch (Throwable $t) {
        Console::log($t->getMessage(), 'LIGHT_RED');
    }

    return true;
}

