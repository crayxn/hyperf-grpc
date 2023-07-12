<?php
declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc\Server\Http2Stream;

/**
 * stream manager
 */
class StreamManager
{
    /**
     * @var Http2Stream[] $pool
     */
    private array $pool = [];

    /**
     * @param int $streamId
     * @return Http2Stream
     */
    public function get(int $streamId): Http2Stream
    {
        if (!isset($this->pool[$streamId])) $this->pool[$streamId] = new Http2Stream($streamId);
        return $this->pool[$streamId];
    }

    /**
     * @param int $streamId
     * @return void
     */
    public function remove(int $streamId): void
    {
        if (isset($this->pool[$streamId])) {
            if ($this->pool[$streamId] instanceof Http2Stream) {
                //close channel
                $this->pool[$streamId]->receiveChannel->close();
            }
            unset($this->pool[$streamId]);
        }
    }

    /**
     * @param int $streamId
     * @return bool
     */
    public function checkActive(int $streamId): bool
    {
        return isset($this->pool[$streamId]) ? $this->pool[$streamId]->active : false;
    }

    /**
     * @param int $streamId
     * @return void
     */
    public function down(int $streamId): void
    {
        if (isset($this->pool[$streamId])) {
            $stream = $this->pool[$streamId];
            $stream->active = false;
            $this->pool[$streamId] = $stream;
        }
    }
}