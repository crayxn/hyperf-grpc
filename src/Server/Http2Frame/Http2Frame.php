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
    const HTTP2_FRAME_TYPE_HEAD = 0x1;
    const HTTP2_FRAME_TYPE_DATA = 0x00;
    const HTTP2_FRAME_TYPE_SETTING = 0x04;
    const HTTP2_FLAG_NONE = 0x00;
    const HTTP2_FLAG_ACK = 0x01;
    const HTTP2_FLAG_END_STREAM = 0x01;
    const HTTP2_FLAG_END_HEADERS = 0x04;
    const HTTP2_FLAG_PADDED = 0x08;
    const HTTP2_FLAG_PRIORITY = 0x20;

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