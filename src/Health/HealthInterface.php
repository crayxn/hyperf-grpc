<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: health.proto

namespace Crayoon\HyperfGrpc\Health;

/**
 * Protobuf type <code>grpc.health.v1.Health</code>
 */
interface HealthInterface
{
    /**
     * Method <code>check</code>
     *
     * @param \Crayoon\HyperfGrpc\Health\HealthCheckRequest $request
     * @return \Crayoon\HyperfGrpc\Health\HealthCheckResponse
     */
    public function check(\Crayoon\HyperfGrpc\Health\HealthCheckRequest $request);

    /**
     * Method <code>watch</code>
     *
     * @param \Crayoon\HyperfGrpc\Health\HealthCheckRequest $request
     * @return \Crayoon\HyperfGrpc\Health\HealthCheckResponse
     */
    public function watch(\Crayoon\HyperfGrpc\Health\HealthCheckRequest $request);

}

