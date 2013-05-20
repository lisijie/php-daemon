<?php
/**
 * PHP 守护进程
 * 
 * 使用示例(demo.php)：
 * #!/bin/env php
 *	<?php
 *	require dirname(__FILE__).'/phpdaemon.php';
 *	
 *	function handler($pno) {
 *		for (;;) {
 *			echo "this is #{$pno}\n";
 *			sleep(3);
 *		}
 *	}
 *	
 *	$obj = new PHPDaemon();
 *	$obj->setProcessNum(1);
 *	$obj->setHandler("handler");
 *	$obj->run();
 *
 * @author lisijie <lsj86@qq.com>
 */

define('PATH', dirname(__FILE__).'/');

class PHPDaemon {
	const VER = '1.0';
	const ERR = -1;
	const OK  = 1;
	
	private $pid;
	private $childPids;
	private $pidFile;
	private $handler;
	private $processNum;
	private $argv;

	public function __construct() {
		global $argv;
		$this->processNum = 1;
		$this->childPids = array();
		$this->argv = $argv;
		$this->setPidFile(PATH . 'daemon.pid');
		if (!extension_loaded('pcntl')) {
			die('daemon needs support of pcntl extension');
		}
		if ('cli' != php_sapi_name()) {
			die('daemon only works in CLI mode.');
		}
	}
	
	//设置PID文件路径
	public function setPidFile($filename) {
		$this->pidFile = $filename;
	}
	
	//设置处理函数
	public function setHandler($handler) {
		$this->handler = $handler;
	}
	
	//设置进程数量
	public function setProcessNum($num) {
		$this->processNum = $num;
	}
	
	//运行程序
	public function run() {
		switch ($this->argv[1]) {
			case 'start':
				$this->start(); break;
			case 'stop':
				$this->stop(); break;
			case 'restart':
				$this->restart(); break;
			default:
				$this->usage(); break;
		}
	}
	
	//启动
	private function start() {
		if (is_file($this->pidFile)) {
			$this->msg("{$this->argv[0]} is running (".file_get_contents($this->pidFile).")");
		} else {
			if (empty($this->handler)) {
				$this->msg("process handler unregistered."); 
				exit(-1);
			}
			$this->demonize();
			for ($i=1; $i<=$this->processNum; $i++) {
				$pid = pcntl_fork();
				if ($pid == -1) {
					$this->msg("fork() process #{$i}", self::ERR);
				} elseif ($pid) {
					$this->childPids[$pid] = $i;
				} else {
					return $this->handle($i);
				}
			}
		}
		
		//等待子进程
		while (count($this->childPids)) {
			$waipid = pcntl_waitpid(-1, $status, WNOHANG);
			unset($this->childPids[$waipid]);
			$this->checkPidFile();
			usleep(1000000);
		}
	}
	
	//停止
	private function stop() {
		if (!is_file($this->pidFile)) {
			$this->msg("{$this->argv[0]} is not running.");
		} else {
			$pid = file_get_contents($this->pidFile);
			if (!@unlink($this->pidFile)) {
				$this->msg("remove pid file: $this->pidFile", self::ERR);
			}
			sleep(1);
			$this->msg("stopping {$this->argv[0]} ({$pid})", self::OK);
		}
	}
	
	//重启
	private function restart() {
		$this->stop();
		sleep(1);
		$this->start();
	}
	
	//使用示例
	private function usage() {
		global $argv;
		echo str_pad('', 50, '-')."\n";
		echo "PHPDaemon v".self::VER."\n";
		echo "author: lisijie <lsj86@qq.com>\n";
		echo str_pad('', 50, '-')."\n";
		echo "usage:\n";
		echo "\t{$argv[0]} start\n";
		echo "\t{$argv[0]} stop\n";
		echo "\t{$argv[0]} restart\n";
		echo str_pad('', 50, '-')."\n";
	}
	
	//检查PID文件，如果文件不存在，则退出运行
	private function checkPidFile() {
		clearstatcache();
		if (!is_file($this->pidFile)) {
			foreach ($this->childPids as $pid => $pno) {
				posix_kill($pid, SIGKILL);
			}
			exit;
		}
	}
	
	//转为守护进程模式
	private function demonize() {
		$pid = pcntl_fork();
		if ($pid == -1) {
			$this->msg("create main process", self::ERR);
		//父进程
		} elseif ($pid) {
			$this->msg("starting {$this->argv[0]}", self::OK);
			exit;
		//子进程
		} else {
			posix_setsid();
			$this->pid = posix_getpid();
			file_put_contents($this->pidFile, $this->pid);
		}
	}
	
	//执行用户处理函数
	private function handle($pno) {
		if ($this->handler) {
			call_user_func($this->handler, $pno);
		}
	}
	
	//输出消息
	private function msg($msg, $msgno = 0) {
		if ($msgno == 0) {
			fprintf(STDIN, $msg . "\n");
		} else {
			fprintf(STDIN, $msg . " ...... ");
			if ($msgno == self::OK) {
				fprintf(STDIN, $this->colorize('success', 'green'));
			} else {
				fprintf(STDIN, $this->colorize('failed', 'red'));
				exit;
			} 
			fprintf(STDIN, "\n");
		}
	}
	
	//在终端输出带颜色的文字
	private function colorize($text, $color, $bold = FALSE) {
		$colors = array_flip(array(30 => 'gray', 'red', 'green', 'yellow', 'blue', 'purple', 'cyan', 'white', 'black'));
		return "\033[" . ($bold ? '1' : '0') . ';' . $colors[$color] . "m$text\033[0m";
	}
}
