<?php

namespace Swoft\WebSocket\Server\Helper;

/**
 * Class WsHelper
 * @package Swoft\WebSocket\Server\Helper
 */
final class WebSocket
{
    const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    /**
     * Generate WebSocket sign.(for server)
     * @param string $key
     * @return string
     */
    public static function genSign(string $key): string
    {
        return \base64_encode(\sha1(trim($key) . self::SIGN_KEY, true));
    }

    /**
     * @param string $secWSKey 'sec-websocket-key: xxxx'
     * @return bool
     */
    public function isInvalidSecWSKey($secWSKey): bool
    {
        return 0 === \preg_match(self::KEY_PATTEN, $secWSKey) ||
            16 !== \strlen(\base64_decode($secWSKey));
    }

}
