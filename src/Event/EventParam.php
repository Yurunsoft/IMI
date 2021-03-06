<?php

namespace Imi\Event;

class EventParam
{
    /**
     * 事件名称.
     *
     * @var string
     */
    protected $__eventName;

    /**
     * 触发该事件的对象
     *
     * @var object|null
     */
    protected $__target;

    /**
     * 数据.
     *
     * @var array
     */
    protected $__data = [];

    /**
     * 阻止事件继续传播.
     *
     * @var bool
     */
    protected $__stopPropagation = false;

    /**
     * @param string      $eventName
     * @param array       $data
     * @param object|null $target
     */
    public function __construct($eventName, $data = [], $target = null)
    {
        $this->__eventName = $eventName;
        $this->__target = $target;
        $this->__data = $data;
        if ($data)
        {
            foreach ($data as $key => &$value)
            {
                $this->$key = &$value;
            }
        }
    }

    /**
     * 获取事件名称.
     *
     * @return string
     */
    public function getEventName()
    {
        return $this->__eventName;
    }

    /**
     * 获取触发该事件的对象
     *
     * @return object
     */
    public function getTarget()
    {
        return $this->__target;
    }

    /**
     * 获取数据.
     *
     * @return array
     */
    public function getData()
    {
        return $this->__data;
    }

    /**
     * 阻止事件继续传播.
     *
     * @param bool $isStop 是否阻止事件继续传播
     *
     * @return void
     */
    public function stopPropagation($isStop = true)
    {
        $this->__stopPropagation = $isStop;
    }

    /**
     * 是否阻止事件继续传播.
     *
     * @return bool
     */
    public function isPropagationStopped()
    {
        return $this->__stopPropagation;
    }
}
