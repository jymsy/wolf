<?php

/**
 * Class EventEmitter 事件绑定，解绑，触发类
 *
 */
class EventEmitter
{
    /**
     * @var array Events handlers
     */
    protected $_events = array();

    /**
     * 触发事件
     * @param string $name
     * @param  Event $event
     * @throws Exception
     */
    public function trigger($name, $event){
        $name = strtolower($name);
        if(isset($this->_events[$name]))
        {
            foreach($this->_events[$name] as $handler){
                if(is_string($handler) || is_callable($handler,true)) {
                    call_user_func($handler, $event);
                }else{
 					throw new Exception('Event '.$name.' is attached with an invalid handler '.$handler);
                }
            }
        }else{
            throw new Exception('Event '.$name.' is not defined.');
        }
    }

    /**
     * 绑定事件
     *
     * @static
     * @param array|string $event
     * @param mixed    $handler
     * @return $this
     */
    public function on($event, $handler)
    {
        foreach ((array)$event as $e) {
            $this->_events[strtolower($e)][] = $handler;
        }

        return $this;
    }

    /**
     * 移除事件绑定
     *
     * @param array|string $event
     * @param mixed     $handler
     * @return $this
     */
    public function off($event, $handler)
    {
        foreach ((array)$event as $e) {
            $e = strtolower($e);
            if (!empty($this->_events[$e])) {
                // Find Listener index
                if (($key = array_search($handler, $this->_events[$e])) !== false) {
                    // Remove it
                    unset($this->_events[$e][$key]);
                }
            }
        }
        return $this;
    }

    /**
     * 获取指定事件的处理方法
     *
     * @param string $event
     * @return array
     */
    public function listeners($event)
    {
        if (!empty($this->_events[$event])) {
            return $this->_events[$event];
        }
        return array();
    }

    /**
     * 移除指定事件的所有handlers
     *
     * @param string $event
     * @return $this
     */
    public function removeAllListeners($event = null)
    {
        if ($event === null) {
            $this->_events = array();
        } else if (($event = strtolower($event)) && !empty($this->_events[$event])) {
            $this->_events[$event] = array();
        }
        return $this;
    }

}

/**
 * Event是所有事件类的基类。
 *
 * 它封装了与事件相关的参数。
 * sender属性指的是谁发起来的事件。
 * handled属性指的是事件的处理方式.
 * 如果一个事件处理程序设置了handled为true，
 * 其它未处理的事件处理程序将不会被调用。
 */
class Event {
    /**
     * @var object 事件的发起者
     */
    public $sender;
    /**
     * @var boolean 事件是否已被处理。默认为false。
     * 当这个值设置为true，其它未处理的事件将不会被处理。
     */
    public $handled=false;
    /**
     * @var mixed 附加的事件参数。
     */
    public $params;

    /**
     * @param mixed $sender 事件的发起者
     * @param mixed $params 附加的事件参数
     */
    public function __construct($sender=null,$params=null){
        $this->sender=$sender;
        $this->params=$params;
    }
}