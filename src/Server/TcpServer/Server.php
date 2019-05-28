<?php
namespace Imi\Server\TcpServer;

use Imi\App;
use Imi\Server\Base;
use Imi\ServerManage;
use Imi\Bean\Annotation\Bean;
use Imi\Server\Event\Param\CloseEventParam;
use Imi\Server\Event\Param\BufferEventParam;
use Imi\Server\Event\Param\ConnectEventParam;
use Imi\Server\Event\Param\ReceiveEventParam;

/**
 * TCP 服务器类
 * @Bean
 */
class Server extends Base
{
    /**
     * 构造方法
     * @param string $name
     * @param array $config
     * @param \swoole_server $serverInstance
     * @param bool $subServer 是否为子服务器
     */
    public function __construct($name, $config, $isSubServer = false)
    {
        parent::__construct($name, $config, $isSubServer);
    }

    /**
     * 创建 swoole 服务器对象
     * @return void
     */
    protected function createServer()
    {
        $config = $this->getServerInitConfig();
        $this->swooleServer = new \swoole_server($config['host'], $config['port'], $config['mode'], $config['sockType']);
    }

    /**
     * 从主服务器监听端口，作为子服务器
     * @return void
     */
    protected function createSubServer()
    {
        $config = $this->getServerInitConfig();
        $this->swooleServer = ServerManage::getServer('main')->getSwooleServer();
        $this->swoolePort = $this->swooleServer->addListener($config['host'], $config['port'], $config['sockType']);
    }

    /**
     * 获取服务器初始化需要的配置
     * @return array
     */
    protected function getServerInitConfig()
    {
        return [
            'host'      => isset($this->config['host']) ? $this->config['host'] : '0.0.0.0',
            'port'      => isset($this->config['port']) ? $this->config['port'] : 8080,
            'sockType'  => isset($this->config['sockType']) ? (SWOOLE_SOCK_TCP | $this->config['sockType']) : SWOOLE_SOCK_TCP,
            'mode'      => isset($this->config['mode']) ? $this->config['mode'] : SWOOLE_PROCESS,
        ];
    }

    /**
     * 绑定服务器事件
     * @return void
     */
    protected function __bindEvents()
    {
        $server = $this->swoolePort ?? $this->swooleServer;

        $server->on('connect', function(\swoole_server $server, $fd, $reactorID){
            try{
                $this->trigger('connect', [
                    'server'    => $this,
                    'fd'        => $fd,
                    'reactorID' => $reactorID,
                ], $this, ConnectEventParam::class);
            }
            catch(\Throwable $ex)
            {
                App::getBean('ErrorLog')->onException($ex);
            }
        });
        
        $server->on('receive', function(\swoole_server $server, $fd, $reactorID, $data){
            try{
                $this->trigger('receive', [
                    'server'    => $this,
                    'fd'        => $fd,
                    'reactorID' => $reactorID,
                    'data'      => $data,
                ], $this, ReceiveEventParam::class);
            }
            catch(\Throwable $ex)
            {
                App::getBean('ErrorLog')->onException($ex);
            }
        });
        
        $server->on('close', function(\swoole_server $server, $fd, $reactorID){
            try{
                $this->trigger('close', [
                    'server'    => $this,
                    'fd'        => $fd,
                    'reactorID' => $reactorID,
                ], $this, CloseEventParam::class);
            }
            catch(\Throwable $ex)
            {
                App::getBean('ErrorLog')->onException($ex);
            }
        });

        $server->on('BufferFull', function(\swoole_server $server, $fd){
            try{
                $this->trigger('bufferFull', [
                    'server'    => $this,
                    'fd'        => $fd,
                ], $this, BufferEventParam::class);
            }
            catch(\Throwable $ex)
            {
                App::getBean('ErrorLog')->onException($ex);
            }
        });

        $server->on('BufferEmpty', function(\swoole_server $server, $fd){
            try{
                $this->trigger('bufferEmpty', [
                    'server'    => $this,
                    'fd'        => $fd,
                ], $this, BufferEventParam::class);
            }
            catch(\Throwable $ex)
            {
                App::getBean('ErrorLog')->onException($ex);
            }
        });
    }
}