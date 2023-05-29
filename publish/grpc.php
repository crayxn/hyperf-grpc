<?php
declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

return [
    // 服务注册
    "register"   => [
        //是否开启注册
        "enable"      => (bool)\Hyperf\Support\env("REGISTER_ENABLE", true),
        // 对应服务名称 默认grpc
        "server_name" => "grpc",
        // 支持 nacos、consul4grpc、consul
        "driver_name" => \Hyperf\Support\env("REGISTER_DRIVER", "nacos"),
        // 负载算法 支持 random、round-robin、weighted-random、weighted-round-robin 默认round-robin
        "algo"        => \Hyperf\Support\env("REGISTER_ALGO", "round-robin"),
    ],
    "trace"      => [
        //是否开启追踪
        "enable" => (bool)\Hyperf\Support\env("TRACER_ENABLE", true)
    ],
    "reflection" => [
        //是否开启服务反射 默认是true
        "enable"     => (bool)\Hyperf\Support\env("REFLECTION_ENABLE", true),
        //反射路径 指protoc生成的GPBMetadata文件路径
        "path"       => \Hyperf\Support\env("REFLECTION_PATH", 'app/Grpc/GPBMetadata'),
        //需要引入的 基础proto文件名 如 google/protobuf/struct.proto
        "base_files" => [
            'google/protobuf/struct.proto',
            'google/protobuf/empty.proto',
            'google/protobuf/any.proto',
            'google/protobuf/timestamp.proto',
            'google/protobuf/duration.proto'
        ],
    ]
];