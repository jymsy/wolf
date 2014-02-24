<?php
declare(ticks = 1);
/**
 * @author Jiangyumeng
 *
 */
class childProcess {
	/**
	 * @var Process
	 */
	public $manager;
	public $pid;
	public $ppid;
	private $_init = false;
	public $cmd;
	public $cmdpid;
	public $name;
	public $emit;
	public $startTime;
	
	/**
	 * @var resource
	 */
	public $queue;
	public $logFiles = array();
	/**
	 * @var int Exit code of this process
	 */
	public $status;
	
	public function __construct(Process $process,$pid, $ppid)
	{
		$this->pid=$pid;
		$this->ppid = $ppid;
		$this->emit = new EventEmitter();
		$this->manager = $process;
		if($this->pid)
			$this->init($pid);
	}
	
	public function init($pid = null)
	{
		if ($this->_init)
			throw new \Exception('Process has been initialized');
		
		if (!$pid && !$this->pid) {
			throw new \Exception('Process has not pid');
		}
		
		$this->pid = $pid;
		$this->_init = true;
		$self = $this;
		
// 		$tick = function () use ($self)
// 		{
// 			if (!$self->manager || !$self->queue) {
// // 				echo "$self->pid empty\n";
// 				return;
// 			}
			
// 			if ($self->manager->isMaster()) {
// // 				echo "is master\n";
// 				if (!msg_queue_exists($self->pid))
// 					return;
// // 				echo "get child queue:$self->pid\n";
// 				$self->queue = msg_get_queue($self->pid);
// 			}else{
// 				if (!msg_queue_exists($self->ppid))
// 					return;
// // 				echo "get parent queue:$self->ppid\n";
// 				$self->queue = msg_get_queue($self->ppid);
// 			}
// 		};
		
// 		$this->manager->emit->on('tick', $tick);
		
		// When child process exit remove this
// 		$this->emit->on('exit', function () use ($self, $tick) {
// 			$self->manager->emit->removeListener('tick', $tick);
// 		});
		
		return $this;
	}
	
	/**
	 * Kill process
	 *
	 * @param int $signal
	 * @return bool
	 */
	public function kill($signal = SIGKILL)
	{
		return posix_kill($this->pid, $signal);
	}
	
	public function listen()
	{
		$this->manager->listen();
		return $this;
	}
	
	/**
	 * Send msg to child process
	 *
	 * @param mixed $msg
	 * @return bool
	 */
	public function send($msg)
	{
		// Check queue and send messages
		if ($this->queue && is_resource($this->queue) && msg_stat_queue($this->queue)) {
			return msg_send($this->queue, 1, array(
					'from' => $this->manager->isMaster() ? $this->ppid : $this->pid,
					'to'   => $this->manager->isMaster() ? $this->pid : $this->ppid,
					'body' => $msg
			), true, false, $error);
		}
		return false;
	}
	
	public function getMemSize($pid)
	{
		if (!file_exists("/proc/$pid/statm")) {
			return 0;
		}
		$fp = @fopen("/proc/$pid/statm", 'r');
		$meminfo=@fgets($fp);
		$realmem=explode(' ', trim($meminfo,"\n"));
		return $realmem[1]*4;
	}
	
	public function shutdown($status = 0)
	{
		if ($this->status === null) {
			$this->status = $status;
			$this->emit->emit('exit', $status);
		}
	}
	
	public function isExit()
	{
		return $this->status !== null;
	}
	
	/**
	 * Userland solution for memory leak
	 */
	function __destruct()
	{
		$this->manager = null;
		$this->queue = null;
		$this->listeners = array();
	}
}