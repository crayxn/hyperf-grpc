<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    'enable'    => [
        'discovery' => true,
        'register'  => true,
    ],
    'consumers' => [],
    'providers' => [],
    'drivers'   => [
        'consul' => [
            'uri'   => env("CONSUL_HOST", "consul:8500"),
            'token' => env("CONSUL_TOKEN", "consul:8500"),
            'check' => [
                'deregister_critical_service_after' => '90m',
                'interval'                          => '5s',
            ],
        ],
        'nacos'  => [
            // nacos server url like https://nacos.hyperf.io, Priority is higher than host:port
            // 'url' => '',
            // The nacos host info
            'host'         => env("NACOS_HOST", "nacos"),
            'port'         => intval(env("NACOS_PORT", 8848)),
            // The nacos account info
            'username'     => env("NACOS_USER", "nacos"),
            'password'     => env("NACOS_PWD", "nacos"),
            'guzzle'       => [
                'config' => null,
            ],
            'group_name'   => env("NACOS_GROUP", "api"),
            'namespace_id' => env("NACOS_NAMESPACE", ""),
            'heartbeat'    => intval(env("NACOS_HEARTBEAT", 5)),
            'ephemeral'    => true,
        ],
    ],
];
