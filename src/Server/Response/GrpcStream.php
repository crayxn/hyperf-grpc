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
use Crayoon\HyperfGrpc\Server\Http2Frame\FrameParser;
use Crayoon\HyperfGrpc\Server\Http2Frame\Http2Frame;
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

    /**
     * @var FrameParser
     */
    protected FrameParser $frameParser;

    protected bool $withHeader = false;


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
            // Get Server
            $this->server = $container->get(\Swoole\Server::class);

            // Get swoole request and response
            $this->request = Context::get(ServerRequestInterface::class)->getSwooleRequest();
            /**
             * @var WritableConnection $connect
             */
            $connect = Context::get(ResponseInterface::class)->getConnection();
            if (!$connect) {
                throw new \Exception('undefined response');
            }
            $this->response = $connect->getSocket();

            // Get Parser
            $this->frameParser = $container->get(FrameParser::class);

        } catch (\Throwable $e) {
            throw new GrpcStreamException($e->getMessage());
        }
    }

    /**
     * @param null|Http2Frame|Http2Frame[] $frames
     * @return bool
     */
    public function emit(null|Http2Frame|array $frames): bool
    {
        if (!$frames) return false;
        $mixedStream = implode('', array_map(function ($item) {
            return $this->frameParser->pack($item);
        }, is_array($frames) ? $frames : [$frames]));

        return $this->server->send($this->response->fd, $mixedStream);
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

        $streams = [];
        // add header
        if (!$this->withHeader) {
            $streams[] = $this->buildHeader();
            $this->withHeader = true;
        }
        // add message
        $streams[] = new Http2Frame(
            $data ? Parser::serializeMessage($data) : '',
            Http2Frame::HTTP2_FRAME_TYPE_DATA,
            Http2Frame::HTTP2_FLAG_NONE,
            $this->request->streamId
        );

        return $this->emit($streams);
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

        $headerStream = $this->buildHeader(true, [
            [':status', '200'],
            ['content-type', 'application/grpc+proto'],
            ['trailer', 'grpc-status, grpc-message'],
            ['grpc-status', (string)$status],
            ['grpc-message', $message],
        ]);

        $res = $this->emit($headerStream);
        if ($res) $this->response->detach();

        return $res;
    }


    /**
     * @param bool $end
     * @param array $headers
     * @return Http2Frame
     */
    private function buildHeader(bool $end = false, array $headers = [
        [':status', '200'],
        ['content-type', 'application/grpc+proto']
    ]): Http2Frame
    {
        $frame = $this->frameParser->encodeHeaderFrame($headers, $this->request->streamId);
        if ($frame && $end) {
            $frame->flags = Http2Frame::HTTP2_FLAG_END_STREAM | Http2Frame::HTTP2_FLAG_END_HEADERS;
        }
        return $frame;
    }

    public function isWritable()
    {
        return $this->response->isWritable();
    }
}