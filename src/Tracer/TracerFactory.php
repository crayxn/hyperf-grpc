<?php
declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc\Tracer;

use Exception;
use Hyperf\Stringable\Str;
use Hyperf\Tracer\Contract\NamedFactoryInterface;

class TracerFactory implements NamedFactoryInterface
{
    /**
     * @throws Exception
     */
    public function make(string $name): \OpenTracing\Tracer
    {
        $class = sprintf("OpenTracing\\%sTracer", Str::studly($name));
        if (!class_exists($class)) {
            throw new Exception("$class Tracer no found");
        }
        return new $class;
    }
}