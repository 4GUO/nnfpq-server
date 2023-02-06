<?php

namespace GW\Utils\Command;

class Console {

    const COLORS = [
        'LIGHT_RED'   => "[1;31m",
        'LIGHT_GREEN' => "[1;32m",
        'YELLOW'      => "[1;33m",
        'LIGHT_BLUE'  => "[1;34m",
        'MAGENTA'     => "[1;35m",
        'LIGHT_CYAN'  => "[1;36m",
        'WHITE'       => "[1;37m",
        'NORMAL'      => "[0m",
        'BLACK'       => "[0;30m",
        'RED'         => "[0;31m",
        'GREEN'       => "[0;32m",
        'BROWN'       => "[0;33m",
        'BLUE'        => "[0;34m",
        'CYAN'        => "[0;36m",
        'BOLD'        => "[1m",
        'UNDERSCORE'  => "[4m",
        'REVERSE'     => "[7m",
    ];

    static function log($text, $color = "LIGHT_GREEN", $back = 0) {

        $out = self::COLORS["$color"] ?? '';
        if ($out == '') {
            $out = "[0m";
        }
        if ($back) {
            return chr(27) . "$out$text" . chr(27) . chr(27);
        } else {
            echo chr(27) . "$out$text" . chr(27) . chr(27);
        }//fi
        echo "\n";
    }

}