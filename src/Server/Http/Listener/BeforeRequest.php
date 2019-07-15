<?php
namespace Imi\Server\Http\Listener;

use Imi\RequestContext;
use Imi\Bean\Annotation\ClassEventListener;
use Imi\Server\Event\Param\RequestEventParam;
use Imi\Server\Event\Listener\IRequestEventListener;
use Imi\App;
use Imi\Worker;
use Imi\Util\Coroutine;

/**
 * request事件前置处理
 * @ClassEventListener(className="Imi\Server\Http\Server",eventName="request",priority=PHP_INT_MAX)
 */
class BeforeRequest implements IRequestEventListener
{
    /**
     * 事件处理方法
     * @param RequestEventParam $e
     * @return void
     */
    public function handle(RequestEventParam $e)
    {
        if(!Worker::isWorkerStartAppComplete())
        {
            $GLOBALS['WORKER_START_END_RESUME_COIDS'][] = Coroutine::getuid();
            Coroutine::suspend();
        }
        
        // 上下文创建
        RequestContext::create();
        RequestContext::set('server', $e->request->getServerInstance());
        RequestContext::set('request', $e->request);
        RequestContext::set('response', $e->response);

        // 中间件
        $dispatcher = RequestContext::getServerBean('HttpDispatcher');
        $dispatcher->dispatch($e->request, $e->response);
    }
}