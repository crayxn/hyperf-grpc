<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Crayoon\HyperfGrpc\Health;

/**
 */
class HealthClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \Crayoon\HyperfGrpc\Health\HealthCheckRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function Check(\Crayoon\HyperfGrpc\Health\HealthCheckRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/grpc.health.v1.Health/Check',
        $argument,
        ['\Crayoon\HyperfGrpc\Health\HealthCheckResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Crayoon\HyperfGrpc\Health\HealthCheckRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function Watch(\Crayoon\HyperfGrpc\Health\HealthCheckRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/grpc.health.v1.Health/Watch',
        $argument,
        ['\Crayoon\HyperfGrpc\Health\HealthCheckResponse', 'decode'],
        $metadata, $options);
    }

}
