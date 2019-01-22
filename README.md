# 简介
- 这是一个简单的基于 php 的 websocket 服务器
- 支持 7.1 或以上版本
- 只能在 cli 模式下运行

#### 如何使用
1. 引入 WebSocketServer.php
```php
require_once("WebSocketServer.php");
```
2. 实例化
```php
$ip = 127.0.0.1;
$port = 1935;
$somaxconn = 256;
$ws = new WebSocketServer($ip, $port, $somaxconn, 0, 0);
```
3. 编写 onConnect，onClose，onMessage 三个方法
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
4. 调用 run()
```php
$ws->run();
```
5. 运行
```
php demo.php
```

#### 完整 demo
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
├─ExampleEcho.html       demo 前端
├─ExampleEcho.php        demo 后端
├─WebSocketServer.php    主文件
~~~
#### TODO
1. 发送 ping 帧
2. 数据分片

#### 存在的 bug
1. 使用火狐浏览器首次连接时，速度非常慢
2. 无法正确响应 opcode 为 7 的请求
