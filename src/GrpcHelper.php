<?php

declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc;

use Crayoon\HyperfGrpc\Health\Health;
use Crayoon\HyperfGrpc\Health\StreamHealth;
use Crayoon\HyperfGrpc\Middleware\GrpcTraceMiddleware;
use Crayoon\HyperfGrpc\Reflection\Reflection;
use Crayoon\HyperfGrpc\Reflection\StreamReflection;
use Crayoon\HyperfGrpc\Server\Handler\StreamHandler;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Router\Router;

class GrpcHelper
{

    /**
     * register routers
     * @param callable $callback
     * @param string $serverName
     * @param array $options
     * @return void
     */
    public static function RegisterRoutes(callable $callback, string $serverName = "grpc", array $options = []): void
    {

        $streamMode = false;
        if (Context::has(StreamHandler::class)) {
            $streamMode = true;
        }

        Router::addServer($serverName, function () use ($callback, $options, $streamMode) {
            //reflection
            Router::addGroup('/grpc.reflection.v1alpha.ServerReflection', function () use ($streamMode) {
                if ($streamMode) {
                    Router::post('/ServerReflectionInfo', [StreamReflection::class, 'serverReflectionInfoStream']);
                } else {
                    Router::post('/ServerReflectionInfo', [Reflection::class, 'serverReflectionInfo']);
                }
            }, [
                "register" => false
            ]);

            //health
            Router::addGroup('/grpc.health.v1.Health', function () use ($streamMode) {
                if ($streamMode) {
                    Router::post('/Check', [StreamHealth::class, 'check']);
                    Router::post('/Watch', [StreamHealth::class, 'watch']);
                } else {
                    Router::post('/Check', [Health::class, 'check']);
                    Router::post('/Watch', [Health::class, 'watch']);
                }
            }, [
                "register" => false
            ]);

            //other
            Router::addGroup("", $callback, array_merge([
                "middleware" => [
                    GrpcTraceMiddleware::class
                ]
            ], $options));
        });
    }
}