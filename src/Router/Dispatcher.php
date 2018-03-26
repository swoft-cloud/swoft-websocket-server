<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018/3/18
 * Time: 上午2:20
 */

namespace Swoft\WebSocket\Server\Router;

use Swoft\Http\Message\Server\Request;
use Swoft\Http\Message\Server\Response;
use Swoft\WebSocket\Server\Controller\HandlerInterface;
use Swoft\WebSocket\Server\Exception\WsRouteException;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

/**
 * Class WsDispatcher
 * @package Swoft\WebSocket\Server
 */
class Dispatcher
{
    /**
     * dispatch handshake request
     * @param Request $request
     * @param Response $response
     * @return array eg. [status, response]
     * @throws \Swoft\WebSocket\Server\Exception\WsRouteException
     * @throws \InvalidArgumentException
     */
    public function handshake(Request $request, Response $response): array
    {
        $path = $request->getUri()->getPath();

        /** @var HandlerMapping $router */
        $router = \bean('wsRouter');
        $routeInfo = $router->getHandler($path);

        if ($routeInfo[0] !== HandlerMapping::FOUND) {
            throw new WsRouteException(sprintf(
                'The requested websocket route "%s" path is not exist! ',
                $path
            ));
        }

        $className = $routeInfo[1];
        /** @var HandlerInterface $handler */
        $handler = \bean($className);

        if (!\method_exists($handler, 'checkRequest')) {
            return [
                HandlerInterface::HANDSHAKE_OK,
                $response->withAddedHeader('swoft-ws-handshake', 'auto')
            ];
        }

        return $handler->checkRequest($request, $response);
    }

    /**
     * dispatch ws message
     * @param Server $server
     * @param Frame $frame
     */
    public function dispatch(Server $server, Frame $frame)
    {
        $fd = $frame->fd;
    }
}
