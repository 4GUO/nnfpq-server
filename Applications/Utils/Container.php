<?php

namespace GW\Utils;

class Container
{
    protected static $classes;

    public static function make($namespace = '')
    {
        if (isset(self::$classes[$namespace])) {
            return self::$classes[$namespace];
        }
        try {
            $class                     = new $namespace;
            self::$classes[$namespace] = $class;
            return $class;
        } catch (\Exception $e) {
            return new \Exception(sprintf("%s : %s", $namespace, $e->getMessage()));
        }
    }

    public static function loadClass($class)
    {
        [$namespacePrefox] = explode('\\', $class);

        if ($namespacePrefox == "GW") {
            $realPath = str_replace("GW", '.' . DIRECTORY_SEPARATOR . 'Applications', $class);
            require_once $realPath . ".php";
        }
    }

}