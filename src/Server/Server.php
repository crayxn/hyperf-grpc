<?php
declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc\Server;

use Hyperf\Context\Context;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\HttpMessage\Server\Response as Psr7Response;
use Hyperf\HttpServer\Event\RequestHandled;
use Hyperf\HttpServer\Event\RequestReceived;
use Hyperf\HttpServer\Event\RequestTerminated;
use Hyperf\HttpServer\MiddlewareManager;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\Support\SafeCaller;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use function Hyperf\Coroutine\defer;

class Server extends \Hyperf\GrpcServer\Server
{
    public function onRequest($request, $response): void
    {
        try {
            CoordinatorManager::until(Constants::WORKER_START)->yield();

            [$psr7Request, $psr7Response] = $this->initRequestAndResponse($request, $response);

            $this->option?->isEnableRequestLifecycle() && $this->event?->dispatch(new RequestReceived($psr7Request, $psr7Response));

            $psr7Request = $this->coreMiddleware->dispatch($psr7Request);
            /** @var Dispatched $dispatched */
            $dispatched = $psr7Request->getAttribute(Dispatched::class);
            $middlewares = $this->middlewares;

            if ($dispatched->isFound()) {
                $registeredMiddlewares = MiddlewareManager::get($this->serverName, $dispatched->handler->route, $psr7Request->getMethod());
                $middlewares = array_merge($middlewares, $registeredMiddlewares);
            }

            $psr7Response = $this->dispatcher->dispatch($psr7Request, $middlewares, $this->coreMiddleware);
        } catch (\Throwable $throwable) {
            // Delegate the exception to exception handler.
            $psr7Response = $this->container->get(SafeCaller::class)->call(function () use ($throwable) {
                return $this->exceptionHandlerDispatcher->dispatch($throwable, $this->exceptionHandlers);
            }, static function () {
                return (new Psr7Response())->withStatus(400);
            });
        } finally {
            if (isset($psr7Request) && $this->option?->isEnableRequestLifecycle()) {
                defer(fn() => $this->event?->dispatch(new RequestTerminated($psr7Request, $psr7Response ?? null, $throwable ?? null)));

                $this->event?->dispatch(new RequestHandled($psr7Request, $psr7Response ?? null, $throwable ?? null));
            }

            // Send the Response to client.
            // Add check the Response isWritable
            if (!isset($psr7Response) || !$psr7Response instanceof ResponseInterface || !$response->isWritable()) {
                return;
            }


            if (isset($psr7Request) && $psr7Request->getMethod() === 'HEAD') {
                $this->responseEmitter->emit($psr7Response, $response, false);
            } else {
                $this->responseEmitter->emit($psr7Response, $response);
            }
        }
    }
}