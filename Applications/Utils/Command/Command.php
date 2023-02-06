<?php

namespace GW\Utils\Command;
/**
 * 命令基类
 */
interface Command
{

    // 用来干什么
    public function desc();

    // 代码逻辑
    public function exec(): void;


}