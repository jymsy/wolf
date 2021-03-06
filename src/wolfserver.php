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
     * @var string wolf所在服务器名
     */
    public $name = 'test';
	/**
	 * @var Process
	 */
	public $process;
    /**
     * @var WolfServer
     */
    public static $app;
    /**
     * @var string smtp 服务器
     */
    public $mail_host='';
    /**
     * @var string 邮箱用户名
     */
    public $mail_account='';
    /**
     * @var string 邮箱用户密码
     */
    public $mail_pwd='';
	/**
	 * @var array 命令和处理函数对应数组
	 */
	private $_cmdList = array(
			'status'=>'statusCommand',
			'stop'=>'stopCommand',
			'start'=>'startCommand',
			'reload'=>'reloadCommand',
			'restart'=>'restartCommand',
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
        self::$app = $this;
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
        echo $this->printProcessConfig();
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
            try{
                $server = new SocketThreadServer($this->host, $this->port);
                $server->listen(array($this, 'parseCmd'));
            }catch (Exception $e){
                echo $e->getMessage();
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

    /**
     * 执行命令
     * @param string $cmd
     */
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
	
		$this->process->sendMsg($msg, $this->process->serverPid,$this->process->pid);
	}

    /**
     * 将命令从socket进程发送到父进程
     * @param $cmd
     * @return string
     */
    public function parseCmd($cmd)
	{
		$this->process->sendMsg($cmd, $this->process->ppid,$this->process->ppid);
		if(msg_receive($this->process->queue, $this->process->pid, $null, 1024, $result, true))
		{
			return $result;
		}
		return "exec cmd error\n";
	}

    /**
     * 获取进程状态
     * @return string
     */
    public function statusCommand()
	{
		$msg = "total process :".count($this->process->totalprocess)."\n";
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

    /**
     * 启动进程
     * @param string $name
     * @return string
     */
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
                $files = $this->process->createLogFile($name, $this->process->totalprocess[$name]);
				$child = $this->process->parallel(array($this->process, 'parllelCallback'),
                    $this->process->totalprocess[$name]['command'], $files, $name);

				$msg="start process:$name success.pid is $child->pid\n";
			}
		}else{
			$msg =  "process name:$name is not exist.\n";
		}
	
		return $msg;
	}

    /**
     * 停止进程
     * @param string $name
     * @param string $cmdpid
     * @param bool $fromrestart
     * @return string
     */
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
			$msg="Error:could not find process '$name' or process has already stopped.\n";
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

    /**
     * 获取主进程的pid
     * @return string
     */
    public function pidCommand()
	{
		return $this->process->pid."\n";
	}

    /**
     * 停止所有正在运行的进程
     * @return string
     */
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

    /**
     * 记录日志文件
     * @param string $msg 日志内容
     * @param string $level 日志等级
     */
    public static function log($msg, $level)
	{
		self::$_log->log($msg, $level);
	}

    /**
     *  打印进程配置
     */
    public function printProcessConfig(){
        $config='';
        foreach($this->process->totalprocess as $name=>$process){
            $config.="name:$name\n";
            foreach($process as $k=>$v){
                $config.="\t$k:$v\n";
            }
            $config.="\n";
        }
        return $config;
    }
}