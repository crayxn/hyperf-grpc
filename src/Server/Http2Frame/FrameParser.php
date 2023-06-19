<?php
declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc\Server\Http2Frame;

use Amp\Http\HPack;
use Amp\Http\HPackException;

class FrameParser implements FrameParserInterface
{
    protected HPack $hPack;

    public function __construct()
    {
        $this->hPack = new HPack();
    }

    /**
     * @param string $frame_data
     * @param null|Http2Frame[] $result
     * @throws \Exception
     */
    public function unpack(string $frame_data, &$result): void
    {
        if (strlen($frame_data) < 9) {
            return;
        }
        //header
        $lengthPack = unpack('C3', substr($frame_data, 0, 3));
        $length = ($lengthPack[1] << 16) | ($lengthPack[2] << 8) | $lengthPack[3];
        $headers = unpack('Ctype/Cflags/NstreamId', substr($frame_data, 3, 6));
        $result[] = new Http2Frame(
            substr($frame_data, 9, $length),   //payload
            $headers['type'],
            $headers['flags'],
            $headers['streamId'] & 0x7FFFFFFF
        );
        if ('' != $next = substr($frame_data, $length + 9)) {
            static::unpack($next, $result);
        }
    }

    public function pack(Http2Frame $frame): string
    {
        return (substr(pack("NccN", $frame->length, $frame->type, $frame->flags, $frame->streamId), 1) . $frame->payload);
    }

    public function decodeHeaderFrame(Http2Frame $frame): ?array
    {
        if ($frame->type !== Http2Frame::HTTP2_FRAME_TYPE_HEAD) return null;
        return $this->hPack->decode($frame->payload, 4096);
    }

    public function encodeHeaderFrame($headers, $streamId): ?Http2Frame
    {
        try {
            $compressedHeaders = $this->hPack->encode($headers);
            return new Http2Frame(
                $compressedHeaders,
                Http2Frame::HTTP2_FRAME_TYPE_HEAD,
                Http2Frame::HTTP2_FLAG_END_HEADERS,
                $streamId);
        } catch (HPackException) {
            return null;
        }
    }

}