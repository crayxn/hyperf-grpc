<?php

declare(strict_types=1);
/**
 * @author   crayoon
 * @contact  so.wo@foxmail.com
 */

namespace Crayoon\HyperfGrpc\Health;

use Crayoon\HyperfGrpc\Exception\GrpcStreamException;
use Crayoon\HyperfGrpc\Server\Response\GrpcStream;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;

class Health implements HealthInterface
{

    protected array $config;

    public function __construct(ConfigInterface $config, protected StdoutLoggerInterface $stdoutLogger)
    {
        $this->config = $config->get("grpc.health") ?? [];
    }

    public function check(HealthCheckRequest $request): HealthCheckResponse
    {
        $response = new HealthCheckResponse();
        $response->setStatus(ServingStatus::SERVING);
        return $response;
    }

    public function watch(HealthCheckRequest $request): HealthCheckResponse
    {
        $response = new HealthCheckResponse();
        $response->setStatus(ServingStatus::SERVING);
        //Streaming Response
        try {
            $stream = new GrpcStream();
            while (true) {
                if (!$stream->write($response)) {
                    break;
                };
                sleep($this->config['wait'] ?? 300);
            }
            $stream->close();
            $this->stdoutLogger->debug("Grpc watcher close");
        } catch (GrpcStreamException $exception) {
            $this->stdoutLogger->error("Create stream fail: " . $exception->getMessage());
        }

        return $response;
    }
}