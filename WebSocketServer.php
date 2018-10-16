<?php
class WebSocketServer
{
    private $config;
    private $read_socket;
    public $ws_conn;

    public $onConnect = null;
    public $onClose = null;
    public $onMessage = null;

    function __construct($ip, $port, $somaxconn, $echolog, $locallog) {

        // set_error_handler(function($errno, $errstr, $errfile, $errline) {
        //     throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        // });

        $this->config = array();
        $this->config['ip'] = $ip;
        $this->config['port'] = $port;
        $this->config['somaxconn'] = $somaxconn;
        $this->read_socket = array();
        $this->write_socket = array();
        $this->ws_conn = array();
    }

    public function run() {
        $ip = $this->config['ip'];
        $port = $this->config['port'];
        $somaxconn = $this->config['somaxconn'];

        $conf = array(
            "ip:".$ip,
            "port:".$port,
            "somaxconn:".$somaxconn
        );
        $this->logger(implode(" ", $conf));

        if (false == ($sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
            die("socket_create() failed: reason: " . socket_strerror(socket_last_error()).PHP_EOL);
        }
        if (!socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1)) {
            die("Unable to set option on socket: ". socket_strerror(socket_last_error()).PHP_EOL);
        }
        if (false == (@socket_bind($sock, $ip, $port))) {
            die("socket_bind() failed: reason: " . socket_strerror(socket_last_error()).PHP_EOL);
        }
        if (false == (@socket_listen($sock, $somaxconn))) {
            die("socket_listen() failed: reason: " . socket_strerror(socket_last_error()).PHP_EOL);
        }
        socket_set_nonblock($sock); // 非阻塞
        // 接收套接流的最大超时时间1秒，后面是微秒单位超时时间，设置为零，表示不管它
        socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 5, "usec" => 0));
        $this->logger("server start");

        array_push($this->read_socket, $sock);

        while (true) {

            $tmp_reads = $this->select($this->read_socket);
            if ($tmp_reads == false) {
                continue;
            }
            if (in_array($sock, $tmp_reads)) {
                $newconn = socket_accept($sock);
                if ($newconn) {

                    // 把新的连接sokcet加入监听
                    $wsid = $this->addNewconn($newconn);

                    $wsid = $this->getWsid($newconn);
                    $this->logger("new Client", $wsid);

                    $line = $this->usocketRead($newconn);
                    // print_r($line);var_dump($line);
                    if (!$this->handShake($wsid, $line)) {
                        // 握手失败
                        $this->logger("handshake fail", $wsid);
                        $this->wsClose($newconn);

                        continue;
                    }
                    $this->logger("handshake success", $wsid);

                    $this->call_func($this->onConnect, array($this->ws_conn[$wsid]));

                    array_splice($tmp_reads, array_search($newconn, $tmp_reads), 1);
                }
            }

            // 轮循读通道
            foreach ($tmp_reads as $rfd) {
                // 从通道读取
                $received = $this->wsRead($rfd);
                if ($received === false) {
                    continue;
                } else {
                    $opcode = $received->getOpcode();
                    $wsid = $this->getWsid($rfd);
                    switch($opcode) {
                        case 0x1:
                        case 0x2:
                            $this->call_func($this->onMessage, array($this->ws_conn[$wsid], $received));
                            break;
                        case 0x5:
                        case 0x6:
                        case 0x7:
                            // print_r($received);
                            $this->logger("opcode ".hexdec($opcode), $wsid);
                            break;
                        case 0x8: // 客户端主动关闭连接
                            $this->wsClose($rfd);
                            break;
                        case 0x9: // 心跳连接ping帧
                            $this->logger("ping", $wsid);
                            $this->wsWrite($this->ws_conn[$wsid]->getResource(), $received->getData());
                            break;
                        case 0xA: // 心跳连接pong帧
                            $this->logger("pong", $wsid);
                            break;
                        default:
                            $this->logger("error opcode", $wsid);
                    }
                }
            }

            unset($tmp_reads);
            unset($tmp_writes);
        }
    }

    /**
     * 获取活跃的链接
     *
     * @param Resource $tmp_reads
     * @return Resource $tmp_reads
     */
    public function select($tmp_reads) {
        $tmp_writes = null;
        $except_socks = null; // 注意 php 不支持直接将NULL作为引用传参，所以这里定义一个变量
        $count = socket_select($tmp_reads, $tmp_writes, $except_socks, 1); // timeout 传 NULL 会一直阻塞直到有结果返回

        if ($count == 0) {
            return false;
        }
        return $tmp_reads;
    }

    public function getConfig() {
        return $this->config;
    }

    /**
     * 新增链接
     *
     * @param Resource $newconn
     * @return Integer $wsid
     */
    private function addNewconn($newconn) {

        array_push($this->read_socket, $newconn);

        $wsid = uniqid('wsid_');
        $resid = (int)$newconn;
        socket_getpeername($newconn, $ip, $port);  //获取远程客户端ip地址和端口
        $newconn_obj = new class($wsid, $newconn, $resid, $ip, $port) {
            private $info;
            function __construct($wsid, $resource, $resid, $ip, $port) {
                $this->info = array();
                $this->info['wsid'] = $wsid;
                $this->info['resource'] = $resource;
                $this->info['resid'] = $resid;
                $this->info['ip'] = $ip;
                $this->info['port'] = $port;
                $this->info['handshake'] = false;
            }
            public function getWsid() {
                return $this->wsid['wsid'];
            }
            public function getResource() {
                return $this->info['resource'];
            }
            public function getResid() {
                return $this->info['resid'];
            }
            public function getIp() {
                return $this->info['ip'];
            }
            public function getPort() {
                return $this->info['port'];
            }
            public function getHandShake() {
                return $this->info['handshake'];
            }
            public function handShakeSuccess() {
                $this->info['handshake'] = true;
            }
            public function getInfo() {
                return $this->info;
            }
        };
        $this->ws_conn[$wsid] = $newconn_obj;
        return $wsid;
    }

    /**
     * 通过socket resource获取wsid
     *
     * @param Resource $sock
     * @return Integer $wsid
     */
    private function getWsid($sock) {
        foreach ($this->ws_conn as $k => $v) {
            if ($sock == $v->getResource()) {
                return $k;
            }
        }
        return false;
    }

    /**
     * websocket握手
     *
     * @param Integer $wsid
     * @param String $line
     * @return Boolean
     */
    private function handShake($wsid, $line) {
        if (empty($line)) {
            return false;
        }
        // Get Sec-WebSocket-Key.
        $Sec_WebSocket_Key = '';
        if (preg_match("/Sec-WebSocket-Key: *(.*?)\r\n/i", $line, $match)) {
            $Sec_WebSocket_Key = $match[1];
            if (empty($Sec_WebSocket_Key)) {
                return false;
            }
        }
        // var_dump($Sec_WebSocket_Key);
        // Calculation websocket key.
        $new_key = base64_encode(sha1($Sec_WebSocket_Key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));
        // Handshake response data.
        $handshake_message = "HTTP/1.1 101 Switching Protocols\r\n";
        $handshake_message .= "Upgrade: websocket\r\n";
        $handshake_message .= "Sec-WebSocket-Version: 13\r\n";
        $handshake_message .= "Connection: Upgrade\r\n";
        $handshake_message .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";
        $ret = socket_write($this->ws_conn[$wsid]->getResource(),  $handshake_message);
        if ($ret === false) {
            return false;
        }
        $this->ws_conn[$wsid]->handShakeSuccess();
        // $this->logger("handshake");
        // echo $handshake_message;
        return true;
    }

    /**
     * 读取客户端的内容
     *
     * @param Resource $rfd
     * @return String $line
     */
    private function wsRead($rfd) {
        $received = $this->usocketRead($rfd);

        if ($received === false) {
            return false;
        }
        return $this->decode($received);
    }

    private function usocketRead($rfd) {

        $buffer = "";
        $bytes = @socket_recv($rfd, $buffer, 4096, 0);
        if ($bytes === false) {
            return false;
        } elseif ($bytes > 0) {
            return substr($buffer, 0, $bytes);
        } else {
            return false;
        }

        return $line;
    }

    /**
     * 发送内容至客户端
     *
     * @param Resource $rfd
     * @param String $content
     */
    public function wsWrite($rfd, $content) {
        $response = $this->encode($content);
        $ret = socket_write($rfd, $response, strlen($response));
    }

    /**
     * 关闭连接
     *
     * @param Resource $rfd
     */
    public function wsClose($rfd) {

        $wsid = $this->getWsid($rfd);
        $this->call_func($this->onClose, array($this->ws_conn[$wsid]));

        $status = 1000;
        $message = pack('n', $status);
        $messageLength = strlen($message);
        $fin = 128;
        $opcode = 8;
        $response = pack('n', (($fin | $opcode) << 8) | $messageLength).$message;
        $ret = socket_write($rfd, $response, strlen($response));

        @socket_shutdown($rfd);
        @socket_close($rfd);

        $key = array_search($rfd, $this->read_socket);
        if ($key) {
            array_splice($this->read_socket, $key, 1);
        }

        $this->logger("close conn", $wsid);

        unset($this->ws_conn[$wsid]);
    }

    /**
     * 数据从浏览器通过websocket发送给服务器的数据，是原始的帧数据
     * 默认是被掩码处理过的，所以需要对其利用掩码进行解码。
     */

    /**
     * 编码
     *
     * @param String $buffer
     * @return String $encode_buffer
     */
    private function encode($buffer) {
        $len = strlen($buffer);

        $first_byte = "\x81"; // 这是16进制数字 81 即二进制的 10000001 即十进制的 129

        if ($len <= 125) {
            $encode_buffer = $first_byte . chr($len) . $buffer;
        } else {
            if ($len <= 65535) {
                $encode_buffer = $first_byte . chr(126) . pack("n", $len) . $buffer;
            } else {
              //pack("xxxN", $len)pack函数只处理2的32次方大小的文件，实际上2的32次方已经4G了。
                $encode_buffer = $first_byte . chr(127) . pack("xxxxN", $len) . $buffer;
            }
        }

        return $encode_buffer;
    }

    /**
     * 编码
     *
     * @param String $buffer
     * @return Object $received
     */
    private function decode($buffer) {

        $firstbyte  = ord($buffer[0]);
        $secondbyte = ord($buffer[1]);

        $len = $mask = $data = $decoded = null;

        $len = $secondbyte & 127;
        if ($len === 126) {
            $mask = substr($buffer, 4, 4);
            $data  = substr($buffer, 8);
        } else {
            if ($len === 127) {
                $mask = substr($buffer, 10, 4);
                $data = substr($buffer, 14);
            } else {
                $mask = substr($buffer, 2, 4);
                $data = substr($buffer, 6);
            }
        }
        for ($index = 0; $index < strlen($data); $index++) {
            $decoded .= $data[$index] ^ $mask[$index % 4];
        }

        $fin = $this->getBitOne($firstbyte, 8);
        $rsv1 = $this->getBitOne($firstbyte, 7);
        $rsv2 = $this->getBitOne($firstbyte, 6);
        $rsv3 = $this->getBitOne($firstbyte, 5);
        $opcode = $this->getBit($firstbyte, 4, 0);
        $mask = $this->getBitOne($secondbyte, 8);

        return new class($fin, $rsv1, $rsv2, $rsv3,
                        $opcode, $mask, $len, $decoded, $buffer) {
            private $info;

            function __construct($fin, $rsv1, $rsv2, $rsv3,
                        $opcode, $mask, $playload_len, $data, $buffer) {
                $this->info['fin'] = $fin;
                $this->info['rsv1'] = $rsv1;
                $this->info['rsv2'] = $rsv2;
                $this->info['rsv3'] = $rsv3;
                $this->info['opcode'] = $opcode;
                $this->info['mask'] = $mask;
                $this->info['playload_len'] = $playload_len;
                $this->info['data'] = $data;
            }

            public function getRaw() {
                return $this->info['raw'];
            }
            public function getFin() {
                return $this->info['fin'];
            }
            public function getRsv1() {
                return $this->info['rsv1'];
            }
            public function getRsv2() {
                return $this->info['rsv2'];
            }
            public function getRsv3() {
                return $this->info['rsv3'];
            }
            public function getOpcode() {
                return $this->info['opcode'];
            }
            public function getMask() {
                return $this->info['mask'];
            }
            public function getPlayloadLen() {
                return $this->info['playload_len'];
            }
            public function getData() {
                return $this->info['data'];
            }
            public function getRsv() {
                return $this->get_rsv1().$this->get_rsv2().$this->get_rsv3();
            }
            public function getInfo() {
                return $this->info;
            }
        };
    }


    /**
     * 截取一个字节某一段bit
     *
     * @param Integer $t 目标字节
     * @param Integer $l 某段bit的长度
     * @param Integer $s 开始截取的位置位置，从右往左
     * @return Integer
     */
    private function getBit($t, $l, $s) {
        return (((2<<($l-1))-1)<<$s&$t)>>$s;
    }

    /**
     * 获取一个字节第n位的bit，从右往左
     *
     * @param Integer $t 目标字节
     * @param Integer $n 第n位，从右往左
     * @return Integer
     */
    private function getBitOne($t, $n) {
        return 0x01&($t>>$n-1);
    }

    /**
     * 打印日志
     *
     * @param String $buffer
     * @param Integer $wsid
     * @param Integer $level
     */
    private function logger($msg, $wsid = null, $level = 0) {
        $time = date('Y-m-d H:i:s');
        if (empty($wsid)) {
            $out = "[".$time."] ".$msg;
        } else {
            $client = array(
                'wsid' => $wsid,
                'resid' => $this->ws_conn[$wsid]->getResid(),
                'ip' => $this->ws_conn[$wsid]->getIp().":".$this->ws_conn[$wsid]->getPort(),
                'h' => (int)$this->ws_conn[$wsid]->getHandShake(),
            );
            $client_str = '';
            foreach ($client as $k => $v) {
                $client_str .= $k."=".$v." ";
            }
            $out = "[".$time."] ".$msg."\t".$client_str;
        }

        echo $out.PHP_EOL;
    }

    private function call_func($func, $parameters) {
        if (!is_object($func)) {
            return;
        }
        array_push($parameters, $this);
        try {
            call_user_func_array($func, $parameters);
        } catch (\Exception $e) {
            echo 1;
            var_dump($e);
            exit(250);
        } catch (\Error $e) {
            echo 2;
            var_dump($e);
            exit(250);
        }
    }
}
