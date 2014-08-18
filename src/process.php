<?php 
require 'FileLog.php';
require 'mail/PHPMailerAutoload.php';
declare(ticks = 1);
/**
 * 进程管理主进程
 * 
 * 可以实现进程的启动、停止，
 * 记录进程的标准输出和标准出错。
 * 获取进程运行状态，内存。
 * 
 * 通过监听本地端口，可以通过wolfctl
 * 或telnet执行管理命令
 * @author Jiangyumeng
 */
class Process {
	/**
	 * @var childProcess[] 子进程对象
	 */
	public $childprocess = array();
	/**
	 * @var array() 进程配置
	 */
	public $totalprocess = array();
	/**
	 * @var boolean 是否是主进程
	 */
	public $master = true;
	/**
	 * @var array() 日志文件后缀
	 */
	public $types = array('stdout_logfile', 'stderr_logfile');
	/**
	 * @var integer 当前进程id
	 */
	public $pid;
	/**
	 * @var integer 父进程id
	 */
	public $ppid;
	/**
	 * @var integer 
	 */
	public $serverPid;
	/**
	 * @var EventEmitter 事件对象
	 */
	public $emit;
	/**
	 * @var array 默认配置
	 */
	public $programDefaultConfig= array(
			'command'=>'',
			'autostart'=>'',
			'autorestart'=>'',
			'startsecs'=>1,
			'startretries'=>3,
			'startretriesecs'=>30,
			'stdout_logfile'=>'auto',
			'stdout_logfile_maxbytes'=>10240,
			'stdout_logfile_backups'=>5,
			'stderr_logfile'=>'auto',
			'stderr_logfile_maxbytes'=>10240,
			'stderr_logfile_backups'=>5,
			'last_stop_time'=>'Not started',
			'mailto'=>'none',
			'alreadyretries'=>0,
			'first_stop_time'=>0,
	);
	/**
	 * @var resource 消息队列
	 */
	public $queue;
	
	public function __construct()
	{
		$this->emit = new EventEmitter();
		
		$this->pid = posix_getpid();
		$this->registerSigHandlers();
		$this->registerShutdownHandlers();
		$this->registerTickHandlers();
	}
	
	public function registerSigHandlers()
	{
		pcntl_signal(SIGTERM, array($this, 'signalHandler'));
		pcntl_signal(SIGINT, array($this, 'signalHandler'));
		pcntl_signal(SIGUSR1, array($this, 'signalHandler'));
		pcntl_signal(SIGUSR2, array($this, 'signalHandler'));
	}
	
	public function registerShutdownHandlers()
	{
		register_shutdown_function(array($this, 'end'));
	}
	
	public function registerTickHandlers()
	{
		register_tick_function(array($this, 'tickHandler'));
	}
	
	public function signalHandler($signal)
	{
		switch ($signal)
		{
			case SIGTERM:
			case SIGINT:
				// Check children
// 				while ($this->childprocess)
// 				{
// 					foreach ($this->childprocess as $child)
// 					{
// 						$child->kill(SIGINT);
// 						$child->shutdown($signal);
// 						$this->clear($child);
// 					}
// 				}
// 				if($this->serverPid)
// 				{
// 					$ret = posix_kill($this->serverPid, SIGKILL);
// 				}
				$this->serverExit($signal);
	
				exit;
				break;
		}
	}
	
	public function tickHandler()
	{
		if ($this->master) {
			while ($pid = pcntl_wait($status, WNOHANG)) 
			{
				if ($pid === -1)
				{
					pcntl_signal_dispatch();
					break;
				}
				
				if (empty($this->childprocess[$pid])) 
					continue;

				$this->childprocess[$pid]->emit->emit('finish', $status);
				$this->childprocess[$pid]->shutdown($status);
				$this->clear($pid);
			}
		}
		$this->emit->emit('tick');
		
		if (!is_resource($this->queue) || !msg_stat_queue($this->queue)) {
			return;
		}
	}
	
	/**
	 * 创建日志文件
	 *
	 * @param string  $name
	 * @param array $detail
	 * @return array files
	 */
    public function createLogFile($name, $detail)
	{
		$dir = dirname(__DIR__).'/var';
		// Files to descriptor
		$files = array();
		
		foreach ($this->types as $type) {
			if ($detail[$type] === 'auto') {
				$files[] = $file = $dir.DIRECTORY_SEPARATOR.$name . '.' . $type;
			}else{
				$files[] = $file = $detail[$type];
			}
			
			$this->log("create log file at $file", FileLog::LEVEL_INFO);
			touch($file);
			chmod($file, 0644);
		}

        return $files;

	}
	
	/**
	 *  创建子进程
	 *
	 * @param callable $callback
	 * @param string   $cmd
	 * @param array   $files
     * @param string $name
	 * @return childProcess
     * @throws Exception
	 */
    public function parallel($callback, $cmd, $files, $name)
	{
		$child = new childProcess($this, null, $this->pid);
		
		$pid = pcntl_fork();
		
		if ($pid === -1) {
			throw new Exception('Unable to fork child process.');
		} else if ($pid) {
            //parent
			$this->log("fork parent:$this->pid,child:$pid", FileLog::LEVEL_TRACE);
			$this->listen();
			// Save child process and return
			$child->init($pid);
			$child->cmd = $cmd;
			$child->name = $name;
			$child->startTime = time();
			$this->childprocess[$pid] = $child;

			$self=$this;
            //当进程停止时调用
			$child->emit->on('finish', function () use (
					$child, $self, $callback, $cmd, $files, $name)
			{
				$self->log("process $child->pid with $cmd is finished", FileLog::LEVEL_INFO);
				$self->totalprocess[$name]['last_stop_time']=@date('Y/m/d H:i');
				if ($self->totalprocess[$name]['mailto']!=='none') {
					$self->sendMail($name, $self->totalprocess[$name]['mailto'], true,$files);
				}
                //配置了autorestart
				if ($self->totalprocess[$name]['autorestart'] === '1') 
				{
					$self->log("retries ".$self->totalprocess[$name]['alreadyretries'], FileLog::LEVEL_INFO);
					if((time()-$self->totalprocess[$name]['first_stop_time']) > $self->totalprocess[$name]['startretriesecs'])
					{
						$self->totalprocess[$name]['first_stop_time']=time();
						$self->totalprocess[$name]['alreadyretries']=1;
						sleep($self->totalprocess[$name]['startsecs']);
						$self->parallel($callback, $cmd, $files, $name);
					}elseif($self->totalprocess[$name]['alreadyretries']<=$self->totalprocess[$name]['startretries'])
					{
						$self->totalprocess[$name]['alreadyretries']++;
						sleep($self->totalprocess[$name]['startsecs']);
						$self->parallel($callback, $cmd, $files, $name);
					}
				}
			});
			
			return $child;
		} else {
			// Child initialize
			$this->childInitialize();
			$childfiles = array();

			$childfiles[] = new FileLog($files[0], 
					$this->totalprocess[$name]['stdout_logfile_backups'],
					$this->totalprocess[$name]['stdout_logfile_maxbytes']);
			
			$childfiles[] = new FileLog($files[1], 
					$this->totalprocess[$name]['stderr_logfile_backups'],
					$this->totalprocess[$name]['stderr_logfile_maxbytes']);
			
			call_user_func($callback, $cmd, $childfiles, $name);
			exit;
		}
	}

    /**
     * 发送进程通知邮件
     * @param $name 进程名
     * @param $sendto 收件人
     * @param bool $finished 进程停止还是启动
     * @param array $files 日志文件路径
     */
    public function sendMail($name,$sendto,$finished=true,$files=array())
	{

		$content='server name:'.WolfServer::$app->name;
		if ($finished) {
			$title='process '.$name.' has been stopped.';
			$content.="\nstdout\n".$this->getLogTail($files[0])."\n\nstderr\n{$this->getLogTail($files[1])}";
		}else {
			$title='process '.$name.' has been started.';
		}
		$sendArr=explode(',', $sendto);
		$mail = new PHPMailer;
		$mail->isSMTP();
		$mail->SMTPAuth=true;
		$mail->Host = 'smtp.163.com';
		$mail->Username = 'jymcron';
		$mail->Password = '7717810483';
		
		$mail->From = 'jymcron@163.com';
		$mail->FromName = 'Wolf';
		foreach ($sendArr as $send)
		{
			$mail->addAddress($send);  // Add a recipient
		}
		
		$mail->Subject = $title;
		$mail->Body    = $content;
		$mail->send();
	}
	
	public function getLogTail($filename)
	{
		return shell_exec("tail -n 30 $filename");
	}

//    /**
//     * 获取进程所在服务器地址
//     * @param $eth
//     * @return string
//     */
//    function getServerName($eth){
//		return shell_exec("/sbin/ifconfig $eth|grep \"inet addr:\"|cut -d: -f2|awk '{print $1}'");
//	}
	
	/**
     * 通过proc_open执行命令
	 * @param string $cmd
	 * @param FileLog[] $childfiles
	 * @param string process name
	 * @throws Exception
	 */
	public function parllelCallback($cmd, $childfiles, $name)
	{
		$this->log("this is child process:$this->pid", FileLog::LEVEL_TRACE);
		$pipes = array();
	
		// Make pipe descriptors for proc_open()
		$fd = array(
				1 => array("pipe", "w"),
				2 => array("pipe", "w")
		);
	
		$resource = proc_open($cmd, $fd, $pipes);
		$this->log("run cmd:$cmd", FileLog::LEVEL_TRACE);
		$this->sendMail($name, $this->totalprocess[$name]['mailto'],false);
		if (!is_resource($resource)) {
			throw new Exception('Can not run "'.$cmd.'" using pipe open');
		}
	
		if(msg_queue_exists($this->ppid))
		{
			$this->queue=msg_get_queue($this->ppid);
			$status=proc_get_status($resource);
			if ($status !== FALSE) {
				msg_send($this->queue, $this->pid,
				array('type'=>'cmdpid','from'=>$this->pid,'value' =>$status['pid']),
				true, false, $error);
			}
		}
		$stdout = $stderr = null;
		$reader= array($pipes[1]);
		stream_select($reader, $null, $null, 0, NULL);
		do {
			// 			$stdout =;
			$childfiles[0]->log(trim(fread($pipes[1], 2048),"\n"), FileLog::LEVEL_INFO);
			// 			fwrite($filep[0], $stdout);
		} while (!feof($pipes[1]));
	
		while (!feof($pipes[2])) {
			// 			$stderr = fread($pipes[2], 2048);
			$childfiles[1]->log(trim(fread($pipes[2], 2048),"\n"), FileLog::LEVEL_INFO);
			// 			fwrite($filep[1], $stderr);
		}
	
		foreach ($pipes as $pipe) {
			fclose($pipe);
		}
		// 		echo "close pipe\n";
		proc_close($resource);
		exit;
	}

    /**
     * 初始化子进程属性
     */
    protected function childInitialize()
	{
		$this->emit->removeAllListeners();
		$this->master = false;
		$this->serverPid = null;
		$pid = posix_getpid();
		$this->ppid = $this->pid;
		$this->pid = $pid;
		$this->queue = null;
		$this->childprocess = array();
	}

    /**
     * 开始监听消息队列
     */
	public function listen()
	{
		if (!$this->queue) {
			$this->queue = msg_get_queue($this->pid);
		}
		return $this;
	}


    /**
     *  运行配置了自动启动的进程
     */
    public function runCmd()
	{
		foreach ($this->totalprocess as $name=>$detail)
		{
			if (isset($detail['autostart']) && $detail['autostart'] === '1')
			{
				if($detail['command'] === ''){
					echo "process $name command is invalid.\n";
				}else{
                    $files = $this->createLogFile($name, $this->totalprocess[$name]);
                    $this->parallel(array($this, 'parllelCallback'),
                        $detail['command'], $files, $name);
                }

			}
		}
	}
	
	public function recvMsg(&$msg, $pid)
	{
		return msg_receive($this->queue, $pid, $null, 1024, $msg, true, MSG_IPC_NOWAIT, $error);
	}
	
	public function sendMsg($msg, $pid, $key)
	{
		if (!is_resource($this->queue) || !msg_stat_queue($this->queue))
		{
			$this->queue = msg_get_queue($key);
		}
		return msg_send($this->queue, $pid, $msg, true, false, $error);
	}
	
	public function isMaster()
	{
		return $this->master;
	}
	
	public function log($msg, $level)
	{
		WolfServer::log($msg, $level);
	}
	
	public function clear($process)
	{
		if ($process instanceof childProcess) {
			if (($index = array_search($process, $this->childprocess)) !== false) {
				$this->childprocess[$index]->__destruct();
				unset($this->childprocess[$index]);
			}
		} elseif (is_numeric($process)) {
			if (isset($this->childprocess[$process])) {
				$this->childprocess[$process]->__destruct();
				unset($this->childprocess[$process]);
			}
		} else {
			throw new \InvalidArgumentException("Illegal argument");
		}
	}
	
	public function serverExit($signal=0)
	{
		while ($this->childprocess)
		{
			foreach ($this->childprocess as $child)
			{
				$child->kill(SIGINT);
				$child->shutdown($signal);
				$this->clear($child);
			}
		}
		if($this->serverPid)
		{
			$ret = posix_kill($this->serverPid, SIGKILL);
		}
	}
	
	public function end($status = 0, $info = null)
	{
// 		foreach ($this->childprocess as $pid=>$child)
// 		{
// 			if (!$child->isExit())
// 			{
// 				$child->status = $status;
// 				$child->emit->emit('exit', $status, $info);
// 			}
// 		}
// 		$this->emit->emit('exit', $status, $info);
		
		if ($this->isMaster()) {
			if (is_resource($this->queue) && msg_stat_queue($this->queue)) {
				msg_remove_queue($this->queue);
			}
		}
		$this->serverExit();
	}
}
