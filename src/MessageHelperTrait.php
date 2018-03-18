<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018/3/18
 * Time: 上午2:39
 */

namespace Swoft\WebSocket\Server;

/**
 * Trait MessageHelperTrait
 * @package Swoft\WebSocket\Server
 */
trait MessageHelperTrait
{
    /**
     * send message to client(s)
     * @param string $data
     * @param int|array $receivers
     * @param int|array $expected
     * @param int $sender
     * @return int
     */
    public function send(string $data, $receivers = 0, $expected = 0, int $sender = 0): int
    {
        if (!$data) {
            return 0;
        }
        $receivers = (array)$receivers;
        $expected = (array)$expected;
        // only one receiver
        if (1 === \count($receivers)) {
            return $this->sendTo(array_shift($receivers), $data, $sender);
        }
        // to all
        if (!$expected && !$receivers) {
            $this->sendToAll($data, $sender);
            // to some
        } else {
            $this->sendToSome($data, $receivers, $expected, $sender);
        }

        return $this->getErrorNo();
    }

    /**
     * Send a message to the specified user 发送消息给指定的用户
     * @param int $receiver 接收者
     * @param string $data
     * @param int $sender 发送者
     * @return int
     */
    public function sendTo(int $receiver, string $data, int $sender = 0): int
    {
        $finish = true;
        $opcode = 1;
        $fromUser = $sender < 1 ? 'SYSTEM' : $sender;

        $this->log("(private)The #{$fromUser} send message to the user #{$receiver}. Data: {$data}");

        return $this->server->push($receiver, $data, $opcode, $finish) ? 0 : -500;
    }

    /**
     * broadcast message 广播消息
     * @param string $data 消息数据
     * @param int $sender 发送者
     * @param int[] $receivers 指定接收者们
     * @param int[] $expected 要排除的接收者
     * @return int   Return socket last error number code.  gt 0 on failure, eq 0 on success
     */
    public function broadcast(string $data, array $receivers = [], array $expected = [], int $sender = 0): int
    {
        if (!$data) {
            return 0;
        }

        // only one receiver
        if (1 === \count($receivers)) {
            return $this->sendTo(array_shift($receivers), $data, $sender);
        }

        // to all
        if (!$expected && !$receivers) {
            $this->sendToAll($data, $sender);
            // to some
        } else {
            $this->sendToSome($data, $receivers, $expected, $sender);
        }

        return $this->getErrorNo();
    }

    /**
     * @param string $data
     * @param int $sender
     * @return int
     */
    public function sendToAll(string $data, int $sender = 0): int
    {
        $startFd = 0;
        $count = 0;
        $fromUser = $sender < 1 ? 'SYSTEM' : $sender;
        $this->log("(broadcast)The #{$fromUser} send a message to all users. Data: {$data}");

        while (true) {
            $connList = $this->server->connection_list($startFd, 50);

            if ($connList === false || ($num = \count($connList)) === 0) {
                break;
            }

            $count += $num;
            $startFd = end($connList);

            /** @var $connList array */
            foreach ($connList as $fd) {
                $info = $this->getClientInfo($fd);

                if ($info && $info['websocket_status'] > 0) {
                    $this->server->push($fd, $data);
                }
            }
        }

        return $count;
    }

    /**
     * @param string $data
     * @param array $receivers
     * @param array $expected
     * @param int $sender
     * @return int
     */
    public function sendToSome(string $data, array $receivers = [], array $expected = [], int $sender = 0): int
    {
        $count = 0;
        $res = $data;
        $len = \strlen($res);
        $fromUser = $sender < 1 ? 'SYSTEM' : $sender;
        // to receivers
        if ($receivers) {
            $this->log("(broadcast)The #{$fromUser} gave some specified user sending a message. Data: {$data}");

            foreach ($receivers as $receiver) {
                if ($this->hasConnection($receiver)) {
                    $count++;
                    $this->server->push($receiver, $res, $len);
                }
            }

            return $count;
        }

        // to special users
        $startFd = 0;
        $this->log("(broadcast)The #{$fromUser} send the message to everyone except some people. Data: {$data}");

        while (true) {
            $connList = $this->server->connection_list($startFd, 50);

            if ($connList === false || ($num = \count($connList)) === 0) {
                break;
            }

            $count += $num;
            $startFd = end($connList);

            /** @var $connList array */
            foreach ($connList as $fd) {
                if (isset($expected[$fd])) {
                    continue;
                }

                if ($receivers && !isset($receivers[$fd])) {
                    continue;
                }

                $this->server->push($fd, $data);
            }
        }

        return $count;
    }

    /**
     * response data to client by socket connection
     * @param int $fd
     * @param string $data
     * param int $length
     * @return int   Return error number code. gt 0 on failure, eq 0 on success
     */
    public function writeTo($fd, string $data): int
    {
        return $this->server->send($fd, $data) ? 0 : 1;
    }

    /**
     * @param int $cid
     * @return bool
     */
    public function exist(int $cid): bool
    {
        return $this->server->exist($cid);
    }

    /**
     * @return int
     */
    public function getErrorNo(): int
    {
        return $this->server->getLastError();
    }
}
