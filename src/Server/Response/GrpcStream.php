<?php
declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc\Server\Response;

use Amp\Http\HPack;
use Amp\Http\HPackException;
use Crayoon\HyperfGrpc\Exception\GrpcStreamException;
use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Grpc\Parser;
use Hyperf\Grpc\StatusCode;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

class GrpcStream
{
    /**
     * @var Server
     */
    protected Server $server;
    /**
     * @var Request
     */
    protected Request $request;

    /**
     * @var Response
     */
    protected Response $response;

    protected bool $withHeader = false;

    const SW_HTTP2_FRAME_TYPE_HEAD = 0x1;
    const SW_HTTP2_FRAME_TYPE_DATA = 0x00;
    const SW_HTTP2_FLAG_NONE = 0x00;
    const SW_HTTP2_FLAG_ACK = 0x01;
    const SW_HTTP2_FLAG_END_STREAM = 0x01;
    const SW_HTTP2_FLAG_END_HEADERS = 0x04;
    const SW_HTTP2_FLAG_PADDED = 0x08;
    const SW_HTTP2_FLAG_PRIORITY = 0x20;

    /**
     * @throws GrpcStreamException
     */
    public function __construct(?Request $request = null, ?Response $response = null)
    {
        /**
         * @var ContainerInterface $container
         */
        $container = ApplicationContext::getContainer();
        try {
            $this->server = $container->get(\Swoole\Server::class);
            // Get swoole request and response
            $this->request = $request ?? Context::get(Request::class);
            $this->response = $response ?? Context::get(Response::class);

        } catch (\Throwable $e) {
            throw new GrpcStreamException($e->getMessage());
        }
    }

    /**
     * 写入消息
     * @param mixed $data
     * @param bool $end
     * @return bool
     */
    public function write(mixed $data, bool $end = false): bool
    {
        if (!$this->withHeader && !$this->withHeader()) {
            return false;
        }

        if (!$this->response->isWritable()) {
            return false;
        }

        $res = $this->server->send($this->response->fd, $this->frame(
            $data ? Parser::serializeMessage($data) : '',
            self::SW_HTTP2_FRAME_TYPE_DATA,
            $end ? self::SW_HTTP2_FLAG_END_STREAM : self::SW_HTTP2_FLAG_NONE,
            $this->request->streamId
        ));

        if ($end) {
            $this->response->detach();
        }

        return $res;
    }

    public function close(): bool
    {
        return $this->write(null, true);
    }


    private function frame(string $data, int $type, int $flags, int $stream = 0): string
    {
        return (substr(pack("NccN", strlen($data), $type, $flags, $stream), 1) . $data);
    }

    /**
     * @param array $headers
     * @return bool
     */
    public function withHeader(array $headers = [
        [':status', '200'],
        ['server', 'Hyperf'],
        ['content-type', 'application/grpc'],
        ['trailer', 'grpc-status, grpc-message'],
        ['grpc-status', '0'],
        ['grpc-message', ''],
    ]): bool
    {
        try {
            $compressedHeaders = (new HPack())->encode($headers);
        } catch (HPackException) {
            return false;
        }
        $res = $this->server->send($this->response->fd, $this->frame(
            $compressedHeaders,
            self::SW_HTTP2_FRAME_TYPE_HEAD,
            self::SW_HTTP2_FLAG_END_HEADERS,
            $this->request->streamId
        ));
        $this->withHeader = $res;
        return $res;
    }
}