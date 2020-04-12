<?php
namespace More\Src\Core\Http;

use duncan3dc\Laravel\BladeInstance;
use More\Src\Core\App;
use More\Src\Core\Route\RouteRule;
use More\Src\Lib\Pipeline\Pipeline;

class Dispatcher
{
    // 应用容器
    protected $app;
    // 控制器命名空间前缀
    protected $nameSpacePrefix;

    public function __construct(App $app, $controllerNameSpace)
    {
        $this->app = $app;
        $this->nameSpacePrefix = trim($controllerNameSpace, '\\');
    }

    /**
     * 路由调度
     * @param Request $request
     * @param Response $response
     * @param BladeInstance $view
     * @throws \Exception
     */
    public function dispatch(Request $request, Response $response, BladeInstance $view)
    {
        $router = $this->app->make('router', [$request->getMethod(), $request->getPathInfo()]);

        $routeInfo = $router->dispatch();
        switch ($routeInfo['status']) {
            case RouteRule::NOT_FOUND:
                $list = explode('/', $routeInfo['target']);
                $controllerNamespace = $this->nameSpacePrefix;
                for ($i = 0; $i < count($list) - 1; $i++) {
                    $controllerNamespace = $controllerNamespace . "\\" . ucfirst($list[$i]);
                }
                $request->setControllerNamespace($controllerNamespace . 'Controller');
                $request->setActionName($list[$i]);
                break;
            case RouteRule::FOUND:
                $params = $request->get();
                // key相同的情况下，路由变量优先
                $request->setRequestParams($routeInfo['args'] + $params);

                if (is_callable($routeInfo['target'])) {
                    // 未绑定控制器，直接调用
                    call_user_func_array($routeInfo['target'], [$request, $response, $view]);
                    return $response;
                } elseif (is_string($routeInfo['target'])) {
                    $list = explode('@', $routeInfo['target']);
                    $request->setControllerNamespace($list[0]);
                    $request->setActionName($list[1]);
                }
                break;
        }

        return $this->runAction($request, $response, $view);
    }

    /**
     * 执行控制器方法
     * @param Request $request
     * @param Response $response
     * @param BladeInstance $view
     * @throws \ReflectionException
     */
    public function runAction(Request $request, Response $response, BladeInstance $view)
    {
        $controllerNamespace = $request->getControllerNamespace();
        if (class_exists($controllerNamespace)) {
            /**  @var Controller  */
            $obj = new $controllerNamespace($request, $response, $view);

            (new Pipeline($this->app))->send($request)
                ->trough($obj->getMiddlewares())
                ->then(function ($request) use ($obj) {
                    $actionName = $request->getActionName();
                    if (method_exists($obj, $actionName)) {
                        $this->app->call([$obj, $actionName]);
                    } else {
                        $obj->actionNotFound();
                    }
                });
        } else {
            // 返回404
            $response->withStatus(404);
            $response->write('<h1>page not found</h1>');
        }

        return $response;
    }
}