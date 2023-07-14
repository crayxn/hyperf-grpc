<?php
declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc\Server\Http2Stream;

use Swoole\Coroutine\Channel;

class Http2Stream
{
    public Channel $receiveChannel;

    public bool $active = true;

    public function __construct(public int $id)
    {
        // create receive channel
        $this->receiveChannel = new Channel(5);
    }
}