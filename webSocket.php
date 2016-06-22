<?php
    error_reporting(E_ALL);
    set_time_limit(0);
    ob_implicit_flush();

    //创建一个socket连接 设置参数 绑定 监听 并且返回
    $master  = WebSocket("localhost",9998);

    //标示是否已经进行过握手了
    $is_shaked = false;

    //是否已经关闭
    $is_closed = true;

    //将socket变为一个可用的socket

    while(true){
        //如果是关闭状态并且是没有握手的话 则创建一个可用的socket（貌似第二个条件可以去除）
        if($is_closed && !$is_shaked){
            if(($sock = socket_accept($master)) < 0){
                echo "socket_accept() failed: reason: " . socket_strerror($sock) . "\n";
            }

            //将关闭状态修改为false
            $is_closed = false;
        }

        //开始进行数据处理
        process($sock);
    }

    //处理请求的函数
    function process($socket){
        //先从获取到全局变量
        global $is_closed, $is_shaked;

        //从socket中获取数据
        $buffer = socket_read($socket,2048);

        //如果buffer返回值为false并且已经握手的话 则断开连接
        if(!$buffer && $is_shaked){
            disconnect($socket);
        }else{
            //如果没有握手的话则握手 并且修改握手状态
            if($is_shaked == false){
                $return_str = dohandshake($buffer);
                $is_shaked = true;
            }else{
                //如果已经握手的话则送入deal函数中进行相应处理
                $data_str = decode($buffer);    //解析出来的从前端送来的内容
                console($data_str);
                $return_str = encode(deal($socket, $data_str));
                //$return_str = encode($data_str);
                $startTime=microtime(true);
                for($i=0;$i<100000;$i++)
                {
                    $test_str = encode(deal($socket, 'test'.$i));
                    socket_write($socket,$test_str,strlen($test_str));
                }
                $endTime=microtime(true);
                echo 'Send One hundred thousand Socket Time:'.($endTime-$startTime)."s\n";
            }
            
            //将应该返回的字符串写入socket返回
            socket_write($socket,$return_str,strlen($return_str));
        }
    }

    function deal($socket, $msgObj){
        $obj = json_decode($msgObj);
        if ($obj == null) {
            console($msgObj."\n");
            return $msgObj;
        }
        foreach($obj as $key=>$value){
            if($key == 'close'){
                disconnect($socket);
                console('close success');
                return 'close success';
            }else if($key == 'msg'){
                console($value."\n");
                return $value;
            }
        }
    }

    //获取头部信息 
    function getheaders($req){
        $r=$h=$o=null;
        if(preg_match("/GET (.*) HTTP/"   ,$req,$match)){ $r=$match[1]; }
        if(preg_match("/Host: (.*)\r\n/"  ,$req,$match)){ $h=$match[1]; }
        if(preg_match("/Origin: (.*)\r\n/",$req,$match)){ $o=$match[1]; }
        if(preg_match("/Sec-WebSocket-Key: (.*)\r\n/",$req,$match)){ $key=$match[1]; }
        if(preg_match("/\r\n(.*?)\$/",$req,$match)){ $data=$match[1]; }
        return array($r,$h,$o,$key,$data);
    }

    function WebSocket($address,$port){
        $master=socket_create(AF_INET, SOCK_STREAM, SOL_TCP)     or die("socket_create() failed");
        socket_set_option($master, SOL_SOCKET, SO_REUSEADDR, 1)  or die("socket_option() failed");
        socket_bind($master, $address, $port)                    or die("socket_bind() failed");
        socket_listen($master,20)                                or die("socket_listen() failed");
        echo "Server Started : ".date('Y-m-d H:i:s')."\n";
        echo "Master socket  : ".$master."\n";
        echo "Listening on   : ".$address." port ".$port."\n\n";
        return $master;
    }

    function dohandshake($buffer){
        list($resource,$host,$origin,$key,$data) = getheaders($buffer);
        echo "resource is $resource\n";
        echo "origin is $origin\n";
        echo "host is $host\n";
        echo "key is $key\n\n";

        $response_key = base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

        $return_str = "HTTP/1.1 101 Switching Protocols\r\n".
                    "Upgrade: websocket\r\n".
                    "Connection: Upgrade\r\n".
                    "Sec-WebSocket-Accept: $response_key\r\n\r\n";
        return $return_str;
    }

    function console($msg){
        $msg = transToGBK($msg);
        // echo "$msg\n";
        return $msg;
    }

    function decode($msg="") {
        $mask = array();
        $data = "";
        $msg = unpack("H*",$msg);

        $head = substr($msg[1],0,2);

        if (hexdec($head{1}) === 8){
            $data = false;
        } else if (hexdec($head{1}) === 1){
            $mask[] = hexdec(substr($msg[1],4,2));
            $mask[] = hexdec(substr($msg[1],6,2));
            $mask[] = hexdec(substr($msg[1],8,2));
            $mask[] = hexdec(substr($msg[1],10,2));

            $s = 12;
            $e = strlen($msg[1])-2;
            $n = 0;
            for ($i= $s; $i<= $e; $i+= 2){
                $data .= chr($mask[$n%4]^hexdec(substr($msg[1],$i,2)));
                $n++;
            }  
        }  

        return $data;
    }

    function encode($msg=""){
        $frame = array();
        $frame[0] = "81";
        $msg .= '后端已连接';
        $len = strlen($msg);
        $frame[1] = $len<16?"0".dechex($len):dechex($len);
        $frame[2] = ord_hex($msg);
        $data = implode("",$frame);
        return pack("H*", $data);
    }


    function transToGBK($s){//UTF8->GBK
        //echo $s;
        // var_dump($s);
        // return iconv("UTF-8", "GBK", $s);
        return $s;
    }

    function ord_hex($data){
        $msg = "";
        $l = strlen($data);

        for ($i=0; $i<$l; $i++){
            //ord是返回字符串第一个字符的ascii值
            //dechex把十进制转换为十六进制
            $msg .= dechex(ord($data{$i}));
        }

        return $msg;
    }

    function disconnect($socket){
        global $is_shaked, $is_closed;
        $is_shaked = false;
        $is_closed = true;
        socket_close($socket);
    }