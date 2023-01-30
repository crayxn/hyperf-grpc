<?php

declare(strict_types=1);
/**
 * @author   crayoon
 * @contact  so.wo@foxmail.com
 */

namespace Crayoon\HyperfGrpc\Listener;

use Hyperf\Consul\Exception\ServerException;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\IPReaderInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\MainWorkerStart;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Router\Handler;
use Hyperf\ServiceGovernance\DriverManager;
use Hyperf\ServiceGovernance\ServiceManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

class RegisterGrpcServiceListener implements ListenerInterface {
    protected LoggerInterface $logger;

    protected ServiceManager $serviceManager;

    protected ConfigInterface $config;

    protected IPReaderInterface $ipReader;

    protected DriverManager $governanceManager;

    protected DispatcherFactory $dispatcherFactory;

    /**
     * @param ContainerInterface $container
     * @throws ContainerExceptionInterface | NotFoundExceptionInterface
     */
    public function __construct(ContainerInterface $container) {
        $this->logger            = $container->get(StdoutLoggerInterface::class);
        $this->serviceManager    = $container->get(ServiceManager::class);
        $this->config            = $container->get(ConfigInterface::class);
        $this->ipReader          = $container->get(IPReaderInterface::class);
        $this->governanceManager = $container->get(DriverManager::class);
        $this->dispatcherFactory = $container->get(DispatcherFactory::class);
    }

    public function listen(): array {
        return [
            MainWorkerStart::class,
        ];
    }

    public function process(object $event): void {
        if ($this->config->get("grpc.register.enable") !== true) {
            $this->logger->info("grpc service register closed!");
            return;
        }
        $continue   = true;
        $protocol   = 'grpc';
        $serverName = $this->config->get("grpc.register.server_name", "grpc");
        $driverName = $this->config->get("grpc.register.driver_name", "nacos");
        $services   = [];
        $routes     = $this->dispatcherFactory
            ->getRouter($serverName)
            ->getData();
        /**
         * @var Handler $handler
         */
        if (!empty($routes) && isset($routes[0]['POST'])) foreach ($routes[0]['POST'] as $handler) {
            if (isset($handler->options['register']) && !$handler->options['register']) {
                continue;
            }
            $service = trim(current(explode(".", $handler->route)), "/");
            if (!in_array($service, $services)) {
                $services[] = $service;
            }
        }
        if (empty($services)) {
            $this->logger->info("grpc service is empty!");
            return;
        }
        while ($continue) {
            try {
                foreach ($services as $name) {
                    [$host, $port] = $this->getServers()[$protocol];
                    if ($governance = $this->governanceManager->get($driverName)) {
                        if (!$governance->isRegistered($name, $host, (int)$port, ['protocol' => $protocol])) {
                            $governance->register($name, $host, $port, ['protocol' => $protocol]);
                        }
                    }
                }
                $continue = false;
            } catch (ServerException $throwable) {
                if (str_contains($throwable->getMessage(), 'Connection failed')) {
                    $this->logger->warning('Cannot register service, connection of service center failed, re-register after 10 seconds.');
                    sleep(10);
                } else {
                    $continue = false;
                    $this->logger->error($throwable->getMessage());
                }
            } catch (\Throwable $throwable) {
                $this->logger->error($throwable->getMessage());
                // 打印完日志就 退出吧
                $continue = false;
            }
        }
    }

    protected function getServers(): array {
        $result  = [];
        $servers = $this->config->get('server.servers', []);
        foreach ($servers as $server) {
            if (!isset($server['name'], $server['host'], $server['port'])) {
                continue;
            }
            if (!$server['name']) {
                throw new \Exception('Invalid server name');
            }
            $host = $server['host'];
            if (in_array($host, ['0.0.0.0', 'localhost'])) {
                $host = $this->ipReader->read();
            }
            if (!filter_var($host, FILTER_VALIDATE_IP)) {
                throw new \Exception(sprintf('Invalid host %s', $host));
            }
            $port = $server['port'];
            if (!is_numeric($port) || ($port < 0 || $port > 65535)) {
                throw new \Exception(sprintf('Invalid port %s', $port));
            }
            $port                    = (int)$port;
            $result[$server['name']] = [$host, $port];
        }
        return $result;
    }
}