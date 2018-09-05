<?php

require_once("WebSocketServer.php");

class ExampleEcho extends WebSocketServer
{
    protected function onConnect($ws_obj) {
        echo "onConnect".PHP_EOL;
        print_r($ws_obj);
    }
    protected function onClose($ws_obj) {
        echo "onClose".PHP_EOL;
        print_r($ws_obj);
    }
    protected function onMessage($ws_obj, $received) {
        echo "onMessage".PHP_EOL;
        print_r($received);
        $this->wsWrite($ws_obj->getResource(), $received->getData());
    }
}

$shortopts = array();
$longopts  = array(
    "ip:",
    "port:",
    "somaxconn:",
);
$optind = null;
$cmd_options = getopt(implode("", $shortopts), $longopts, $optind);

$ip = empty($cmd_options['ip']) ? '127.0.0.1' : $cmd_options['ip'];
$port = empty($cmd_options['port']) ? '1935' : $cmd_options['port'];
$somaxconn = empty($cmd_options['somaxconn']) ? '256' : $cmd_options['somaxconn'];
$locallog = 0;
$echolog = 0;
$ws = new ExampleEcho($ip, $port, $somaxconn, $echolog, $locallog);
$ws->run();