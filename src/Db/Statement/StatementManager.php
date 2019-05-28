<?php
namespace Imi\Db\Statement;

use Imi\RequestContext;
use Imi\Db\Interfaces\IDb;
use Imi\Db\Interfaces\IStatement;

abstract class StatementManager
{
    /**
     * statement 缓存数据
     *
     * @var array
     */
    private static $statements = [];

    /**
     * 设置statement缓存
     *
     * @param IStatement $statement
     * @param bool $using
     * @return void
     */
    public static function set(IStatement $statement, bool $using)
    {
        static::$statements[$statement->getDb()->hashCode()][$statement->getSql()] = [
            'statement'     =>  $statement,
            'using'         =>  $using,
        ];
        if($using && RequestContext::exsits())
        {
			$statementCaches = RequestContext::get('statementCaches', []);
			$statementCaches[] = $statement;
			RequestContext::set('statementCaches', $statementCaches);
        }
    }

    /**
     * 获取连接中对应sql的statement
     * 
     * 返回数组则代表获取成功
     * 返回 null 代表没有缓存
     * 返回 false 代表当前缓存不可用
     *
     * @param IDb $db
     * @param string $sql
     * @return array|null|boolean
     */
    public static function get(IDb $db, string $sql)
    {
        $hashCode = $db->hashCode();
        $result = static::$statements[$hashCode][$sql] ?? null;
        if(null === $result)
        {
            return $result;
        }
        if($result['using'])
        {
            return false;
        }
        static::$statements[$hashCode][$sql]['using'] = true;
        $statement = static::$statements[$hashCode][$sql];
        if(RequestContext::exsits())
        {
            $statementCaches = RequestContext::get('statementCaches', []);
            $statementCaches[] = $statement['statement'];
            RequestContext::set('statementCaches', $statementCaches);
        }
        return $statement;
    }

    /**
     * 将statement设为可用
     *
     * @param IStatement $statement
     * @return void
     */
    public static function unUsingStatement(IStatement $statement)
    {
        return static::unUsing($statement->getDb(), $statement->getSql());
    }

    /**
     * 将连接中对应sql的statement设为可用
     *
     * @param IDb $db
     * @param string $sql
     * @return void
     */
    public static function unUsing(IDb $db, string $sql)
    {
        $hashCode = $db->hashCode();
        if(isset(static::$statements[$hashCode][$sql]))
        {
            static::$statements[$hashCode][$sql]['using'] = false;
        }
    }

    /**
     * 将连接中所有statement设为可用
     *
     * @param IDb $db
     * @return void
     */
    public static function unUsingAll(IDb $db)
    {
        foreach(static::$statements[$db->hashCode()] as &$item)
        {
            $item['using'] = false;
        }
    }

    /**
     * 查询连接中有哪些sql缓存statement
     *
     * @param IDb $db
     * @return array
     */
    public static function select(IDb $db)
    {
        return static::$statements[$db->hashCode()] ?? [];
    }

    /**
     * 移除连接中对应sql的statement
     *
     * @param IDb $db
     * @param string $sql
     * @return void
     */
    public static function remove(IDb $db, string $sql)
    {
        $hashCode = $db->hashCode();
        if(isset(static::$statements[$hashCode][$sql]))
        {
            unset(static::$statements[$hashCode][$sql]);
        }
    }

    /**
     * 清空连接中所有statement
     *
     * @param IDb $db
     * @return void
     */
    public static function clear(IDb $db)
    {
        static::$statements[$db->hashCode()] = [];
    }

    /**
     * 获取所有连接及对应缓存
     *
     * @return array
     */
    public static function getAll()
    {
        return static::$statements;
    }

    /**
     * 清空所有连接及对应缓存
     *
     * @return void
     */
    public static function clearAll()
    {
        static::$statements = [];
    }

    /**
     * 释放请求上下文
     *
     * @return void
     */
    public static function destoryRequestContext()
    {
        $statementCaches = RequestContext::get('statementCaches', []);
        foreach($statementCaches as $statement)
        {
            static::unUsingStatement($statement);
        }
    }

}