<?php
namespace Imi\Model;

use Imi\Db\Db;
use Imi\Util\Text;
use Imi\Event\Event;
use Imi\Bean\BeanFactory;
use Imi\Model\Relation\Query;
use Imi\Util\LazyArrayObject;
use Imi\Model\Relation\Update;
use Imi\Util\Traits\TBeanRealClass;
use Imi\Model\Event\ModelEvents;
use Imi\Db\Query\Interfaces\IQuery;
use Imi\Db\Query\Interfaces\IResult;
use Imi\Model\Event\Param\InitEventParam;

/**
 * 常用的数据库模型
 */
abstract class Model extends BaseModel
{
    use TBeanRealClass;

    public function __init($data = [])
    {
        $this->on(ModelEvents::AFTER_INIT, function(InitEventParam $e){
            ModelRelationManager::initModel($this);
        }, PHP_INT_MAX);
        parent::__init($data);
    }

    /**
     * 返回一个查询器
     * @param string|object $object
     * @return \Imi\Db\Query\Interfaces\IQuery
     */
    public static function query($object = null)
    {
        if($object)
        {
            $class = BeanFactory::getObjectClass($object);
        }
        else
        {
            $class = static::__getRealClassName();
        }
        return Db::query(ModelManager::getDbPoolName($class), $class)->table(ModelManager::getTable($class));
    }

    /**
     * 查找一条记录
     * @param callable|mixed ...$ids
     * @return static
     */
    public static function find(...$ids)
    {
        if(!isset($ids[0]))
        {
            return null;
        }
        $query = static::query();
        if(is_callable($ids[0]))
        {
            // 回调传入条件
            call_user_func($ids[0], $query);
        }
        else
        {
            // 传主键值
            if(is_array($ids[0]))
            {
                // 键值数组where条件
                foreach($ids[0] as $k => $v)
                {
                    $query->where($k, '=', $v);
                }
            }
            else
            {
                // 主键值
                $id = ModelManager::getId(static::__getRealClassName());
                if(is_string($id))
                {
                    $id = [$id];
                }
                foreach($id as $i => $idName)
                {
                    if(!isset($ids[$i]))
                    {
                        break;
                    }
                    $query->where($idName, '=', $ids[$i]);
                }
            }
        }

        // 查找前
        Event::trigger(static::__getRealClassName() . ':' . ModelEvents::BEFORE_FIND, [
            'ids'   => $ids,
            'query' => $query,
        ], null, \Imi\Model\Event\Param\BeforeFindEventParam::class);

        $result = $query->select()->get();

        // 查找后
        Event::trigger(static::__getRealClassName() . ':' . ModelEvents::AFTER_FIND, [
            'ids'   => $ids,
            'model' => &$result,
        ], null, \Imi\Model\Event\Param\AfterFindEventParam::class);

        return $result;
    }

    /**
     * 查询多条记录
     * @param array|callable $where
     * @return static[]
     */
    public static function select($where = null)
    {
        $query = static::parseWhere(static::query(), $where);

        // 查询前
        Event::trigger(static::__getRealClassName() . ':' . ModelEvents::BEFORE_SELECT, [
            'query' => $query,
        ], null, \Imi\Model\Event\Param\BeforeSelectEventParam::class);

        $result = $query->select()->getArray();

        // 查询后
        Event::trigger(static::__getRealClassName() . ':' . ModelEvents::AFTER_SELECT, [
            'result' => &$result,
        ], null, \Imi\Model\Event\Param\AfterSelectEventParam::class);

        return $result;
    }

    /**
     * 插入记录
     * 
     * @param mixed $data
     * @return IResult
     */
    public function insert($data = null): IResult
    {
        if(null === $data)
        {
            $data = static::parseSaveData($this);
        }
        else if(!$data instanceof \ArrayAccess)
        {
            $data = new LazyArrayObject($data);
        }
        $query = static::query($this);

        // 插入前
        $this->trigger(ModelEvents::BEFORE_INSERT, [
            'model' => $this,
            'data'  => $data,
            'query' => $query,
        ], $this, \Imi\Model\Event\Param\BeforeInsertEventParam::class);

        $result = $query->insert($data);
        if($result->isSuccess())
        {
            foreach(ModelManager::getFields($this) as $name => $column)
            {
                if($column->isAutoIncrement)
                {
                    $this[$name] = $result->getLastInsertId();
                    break;
                }
            }
        }

        // 插入后
        $this->trigger(ModelEvents::AFTER_INSERT, [
            'model' => $this,
            'data'  => $data,
            'result'=> $result
        ], $this, \Imi\Model\Event\Param\AfterInsertEventParam::class);

        // 子模型插入
        ModelRelationManager::insertModel($this);

        return $result;
    }

    /**
     * 更新记录
     * 
     * @param mixed $data
     * @return IResult
     */
    public function update($data = null): IResult
    {
        $query = static::query($this);
        $query = $this->parseWhereId($query);
        if(null === $data)
        {
            $data = static::parseSaveData($this);
        }
        else if(!$data instanceof \ArrayAccess)
        {
            $data = new LazyArrayObject($data);
        }

        // 更新前
        $this->trigger(ModelEvents::BEFORE_UPDATE, [
            'model' => $this,
            'data'  => $data,
            'query' => $query,
        ], $this, \Imi\Model\Event\Param\BeforeUpdateEventParam::class);

        if(!isset($query->getOption()->where[0]))
        {
            throw new \RuntimeException('use Model->update(), primary key can not be null');
        }

        $result = $query->update($data);

        // 更新后
        $this->trigger(ModelEvents::AFTER_UPDATE, [
            'model' => $this,
            'data'  => $data,
            'result'=> $result,
        ], $this, \Imi\Model\Event\Param\AfterUpdateEventParam::class);

        // 子模型更新
        ModelRelationManager::updateModel($this);

        return $result;
    }

    /**
     * 批量更新
     * @param mixed $data
     * @param array|callable $where
     * @return IResult
     */
    public static function updateBatch($data, $where = null): IResult
    {
        $class = static::__getRealClassName();
        if(Update::hasUpdateRelation($class))
        {
            $query = Db::query()->table(ModelManager::getTable($class));
            $query = static::parseWhere($query, $where);

            $list = $query->select()->getArray();
            
            foreach($list as $row)
            {
                $model = static::newInstance($row);
                $model->set($data);
                $model->update();
            }
        }
        else
        {
            $query = static::query();
            $query = static::parseWhere($query, $where);

            $updateData = static::parseSaveData($data);

            // 更新前
            Event::trigger(static::__getRealClassName() . ':' . ModelEvents::BEFORE_BATCH_UPDATE, [
                'data'  => $updateData,
                'query' => $query,
            ], null, \Imi\Model\Event\Param\BeforeBatchUpdateEventParam::class);
            
            $result = $query->update($updateData);
    
            // 更新后
            Event::trigger(static::__getRealClassName() . ':' . ModelEvents::AFTER_BATCH_UPDATE, [
                'data'  => $updateData,
                'result'=> $result,
            ], null, \Imi\Model\Event\Param\BeforeBatchUpdateEventParam::class);
    
            return $result;
        }
    }

    /**
     * 保存记录
     * @return IResult
     */
    public function save(): IResult
    {
        $query = static::query($this);
        $query = $this->parseWhereId($query);
        $data = static::parseSaveData($this);

        // 保存前
        $this->trigger(ModelEvents::BEFORE_SAVE, [
            'model' => $this,
            'data'  => $data,
            'query' => $query,
        ], $this, \Imi\Model\Event\Param\BeforeSaveEventParam::class);

        $result = $query->replace($data);

        // 保存后
        $this->trigger(ModelEvents::AFTER_SAVE, [
            'model' => $this,
            'data'  => $data,
            'result'=> $result,
        ], $this, \Imi\Model\Event\Param\BeforeSaveEventParam::class);

        return $result;
    }

    /**
     * 删除记录
     * @return IResult
     */
    public function delete(): IResult
    {
        $query = static::query($this);
        $query = $this->parseWhereId($query);

        // 删除前
        $this->trigger(ModelEvents::BEFORE_DELETE, [
            'model' => $this,
            'query' => $query,
        ], $this, \Imi\Model\Event\Param\BeforeDeleteEventParam::class);

        if(!isset($query->getOption()->where[0]))
        {
            throw new \RuntimeException('use Model->delete(), primary key can not be null');
        }
        $result = $query->delete();

        // 删除后
        $this->trigger(ModelEvents::AFTER_DELETE, [
            'model' => $this,
            'result'=> $result,
        ], $this, \Imi\Model\Event\Param\AfterDeleteEventParam::class);

        // 子模型删除
        ModelRelationManager::deleteModel($this);

        return $result;
    }

    /**
     * 查询指定关联
     *
     * @param string ...$names
     * @return void
     */
    public function queryRelations(...$names)
    {
        ModelRelationManager::queryModelRelations($this, ...$names);

        // 提取属性支持
        $propertyAnnotations = ModelManager::getExtractPropertys($this);
        foreach($names as $name)
        {
            if(isset($propertyAnnotations[$name]))
            {
                $this->parseExtractProperty($name, $propertyAnnotations[$name]);
            }
        }
    }

    /**
     * 初始化关联属性
     *
     * @param string ...$names
     * @return void
     */
    public function initRelations(...$names)
    {
        foreach($names as $name)
        {
            Query::initRelations($this, $name);
        }
    }

    /**
     * 批量删除
     * @param array|callable $where
     * @return IResult
     */
    public static function deleteBatch($where = null): IResult
    {
        $query = static::query();
        $query = static::parseWhere($query, $where);

        // 删除前
        Event::trigger(static::__getRealClassName() . ':' . ModelEvents::BEFORE_BATCH_DELETE, [
            'query' => $query,
        ], null, \Imi\Model\Event\Param\BeforeBatchDeleteEventParam::class);

        $result = $query->delete();

        // 删除后
        Event::trigger(static::__getRealClassName() . ':' . ModelEvents::AFTER_BATCH_DELETE, [
            'result'=> $result,
        ], null, \Imi\Model\Event\Param\BeforeBatchDeleteEventParam::class);

        return $result;
    }

    /**
     * 统计数量
     * @param string $field
     * @return int
     */
    public static function count($field = '*')
    {
        return static::aggregate('count', $field);
    }

    /**
     * 求和
     * @param string $field
     * @return float
     */
    public static function sum($field)
    {
        return static::aggregate('sum', $field);
    }

    /**
     * 平均值
     * @param string $field
     * @return float
     */
    public static function avg($field)
    {
        return static::aggregate('avg', $field);
    }
    
    /**
     * 最大值
     * @param string $field
     * @return float
     */
    public static function max($field)
    {
        return static::aggregate('max', $field);
    }
    
    /**
     * 最小值
     * @param string $field
     * @return float
     */
    public static function min($field)
    {
        return static::aggregate('min', $field);
    }

    /**
     * 聚合函数
     * @param string $functionName
     * @param string $fieldName
     * @param callable $queryCallable
     * @return mixed
     */
    public static function aggregate($functionName, $fieldName, callable $queryCallable = null)
    {
        $query = static::query();
        if(null !== $queryCallable)
        {
            // 回调传入条件
            call_user_func($queryCallable, $query);
        }
        return call_user_func([$query, $functionName], $fieldName);
    }

    /**
     * 处理主键where条件
     * @param IQuery $query
     * @return IQuery
     */
    private function parseWhereId(IQuery $query)
    {
        // 主键条件加入
        $id = ModelManager::getId($this);
        if(is_string($id))
        {
            $id = [$id];
        }
        foreach($id as $idName)
        {
            if(isset($this->$idName))
            {
                $query->where($idName, '=', $this->$idName);
            }
        }
        return $query;
    }

    /**
     * 处理where条件
     * @param IQuery $query
     * @param array $where
     * @return IQuery
     */
    private static function parseWhere(IQuery $query, $where)
    {
        if(null === $where)
        {
            return $query;
        }
        if(is_callable($where))
        {
            // 回调传入条件
            call_user_func($where, $query);
        }
        else
        {
            foreach($where as $k => $v)
            {
                if(is_array($v))
                {
                    $operation = array_shift($v);
                    $query->where($k, $operation, $v[0]);
                }
                else
                {
                    $query->where($k, '=', $v);
                }
            }
        }
        return $query;
    }

    /**
     * 处理保存的数据
     * @param object|array $data
     * @param object|string $object
     * @return array
     */
    private static function parseSaveData($data, $object = null)
    {
        // 处理前
        Event::trigger(static::__getRealClassName() . ':' . ModelEvents::BEFORE_PARSE_DATA, [
            'data'   => &$data,
            'object' => &$object,
        ], null, \Imi\Model\Event\Param\BeforeParseDataEventParam::class);

        if(is_object($data))
        {
            if(null === $object)
            {
                $object = $data;
            }
            $_data = [];
            foreach($data as $k => $v)
            {
                $_data[$k] = $v;
            }
            $data = $_data;
        }
        if($object)
        {
            $class = BeanFactory::getObjectClass($object);
        }
        else
        {
            $class = static::__getRealClassName();
        }
        $result = new LazyArrayObject;
        foreach(ModelManager::getFields($class) as $name => $column)
        {
            // 虚拟字段不参与数据库操作
            if($column->virtual)
            {
                continue;
            }
            if(array_key_exists($name, $data))
            {
                $value = $data[$name];
            }
            else
            {
                $fieldName = Text::toCamelName($name);
                if(array_key_exists($fieldName, $data))
                {
                    $value = $data[$fieldName];
                }
                else
                {
                    $value = null;
                }
            }
            if(null === $value && !$column->nullable)
            {
                continue;
            }
            switch($column->type)
            {
                case 'json':
                    if(null !== $value)
                    {
                        $value = json_encode($value);
                    }
                    break;
            }
            $result[$name] = $value;
        }

        // 处理后
        Event::trigger(static::__getRealClassName() . ':' . ModelEvents::AFTER_PARSE_DATA, [
            'data'   => &$data,
            'object' => &$object,
            'result' => &$result,
        ], null, \Imi\Model\Event\Param\AfterParseDataEventParam::class);

        return $result;
    }

}