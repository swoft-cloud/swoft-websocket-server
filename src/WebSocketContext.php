<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018/3/21
 * Time: 上午11:06
 */

namespace Swoft\WebSocket\Server;

/**
 * Class WebSocketContext
 * @package Swoft\WebSocket\Server
 */
class WebSocketContext
{
    private static $contexts = [];

    /**
     * @return array
     */
    public static function getContexts(): array
    {
        return self::$contexts;
    }
}
