<?php
/**
 * Created by IntelliJ IDEA.
 * User: jym
 * Date: 14-8-9
 * Time: 下午8:22
 */

class SocketThread extends Thread{
    private $_conn;
    private $_callback;

    public function __construct($conn, $callback){
        $this->_conn = $conn;
        $this->_callback = $callback;
    }

    public function run(){
        while ($data = @socket_read($this->_conn, 1024, PHP_NORMAL_READ))
        {
            $result = call_user_func($this->_callback, trim($data));
            socket_write($this->_conn, $result,strlen($result));
        }
        socket_shutdown($this->_conn);
        socket_close($this->_conn);

    }
} 