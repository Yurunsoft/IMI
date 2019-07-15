<?php
namespace Imi\Log;

use Imi\App;
use Imi\Config;
use Imi\Worker;
use Imi\Util\File;
use Imi\Log\LogLevel;
use Imi\Util\Coroutine;
use Imi\Bean\BeanFactory;
use Psr\Log\AbstractLogger;
use Imi\Bean\Annotation\Bean;
use Imi\Util\Imi;
use Imi\Util\Traits\TBeanRealClass;

/**
 * @Bean("Logger")
 */
class Logger extends AbstractLogger
{
    use TBeanRealClass;

    /**
     * 核心处理器
     * @var array
     */
    protected $coreHandlers = [
        [
            'class'     => \Imi\Log\Handler\Console::class,
            'options'   => [
                'levels'    => [
                    LogLevel::INFO,
                ],
                'format'    => '{Y}-{m}-{d} {H}:{i}:{s} [{level}] {message}',
            ],
        ],
        [
            'class'     => \Imi\Log\Handler\Console::class,
            'options'   => [
                'levels' => [
                    LogLevel::DEBUG,
                    LogLevel::NOTICE,
                    LogLevel::WARNING,
                ],
            ],
        ],
        [
            'class'     => \Imi\Log\Handler\Console::class,
            'options'   => [
                'levels' => [
                    LogLevel::ALERT,
                    LogLevel::CRITICAL,
                    LogLevel::EMERGENCY,
                    LogLevel::ERROR,
                ],
                'format' => '{Y}-{m}-{d} {H}:{i}:{s} [{level}] {message} {errorFile}:{errorLine}' . PHP_EOL . 'Stack trace:' . PHP_EOL . '{trace}',
                'length' => 1024,
            ],
        ]
    ];

    /**
     * 扩展处理器
     * @var array
     */
    protected $exHandlers = [];

    /**
     * 处理器对象数组
     *
     * @var \Imi\Log\Handler\Base[]
     */
    protected $handlers = [];

    /**
     * 日志记录
     * @var \Imi\Log\Record[]
     */
    protected $records = [];
    
    /**
     * 定时器ID
     *
     * @var int
     */
    private $timerID;

    public function __init()
    {
        foreach(array_merge($this->coreHandlers, $this->exHandlers) as $handlerOption)
        {
            $this->handlers[] = BeanFactory::newInstance($handlerOption['class'], $handlerOption['options']);
        }
    }

    public function __destruct()
    {
        if(null !== $this->timerID)
        {
            swoole_timer_clear($this->timerID);
            $this->timerID = null;
        }
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        $context = $this->parseContext($context);
        $trace = $context['trace'];
        $logTime = time();
        $record = new Record($level, $message, $context, $trace, $logTime);
        foreach($this->handlers as $handler)
        {
            $handler->log($record);
        }
    }

    /**
     * 强制保存所有日志
     * @return void
     */
    public function save()
    {
        foreach($this->handlers as $handler)
        {
            $handler->save();
        }
    }

    /**
     * 获取代码调用跟踪
     * @return array
     */
    protected function getTrace($backtrace)
    {
        $index = null;
        $hasNull = false;
        $realClassName = static::__getRealClassName();
        foreach($backtrace as $i => $item)
        {
            if(isset($item['file']) && $realClassName === $item['class'] && isset($backtrace[$i + 1]['file']) && 'AbstractLogger.php' !== basename($backtrace[$i + 1]['file']))
            {
                $index = $i + 2;
                break;
            }
        }
        if(null === $index)
        {
            return [];
        }
        return array_splice($backtrace, $index);
    }

    /**
     * 获取错误文件位置
     *
     * @return array
     */
    public function getErrorFile($backtrace)
    {
        $index = null;
        $hasNull = false;
        $realClassName = static::__getRealClassName();
        foreach($backtrace as $i => $item)
        {
            if(isset($item['file']) && $realClassName === $item['class'] && isset($backtrace[$i + 1]['file']) && 'AbstractLogger.php' !== basename($backtrace[$i + 1]['file']))
            {
                $index = $i + 1;
                break;
            }
        }
        return [$backtrace[$index]['file'] ?? '', $backtrace[$index]['line'] ?? 0];
    }

    /**
     * 处理context
     *
     * @param array $context
     * @return array
     */
    private function parseContext($context)
    {
        $debugBackTrace = debug_backtrace();
        if(!isset($context['trace']))
        {
            $context['trace'] = $this->getTrace($debugBackTrace);
        }
        if(!isset($context['errorFile']))
        {
            list($file, $line) = $this->getErrorFile($debugBackTrace);
            $context['errorFile'] = $file;
            $context['errorLine'] = $line;
        }
        return $context;
    }
    
    /**
     * 增加扩展处理器
     *
     * @param array $exHandler
     * @return void
     */
    public function addExHandler($exHandler)
    {
        $this->exHandlers = $exHandler;
        $this->handlers[] = BeanFactory::newInstance($exHandler['class'], $exHandler['options']);
    }
}