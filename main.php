<?php
/**
 * Created by PhpStorm.
 * User: lancelot
 * Date: 16-11-27
 * Time: 下午8:44
 */

use push\Entrance;

require "vendor/autoload.php";

global $debug;
Entrance::run(__DIR__, $debug);