<?php

namespace push;

use core\common\Formater;
use push\server\MainServer;
use core\component\config\Config;

class Entrance
{
    public static $rootPath;
    public static $configPath;

    final public static function fatalHandler()
    {
        $error = \error_get_last();
        if(empty($error)) {
            return '';
        }
        if(!in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            return '';
        }

        return json_encode(Formater::fatal($error));
    }

    public static function run($runPath, $configPath)
    {
        self::$rootPath = $runPath;
        self::$configPath = $runPath . '/config/' . $configPath;

        Config::load(self::$configPath);

        \register_shutdown_function( __CLASS__ . '::fatalHandler' );

        $timeZone = Config::get('time_zone', 'Asia/Shanghai');
        \date_default_timezone_set($timeZone);

        $service = MainServer::getInstance()->init(Config::get('ws_server'));
        $callback = Config::getField('project', 'main_callback');
        if( !class_exists($callback) )
        {
            throw new \Exception("No class {$callback}");
        }
        $service->setCallback(new $callback());
        $service->run();
    }
}

