<?php

declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc;

use Crayoon\HyperfGrpc\Listener\RegisterConsul4GrpcDriverListener;
use Crayoon\HyperfGrpc\Listener\RegisterGrpcServiceListener;

class ConfigProvider {
    public function __invoke(): array {
        return [
            'dependencies' => [
            ],
            'commands'     => [
            ],
            'annotations'  => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'listeners'    => [
                RegisterConsul4GrpcDriverListener::class,
                RegisterGrpcServiceListener::class
            ],
            'publish'      => [
                [
                    'id'          => 'config',
                    'description' => 'the config for grpc',
                    'source'      => __DIR__ . '/../publish/grpc.php',
                    'destination' => BASE_PATH . '/config/autoload/grpc.php',
                ],
                [
                    'id'          => 'config',
                    'description' => 'The config for tracer.',
                    'source'      => __DIR__ . '/../publish/opentracing.php',
                    'destination' => BASE_PATH . '/config/autoload/opentracing.php',
                ],
                [
                    'id'          => 'config',
                    'description' => 'The config for services.',
                    'source'      => __DIR__ . '/../publish/services.php',
                    'destination' => BASE_PATH . '/config/autoload/services.php',
                ],
            ]
        ];
    }
}
