<?php
namespace Imi\Model\Listener;

use Imi\App;
use Imi\Util\Imi;
use Imi\Tool\Tool;
use Imi\Main\Helper;
use Imi\Bean\Annotation;
use Imi\Event\EventParam;
use Imi\Util\AtomicManager;
use Imi\Event\IEventListener;
use Imi\Model\Annotation\Column;
use Imi\Util\MemoryTableManager;
use Imi\Bean\Annotation\Listener;
use Imi\Model\Annotation\MemoryTable;
use Imi\Bean\Annotation\AnnotationManager;

/**
 * @Listener(eventName="IMI.INITED", priority=19940280)
 */
class Init implements IEventListener
{
    /**
     * 事件处理方法
     * @param EventParam $e
     * @return void
     */
    public function handle(EventParam $e)
    {
        if('server' !== Tool::getToolName() || 'start' !== Tool::getToolOperation())
        {
            return;
        }

        $runtimeInfo = App::getRuntimeInfo();

        // 初始化 MemoryTable
        foreach($runtimeInfo->memoryTable as $item)
        {
            $memoryTableAnnotation = $item['annotation'];
            MemoryTableManager::addName($memoryTableAnnotation->name, [
                'size'                  => $memoryTableAnnotation->size,
                'conflictProportion'    => $memoryTableAnnotation->conflictProportion,
                'columns'               => $item['columns'],
            ]);
        }

        MemoryTableManager::init();
    }

}