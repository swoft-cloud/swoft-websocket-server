<?php

namespace Swoft\WebSocket\Server\Bootstrap;

use Swoft\Bean\Annotation\BootBean;
use Swoft\Core\BootBeanInterface;
use Swoft\WebSocket\Server\HandlerMapping;
use Swoft\WebSocket\Server\WsDispatcher;

/**
 * The core bean of service
 *
 * @BootBean()
 */
class CoreBean implements BootBeanInterface
{
    /**
     * @return array
     */
    public function beans()
    {
        return [
            'wsDispatcher' => [
                'class' => WsDispatcher::class,
            ],
            'wsRouter'     => [
                'class' => HandlerMapping::class,
            ],
        ];
    }
}
