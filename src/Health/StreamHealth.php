<?php
declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc\Health;

use Crayoon\HyperfGrpc\Server\Handler\StreamHandler;
use Hyperf\Context\Context;

class StreamHealth extends Health
{
    public function streamCheck(): void
    {
        /**
         * @var StreamHandler $handler
         */
        $handler = Context::get(StreamHandler::class);
        $response = new HealthCheckResponse();
        $response->setStatus(ServingStatus::SERVING);
        $handler->push($response);
    }

    public function streamWatch(): void
    {
        /**
         * @var StreamHandler $handler
         */
        $handler = Context::get(StreamHandler::class);
        $response = new HealthCheckResponse();
        $response->setStatus(ServingStatus::SERVING);
        //Streaming Response
        while (true) {
            if (!$handler->push($response)) {
                break;
            };
            sleep($this->config['wait'] ?? 300);
        }
        $this->stdoutLogger->debug("Grpc watcher close");
    }
}