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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Hyperf\Engine\Http\WritableConnection;

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
            $this->request = Context::get(ServerRequestInterface::class)->getSwooleRequest();
            /**
             * @var WritableConnection $connect
             */
            $connect = Context::get(ResponseInterface::class)->getConnection();
            if( !$connect ) {
                throw new \Exception('undefined response');
            }
            $this->response = $connect->getSocket();
        } catch (\Throwable $e) {
            throw new GrpcStreamException($e->getMessage());
        }
    }

    /**
     * @param mixed $data
     * @return bool
     */
    public function write(mixed $data): bool
    {
        if (!$this->response->isWritable()) {
            return false;
        }

        $stream = '';
        // add header
        if (!$this->withHeader) {
            $stream .= $this->buildHeader();
            $this->withHeader = true;
        }
        // add message
        $stream .= $this->frame(
            $data ? Parser::serializeMessage($data) : '',
            self::SW_HTTP2_FRAME_TYPE_DATA,
            self::SW_HTTP2_FLAG_NONE,
            $this->request->streamId
        );

        return $this->server->send($this->response->fd, $stream);
    }

    /**
     * @param int $status
     * @param string $message
     * @return bool
     */
    public function close(int $status = StatusCode::OK, string $message = ''): bool
    {
        if (!$this->response->isWritable()) {
            return true;
        }

        $header = $this->buildHeader(true, [
            [':status', '200'],
            ['content-type', 'application/grpc+proto'],
            ['trailer', 'grpc-status, grpc-message'],
            ['grpc-status', (string)$status],
            ['grpc-message', $message],
        ]);

        $res = $this->server->send($this->response->fd, $header);
        if ($res) $this->response->detach();

        return $res;
    }


    private function frame(string $data, int $type, int $flags, int $stream = 0): string
    {
        return (substr(pack("NccN", strlen($data), $type, $flags, $stream), 1) . $data);
    }

    /**
     * @param bool $end
     * @param array $headers
     * @return string
     */
    private function buildHeader(bool $end = false, array $headers = [
        [':status', '200'],
        ['content-type', 'application/grpc+proto']
    ]): string
    {
        try {
            $compressedHeaders = (new HPack())->encode($headers);
        } catch (HPackException) {
            return '';
        }

        return $this->frame(
            $compressedHeaders,
            self::SW_HTTP2_FRAME_TYPE_HEAD,
            $end ? (self::SW_HTTP2_FLAG_END_STREAM | self::SW_HTTP2_FLAG_END_HEADERS) : self::SW_HTTP2_FLAG_END_HEADERS,
            $this->request->streamId
        );
    }
}