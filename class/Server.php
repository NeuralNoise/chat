<?php

class Server {

	private $config;
    private $host;
    private $port;
	private $starttime;
	private $socket;
	private $connections;
    private $chat;
	private $clients;
	private $ips;
	private $uid;
	private $online;

	public function __construct($config) {

        $this->config = $config;
		$this->connections = array();
		$this->clients = array();
		$this->ips = array();
		$this->uid = 1;
		$this->online = 0;

		if($this->config['log']){
			Console::start();
			Console::write("WebsocketServer __construct"); 
		}
    }

	public function __destruct() {
		Console::write("WebsocketServer __destruct()");
		Console::write("time = ".(round(microtime(true),2) - $this->starttime)); 
		/*
		fclose($this->socket);
		Console::write("socket - closed");	
		unlink($this->config['pidfile']);
		Console::write("pidfile ".$this->config['pidfile']." unlinked");
		*/
		Console::end();
		exit();		
    }

    public function start() {

        Console::write("WebsocketServer start()");

		$pidfile = $this->config['pidfile_on'];
		$offfile = $this->config['pidfile_off'];
		$this->starttime = round(microtime(true),2);

		$pid = getmypid();
		file_put_contents($pidfile, $pid);// save PID to file

		Console::write("WebsocketServer start() getmypid() PID = ".$pid);

		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		if (!$this->socket) {
			Console::write("WebsocketServer start() socket_create() ERROR");
			unlink($pidfile);
			Console::write("WebsocketServer start() pidfile ".$pidfile." ulinked");
			Console::end();
			die();
		}

        Console::write("WebsocketServer start() socket_create() SUCCESS");

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->socket, 0, $this->config['port']);
        socket_listen($this->socket);

        $this->connections = array($this->socket);
        $this->chat = new Chat($this->config);

		while (true) {

			Console::write("WebsocketServer start() beginning while loop");

            $read = $this->connections;
            $write = null;
            $except = null;

/////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////

            if (!socket_select($read, $write, $except, null)) {
                break;
            }

            if (in_array($this->socket, $read)) {

                if ($new_socket = socket_accept($this->socket)) {

                    $header = socket_read($new_socket, 1024);
                    $info = $this->handshake($header, $new_socket);

                    if(!isset($this->ips[$info['ip']])){
                        $this->ips[$info['ip']] = 1;
                    }
                    else {
                        $this->ips[$info['ip']]++;
                        if($this->ips[$info['ip']] > $this->config['max_connections_from_ip']){

                            Console::write("WebsocketServer too much connections from IP ".$info['ip'].", rejecting");

                            continue;
                        }
                    }

                    Console::write("WebsocketServer new connection from IP: ".$info['ip']);

                    $this->connections[] = $new_socket;

                    $info['uid'] = $this->uid++;
                    if($this->uid > 10000) {
                        $this->uid = 1;
                    }

                    $this->online++;
                    $this->clients[$this->uid] = $info;

                    $this->onOpen($new_socket);
                }
                unset($read[ array_search($this->socket, $read) ]);
            }

            foreach($read as $read_socket) { // process all connections

                $buffer = socket_read($read_socket, 100000);

//                $error_code = socket_last_error();
//                if($error_code == 104){
//                    $this->closeSocket($pidfile, $offfile);
//                    //$this->start();
//                    $this->__destruct();
//                }

                if (!$buffer) { // connection closed

                    $uid = array_search($read_socket, $this->connections);//get uid
                    if(isset($this->clients[$uid])) {
                        if ($this->ips[$this->clients[$uid]['ip']] == 1) {
                            unset($this->ips[$this->clients[$uid]['ip']]); //remove IP
                        } else {
                            $this->ips[$this->clients[$uid]['ip']]--;
                        }
                    }
                    $this->online --;

                    unset($this->clients[ array_search($read_socket, $this->connections) ]); //remove client info
                    unset($this->connections[ array_search($read_socket, $this->connections) ]); //remove connection info

                    socket_close($read_socket);//close connection

                    $this->onClose($uid);
                    Console::write("WebsocketServer connection closed");
                    continue;
                }

                $this->onMessage($read_socket, $buffer);
            }


/////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////

			if(file_exists($offfile)){
                $this->closeSocket($pidfile, $offfile);
			}
		}
	}

	private function handshake($header, $connection) {

        $info = array();

        $lines = preg_split("/\r\n/", $header);
        foreach($lines as $line) {
            $line = chop($line);
            if(preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $info[$matches[1]] = $matches[2];
            }
        }

        socket_getpeername($connection, $address, $port); // getting client address
        $info['ip'] = $address;
        $info['port'] = $port;

        if (empty($info['Sec-WebSocket-Key'])) {
            return false;
        }

        $secWebSocketAccept = base64_encode(pack('H*', sha1($info['Sec-WebSocket-Key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $upgrade =  "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
                    "Upgrade: websocket\r\n" .
                    "Connection: Upgrade\r\n" .
                    "Sec-WebSocket-Accept:".$secWebSocketAccept."\r\n\r\n";

        socket_write($connection, $upgrade);

		return $info;
	}

    protected function closeSocket($pidfile, $offfile){

        socket_close($this->socket);
        Console::write("socket - closed");
        unlink($pidfile);
        Console::write("pidfile ".$pidfile." unlinked");

        if(file_exists($offfile)){

            Console::write("off file found");
            Console::write("time = ".(round(microtime(true),2) - $this->starttime));
            if(!unlink($offfile)) {
                Console::write("ERROR DELETING OFF FILE".$offfile);
                exit(-1);
            }

            Console::write("offfile ".$offfile." unlinked");
        }

        Console::end();
        exit();
    }

	protected function sendMessage($message){
        Console::write('<pre>'.print_r($message, true).'</pre>');
        if(!empty($message)) {
            foreach ($message['targets'] as $index => $target_id) {
                if ($target_id > 0) {
                    $message_type = $message['type'];
                    $message_from = $message['from'];
                    $message_text = (isset($message['text'][$index]) ? $message['text'][$index] : '');
                    $message_data = (isset($message['data'][$index]) ? $message['data'][$index] : '');
                    $encoded_message = $this->encode(json_encode(array('type' => $message_type, 'from' => $message_from, 'text' => $message_text, 'data' => $message_data)));
                    if(isset($this->connections[$target_id])) {
                        socket_write($this->connections[$target_id], $encoded_message);
                    }
                }
            }
        }
    }

    protected function onOpen($connection) {
        $uid = array_search($connection, $this->connections);
        $result = $this->chat->start($uid);
        $this->sendMessage($result);
	}

	protected function onMessage($connection, $message) {

		$uid = array_search($connection, $this->connections);
        $decoded_message = $this->decode($message);

		if($decoded_message['payload'] == "" || $decoded_message['payload'] == " ") {
		    return;
        }

        $payload = json_decode($decoded_message['payload'], true);
        $result = $this->chat->process($uid, $payload);
        $this->sendMessage($result);
	}

    protected function onClose($uid) {
        $result = $this->chat->removeFromChat($uid);
        $this->sendMessage($result);
   }

    private function encode($payload, $type = 'text', $masked = false) {
        $frameHead = array();
        $payloadLength = strlen($payload);

        switch ($type) {
            case 'text':
                // first byte indicates FIN, Text-Frame (10000001):
                $frameHead[0] = 129;
                break;

            case 'close':
                // first byte indicates FIN, Close Frame(10001000):
                $frameHead[0] = 136;
                break;

            case 'ping':
                // first byte indicates FIN, Ping frame (10001001):
                $frameHead[0] = 137;
                break;

            case 'pong':
                // first byte indicates FIN, Pong frame (10001010):
                $frameHead[0] = 138;
                break;
        }

        // set mask and payload length (using 1, 3 or 9 bytes)
        if ($payloadLength > 65535) {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 255 : 127;
            for ($i = 0; $i < 8; $i++) {
                $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
            }
            // most significant bit MUST be 0
            if ($frameHead[2] > 127) {
                return array('type' => '', 'payload' => '', 'error' => 'frame too large (1004)');
            }
        } elseif ($payloadLength > 125) {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        } else {
            $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
        }

        // convert frame-head to string:
        foreach (array_keys($frameHead) as $i) {
            $frameHead[$i] = chr($frameHead[$i]);
        }
        if ($masked === true) {
            // generate a random mask:
            $mask = array();
            for ($i = 0; $i < 4; $i++) {
                $mask[$i] = chr(rand(0, 255));
            }

            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);

        // append payload to frame:
        for ($i = 0; $i < $payloadLength; $i++) {
            $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        }

        return $frame;
    }

    private function decode($data){
        $unmaskedPayload = '';
        $decodedData = array();

        // estimate frame type:
        $firstByteBinary = sprintf('%08b', ord($data[0]));
        $secondByteBinary = sprintf('%08b', ord($data[1]));
        $opcode = bindec(substr($firstByteBinary, 4, 4));
        $isMasked = ($secondByteBinary[0] == '1') ? true : false;
        $payloadLength = ord($data[1]) & 127;

        // unmasked frame is received:
        if (!$isMasked) {
            return array('type' => '', 'payload' => '', 'error' => 'protocol error (1002)');
        }

        switch ($opcode) {
            // text frame:
            case 1:
                $decodedData['type'] = 'text';
                break;

            case 2:
                $decodedData['type'] = 'binary';
                break;

            // connection close frame:
            case 8:
                $decodedData['type'] = 'close';
                break;

            // ping frame:
            case 9:
                $decodedData['type'] = 'ping';
                break;

            // pong frame:
            case 10:
                $decodedData['type'] = 'pong';
                break;

            default:
                return array('type' => '', 'payload' => '', 'error' => 'unknown opcode (1003)');
        }

        if ($payloadLength === 126) {
            $mask = substr($data, 4, 4);
            $payloadOffset = 8;
            $dataLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
        } elseif ($payloadLength === 127) {
            $mask = substr($data, 10, 4);
            $payloadOffset = 14;
            $tmp = '';
            for ($i = 0; $i < 8; $i++) {
                $tmp .= sprintf('%08b', ord($data[$i + 2]));
            }
            $dataLength = bindec($tmp) + $payloadOffset;
            unset($tmp);
        } else {
            $mask = substr($data, 2, 4);
            $payloadOffset = 6;
            $dataLength = $payloadLength + $payloadOffset;
        }

        /**
         * We have to check for large frames here. socket_recv cuts at 1024 bytes
         * so if websocket-frame is > 1024 bytes we have to wait until whole
         * data is transferd.
         */
        if (strlen($data) < $dataLength) {
            return false;
        }

        if ($isMasked) {
            for ($i = $payloadOffset; $i < $dataLength; $i++) {
                $j = $i - $payloadOffset;
                if (isset($data[$i])) {
                    $unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
                }
            }
            $decodedData['payload'] = $unmaskedPayload;
        } else {
            $payloadOffset = $payloadOffset - 4;
            $decodedData['payload'] = substr($data, $payloadOffset);
        }

        return $decodedData;
    }

    /**
     * Getting process info
     */
    public static function getProcessInfo($pid) {

        $result = array ('run' => false);
        $output = null;

        exec("ps -aux -p ".$pid, $output);

        if(count($output) > 1) {
            $result['run'] = true;
            $result['info'] = $output;
        }

        return $result;
    }

    /**
     * Check if process exists
     * reutrn values:
     * $pid: both file and process exist
     * -1: there is not file nor process
     * -2: file exists, process does not
     */
    public static function getProcessStatus($pidfile) {

        if( file_exists($pidfile) ) {

            $pid = file_get_contents($pidfile);
            $output = null;

            exec("ps -aux -p ".$pid, $output);

            if(count($output) > 1){
                return $pid;
            }
            else {
                return -2;
            }
        }

        return -1;
    }
}

