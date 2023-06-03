<?php
declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc\Exception;

use Hyperf\Grpc\StatusCode;
use Hyperf\GrpcServer\Exception\Handler\GrpcExceptionHandler;
use PHPUnit\Event\Code\Throwable;

class GrpcException extends \Hyperf\GrpcServer\Exception\GrpcException
{
    public function __construct(string $message = '', int $code = 0, int $statusCode = StatusCode::ABORTED, ?Throwable $previous = null)
    {
        $message = "$code#$message";
        parent::__construct($message, $statusCode, $previous);
    }
}