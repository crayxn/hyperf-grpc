<?php
declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc\Server\Handler;

use Crayoon\HyperfGrpc\Server\Channel\ChannelManager;
use Crayoon\HyperfGrpc\Server\Http2Frame\FrameParser;
use Crayoon\HyperfGrpc\Server\Http2Frame\Http2Frame;
use Exception;
use Google\Protobuf\Internal\Message;
use Hyperf\Context\ApplicationContext;
use Hyperf\Grpc\Parser;
use Hyperf\Grpc\StatusCode;
use Hyperf\HttpMessage\Server\Request;
use Hyperf\HttpMessage\Uri\Uri;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Swoole\Coroutine\Channel;
use Swoole\Server as SwooleServer;

class StreamHandler
{
    /**
     * @var SwooleServer
     */
    private SwooleServer $swooleServer;

    /**
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * @var ServerRequestInterface
     */
    private ServerRequestInterface $request;

    /**
     * @var Channel
     */
    private Channel $dataChannel;

    /**
     * @var int
     */
    private int $fd;

    /**
     * @var int
     */
    private int $streamId;

    /**
     * @var FrameParser
     */
    private FrameParser $frameParser;

    /**
     * is polluted
     * @var bool
     */
    private bool $polluted = false;


    /**
     * @param SwooleServer $server
     * @param int $fd
     * @param Http2Frame $swooleHeaderFrame
     * @param mixed|null $body
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function __construct(SwooleServer $server, int $fd, Http2Frame $swooleHeaderFrame, mixed $body = null)
    {
        if ($swooleHeaderFrame->type != Http2Frame::HTTP2_FRAME_TYPE_HEAD && $swooleHeaderFrame->flags != Http2Frame::HTTP2_FLAG_END_HEADERS) {
            throw new Exception("Grpc request header frame is invalid!");
        }
        $this->swooleServer = $server;
        $this->fd = $fd;
        $this->streamId = $swooleHeaderFrame->streamId;
        $this->container = ApplicationContext::getContainer();
        $this->dataChannel = $this->container->get(ChannelManager::class)->create($fd);
        $this->frameParser = $this->container->get(FrameParser::class);
        // push data
        if ($body) $this->dataChannel->push($body);
        // emit setting
        $this->swooleServer->send($fd, $this->frameParser->pack(
            new Http2Frame(hex2bin(Http2Frame::SETTING_HEX), Http2Frame::HTTP2_FRAME_TYPE_SETTING, Http2Frame::HTTP2_FLAG_NONE, 0)
        ));
        // headers
        $swooleHeaders = [];
        foreach ($this->frameParser->decodeHeaderFrame($swooleHeaderFrame) as [$key, $value]) {
            $swooleHeaders[$key] = $value;
        }
        //create request
        $uri = new Uri(sprintf("%s://%s:%d%s", $swooleHeaders[':scheme'] ?? 'http', $server->host, $server->port, $swooleHeaders[':path'] ?? '/'));
        $this->request = new Request($swooleHeaders[':method'], $uri, $swooleHeaders, $body, '2');
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function receive(string|array $deserialize = null): mixed
    {
        $data = $this->dataChannel->pop();
        if ($deserialize && $data != Http2Frame::EOF) {
            $data = Parser::deserializeMessage(is_array($deserialize) ? $deserialize : [$deserialize, 'mergeFromString'], $data);
        }
        return $data;
    }


    private function write(Http2Frame $frame): bool
    {
        $frames = [];
        if (!$this->polluted && $frame->type == Http2Frame::HTTP2_FRAME_TYPE_DATA) {
            //with header
            $frames[] = $this->frameParser->pack(
                $this->frameParser->encodeHeaderFrame([
                    [':status', '200'],
                    ['content-type', 'application/grpc'],
                    ['trailer', 'grpc-status, grpc-message']
                ], $this->streamId)
            );
            $this->polluted = true;
        }

        $frames[] = $this->frameParser->pack($frame);
        // write
        return $this->swooleServer->send($this->fd, implode('', $frames));
    }

    public function push(Message $message): bool
    {
        return $this->write(new Http2Frame(Parser::serializeMessage($message), Http2Frame::HTTP2_FRAME_TYPE_DATA, Http2Frame::HTTP2_FLAG_NONE, $this->streamId));
    }

    public function end(int $status = StatusCode::OK, string $message = 'ok'): bool
    {
        //send status
        $end = $this->frameParser->encodeHeaderFrame([
            ['grpc-status', (string)$status],
            ['grpc-message', $message],
        ], $this->streamId);
        $end->flags = Http2Frame::HTTP2_FLAG_END_HEADERS | Http2Frame::HTTP2_FLAG_END_STREAM;
        return $this->write($end);
    }
}