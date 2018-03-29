<?php

namespace Swoft\WebSocket\Server\Component;

use Swoft\Bean\Annotation\Bean;

/**
 * Class User
 * @package Swoft\WebSocket\Server\Component
 * @Bean()
 */
class User
{
    /**
     * @var int|string
     */
    public $id;

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = \trim($name);
    }
}
