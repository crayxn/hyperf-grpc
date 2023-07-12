<?php
declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc\Server\Http2Frame;

use Hyperf\Grpc\Parser;

class Http2Frame
{
    const HTTP2_FRAME_TYPE_HEAD = 1;
    const HTTP2_FRAME_TYPE_DATA = 0;
    const HTTP2_FRAME_TYPE_RST = 3;
    const HTTP2_FRAME_TYPE_SETTING = 4;
    const HTTP2_FRAME_TYPE_GOAWAY = 7;
    const HTTP2_FLAG_NONE = 0;
    const HTTP2_FLAG_ACK = 1;
    const HTTP2_FLAG_END_STREAM = 1;
    const HTTP2_FLAG_END_HEADERS = 4;
    const HTTP2_FLAG_PADDED = 8;
    const HTTP2_FLAG_PRIORITY = 20;

    const SETTING_HEX = '00030000008000040000ffff000500004000';
    const EOF = '\r\n';

    public int $length = 0;

    public function __construct(
        public string $payload,
        public int    $type,
        public int    $flags,
        public int    $streamId
    )
    {
        $this->length = strlen($this->payload);
    }
}