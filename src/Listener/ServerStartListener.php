<?php
declare(strict_types=1);
/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */

namespace Crayoon\HyperfGrpc\Listener;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\OnStart;

class ServerStartListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            OnStart::class
        ];
    }

    public function process(object $event): void
    {
        if($event instanceof \Swoole\Server){
            /**
             * @var ContainerInterface $container
             */
            $container = ApplicationContext::getContainer();
            $container->set(\Swoole\Server::class, $event);
        }
    }
}