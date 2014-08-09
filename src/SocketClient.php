<?php
class SocketClient{
	public $host;
	public $port;
	private $_socket;
	
	public function __construct($host, $port)
	{
		$this->host = $host;
		$this->port = $port;
		if(($this->_socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP))===false){
			echo 'socket create error:'.socket_strerror(socket_last_error($this->_socket));
			return false;
		}

		$result=@socket_connect($this->_socket ,$this->host, $this->port);
		if ($result === FALSE) {
			echo "socket connect error:".socket_strerror(socket_last_error($this->_socket))."\n";
			return false;
		}
	}
	
	public function send($msg)
	{
		$result = socket_write($this->_socket,$msg,strlen($msg));
		if ($result) 
		{
			$result = @socket_read($this->_socket, 1024);
		}
		
		return $result;
	}
	
	public function __destruct()
	{
		$this->disconnect();
	}
	
	public function disconnect()
	{
		if ($this->_socket && is_resource($this->_socket)) {
			socket_close($this->_socket);
		}
	}
}