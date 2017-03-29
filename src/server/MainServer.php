<?php
/**
 * Created by PhpStorm.
 * User: lidanyang
 * Date: 16/4/8
 * Time: 下午4:29
 */

namespace push\server;


use core\component\config\Config;

class MainServer
{  
    private static $instance = null;

    /**
     * @return MainServer
     */
    public static function getInstance()
    {
        if(MainServer::$instance == null)
        {
            MainServer::$instance = new MainServer();
        }
        return MainServer::$instance;
    }
    
    protected function __construct()
    {
    
    }
    /**
     * @var \swoole_server
     */
    private $_server;
    /**
     * @var BaseCallback
     */
    private $_callback;

    private $config;

    public function init(array $config)
    {
        $this->_server = new \swoole_websocket_server($config['host'], $config['port']);
        $this->config = $config['setting'];
        $this->_server->set($this->config);
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    public function setCallback($callback)
    {
        if( !( $callback instanceof BaseCallback ) )
        {
            throw new \Exception('client must object');
        }
        $this->_callback = $callback;
        $this->_callback->setServer($this->_server);
    }

    public function run()
    {
        $handlerArray = array(
            'onConnect',
            'onClose',

            'onWorkerStop',
            'onWorkerError',

            'onTask',
            'onFinish',

            'onManagerStart',
            'onManagerStop',

            'onPipeMessage',

            'onHandShake',
            'onOpen',
        );

        $this->_server->on('Start', [$this->_callback, 'onStart']);
        $this->_server->on('Shutdown', array($this->_callback, 'onShutdown'));
        $this->_server->on('Request', array($this->_callback, 'doRequest'));
        $this->_server->on('Message', array($this->_callback, 'doMessage'));
        $this->_server->on('WorkerStart', array($this->_callback, 'doWorkerStart'));

        foreach($handlerArray as $handler) {
            if(method_exists($this->_callback, $handler)) {
                $this->_server->on(\substr($handler, 2), array($this->_callback, $handler));
            }
        }
        $config = Config::get('tcp_server');
        $port = $this->_server->listen(
            $config['host'],
            $config['port'],
            $this->getType($config)
        );

        $port->set($config['protocol']);

        $port->on("Receive", array($this->_callback, 'doReceive'));

        $this->_callback->_beforeStart();
        $this->_server->start();
    }

    private function getType($config)
    {
        if( !isset($config['enable_ssl']))
        {
            return SWOOLE_TCP;
        }

        if( $config['enable_ssl'] )
        {
            if( !isset($config['protocol']['ssl_cert_file'])
                || !isset($config['protocol']['ssl_key_file']) )
            {
                return SWOOLE_TCP;
            }
            else
            {
                return SWOOLE_TCP | SWOOLE_SSL;
            }
        }
        return SWOOLE_TCP;
    }

    /**
     * @return \swoole_server
     */
    public function getServer()
    {
        return $this->_server;
    }

}
