<?php

declare(strict_types=1);
/**
 * @author   crayoon
 * @contact  so.wo@foxmail.com
 */

namespace Crayoon\HyperfGrpc\Health;

class Health implements HealthInterface {
    public function check(HealthCheckRequest $request): HealthCheckResponse {
        $response = new HealthCheckResponse();
        $response->setStatus(ServingStatus::SERVING);
        return $response;
    }

    public function watch(HealthCheckRequest $request): HealthCheckResponse {
        $response = new HealthCheckResponse();
        $response->setStatus(ServingStatus::SERVING);
        return $response;
    }
}