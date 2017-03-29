<?php
/**
 * Created by PhpStorm.
 * User: lidanyang
 * Date: 17/3/1
 * Time: 13:45
 */

namespace app\server;

use push\Entrance;
use push\link\LinkManager;
use push\server\BaseCallback;
use core\common\Globals;
use core\component\cache\CacheLoader;
use core\component\config\Config;
use core\component\pool\PoolManager;

class MainServer extends BaseCallback
{
    /**
     * 服务启动前执行该回调, 用于添加额外监听端口, 添加额外Process
     * @return mixed
     */
    public function beforeStart()
    {
        // 打开内存Cache进程
        $this->openCacheProcess(function(){
            Globals::setProcessName(Config::getField('project', 'project_name') . 'cache process');
            // 设置全局Server变量
            Globals::$server = \push\server\MainServer::getInstance()->getServer();

            // 初始化连接池
            PoolManager::getInstance()->init('mysql_master');
            PoolManager::getInstance()->init('redis_master');

            // 初始化缓存Cache
            $cache_config = Config::getField('component', 'cache');
            CacheLoader::getInstance()->init(Entrance::$rootPath . $cache_config['cache_path'],
                $cache_config['cache_path']);

            return $cache_config['cache_tick'];
        });
    }

    /**
     * 进程初始化回调, 用于初始化全局变量
     * @param \swoole_websocket_server $server
     * @param $workerId
     * @return \Generator
     */
    public function onWorkerStart($server, $workerId)
    {
        // 加载配置
        Config::load(Entrance::$configPath);

        // 初始化连接池
        PoolManager::getInstance()->init('mysql_master');
        PoolManager::getInstance()->init('redis_master');

        /**
         * 初始化内存缓存
         */
        $cache_config = Config::getField('component', 'cache');
        CacheLoader::getInstance()->init(Entrance::$rootPath . $cache_config['cache_path'],
            $cache_config['cache_path']);

        LinkManager::getInstance('ws')->init("fd_list", "id_");
        LinkManager::getInstance('tcp')->init("fd_list", "id_");
    }

    /**
     * Http接口,处理Http请求
     * @param \swoole_http_request $request
     * @param \swoole_http_response $response
     */
    public function onRequest(\swoole_http_request $request, \swoole_http_response $response)
    {

    }

    /**
     * WebSocket Receive, 处理来自网页的WebSocket请求
     * @param \swoole_websocket_server $server
     * @param \swoole_websocket_frame $frame
     */
    public function onMessage(\swoole_websocket_server $server, \swoole_websocket_frame $frame)
    {
        $data   = $frame->data;
        $fd     = $frame->fd;

        $data = json_decode($data, true);

        $cmd    = $data['cmd'];
        $params = $data['params'];
        switch ($cmd)
        {
            case 'hb':
            {
                $this->server->push($fd, json_encode([
                    'cmd'    => 'hb',
                    'result' => 0
                ]));
                break;
            }
            case 'online':
            {
                $id = $params['id'];
                LinkManager::getInstance('ws')->set($id, $fd);
                $this->server->push($fd, json_encode([
                    'cmd'    => 'online',
                    'result' => 0
                ]));
                break;
            }
            case 'list':
            {
                $result = yield LinkManager::getInstance('tcp')->all();

                $this->server->push($fd, json_encode([
                    'cmd'       => 'list',
                    'result'    => array_keys($result)
                ]));
                break;
            }
            case 'list_ws':
            {
                $result = yield LinkManager::getInstance('ws')->all();

                $this->server->push($fd, json_encode([
                    'cmd'       => 'list_ws',
                    'result'    => array_keys($result)
                ]));
                break;
            }
            case 'send':
            {
                $id     = $params['id'];
                $cmd    = $params['cmd'];
                $data   = $params['data'];

                $send_fd = yield LinkManager::getInstance('tcp')->get($id);

                if($this->server->exist($send_fd) && !$this->check_ws($send_fd)){
                    $this->server->push($send_fd, json_encode([
                        'cmd'       => $cmd,
                        'params'    => $data
                    ]));
                } else {
                    LinkManager::getInstance('tcp')->del($id);
                }

                break;
            }
            case 'send_ws':
            {
                $id     = $params['id'];
                $cmd    = $params['cmd'];
                $data   = $params['data'];

                $send_fd = yield LinkManager::getInstance('ws')->get($id);
                if($this->server->exist($send_fd) && $this->check_ws($send_fd)){
                    $this->server->push($send_fd, json_encode([
                        'cmd'       => $cmd,
                        'params'    => $data
                    ]));
                } else {
                    LinkManager::getInstance('ws')->del($id);
                }


                break;
            }
            case 'broadcast':
            {
                $cmd    = $params['cmd'];
                $data   = $params['data'];
                $list = yield LinkManager::getInstance('tcp')->all();

                foreach ($list as $id => $send_fd)
                {
                    if($this->server->exist($send_fd) && !$this->check_ws($send_fd)){
                        $this->send($send_fd, json_encode([
                            'cmd'       => $cmd,
                            'params'    => $data
                        ]));
                    } else {
                        LinkManager::getInstance('ws')->del($id);
                    }
                }
                break;
            }
        }
    }

    /**
     * TCP Receive, 处理来自TCP连接的请求
     * @param \swoole_server $server
     * @param $fd
     * @param $from_id
     * @param $data
     */
    public function onReceive(\swoole_server $server, $fd, $from_id, $data)
    {
        $data = substr($data, 4);
        $data = json_decode($data, true);
        $cmd    = $data['cmd'];
        $params = $data['params'];
        switch ($cmd)
        {
            case 'hb':
            {
                $this->send($fd, json_encode([
                    'cmd'    => 'hb',
                    'result' => 0
                ]));
                break;
            }
            case 'online':
            {
                $id = $params['id'];
                LinkManager::getInstance('tcp')->set($id, $fd);
                $this->send($fd, json_encode([
                    'cmd'    => 'online',
                    'result' => 0
                ]));
                break;
            }
            case 'broadcast':
            {
                $cmd    = $params['cmd'];
                $data   = $params['data'];
                $list = yield LinkManager::getInstance('ws')->all();
                foreach ($list as $id => $send_fd)
                {
                    if($this->server->exist($send_fd) && $this->check_ws($send_fd)) {
                        $this->server->push($send_fd, json_encode([
                            'cmd'       => $cmd,
                            'params'    => $data
                        ]));
                    } else {
                        LinkManager::getInstance('ws')->del($id);
                    }

                }
                break;
            }
        }
    }

    private function send($fd, $data)
    {
        $this->server->send($fd, pack('N', strlen($data)) . $data);
    }

    private function check_ws($fd)
    {
        $info = $this->server->connection_info($fd);
        return isset($info['websocket_status']);
    }
}