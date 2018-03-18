<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018/3/18
 * Time: 上午2:35
 */

namespace Swoft\WebSocket\Server\Example;

use Swoft\WebSocket\Server\Bean\Annotation\WebSocket;
use Swoft\WebSocket\Server\WsController;

/**
 * Class EchoController
 * @package Swoft\WebSocket\Server\Example
 * @WebSocket("/echo")
 */
class EchoController extends WsController
{
    public function beforeHandshake()
    {

    }

    public function onOpen()
    {

    }

    public function onMessage()
    {

    }
}
