<?php
declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc\Server;

use Crayoon\HyperfGrpc\Server\Channel\ChannelManager;
use Crayoon\HyperfGrpc\Server\Handler\StreamHandler;
use Crayoon\HyperfGrpc\Server\Http2Frame\FrameParser;
use Crayoon\HyperfGrpc\Server\Http2Frame\Http2Frame;
use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Grpc\StatusCode;
use \Hyperf\GrpcServer\Server;
use Hyperf\HttpServer\MiddlewareManager;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\Support\SafeCaller;
use Swoole\Http\Response;
use \Swoole\Server as SwooleServer;
use Throwable;

class StreamServer extends Server
{
    /**
     * @param SwooleServer $server
     * @param int $fd
     * @param int $reactor_id
     * @param string $data
     * @return void
     * @throws Throwable
     */
    public function onReceive(SwooleServer $server, int $fd, int $reactor_id, string $data): void
    {
        $container = ApplicationContext::getContainer();
        $parser = $container->get(FrameParser::class);
        $dataChannel = $container->get(ChannelManager::class);

        $parser->unpack($this->format($data), $frames);
        if (empty($frames)) return;

        $handler = null;
        foreach ($frames as $frame) {
            if ($frame->type == Http2Frame::HTTP2_FRAME_TYPE_HEAD && $frame->length > 0) {
                // new request
                $handler = new StreamHandler($server, $fd, $frame);
                Context::set(StreamHandler::class, $handler);
            } elseif ($frame->type == Http2Frame::HTTP2_FRAME_TYPE_DATA && $frame->flags != Http2Frame::HTTP2_FLAG_END_STREAM) {
                // push data
                $dataChannel->channel($fd)->push($frame->payload);
            }

            //todo 判断客户端断开

            // end stream
            if ($frame->flags == Http2Frame::HTTP2_FLAG_END_STREAM) {
                $dataChannel->channel($fd)->push(Http2Frame::EOF);
            }
        }
        if (!$handler) return;

        try {
            CoordinatorManager::until(Constants::WORKER_START)->yield();
            [$psr7Request,] = $this->initRequestAndResponse($handler->getRequest(), new Response());

            $psr7Request = $this->coreMiddleware->dispatch($psr7Request);
            /** @var Dispatched $dispatched */
            $dispatched = $psr7Request->getAttribute(Dispatched::class);
            $middlewares = $this->middlewares;

            if ($dispatched->isFound()) {
                $registeredMiddlewares = MiddlewareManager::get($this->serverName, $dispatched->handler->route, $psr7Request->getMethod());
                $middlewares = array_merge($middlewares, $registeredMiddlewares);
            }

            $this->dispatcher->dispatch($psr7Request, $middlewares, $this->coreMiddleware);

            // close request
            $handler->end();
        } catch (Throwable $throwable) {
            $this->container->get(SafeCaller::class)->call(function () use ($throwable) {
                return $this->exceptionHandlerDispatcher->dispatch($throwable, $this->exceptionHandlers);
            });
            $handler->end(StatusCode::ABORTED, '500#Service error!');
        } finally {
            // close the data channel
            $dataChannel->remove($fd);
        }
    }

    public function format($data): string
    {
        if (str_contains($data, "PRI * HTTP/2.0\r\n\r\nSM\r\n\r\n")) {
            $data = substr($data, 24);
        }
        return $data;
    }
}