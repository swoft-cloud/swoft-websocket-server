<?php

namespace Swoft\WebSocket\Server;

use Swoft\App;
use Swoft\Core\Coroutine;
use Swoft\WebSocket\Server\Controller\HandlerInterface;
use Swoft\WebSocket\Server\Event\WsEvent;
use Swoft\WebSocket\Server\Router\Dispatcher;
use \Swoft\Http\Message\Server\Request as Psr7Request;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

/**
 * Trait HandshakeTrait - handle ws event in the swoole
 * @package Swoft\WebSocket\Server
 */
trait WebSocketEventTrait
{
    public $forTesting = true;

    /**
     * webSocket 建立连接后进行握手。WebSocket服务器已经内置了handshake，
     * 如果用户希望自己进行握手处理，可以设置 onHandShake 事件回调函数。
     * @param Request $request
     * @param Response $response
     * @return bool
     * @throws \Swoft\WebSocket\Server\Exception\WsRouteException
     * @throws \InvalidArgumentException
     */
    public function onHandShake(Request $request, Response $response): bool
    {
        if ($this->forTesting) {
            return $this->simpleHandshake($request, $response);
        }

        $fd = $request->fd;
        $secWSKey = $request->header['sec-websocket-key'];

        // sec-websocket-key 错误
        if (!WebSocket::isInvalidSecWSKey($secWSKey)) {
            $this->log("Handshake: shake hands failed with the #$fd. 'sec-websocket-key' is error!");

            return false;
        }

        // Initialize psr7 Request and Response and metadata
        $psr7Req = Psr7Request::loadFromSwooleRequest($request);
        $psr7Res = new \Swoft\Http\Message\Server\Response($response);
        $metaAry = $this->buildConnectionMetadata($fd, $request);

        // Initialize client information
        WebSocketContext::set($fd, $metaAry, $psr7Req);

        // init fd and coId mapping
        WebSocketContext::setFdToCoId($fd);

        $cid = Coroutine::tid();

        $this->log(
            "Handshake: Ready to shake hands with the #$fd client connection, co ID #$cid. request headers:",
            $psr7Req->getHeaders()
        );

        App::trigger(WsEvent::ON_HANDSHAKE, null, $request, $response, $fd);

        /** @var Dispatcher $dispatcher */
        $dispatcher = \bean('wsDispatcher');

        /** @var \Swoft\Http\Message\Server\Response $psr7Res */
        list($status, $psr7Res) = $dispatcher->handshake($psr7Req, $psr7Res);

        // handshake check is failed -- 拒绝连接，比如需要认证，限定路由，限定ip，限定domain等
        if (HandlerInterface::HANDSHAKE_OK !== $status) {
            $this->log("The #$fd client handshake check failed");
            $psr7Res->send();

            return false;
        }

        // setting response
        $psr7Res = $psr7Res
            ->withStatus(101)
            ->withHeaders(WebSocket::handshakeHeaders($secWSKey));

        if (isset($request->header['sec-websocket-protocol'])) {
            $psr7Res = $psr7Res->withHeader('Sec-WebSocket-Protocol', $request->header['sec-websocket-protocol']);
        }

        $this->log('Handshake: response headers:', $psr7Res->getHeaders());

        // Response handshake successfully
        $psr7Res->send();
        $this->log("Handshake: The #{$fd} client handshake successful! Meta:\n" . var_export($metaAry, 1));

        // Handshaking successful, Manually triggering the open event
        $this->server->defer(function () use ($psr7Req, $fd) {
            $this->onWsOpen($this->server, $psr7Req, $fd);
        });

        // delete coId to fd mapping
        WebSocketContext::delFdToCoId();

        return true;
    }

    /**
     * @param int $fd
     * @param Request $request
     * @return array
     */
    protected function buildConnectionMetadata(int $fd, Request $request): array
    {
        $info = $this->getClientInfo($fd);

        $this->log(
            "onHandShake: Client #{$fd} send handShake request, connection info: " .
            var_export($info, 1)
        );

        return [
            'id' => $fd,
            'ip' => $info['remote_ip'],
            'port' => $info['remote_port'],
            'path' => $request->server['request_uri'],
            'handshake' => false,
            'connectTime' => $info['connect_time'],
            'handshakeTime' => \microtime(true),
        ];
    }

    /**
     * @param Server $server
     * @param Psr7Request $request
     * @param int $fd
     * @throws \InvalidArgumentException
     */
    public function onWsOpen(Server $server, Psr7Request $request, int $fd)
    {
        App::trigger(WsEvent::ON_OPEN, null, $server, $request, $fd);

        $this->log("connection #$fd has been opened, co ID #" . Coroutine::tid());
    }

    /**
     * When you receive the message
     * @param  Server $server
     * @param  Frame $frame
     * @throws \InvalidArgumentException
     */
    public function onMessage(Server $server, Frame $frame)
    {
        $fd = $frame->fd;

        // init fd and coId mapping
        WebSocketContext::setFdToCoId($fd);

        App::trigger(WsEvent::ON_MESSAGE, null, $server, $frame);

        $this->log("received message: {$frame->data} from fd #{$fd}, co ID #" . Coroutine::tid());

        /** @var Dispatcher $dispatcher */
        $dispatcher = \bean('wsDispatcher');
        $dispatcher->dispatch($server, $frame);

        // delete coId to fd mapping
        WebSocketContext::delFdToCoId();
    }

    /**
     * on webSocket close
     * @param  Server $server
     * @param  int $fd
     * @throws \InvalidArgumentException
     */
    public function onClose(Server $server, $fd)
    {
        /*
        WEBSOCKET_STATUS_CONNECTION = 1，连接进入等待握手
        WEBSOCKET_STATUS_HANDSHAKE = 2，正在握手
        WEBSOCKET_STATUS_FRAME = 3，已握手成功等待浏览器发送数据帧
        */
        $fdInfo = $this->getClientInfo($fd);

        // is web socket request(websocket_status = 2)
        if ($fdInfo['websocket_status'] > 0) {
            // $meta = $this->delConnection($fd);
            //
            // if (!$meta) {
            //     $this->log("the #$fd connection info has lost");
            //
            //     return;
            // }
            WebSocketContext::del($fd);

            // call on close callback
            App::trigger(WsEvent::ON_CLOSE, null, $server, $fd);

            // $this->log(
            //     "onClose: The #$fd client has been closed! workerId: {$server->worker_id} ctxKey:{$meta->getKey()}, From {$meta['ip']}:{$meta['port']}. Count: {$this->count()}"
            // );
            $this->log("onClose: Client #{$fd} is closed. client-info:\n" . var_export($fdInfo, 1));
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    protected function simpleHandshake(Request $request, Response $response): bool
    {
        $this->log("received handshake request from fd #{$request->fd}, co ID #" . Coroutine::tid());

        // websocket握手连接算法验证
        $secWSKey = $request->header['sec-websocket-key'];

        if (WebSocket::isInvalidSecWSKey($secWSKey)) {
            $response->end();

            return false;
        }

        $headers = WebSocket::handshakeHeaders($secWSKey);

        // WebSocket connection to 'ws://127.0.0.1:9502/'
        // failed: Error during WebSocket handshake:
        // Response must not include 'Sec-WebSocket-Protocol' header if not present in request: websocket
        if (isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }

        foreach ($headers as $key => $val) {
            $response->header($key, $val);
        }

        $response->status(101);
        $response->end();

        return true;
    }
}
