<?php

namespace Swoft\WebSocket\Server;

use Swoft\App;
use Swoft\Core\RequestContext;
use Swoft\WebSocket\Server\Event\WsEvent;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

//use Swoft\WebSocket\Server\WebSocket;

/**
 * Trait HandshakeTrait
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
     * @throws \InvalidArgumentException
     */
    public function onHandShake(Request $request, Response $response): bool
    {
        if ($this->forTesting) {
            return $this->simpleHandshake($request, $response);
        }

        $fd = $request->fd;
        $info = $this->getClientInfo($fd);

        $this->log("onHandShake: Client #{$fd} send handShake request, connection info: " . var_export($info, 1));

        $metaAry = [
            'id' => $fd,
            'ip' => $info['remote_ip'],
            'port' => $info['remote_port'],
            'path' => '/',
            'handshake' => false,
            'connectTime' => $info['connect_time'],
        ];

        // 初始化客户端信息
        $meta = new Connection($metaAry);
        $meta->setRequestResponse($request, $response);

        $request = $meta->getRequest();
        $secKey = $request->getHeaderLine('sec-websocket-key');

        $this->log("Handshake: Ready to shake hands with the #$fd client connection. request info:\n" . $request->toString());

        // sec-websocket-key 错误
        if (!$this->validateHeaders($fd, $secKey, $response)) {
            return false;
        }

        $response = $meta->getResponse();
        App::trigger(WsEvent::ON_HANDSHAKE, null, $request, $response, $fd);

        // 如果返回 false -- 拒绝连接，比如需要认证，限定路由，限定ip，限定domain等
        // 就停止继续处理。并返回信息给客户端
        if (false === $this->handleHandshake($request, $response, $fd)) {
            $this->log("The #$fd client handshake's callback return false, will close the connection");

            Psr7Http::respond($response, $response);

            return false;
        }

        // setting response
        $response
            ->setStatus(101)
            ->setHeaders(WebSocket::handshakeHeaders($secKey));

        if (isset($request->header['sec-websocket-protocol'])) {
            $response->setHeader('Sec-WebSocket-Protocol', $request->header['sec-websocket-protocol']);
        }
        $this->log("Handshake: response info:\n" . $response->toString());

        // 响应握手成功
        Psr7Http::respond($response, $response);

        // 标记已经握手 更新路由 path
        $meta->handshake();
        $this->ids[$fd] = $fd;
        $this->connections[$fd] = $meta;

        $this->log("Handshake: The #{$fd} client handshake successful! ctxKey: {$meta->getKey()}, Meta:\n" . var_export($meta->all(), 1));

        // 握手成功 触发 open 事件
        $this->server->defer(function () use ($request) {
            $this->onOpen($this->server, $request);
        });

        return true;
    }

    protected function simpleHandshake(Request $request, Response $response): bool
    {
        // print_r( $request->header );
        // if (如果不满足我某些自定义的需求条件，那么返回end输出，返回false，握手失败) {
        //    $response->end();
        //     return false;
        // }

        // websocket握手连接算法验证
        $secWebSocketKey = $request->header['sec-websocket-key'];
        $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';

        if (0 === preg_match($patten, $secWebSocketKey) || 16 !== \strlen(base64_decode($secWebSocketKey))) {
            $response->end();
            return false;
        }

        // echo $request->header['sec-websocket-key'];
        $key = base64_encode(sha1(
            $request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
            true
        ));

        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => $key,
            'Sec-WebSocket-Version' => '13',
        ];

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


    /**
     * @param Server $server
     * @param Request $request
     * @throws \InvalidArgumentException
     */
    public function onOpen(Server $server, Request $request)
    {
        // Initialize Request and Response and set to RequestContent
        $psr7Request = \Swoft\Http\Message\Server\Request::loadFromSwooleRequest($request);
        // $psr7Response = new \Swoft\Http\Message\Server\Response($response);

        RequestContext::setRequest($psr7Request);

        App::trigger(WsEvent::ON_OPEN, null, $server, $request);

        $this->log("connection #$request->fd has been opened");
    }

    /**
     * When you receive the message
     * @param  Server $server
     * @param  Frame $frame
     */
    public function onMessage(Server $server, Frame $frame)
    {
        /** @var \Swoft\Http\Server\ServerDispatcher $dispatcher */
        // $dispatcher = App::getBean('wsDispatcher');
        // $dispatcher->dispatch($frame);

        $this->log('received message: ' . $frame->data . " from #$frame->fd");
    }

    /**
     * webSocket close
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

            // call on close callback
            App::trigger(WsEvent::ON_CLOSE, null, $server, $fd);

            // $this->log(
            //     "onClose: The #$fd client has been closed! workerId: {$server->worker_id} ctxKey:{$meta->getKey()}, From {$meta['ip']}:{$meta['port']}. Count: {$this->count()}"
            // );
            $this->log("onClose: Client #{$fd} is closed. client-info:\n" . var_export($fdInfo, 1));
        }

        RequestContext::destroy();
    }
}
