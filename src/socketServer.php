<?php
class socketServer{
	public $host;
	public $port;
	private $_socket;
	
	public function __construct($host, $port, $isclient=false)
	{
		$this->host = $host;
		$this->port = $port;
		if(($this->_socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP))===false){
			echo 'socket create error:'.socket_strerror(socket_last_error($this->_socket));
			return false;
		}
		
		if ($isclient) {
			$result=@socket_connect($this->_socket ,$this->host, $this->port);
			if ($result === FALSE) {
				echo "socket connect error:".socket_strerror(socket_last_error($this->_socket))."\n";
				return false;
			}
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
	
	/**
	 * @param callable $callback
	 * @throws Exception
	 */
	public function listen($callback)
	{
		if (empty($this->host) || empty($this->port)) {
			throw new Exception('host ip or port is invalied');
		}
		
		if(@socket_bind($this->_socket, $this->host, $this->port)===FALSE)       //绑定要监听的端口
		{
			echo "socket bind failed:". socket_strerror(socket_last_error($this->_socket));
			return false;
		}
		
		if(@socket_listen($this->_socket)===FALSE)       //监听端口
		{
			echo "socket listen failed: " . socket_strerror(socket_last_error($this->_socket));
			return false;
		}
		
		while(1)
		{
			$connection = socket_accept($this->_socket);
			if ($connection === FALSE) {
				echo "socket accept error : ".socket_strerror(socket_last_error($this->_socket));
			}else{
				while ($data = @socket_read($connection, 1024, PHP_NORMAL_READ)) 
				{
					$result = call_user_func($callback, trim($data));
					socket_write($connection, $result,strlen($result));
				}
				
				socket_close($connection);
			}
		}
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