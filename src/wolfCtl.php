<?php

/**
 * Class wolfCtl
 * wolf客户端类，通过socket和wolf server通信
 */
class wolfCtl{
    /**
     * @var string wolf server 地址
     */
    public $host='127.0.0.1';
    /**
     * @var int wolf server 端口
     */
    public $port=3838;
    /**
     * @var
     */
    private $_socket;
	
	public function __construct($config)
	{
		if (!is_file($config)) {
			echo "config file:$config is not a file or does not exist.\n";
			exit;
		}
		$this->readConfig($config);
		$this->_socket = new SocketClient($this->host, $this->port);
	}

    /**
     * 读取配置
     * @param string $path 配置文件路径
     * @throws Exception
     */
    public function readConfig($path)
	{
		$config = parse_ini_file($path, true);
		
		if ($config=== FALSE) {
			throw new Exception('read config file error.');
		}
		
		foreach ($config as $item=>$detail)
		{
			if ($item==='wolfctl')
			{
				foreach ($detail as $name=>$value)
				{
					if (property_exists($this , $name)) {
						$this->$name = $value;
					}
				}
			}
		}
	}

    /**
     * 解析命令
     * @param string $cmd
     * @return string
     */
    public function parseCmd($cmd)
	{
		if ($cmd=='help' || empty($cmd)) {
			return $this->helpCommand();
		}elseif($cmd=='shutdown'){
			return $this->shutdownCommand();
		}
		return $this->send($cmd);
	}
	
	public function helpCommand()
	{
        $msg=<<<'EOD'
Usage: wolfctl <command>
support command list:
    status                      get all process status info
    help                        show this list
    reload                      reload the config
    start <name>        start a process
    stop <name>         stop a process
    restart <name>      restart a process
    shutdown                shut the wolfserver down.

EOD;
		return $msg;
	}

    /**
     * 关闭wolf server
     * @return string
     */
    public function shutdownCommand()
	{
		echo $this->send('shutdown');
		$wolfPid=trim($this->send('pid'),"\n");
		if (is_numeric($wolfPid)) 
		{
			if(posix_kill($wolfPid, SIGINT))
				return "Shut Down wolfserver with pid $wolfPid.\n";
			else 
				return "Shut Down wolfserver failed with pid $wolfPid.\n";
		}else{
			return "wolfserver pid is not a valid number.\n";
		}
	}

    /**
     * 向wolf server发送命令
     * @param $cmd
     * @return mixed
     */
    public function send($cmd)
	{
		return $this->_socket->send($cmd."\n");
	}
}