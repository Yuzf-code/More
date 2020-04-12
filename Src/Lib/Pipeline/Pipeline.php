<?php

namespace More\Src\Lib\Pipeline;


use More\Src\Core\Container;

/**
 * 流水线/管道类
 * 可用于实现责任链、中间件
 * Class Pipeline
 * @package More\Src\Lib\Pipeline
 */
class Pipeline
{
    /**
     * 管道组
     * @var array
     */
    protected $pipes;

    /**
     * 需要经过流水线加工的对象
     * @var mixed
     */
    protected $product;

    /**
     * 管道对象处理方法名
     * @var string
     */
    protected $method = 'handle';

    /**
     * @var Container
     */
    protected $container;

    /**
     * Pipeline constructor.
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * 在流水线中新增一个管道
     * @param mixed $pipe
     * @return $this
     */
    public function use($pipe)
    {
        $this->pipes[] = $pipe;
        return $this;
    }

    /**
     * 定义流水线的各管道
     * @param array $pipes
     * @return $this
     */
    public function trough(array $pipes)
    {
        $this->pipes = $pipes;
        return $this;
    }

    /**
     * 向流水线发送原材料
     * @param $product
     * @return $this
     */
    public function send($product)
    {
        $this->product = $product;
        return $this;
    }

    /**
     * 设定最终目的地并执行流水线加工对象
     * @param \Closure $destination
     * @return mixed
     */
    public function then(\Closure $destination)
    {
        $handle = array_reduce(array_reverse($this->pipes), $this->carry(), $destination);

        return $handle($this->product);
    }

    /**
     * 获取一个闭包配合array_reduce用以将管道组构造为链式调用结构
     * @return \Closure
     */
    protected function carry()
    {
        return function ($stack, $pipe) {
            return function ($product) use ($stack, $pipe) {
                if ($pipe instanceof \Closure) {
                    return $pipe($product, $stack);
                }

                if (!is_object($pipe)) {
                    $pipe = $this->container->make($pipe);
                }

                return method_exists($pipe, $this->method) ? $pipe->{$this->method}($product, $stack) : $pipe($product, $stack);
            };
        };
    }
}