<?php
/**
 * 日志文件类
 * @author Jiangyumeng
 *
 */
class FileLog{
	const LEVEL_TRACE='trace';
// 	const LEVEL_WARNING='warning';
 	const LEVEL_ERROR='error';
	const LEVEL_INFO='info';
	/**
	 * @var string 日志文件路径，如果没有设置的话
	 * 会使用'/tmp/skyframework.log'，如果包含日
	 * 志文件的路径不存在的话将会自动创建。
	 */
	public $logFile;
	/**
	 * @var integer 日志文件最大大小, 单位KB，默认 10240,  10MB.
	 */
	public $maxFileSize = 10240; // in KB
	/**
	 * @var integer 轮转日志的个数. 默认为5.
	 */
	 public $maxLogFiles = 5;
	 /**
	 * @var integer 新创建目录的权限。
	 * 这个值将会被PHP的chmod()方法使用。
	 * 默认为0775，意味着该目录对所有者和所在组有读写权限，
	 * 对其他用户只有读权限。
	 */
	 public $dirMode = 0775;

    public $logLevel = array(self::LEVEL_INFO);
	 
	 private $_fp;
	 
	 public function __construct($path, $maxLogFiles = 5, $maxFileSize = 10240)
	 {
	 	$this->logFile = $path;
	 	
	 	$logPath = dirname($this->logFile);
	 	if (!is_dir($logPath)) {
	 		self::createDirectory($logPath, $this->dirMode, true);
	 	}
	 	
	 	$this->maxLogFiles = $maxLogFiles;
	 	$this->maxFileSize = $maxFileSize;
	 	if ($this->maxLogFiles < 1) {
	 		$this->maxLogFiles = 1;
	 	}
	 	if ($this->maxFileSize < 1) {
	 		$this->maxFileSize = 1;
	 	}
	 }

    /**
     * 记录日志文件
     * @param $msg
     * @param $level
     * @throws Exception
     */
    public function log($msg, $level)
	 {
	 	if($msg == '' || !in_array($level,$this->logLevel))
	 		return;
	 	$text=self::formatLogMessage($msg,$level,time());
	 	
	 	if (($this->_fp = @fopen($this->logFile, 'a')) === false) {
	 		throw new Exception("Unable to append to log file: {$this->logFile}");
	 	}
	 	clearstatcache();
	 	if (@filesize($this->logFile) > $this->maxFileSize * 1024) 
	 	{
	 		$this->rotateFiles();
	 		@file_put_contents($this->logFile, $text, FILE_APPEND);
	 	} else {
	 		@fwrite($this->_fp, $text);
	 	}
	 	
	 	@fclose($this->_fp);
	 }
	 
	 /**
	  * 轮转日志文件。
	  */
	 protected function rotateFiles()
	 {
	 	$file = $this->logFile;
	 	for ($i = $this->maxLogFiles; $i > 0; --$i) {
	 		$rotateFile = $file . '.' . $i;
	 		if (is_file($rotateFile)) {
	 			// suppress errors because it's possible multiple processes enter into this section
	 			if ($i === $this->maxLogFiles) {
	 				@unlink($rotateFile);
	 			} else {
	 				@rename($rotateFile, $file . '.' . ($i + 1));
	 			}
	 		}
	 	}
	 	if (is_file($file)) {
	 		@rename($file, $file . '.1'); // suppress errors because it's possible multiple processes enter into this section
	 	}
	 }
	 
	 /**
	  * 格式化一条日志信息。
	  * @param string $message 消息内容
	  * @param integer $level 消息等级
	  * @param integer $time 时间戳
	  * @return string 格式化后的消息
	  */
	 protected static function formatLogMessage($message,$level,$time){
	 	return @date('Y/m/d H:i:s',$time)." [$level] $message\n";
	 }
	 
	 /**
	  * 创建一个新目录
	  *
	  * 该方法与PHP的mkdir()函数类似，不同的在于它使用了chmod()来设置
	  * 创建的目录的权限。
	  *
	  * @param string $path 要创建的目录的路径。
	  * @param integer $mode 新创建目录的权限。
	  * @param boolean $recursive 如果父目录不存在的话是否创建。
	  * @return boolean 目录是否成功创建。
	  */
	 public static function createDirectory($path, $mode = 0775, $recursive = true)
	 {
	 	if (is_dir($path)) {
	 		return true;
	 	}
	 	$parentDir = dirname($path);
	 	if ($recursive && !is_dir($parentDir)) {
	 		static::createDirectory($parentDir, $mode, true);
	 	}
	 	$result = mkdir($path, $mode);
	 	chmod($path, $mode);
	 	return $result;
	 }
	 
// 	 public function __destruct()
// 	 {
// 	 	if ($this->_fp) {
// 	 		@fclose($this->_fp);
// 	 	}
// 	 }
}