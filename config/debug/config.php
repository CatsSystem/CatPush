<?php
/**
 * Created by PhpStorm.
 * User: lidanyang
 * Date: 16/6/15
 * Time: 上午10:48
 */

return array(
    'debug' => true,

    'project'=>array(
        'pid_path'          => __DIR__ . '/../../',
        'project_name'      => 'push_service',

        'ctrl_path'         => 'app\\api\\module',
        'main_callback'     => "app\\server\\MainServer",
    ),

    'ws_server' => [
        'host'          => '0.0.0.0',
        'port'          => 9501,

        'setting'   => [
            'daemonize' => 0,

            'worker_num' => 1,
            'dispatch_mode' => 2,

            'task_worker_num' => 1,

            'package_max_length'    => 524288,

            'heartbeat_idle_time' => 60,
            'heartbeat_check_interval' => 10,
        ],
    ],

    'tcp_server' => [
        'host'          => '0.0.0.0',
        'port'          => 9502,

        'protocol'   => [
            'open_length_check' => true,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 4,
        ]
    ],
);
