<?php
/**
 * Created by IntelliJ IDEA.
 * User: jym
 * Date: 14-8-9
 * Time: 下午6:30
 */

class SocketThreadServer {
    private $_host;
    private $_port;
    private $_socket;

    public function __construct($host, $port){
        $this->_host = $host;
        $this->_port = $port;

        if(($socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP))===FALSE){
            throw new Exception('socket create error:'.socket_strerror(socket_last_error($this->_socket)));
        }

        if(@socket_bind($socket, $this->_host, $this->_port)===FALSE)       //绑定要监听的端口
        {
            throw new Exception("socket bind failed:". socket_strerror(socket_last_error($socket)));
        }
        if(@socket_listen($socket,10)===FALSE)       //监听端口
        {
            throw new Exception("socket listen failed: " . socket_strerror(socket_last_error($socket)));
        }

        $this->_socket = $socket;
    }

    public function listen($callback){
        if($this->_socket && is_resource($this->_socket)){
            while(1){
                $connection = socket_accept($this->_socket);
                if ($connection === FALSE) {
                    echo "socket accept error : ".socket_strerror(socket_last_error($this->_socket));
                }else{
                    $thread = new SocketThread($connection, $callback);
                    $thread->start();
                }
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