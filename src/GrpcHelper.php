<?php

declare(strict_types=1);
/**
 * @author   crayoon
 * @contact  so.wo@foxmail.com
 */

namespace Crayoon\HyperfGrpc;

use Crayoon\HyperfGrpc\Health\Health;
use Crayoon\HyperfGrpc\Middleware\GrpcTraceMiddleware;
use Crayoon\HyperfGrpc\Reflection\Reflection;
use Hyperf\HttpServer\Router\Router;

class GrpcHelper {

    /**
     * register routers
     * @param callable $callback
     * @param string $serverName
     * @param array $options
     * @return void
     */
    public static function RegisterRoutes(callable $callback, string $serverName = "grpc", array $options = []): void {
        Router::addServer($serverName, function () use ($callback, $options) {
            //reflection
            Router::addGroup('/grpc.reflection.v1alpha.ServerReflection', function () {
                Router::post('/ServerReflectionInfo', [Reflection::class, 'serverReflectionInfo']);
            }, [
                "register" => false
            ]);

            //health
            Router::addGroup('/grpc.health.v1.Health', function () {
                Router::post('/Check', [Health::class, 'check']);
                Router::post('/Watch', [Health::class, 'watch']);
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