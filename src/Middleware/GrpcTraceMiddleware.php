<?php

declare(strict_types=1);
/**
 * @author   crayoon
 * @contact  so.wo@foxmail.com
 */

namespace Crayoon\HyperfGrpc\Middleware;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Grpc\StatusCode;
use Hyperf\HttpMessage\Server\Response;
use Hyperf\Tracer\SpanStarter;
use OpenTracing\Tracer;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use const OpenTracing\Formats\TEXT_MAP;

class GrpcTraceMiddleware implements MiddlewareInterface {

    use SpanStarter;

    private Tracer $tracer;

    private ConfigInterface $config;

    public function __construct(private ContainerInterface $container) {
        $this->tracer = $container->get(Tracer::class);
        $this->config = $container->get(ConfigInterface::class);
    }

    /**
     * @throws Throwable
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        // 是否全局关闭 链路追踪
        if ($this->config->get("grpc.trace.enable") !== true) {
            return $handler->handle($request);
        }

        $option = [];
        // 判断存在传递的父节点
        if ($request->hasHeader("tracer.carrier")) {
            $carrier     = json_decode($request->getHeaderLine("tracer.carrier"));
            $spanContext = $this->tracer->extract(TEXT_MAP, $carrier);
            if ($spanContext) {
                $option['child_of'] = $spanContext;
            }
        }

        $path = $request->getUri()->getPath();
        $key  = "GRPC Request [RPC] {$path}";
        $span = $this->startSpan($key, $option);
        $span->setTag('rpc.path', $path);
        foreach ($request->getHeaders() as $key => $value) {
            $span->setTag('rpc.headers' . '.' . $key, implode(', ', $value));
        }
        try {
            /**
             * @var Response $response
             */
            $response = $handler->handle($request);
            $status   = $response->getTrailer("grpc-status");
            if ($status != StatusCode::OK) {
                $span->setTag('error', true);
            }
            $span->setTag('rpc.status', $status);
            $span->setTag('rpc.message', $response->getTrailer("grpc-message"));
        } catch (Throwable $e) {
            $span->setTag('error', true);
            $span->log(['message' => $e->getMessage(), 'code' => $e->getCode(), 'stacktrace' => $e->getTraceAsString()]);
            throw $e;
        } finally {
            //提交
            $span->finish();
            $this->tracer->flush();
        }
        return $response;
    }
}