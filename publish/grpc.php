<?php
return [
    // 服务注册
    "register"   => [
        "enable"      => (bool)env("REGISTER_ENABLE", true), //是否开启注册
        "server_name" => "grpc", // 对应服务名称 默认grpc
        "driver_name" => env("REGISTER_DRIVER", "nacos"), // 支持 nacos、consul4grpc、consul
        "algo"        => env("REGISTER_ALGO", "round-robin"), // 负载算法 支持 random、round-robin、weighted-random、weighted-round-robin 默认round-robin
    ],
    "trace"      => [
        "enable" => (bool)env("TRACER_ENABLE", true) //是否开启追踪
    ],
    "reflection" => [
        "enable"                 => (bool)env("REFLECTION_ENABLE", true), //是否开启服务反射 默认是true
        "path"                   => env("REFLECTION_PATH", 'app/Grpc/GPBMetadata'), //反射路径 指protoc生成的GPBMetadata文件路径
        "base_class"             => [], //需要引入的 基础类 如 google/protobuf/Struct
        "route_to_proto_pattern" => "", //路由服务名称转proto名称正则 如 /(.*?)Srv/
    ]
];