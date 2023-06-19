<?php
declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc\Server\Http2Frame;

interface FrameParserInterface
{
    public function unpack(string $frame_data,&$result): void;

    public function pack(Http2Frame $frame): string;
}