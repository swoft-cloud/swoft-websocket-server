<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018/3/18
 * Time: 下午7:50
 */

namespace Swoft\WebSocket\Server;
use Swoft\WebSocket\Server\Exception\WsRouteException;

/**
 * Class HandlerMapping
 * @package Swoft\WebSocket\Server
 */
class HandlerMapping
{
    /**
     * @var array
     */
    private $routes = [];

    /**
     * Get handler from router
     *
     * @param array ...$params
     *
     * @return array
     * @throws \Swoft\WebSocket\Server\Exception\WsRouteException
     * @throws \InvalidArgumentException
     */
    public function getHandler(...$params): array
    {
        return $this->match($params[0]);
    }

    /**
     * Match route
     *
     * @param string $path
     * @return array
     * @throws \Swoft\WebSocket\Server\Exception\WsRouteException
     */
    public function match(string $path): array
    {
        if (!isset($this->routes[$path])) {
            throw new WsRouteException(sprintf('The requested ws route "%s" path is not exist! ', $path));
        }

        return $this->routes[$path];
    }

    /**
     * Register one route
     *
     * @param string $path
     * @param mixed $handler
     */
    private function registerRoute(string $path, $handler)
    {
        $path = '/' . \trim($path, '/ ');

        $this->routes[$path] = $handler;
    }

    /**
     * Auto register routes
     *
     * @param array $serviceMapping
     */
    public function registerRoutes(array $serviceMapping)
    {
        foreach ($serviceMapping as $path => $handler) {
            $this->registerRoute($path, $handler);
        }
    }

    /**
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @param array $routes
     */
    public function setRoutes(array $routes)
    {
        $this->routes = $routes;
    }
}
