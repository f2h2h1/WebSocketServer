# 说明
这是一个简单的基于php的websocket服务器    
支持7.1或以上版本    
只能在cli模式下运行    
#### 如何使用
1.引入WebSocketServer.php
```php
require_once("WebSocketServer.php");
```
2.实例化
```php
$ip = 127.0.0.1;
$port = 1935;
$somaxconn = 256;
$ws = new WebSocketServer($ip, $port, $somaxconn, 0, 0);
```
3.编写onConnect，onClose，onMessage三个方法
```php
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
```
4.调用run()
```php
$ws->run();
```
5.运行
```
php demo.php
```
#### 完整demo
```php
$ip = 127.0.0.1;
$port = 1935;
$somaxconn = 256;
$ws = new WebSocketServer($ip, $port, $somaxconn, 0, 0);

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
```
#### 目录结构    
~~~
├─ExampleEcho.html       demo前端
├─ExampleEcho.php        demo后端
├─WebSocketServer.php    主文件
~~~
#### TODO
1.发送关闭帧    
2.发送ping帧和回应pong帧    