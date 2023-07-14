<?php
declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc\Reflection;

use Crayoon\HyperfGrpc\Server\Handler\StreamHandler;
use Crayoon\HyperfGrpc\Server\Http2Frame\Http2Frame;
use Google\Protobuf\Internal\DescriptorPool;
use Hyperf\Context\Context;
use Hyperf\Grpc\Parser;
use Hyperf\Grpc\StatusCode;
use Hyperf\GrpcServer\Exception\GrpcException;
use Psr\Http\Message\ServerRequestInterface;

class StreamReflection extends Reflection
{
    public function streamServerReflectionInfo(): void
    {
        /**
         * @var StreamHandler $handler
         */
        $handler = Context::get(StreamHandler::class);
        /**
         * @var ServerReflectionRequest $request
         */
        while (Http2Frame::EOF !== $request = $handler->receive(ServerReflectionRequest::class)) {
            $handler->push($this->serverReflectionInfo($request));
        }
    }
}