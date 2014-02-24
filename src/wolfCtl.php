<?php
   
class wolfCtl{
	public $host='127.0.0.1';
	public $port=3838;
	public $pidfile='';
	private $_socket;
	
	public function __construct($config)
	{
		if (!is_file($config)) {
			echo "config file:$config is not a file or does not exist.\n";
			exit;
		}
		$this->readConfig($config);
		$this->_socket = new socketServer($this->host, $this->port);
	}
	
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
			}elseif ($item==='wolfserver')
			{
				foreach ($detail as $name=>$value)
				{
					if ($name==='pidfile') {
						$this->pidfile=$value;
					}
				}
			}
		}
	}
	
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
		$msg="Usage: wolfctl <command>\n\n";
		$msg.="support command list:\n";
		$msg.="\tstatus\tGet all process status info\n";
		$msg.="\thelp\tshow this list\n";
		$msg.="\treload\treload the config\n";
		$msg.="\tstart <name>\tstart a process\n";
		$msg.="\tstop <name>\tstop a process\n";
		$msg.="\tshutdown\tShut the wolfserver down.\n";
		$msg.="\trestart <name>\trestart a process\n";
		return $msg;
	}
	
	public function shutdownCommand()
	{
// 		$wolfPid=file_get_contents($this->pidfile);
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
	
	public function send($cmd)
	{
		return $this->_socket->send($cmd."\n");
	}
}