<?php
/**
 * Created by PhpStorm.
 * User: lidanyang
 * Date: 17/3/29
 * Time: 13:00
 */
class Client
{
    private $client;
    private $channel = 0;
    private $online_list;
    public function __construct() {
        $this->client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $this->client->on('Connect', array($this, 'onConnect'));
        $this->client->on('Receive', array($this, 'onReceive'));
        $this->client->on('Close', array($this, 'onClose'));
        $this->client->on('Error', array($this, 'onError'));

        $this->client->set([
            'open_length_check' => true,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 4,
        ]);
    }

    public function connect() {
        $this->client->connect("127.0.0.1", 9502 , 1);
    }
    public function onReceive( $cli, $data ) {
        var_dump($data);
    }
    public function onConnect(swoole_client $cli) {
        fwrite(STDOUT, "Enter ID: ");
        $msg = trim(fgets(STDIN));
        var_dump($msg);
        $data = json_encode([
            'cmd'=> 'online',
            'params' => [
                'id' => intval($msg)
            ]
        ]);

        $data = pack("N", strlen($data)).$data;
        $cli->send($data);

        swoole_timer_tick(5000, function() use ($cli){
            $data = json_encode( array(
                'cmd'=> 'hb',
                'params' => [

                ]
            ));
            $data = pack("N", strlen($data)). $data;
            $cli->send( $data );
        });
;
        fwrite(STDOUT, "Enter Msg: ");

        swoole_event_add(STDIN, function($fp) use ($cli){
            $msg = trim(fgets(STDIN));
            $data = json_encode( array(
                'cmd'=> 'broadcast',
                'params' => [
                    'cmd'   => 'send',
                    'data'  => $msg
                ]
            ));
            $data = pack("N", strlen($data)). $data;
            $cli->send( $data );
            fwrite(STDOUT, "Enter Msg: ");
        });
    }
    public function onClose( $cli) {
        echo "Client close connection\n";
    }
    public function onError() {
    }
}
$cli = new Client();
$cli->connect();