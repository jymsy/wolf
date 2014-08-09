<?php
declare(ticks = 1);

/**
 * Class WolfServer
 *  wolf 服务端程序，负责控制程序的启动、停止，
 *  监听socket端口，加载配置等等。
 */
class WolfServer{
	/**
	 * @var string 配置文件路径
	 */
	private $_configPath;
	/**
	 * @var string 监听的服务器ip
	 */
	public $host = '127.0.0.1';
	/**
	 * @var integer 监听的端口
	 */
	public $port = 3838;
	/**
	 * @var string 日志文件路径
	 * 默认为空，则在var/wolf.log
	 */
	public $logfile ='';
	/**
	 * @var integer 单个日志文件的最大大小
	 */
	public $logfile_maxsize = 10240;
	/**
	 * @var integer 日志文件的保存个数
	 */
	public $logfile_backups = 5;
	/**
	 * @var string 日志级别
	 */
	public $loglevel = 'info';
	/**
	 * @var FileLog
	 */
	private static $_log;
	/**
	 * @var Process
	 */
	public $process;
	/**
	 * @var array 命令和处理函数对应数组
	 */
	private $_cmdList = array(
			'status'=>'statusCommand',
			'stop'=>'stopCommand',
			'start'=>'startCommand',
			'reload'=>'reloadCommand',
			'restart'=>'restartCommand',
			'help'=>'helpCommand',
			'pid'=>'pidCommand',
			'shutdown'=>'shutdownCommand',
	);
	
	public function __construct($config)
	{
		if (!is_file($config)) {
			echo "config file:$config is not a file or does not exist.\n";
			exit;
		}
		$this->_configPath = $config;
		$this->process = new Process();
		$this->logfile=dirname(__DIR__).'/var/wolf.log';
		$this->readConfig($config);
	}

    /**
     * 读取配置文件
     * @param $path
     * @return bool
     * @throws Exception
     */
    public function readConfig($path)
	{
		$config = parse_ini_file($path, true);
	
		if ($config=== FALSE) {
			throw new Exception('read config file error.');
		}
		$this->totalprocess = array();
		foreach ($config as $item=>$detail)
		{
			if ($item==='wolfserver')
			{
				foreach ($detail as $name=>$value)
				{
					if (property_exists($this , $name)) {
						$this->$name = $value;
					}
				}
			}elseif (strncasecmp($item, "program:", 8)==0){
				$cmdname=substr($item, 8);
				foreach ($this->process->programDefaultConfig as $name=>$value)
				{
					if(!isset($detail[$name]))
						$detail[$name]=$value;
				}
				$this->process->totalprocess[$cmdname]=$detail;
			}
		}
		var_dump($this->process->totalprocess);
		self::$_log = new FileLog($this->logfile, $this->logfile_backups, $this->logfile_maxsize);
        self::$_log->logLevel = explode(',',$this->loglevel);
		self::log("load config from $path", FileLog::LEVEL_INFO);
		return true;
	}

    /**
     * fork 进程，启动socket
     * @throws Exception
     */
    public function wait()
	{
		$this->daemonize();
		$this->process->pid=posix_getpid();
		$pid = pcntl_fork();
			
		if($pid === -1)
			throw new \Exception('Unable to fork child process.');
		elseif($pid)
		{//父进程启动进程
			$this->process->runCmd();
			$run=true;			
			$this->process->serverPid = $pid;
			$this->process->listen();
			while ($run) 
			{
				if($this->process->recvMsg($cmd, $this->process->pid))
				{
					if ($cmd=='killself') {
						$run=false;
					}else
						$this->processCmd($cmd);
				}
				usleep(200000);
			}
		}else{//用来监听socket的子进程
			$this->process->master=false;
			$this->process->ppid = $this->process->pid;
			$this->process->pid = posix_getpid();
			self::log("listenning client at $this->host:$this->port on pid:".$this->process->pid, FileLog::LEVEL_TRACE);
			$server = new SocketThreadServer($this->host, $this->port);
			if ($server) {
				$server->listen(array($this, 'parseCmd'));
			}
			$this->process->sendMsg('killself', $this->process->ppid, $this->process->ppid);
			exit;
		}
	}

    /**
     * 以守护进程方式启动
     * @throws Exception
     */
    protected function daemonize()
	{
		$pid=pcntl_fork();
		if ($pid === -1) {
			throw new \Exception('Unable to fork child process.');
		}elseif($pid)
		{
			pcntl_wait($status, WNOHANG);
			exit;
		}
		chdir("/");
		umask(0);
		// Make first child as session leader.
		$sid = posix_setsid();
		if ($sid === -1) {
			throw new \Exception('Unable to setid process.');
		}
		// Create second child.
		if(pcntl_fork())
		{
			// If pid not 0, means this process is parent, close it.
			exit;
		}
	}
	
	private function processCmd($cmd)
	{
		$msg="\n";
		$args = explode(' ', $cmd);
		$cmd = array_shift($args);
	
		if (!array_key_exists($cmd, $this->_cmdList)) {
			if ($cmd=='') {
				$msg = $this->helpCommand();
			}else
				$msg = "invalid cmd:$cmd\n";
		}else{
			if (is_callable(array($this,$this->_cmdList[$cmd]))) {
				$msg = call_user_func_array(array($this,$this->_cmdList[$cmd]), $args);
			}else{
				$msg = "exec command error.\n";
			}
		}
	
// 		msg_send($this->process->queue, $this->process->serverPid, $msg, true, false, $error);
		$this->process->sendMsg($msg, $this->process->serverPid,$this->process->pid);
	}
	
	public function parseCmd($cmd)
	{
// 		if (!is_resource($this->process->queue) || !msg_stat_queue($this->process->queue))
// 		{
// 			$this->process->queue = msg_get_queue($this->process->ppid);
// 		}
// 		msg_send($this->process->queue, $this->process->ppid, $cmd, true, false, $error);
		$this->process->sendMsg($cmd, $this->process->ppid,$this->process->ppid);
		if(msg_receive($this->process->queue, $this->process->pid, $null, 1024, $result, true))
		{
			return $result;
		}
		return "exec cmd error\n";
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
		$msg.="\trestart <name>\trestart a process\n";
		return $msg;
	}
	
	public function statusCommand()
	{
		$msg = "total process :".count($this->process->totalprocess)."\n";
// 		$msg .= "total running process :".count($this->childprocess)."\n";
		$msg.="wolf memory usage:".$this->getReadableFileSize(memory_get_usage())."\n\n";
		foreach ($this->process->totalprocess as $name => $detail)
		{
			$msg.="$name\t\t";
			$running = false;
			foreach ($this->process->childprocess as $pid=>$child){
				if ($child->name == $name) {
					$running = true;
					break;
				}
			}
	
			if ($running) {
				if (empty($child->cmdpid)) {
					if($this->process->recvMsg($msgArr, $child->pid))
					{
						if ($msgArr['from']==$child->pid) {
							$this->log("get child:".$msgArr['from']." type:".$msgArr['type']." msg:".$msgArr['value'], FileLog::LEVEL_TRACE);
							$child->cmdpid = $msgArr['value'];
						}
					}
				}
				$msg.="RUNNING\tcmdpid:$child->cmdpid, ";
				$msg.="mem usage:".$child->getMemSize($child->cmdpid)." KB, ";
				$msg.="up time:".$this->formatTime(time()-$child->startTime);
			}else{
				$msg.="STOPPED\t".$detail['last_stop_time'];
			}
			$msg.="\n";
		}
		return $msg;
	}
	
	public function startCommand($name=null)
	{
		if (!$name) {
			return "process name is invalid\n";
		}
		$this->log("start process:$name", FileLog::LEVEL_INFO);
		if (array_key_exists($name, $this->process->totalprocess)) {
			$running = false;
			foreach ($this->process->childprocess as $pid=>$child)
			{
				if ($child->name == $name)
				{
					$running = true;
					$msg="process $name is running.you may execute command 'restart' or 'stop'\n";
				}
			}
			if (!$running) {
				$child = $this->process->spawn($name, $this->process->totalprocess[$name], $this->process->totalprocess[$name]['autorestart']==='1');
				$msg="start process:$name success.pid is $child->pid\n";
			}
		}else{
			$msg =  "process name:$name is not exist.\n";
		}
	
		return $msg;
	}
	
	public function stopCommand($name=null,&$cmdpid=null, $fromrestart = false)
	{
		if (!$name) {
			return "process name is invalid\n";
		}
		$find = false;
		foreach ($this->process->childprocess as $pid=>$child)
		{
			if ($child->name == $name) {
				$find = true;
				$ok = false;
				$this->log("stop process $name", FileLog::LEVEL_INFO);
	
				$old=$this->process->totalprocess[$name]['autorestart'];
				if (empty($child->cmdpid))
				{
					if($this->process->recvMsg($msg, $pid))
					{
						if ($msg['from']==$child->pid) {
							$this->log("get child:".$msg['from']." type:".$msg['type']." msg:".$msg['value'], FileLog::LEVEL_TRACE);
							$child->cmdpid = $msg['value'];
							
							if(!$fromrestart )
							{
								$this->process->totalprocess[$name]['autorestart']='';
							}
							$ok = posix_kill($child->cmdpid, SIGINT);
							$cmdpid=$child->cmdpid;
						}
					}
				}else {
					if(!$fromrestart )
					{
						$this->process->totalprocess[$name]['autorestart']='';
					}
					$ok = posix_kill($child->cmdpid, SIGINT);
					$cmdpid=$child->cmdpid;
				}
					
				if (posix_get_last_error() == 1)
					$ok = false;
	
				while (file_exists("/proc/$cmdpid") && $ok)
				{
					usleep(200000);
				}
				
				$msg = $ok?"kill child process $name with pid:$pid success\n":"kill child process $name with pid:$pid failed\n";
				sleep($this->process->totalprocess[$name]['startsecs']);
				$this->process->totalprocess[$name]['autorestart']=$old;
				break;
			}
		}
		if(!$find)
			$msg="Error:could not find process $name\n";
		return $msg;
	}
	
	public function reloadCommand($args = null)
	{
		self::log("reload config", FileLog::LEVEL_INFO);
		$tempArray = $this->process->totalprocess;
		$this->readConfig($this->_configPath);
		$running = false;
		foreach ($tempArray as $name=>$detail)
		{
			if (!array_key_exists($name, $this->process->totalprocess)) {
				foreach ($this->childprocess as $pid=>$child)
				{
					if ($child->name == $name) {
						$running = true;
						break;
					}
				}
			}
		}
	
		if ($running) {
			$this->process->totalprocess = $tempArray;
			$msg = "process $name is still running.you should stop it first.\n";
		}else{
			$msg = "reload config success.\n";
		}
	
		return $msg;
	}
	
	public function restartCommand($name = null)
	{
		if (!$name) {
			return "process name is invalid\n";
		}
		self::log("restart program $name", FileLog::LEVEL_INFO);
		$msg=$this->stopCommand($name, $cmdpid, true);
		if (empty($cmdpid)) {
			return $msg;
		}
		if ($this->process->totalprocess[$name]['autorestart']!=='1')
		{
			while (file_exists("/proc/$cmdpid"))
			{
				usleep(200000);
			}
			sleep($this->process->totalprocess[$name]['startsecs']);
			$msg.=$this->startCommand($name);
			return $msg;
		}
		return $msg.="start process $name success.\n";
	}
	
	public function pidCommand($args = null)
	{
		return $this->process->pid."\n";
	}
	
	public function shutdownCommand()
	{
		$msg='';
		foreach ($this->process->childprocess as $pid=>$child)
		{
			$msg.=$this->stopCommand($child->name);
		}
		return $msg;
	}
	
	public function getReadableFileSize($size, $retstring = null) 
	{
		// adapted from code at http://aidanlister.com/repos/v/function.size_readable.php
		$sizes = array('bytes', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
	
		if ($retstring === null) { $retstring = '%01.2f %s'; }
	
		$lastsizestring = end($sizes);
	
		foreach ($sizes as $sizestring) {
			if ($size < 1024) { break; }
			if ($sizestring != $lastsizestring) { $size /= 1024; }
		}
		if ($sizestring == $sizes[0]) { $retstring = '%01d %s'; } // Bytes aren't normally fractional
		return sprintf($retstring, $size, $sizestring);
	}
	
	public function formatTime($sec)
	{
		$output = '';
		foreach (array(86400 => 'd', 3600 => 'h', 60 => 'm', 1 => 's') as $key => $value)
		{
			if ($sec >= $key)
				$output .= floor($sec/$key).$value.' ';
			$sec %= $key;
		}
		return $output;
	}
	
	public static function log($msg, $level)
	{
		self::$_log->log($msg, $level);
	}
}