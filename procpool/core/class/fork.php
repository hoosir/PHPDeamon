<?php
class fork {

	public $pidFilePath = '';
	public $maxProcess;
	public $current = 0;

	public function __construct() {
		$this->_init();

		error_reporting(0);
		set_time_limit(0);
		ob_implicit_flush();

		register_shutdown_function(array(&$this, 'releaseDaemon'));
	}

	public function _init() {
	}

	function runTask($signal=array())
	{

	}

	/**
     * 启动进程
     *
     * @return bool
     */
    public function main() {

        $this->_logMessage('Starting daemon');
        if (!$this->_daemonize()) {
            $this->_logMessage('Could not start daemon', self::DLOG_ERROR);

            return false;
        }

        $this->_logMessage('Running...');

        $this->_isRunning = true;

        while ($this->_isRunning) {
            $this->_doTask();
        }

        return true;
    }


	/**
	 * @return void
	 */
	public function stop() {

		$this->_logMessage('Stoping daemon');

		$this->_isRunning = false;
	}

	/**
	 * Do task
	 *
	 * @return void
	 */
	protected function _doTask() {
	}

	/**
	 */
	protected function _logMessage($msg) {
	}

	/**
	 */
	private function _daemonize() {

		ob_end_flush();

		if ($this->_isDaemonRunning()) {
			// Deamon is already running. Exiting
			return false;
		}

		if (!$this->_fork()) {
			// Coudn't fork. Exiting.
			return false;
		}

		if (!$this->_setIdentity() && $this->requireSetIdentity) {
			// Required identity set failed. Exiting
			return false;
		}

		if (!posix_setsid()) {
			$this->_logMessage('Could not make the current process a session leader', self::DLOG_ERROR);

			return false;
		}

		if (!$fp = fopen($this->pidFileLocation, 'w')) {
			$this->_logMessage('Could not write to PID file', self::DLOG_ERROR);
			return false;
		} else {
			fputs($fp, $this->_pid);
			fclose($fp);
		}
		$this->writeProcess();

		chdir($this->homePath);
		umask(0);

		declare(ticks = 1);

		pcntl_signal(SIGCHLD, array(&$this, 'sigHandler'));
		pcntl_signal(SIGTERM, array(&$this, 'sigHandler'));
		pcntl_signal(SIGUSR1, array(&$this, 'sigHandler'));
		pcntl_signal(SIGUSR2, array(&$this, 'sigHandler'));

		return true;
	}

	/**
	 * Cheks is daemon already running
	 *
	 * @return bool
	 */
	private function _isDaemonRunning() {

		$oldPid = file_get_contents($this->pidFileLocation);

		if ($oldPid !== false && posix_kill(trim($oldPid),0))
		{
			$this->_logMessage('Daemon already running with PID: '.$oldPid, (self::DLOG_TO_CONSOLE | self::DLOG_ERROR));

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Forks process
	 *
	 * @return bool
	 */
	private function _fork() {

        $this->_logMessage('Forking...');

        $pid = pcntl_fork();

        if ($pid == -1) {
            // 出错
            $this->_logMessage('Could not fork', self::DLOG_ERROR);

            return false;
        } elseif ($pid) {
            // 父进程
            $this->_logMessage('Killing parent');

            exit();
        } else {
            // fork的子进程
            $this->_isChildren = true;
            $this->_pid = posix_getpid();

            return true;
        }
    }

	/**
	 * Sets identity of a daemon and returns result
	 *
	 * @return bool
	 */
	private function _setIdentity() {

		if (!posix_setgid($this->groupID) || !posix_setuid($this->userID))
		{
			$this->_logMessage('Could not set identity', self::DLOG_WARNING);

			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	 * 配置函数
	 */
	public function sigHandler($sigNo) {

		switch ($sigNo)
		{
			case SIGTERM:   // Shutdown
				$this->_logMessage('Shutdown signal');
				exit();
				break;
			case SIGCHLD:   // Halt
				$this->_logMessage('Halt signal');
				while (pcntl_waitpid(-1, $status, WNOHANG) > 0);
				break;
			case SIGUSR1:   // User-defined
				$this->_logMessage('User-defined signal 1');
				$this->_sigHandlerUser1();
				break;
			case SIGUSR2:   // User-defined
				$this->_logMessage('User-defined signal 2');
				$this->_sigHandlerUser2();
				break;
		}
	}

	/**
	 */
	protected function _sigHandlerUser1() {
		apc_clear_cache('user');
	}

	/**
	 */
	protected function _sigHandlerUser2() {
		return true;
	}

	/**
	 */
	public function releaseDaemon() {

		if ($this->_isChildren && is_file($this->pidFileLocation)) {
			$this->_logMessage('Releasing daemon');

			unlink($this->pidFileLocation);
		}
	}

	/**
	 * 记录进程状态
	 *
	 */
	public function writeProcess() {

        // 初始化 proc
        $this->_initProcessLocation();

        $command = trim(implode(' ', $_SERVER['argv']));

        // 指定进程的目录
        $processDir = $this->processLocation . '/' . $this->_pid;
        $processCmdFile = $processDir . '/cmd';
        $processPwdFile = $processDir . '/pwd';

        // 所有进程所在的目录
        if (!is_dir($this->processLocation)) {
            mkdir($this->processLocation, 0777);
            chmod($processDir, 0777);
        }

        // 查询重复的进程记录
        $pDirObject = dir($this->processLocation);
        while ($pDirObject && (($pid = $pDirObject->read()) !== false)) {
            if ($pid == '.' || $pid == '..' || intval($pid) != $pid) {
                continue;
            }

            $pDir = $this->processLocation . '/' . $pid;
            $pCmdFile = $pDir . '/cmd';
            $pPwdFile = $pDir . '/pwd';
            $pHeartFile = $pDir . '/heart';

            // 根据cmd检查启动相同参数的进程
            if (is_file($pCmdFile) && trim(file_get_contents($pCmdFile)) == $command) {
                unlink($pCmdFile);
                unlink($pPwdFile);
                unlink($pHeartFile);

                // 删目录有缓存
                usleep(1000);

                rmdir($pDir);
            }
        }

        // 新进程目录
        if (!is_dir($processDir)) {
            mkdir($processDir, 0777);
            chmod($processDir, 0777);
        }

        // 写入命令参数
        file_put_contents($processCmdFile, $command);
        file_put_contents($processPwdFile, $_SERVER['PWD']);

        // 写文件有缓存
        usleep(1000);

        return true;
    }

	/**
	 * @return void
	 */
	protected function _initProcessLocation() {
	}

}