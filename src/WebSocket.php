<?php

namespace Swoft\WebSocket\Server;

/**
 * Class WsHelper
 * @package Swoft\WebSocket\Server
 */
final class WebSocket
{
    const VERSION = 13;
    const KEY_PATTEN = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
    const SIGN_KEY = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

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
    public static function isInvalidSecWSKey($secWSKey): bool
    {
        return 0 === \preg_match(self::KEY_PATTEN, $secWSKey) ||
               16 !== \strlen(\base64_decode($secWSKey));
    }

    /**
     * @param string $secKey
     * @return array
     */
    public static function handshakeHeaders(string $secKey): array
    {
        return [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => self::genSign($secKey),
            'Sec-WebSocket-Version' => self::VERSION,
        ];
    }
}
