<?php
declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc\Server\Channel;

use Swoole\Coroutine\Channel;

class ChannelManager
{
    private array $pools = [];

    /**
     * create a request data channel
     * @param $fd
     * @return Channel
     */
    public function create($fd): Channel
    {
        $this->pools[$fd] = new Channel(3);
        return $this->pools[$fd];
    }

    /**
     * get the request data channel
     * @param $fd
     * @return Channel
     */
    public function channel($fd): Channel
    {
        return $this->pools[$fd] ?? $this->create($fd);
    }

    /**
     * remove the request data channel
     * @param $fd
     * @return void
     */
    public function remove($fd): void
    {
        if (isset($this->pools[$fd])) {
            // need close
            if ($this->pools[$fd] instanceof Channel) {
                $this->pools[$fd]->close();
            }
            //unset
            unset($this->pools[$fd]);
        }
    }
}