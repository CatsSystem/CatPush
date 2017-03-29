<?php
/**
 * Created by PhpStorm.
 * User: lidanyang
 * Date: 17/3/29
 * Time: 11:33
 */

namespace push\link;

use app\common\Error;
use core\component\pool\adapter\Redis;
use core\component\pool\PoolManager;

class LinkManager
{
    private static $instance = [];

    /**
     * @param $type     string      类型
     * @return LinkManager
     */
    public static function getInstance($type)
    {
        if(!isset(LinkManager::$instance[$type]))
        {
            LinkManager::$instance[$type] = new LinkManager($type);
        }
        return LinkManager::$instance[$type];
    }

    /**
     * @var Redis
     */
    private $redis;

    private $key;

    private $prefix;

    private $type;

    public function __construct($type)
    {
        $this->type = $type;
        $this->redis = PoolManager::getInstance()->get('redis_master');
    }

    public function init($key, $prefix)
    {
        $this->key = $key . $this->type;
        $this->prefix = $prefix;
    }

    public function set($id, $fd)
    {
        $this->redis->pop()->hSet($this->key, $this->prefix . $id, $fd);
    }

    public function get($id)
    {
        return $this->redis->pop()->hGet($this->key, $this->prefix . $id);
    }

    public function all()
    {
        $result = yield $this->redis->pop()->hGetAll($this->key);
        if($result['code'] != Error::SUCCESS)
        {
            return [];
        }
        $result = $result['data'];
        $len = count($result) - 1;
        $list = [];
        for($i = 0; $i < $len; $i = $i + 2)
        {
            $list[ $result[$i] ] = $result[$i + 1];
        }
        return $list;
    }

    public function del($id)
    {
        return $this->redis->pop()->del($this->key, $this->prefix . $id);
    }


}