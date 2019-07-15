<?php
namespace Imi\Server\Http\Middleware;

use Imi\RequestContext;
use Imi\Util\Http\Response;
use Imi\Bean\Annotation\Bean;
use Imi\Controller\HttpController;
use Imi\Server\Http\Message\Request;
use Imi\Server\View\Parser\ViewParser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @Bean
 */
class ActionMiddleware implements MiddlewareInterface
{
    /**
     * 处理方法
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 获取Response对象
        $response = $handler->handle($request);
        // 获取路由结果
        $result = RequestContext::get('routeResult');
        if(null === $result)
        {
            throw new \RuntimeException('RequestContent not found routeResult');
        }
        // 路由匹配结果是否是[控制器对象, 方法名]
        $isObject = is_array($result['callable']) && isset($result['callable'][0]) && $result['callable'][0] instanceof HttpController;
        if($isObject)
        {
            // 复制一份控制器对象
            $result['callable'][0] = clone $result['callable'][0];
            // 传入Request和Response对象
            $result['callable'][0]->request = $request;
            $result['callable'][0]->response = $response;
        }
        // 执行动作
        $actionResult = call_user_func_array($result['callable'], $this->prepareActionParams($request, $result));
        if($isObject)
        {
            // 获得控制器中的Response
            $response = $result['callable'][0]->response;
        }
        // 视图
        if($actionResult instanceof \Imi\Server\View\Annotation\View)
        {
            // 动作返回的值是@View注解
            $viewAnnotation = $actionResult;
        }
        else if($actionResult instanceof Response)
        {
            $response = $actionResult;
        }
        else
        {
            // 获取对应动作的视图注解
            $viewAnnotation = clone ViewParser::getInstance()->getByCallable($result['callable']);
            if(null !== $viewAnnotation)
            {
                if(is_array($actionResult))
                {
                    // 动作返回值是数组，合并到视图注解
                    $viewAnnotation->data = array_merge($viewAnnotation->data, $actionResult);
                }
                else
                {
                    // 非数组直接赋值
                    $viewAnnotation->data = $actionResult;
                }
            }
        }

        if(isset($viewAnnotation))
        {
            // 视图渲染
            $view = RequestContext::getServerBean('View');
            $response = $view->render($viewAnnotation, $response);
        }

        return $response;
    }
    
    /**
     * 准备调用action的参数
     * @param Request $e
     * @param array $routeResult
     * @return array
     */
    private function prepareActionParams(Request $request, $routeResult)
    {
        // 根据动作回调类型获取反射
        try{
            if(is_array($routeResult['callable']))
            {
                $ref = new \ReflectionMethod($routeResult['callable'][0], $routeResult['callable'][1]);
            }
            else if(!$routeResult['callable'] instanceof \Closure)
            {
                $ref = new \ReflectionFunction($routeResult['callable']);
            }
            else
            {
                return [];
            }
        }
        catch(\Throwable $ex)
        {
            return [];
        }
        $result = [];
        foreach($ref->getParameters() as $param)
        {
            if(isset($routeResult['params'][$param->name]))
            {
                // 路由解析出来的参数
                $result[] = $routeResult['params'][$param->name];
            }
            else if($request->hasPost($param->name))
            {
                // post
                $result[] = $request->post($param->name);
            }
            else if($request->hasGet($param->name))
            {
                // get
                $result[] = $request->get($param->name);
            }
            else if($param->isOptional())
            {
                // 方法默认值
                $result[] = $param->getDefaultValue();
            }
            else
            {
                $result[] = null;
            }
        }
        return $result;
    }
    
}