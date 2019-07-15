<?php
namespace Imi\Db\Drivers\CoroutineMysql;

/**
 * Statement fetchAll处理器
 */
class StatementFetchAllParser
{
    /**
     * 单行数据处理器
     * @var StatementFetchParser
     */
    private $fetchParser;

    public function __construct()
    {
        $this->fetchParser = new StatementFetchParser;
    }

    /**
     * 获取单行数据处理器
     * @return StatementFetchParser
     */
    public function getFetchParser(): StatementFetchParser
    {
        return $this->fetchParser;
    }

    /**
     * 处理所有行
     * @param array $data
     * @param integer $fetchStyle
     * @return array
     */
    public function parseAll(array $data, int $fetchStyle, $fetchArgument = null, array $ctorArgs = array()): array
    {
        $result = [];
        foreach($data as $row)
        {
            switch($fetchStyle)
            {
                case \PDO::FETCH_COLUMN:
                    $item = $this->fetchParser->parseRow($row, \PDO::FETCH_BOTH);
                    $result[] = $item[$fetchArgument ?? 0] ?? null;
                    break;
                default:
                    $result[] = $this->fetchParser->parseRow($row, $fetchStyle);
                    break;
            }
        }
        return $result;
    }
}