<?php

namespace GW\Utils;

class Debug
{
    public static function info(...$params)
    {
        print_r($params);
        print_r("\r\n");
    }


    public static function log($content, $sign = '', $name = '')
    {
        // win 环境不写入
        if (strpos(strtolower(PHP_OS), 'win') === 0) {
            $dirArr = ['.', 'Applications', 'Storage', 'Log'];
        } else {
            $dirArr = [APP_ROOT, 'Storage', 'Log'];
        }
        //检测目录是否已经生成
        $date = date('Y-m-d');

        if ($name) {
            $dirArr[] = $name;
        }
        $path     = implode(DIRECTORY_SEPARATOR, $dirArr);
        $fileName = $date . '.txt';
        // 判断文件是否存在 否则创建
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        $content = var_export($content, 1);
        $msg     = $sign ? sprintf("【%s】", $sign) : '【.】'; // 增加分类
        $msg     .= " " . date('Y-md-d H:i:s');
        $msg     .= "\r\n";
        $msg     .= $content . "\r\n";
        $msg     .= ".................................................\r\n";
        file_put_contents($path . DIRECTORY_SEPARATOR . $fileName, $msg, FILE_APPEND);

    }
}