# crayoon/hyperf-grpc

Hyperf Grpc 服务插件，协助完成grpc服务注册、服务链路追踪、服务健康、服务反射等

使用教程 https://learnku.com/articles/75681 如果有帮助到您的话，还请给个星哦

*请先阅读hyperf文档grpc服务一节 https://hyperf.wiki/3.0/#/zh-cn/grpc*

## Update
### [2.x]
- 服务处理重写，支持GRPC全模式（一元模式、客户端流模式、服务端流模式、双向流模式）

### [1.x] 
- 重构服务反射，提升速度
- TracerDriver 默认配置调整为Noop类型
- 服务注册优化异常时跳过
- 增加支持 Grpc Streaming
- 增积支持 GrpcHealth Watch Streaming模式

## 快速开始

### 引入

```
composer require crayoon/hyperf-grpc dev-2.0-alpha
```

### 生成配置文件

```
php bin/hyperf.php vendor:publish crayoon/hyperf-grpc
```

### 配置
修改 config/autoload/annotations.php
```php
return [
    'scan' => [
        'paths' => [
            BASE_PATH . '/app',
        ],
        'ignore_annotations' => [
            'mixin',
        ],
        'class_map' => [
            \Google\Protobuf\Internal\DescriptorPool::class => BASE_PATH.'/vendor/crayoon/hyperf-grpc/class_map/Protobuf/DescriptorPool.php'
        ]
    ]
];
```
修改 config/autoload/server.php
```php
    'servers' => [
        [
            'name' => 'grpc',
            'type' => Server::SERVER_BASE,
            'host' => '0.0.0.0',
            'port' => 9501,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_RECEIVE => [\Crayoon\HyperfGrpc\Server\StreamServer::class, 'onReceive']
            ],
        ],
        ...
    ],
```

### 流模式使用

```php
// config/routes.php
// 路由使用助手类注册
GrpcHelper::RegisterRoutes(function () {
    // 在此处添加路由
    Router::addGroup('/goods.v1.Goods', function () {
        Router::post('/info', [\App\Controller\Grpc\GoodsController::class, "info"]);
        ...
    });
    ...
}, 'grpc', [], true);
```

可以参考 https://github.com/crayxn/grpc-stream-demo
