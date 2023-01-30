<?php

declare(strict_types=1);
/**
 * @author   crayoon
 * @contact  so.wo@foxmail.com
 */

namespace Crayoon\HyperfGrpc\Listener;

use Crayoon\HyperfGrpc\Consul\ConsulDriver;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\ServiceGovernance\DriverManager;
use Psr\Container\ContainerInterface;

class RegisterConsul4GrpcDriverListener implements ListenerInterface {
    protected DriverManager $driverManager;
    protected ConfigInterface $config;

    public function __construct(ContainerInterface $container) {
        $this->driverManager = $container->get(DriverManager::class);
        $this->config        = $container->get(ConfigInterface::class);
    }

    public function listen(): array {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event): void {
        if ($this->config->get("grpc.register.enable") && $this->config->get("grpc.register.driver_name") == "consul4grpc") {
            $this->driverManager->register('consul4grpc', make(ConsulDriver::class));
        }
    }
}