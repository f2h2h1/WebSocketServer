<?php

require_once("WebSocketServer.php");

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
$ws = new WebSocketServer($ip, $port, $somaxconn, $echolog, $locallog);

$ws->onConnect = function($conn, $wsObj) {
    echo "onConnect".PHP_EOL;
    print_r($conn);
};
$ws->onClose = function($conn, $wsObj) {
    echo "onClose".PHP_EOL;
    print_r($conn);
};
$ws->onMessage = function($conn, $received, $wsObj) {
    echo "onMessage".PHP_EOL;
    print_r($received);
    $wsObj->wsWrite($conn->getResource(), $received->getData());
};

$ws->run();
