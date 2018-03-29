<?php
namespace Swoft\WebSocket\Server\Component;

use Swoft\Bean\Annotation\Bean;

/**
 * Class UserManager
 * @package Swoft\WebSocket\Server\Component
 * @Bean()
 */
class UserManager
{
    private $driver;

    /**
     * @return mixed
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @param mixed $driver
     */
    public function setDriver($driver)
    {
        $this->driver = $driver;
    }
}
