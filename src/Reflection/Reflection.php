<?php

declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc\Reflection;

use Google\Protobuf\Internal\DescriptorPool;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\ReflectionManager;
use Hyperf\Grpc\StatusCode;
use Hyperf\GrpcServer\Exception\GrpcException;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Router\Handler;
use Hyperf\Utils\Str;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class Reflection implements ServerReflectionInterface
{
    protected ConfigInterface $config;

    protected DispatcherFactory $dispatcherFactory;

    protected array $servers = [];
    protected array $files = [];

    protected array $baseProtoFiles = [];

    public function __construct(protected ContainerInterface $container)
    {
        $this->config = $this->container->get(ConfigInterface::class);
        $this->dispatcherFactory = $this->container->get(DispatcherFactory::class);
        $this->baseProtoFiles = $this->config->get('grpc.reflection.base_files', []);

        $paths = $this->config->get("grpc.reflection.path");
        $class = ReflectionManager::getAllClasses(is_array($paths) ? $paths : [$paths]);
        foreach ($class as $item => $reflection) {
            call_user_func("{$item}::initOnce");
        }

        //获取服务
        $this->servers = $this->servers();
    }

    /**
     * @param ServerReflectionRequest $request
     * @return ServerReflectionResponse
     */
    public function serverReflectionInfo(ServerReflectionRequest $request): ServerReflectionResponse
    {
        // Get gpb class pool
        $descriptorPool = DescriptorPool::getGeneratedPool();
        // New response
        $resp = new ServerReflectionResponse();
        $resp->setOriginalRequest($request);
        switch ($request->getMessageRequest()) {
            case "list_services":
                $servers = [];
                foreach (array_keys($this->servers) as $server) {
                    $servers[] = (new ServiceResponse())->setName($server);
                }
                $resp->setListServicesResponse(
                    (new ListServiceResponse())->setService($servers)
                );
                break;
            case "file_containing_symbol":
                $symbol = $request->getFileContainingSymbol();
                // set file
                $resp->setFileDescriptorResponse(
                    (new FileDescriptorResponse())->setFileDescriptorProto(array_merge([
                        $this->servers[$symbol]],
                        $this->otherProto($descriptorPool)
                    ))
                );
                break;
            case "file_by_filename":
                $fileName = $request->getFileByFilename();
                $file = $descriptorPool->getContentByProtoName($fileName);
                if (empty($file)) throw new GrpcException("{$fileName} not found", StatusCode::ABORTED);
                $resp->setFileDescriptorResponse(
                    (new FileDescriptorResponse())->setFileDescriptorProto([$file])
                );
                break;
        }
        return $resp;
    }

    /**
     * Get google proto
     * @param DescriptorPool $descriptorPool
     * @return array
     */
    private function otherProto(DescriptorPool $descriptorPool): array
    {
        $tmp = [];
        foreach ($this->baseProtoFiles as $proto) {
            if ('' !== $file = $descriptorPool->getContentByProtoName($proto)) $tmp[] = $file;
        }
        return $tmp;
    }

    /**
     * Get server by router
     * @return array
     */
    private function servers(): array
    {
        // get gpb class pool
        $descriptorPool = DescriptorPool::getGeneratedPool();

        $routes = $this->dispatcherFactory
            ->getRouter($this->config->get("grpc.register.server_name", "grpc"))
            ->getData();
        $services = [];
        /**
         * @var Handler $handler
         */
        if (!empty($routes) && isset($routes[0]['POST'])) foreach ($routes[0]['POST'] as $handler) {
            $service = current(explode("/", trim($handler->route, "/")));
            $file = $descriptorPool->getContentByServerName($service);
            if (!isset($services[$service]) && '' != $file) {
                $services[$service] = $file;
            }
        }
        return $services;
    }
}