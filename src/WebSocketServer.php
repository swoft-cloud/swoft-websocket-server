<?php

namespace Swoft\WebSocket\Server;

use Swoft\App;
use Swoft\Bootstrap\SwooleEvent;
use Swoft\Core\RequestContext;
use Swoft\Http\Server\Http\HttpServer;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

/**
 * Class WebSocketServer
 * @package Swoft\WebSocket\Server
 * @author inhere <in.798@qq.com>
 * @property \Swoole\WebSocket\Server $server
 */
class WebSocketServer extends HttpServer
{
    use MessageHelperTrait;

    /**
     * @var array
     */
    private $wsSettings = [
        // enable handler http request ?
        'enable_http' => true,
    ];

    /**
     * @param array $settings
     * @throws \InvalidArgumentException
     */
    public function initSettings(array $settings)
    {
        parent::initSettings($settings);

        $this->wsSettings = \array_merge($this->httpSetting, $this->wsSettings, $settings['ws']);
    }

    /**
     *
     * @throws \Swoft\Exception\RuntimeException
     */
    public function start()
    {
        if (!empty($this->setting['open_http2_protocol'])) {
            $this->wsSettings['type'] = SWOOLE_SOCK_TCP | SWOOLE_SSL;
        }

        $this->server = new Server(
            $this->wsSettings['host'],
            $this->wsSettings['port'],
            $this->wsSettings['mode'],
            $this->wsSettings['type']
        );

        // config server
        $this->server->set($this->setting);

        // Bind event callback
        $this->server->on(SwooleEvent::ON_START, [$this, 'onStart']);
        $this->server->on(SwooleEvent::ON_WORKER_START, [$this, 'onWorkerStart']);
        $this->server->on(SwooleEvent::ON_MANAGER_START, [$this, 'onManagerStart']);
        $this->server->on(SwooleEvent::ON_PIPE_MESSAGE, [$this, 'onPipeMessage']);

        // bind events for ws server
        $this->server->on(SwooleEvent::ON_HAND_SHAKE, [$this, 'onHandshake']);
        $this->server->on(SwooleEvent::ON_OPEN, [$this, 'onOpen']);
        $this->server->on(SwooleEvent::ON_MESSAGE, [$this, 'onMessage']);
        $this->server->on(SwooleEvent::ON_CLOSE, [$this, 'onClose']);

        // if enable handle http request
        if ($this->wsSettings['enable_http']) {
            $this->server->on(SwooleEvent::ON_REQUEST, [$this, 'onRequest']);
        }

        // Start RPC Server
        if ((int)$this->serverSetting['tcpable'] === 1) {
            $this->registerRpcEvent();
        }

        $this->registerSwooleServerEvents();
        $this->beforeServerStart();
        $this->server->start();
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    public function onHandshake(Request $request, Response $response): bool
    {
        return true;
    }

    public function onOpen(Server $server, Request $request)
    {
        // Initialize Request and Response and set to RequestContent
        $psr7Request = \Swoft\Http\Message\Server\Request::loadFromSwooleRequest($request);
        // $psr7Response = new \Swoft\Http\Message\Server\Response($response);

        RequestContext::setRequest($psr7Request);
    }

    /**
     * When you receive the message
     * @param  Server $server
     * @param  Frame $frame
     */
    public function onMessage(Server $server, Frame $frame)
    {
        /** @var \Swoft\Http\Server\ServerDispatcher $dispatcher */
        $dispatcher = App::getBean('wsDispatcher');
        $dispatcher->dispatch($frame);
    }

    /**
     * webSocket close
     * @param  Server $server
     * @param  int $fd
     */
    public function onClose($server, $fd)
    {
        RequestContext::destroy();
    }

    /**
     * @param string $msg
     * @param array $data
     * @param string $type
     */
    public function log(string $msg, array $data = [], string $type = 'info')
    {
        \output()->writeln(\sprintf(
            '%s [%s] %s %s',
            \date('Y/m/d H:i:s'),
            \strtoupper($type),
            \trim($msg),
            $data ? \json_encode($data, \JSON_UNESCAPED_SLASHES) : ''
        ));
    }

    /**
     * @return array
     */
    public function getWsSettings(): array
    {
        return $this->wsSettings;
    }
}
