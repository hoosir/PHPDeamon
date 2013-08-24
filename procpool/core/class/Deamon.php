<?php
class Deamon  implements config {

	/**
	 *
	 * @var string 运行标记文件
	 */
	protected $runningFlag = NULL;
	public $deamonStatus;
	public $tag='default';

    /**
     *
     * @var string 停止标记文件
     */
    protected $stopFlag = NULL;
    /**
     *
     * @var string Daemon名称
     */
    protected $daemonName = NULL;
    /**
     *
     * @var integer 最大子进程数
     */
    public $maxProcesses = 5;
    /**
     *
     * @var integer 每个子进程最大处理的请求数
     */
    public $maxRequestsPerChild = 10;
    /**
     *
     * @var integer 子进程请求次数
     */
    protected $requestCount = 0;
    /**
     *
     * @var array 保存现有子进程PID的数组
     */
    protected $currentJobs = array();
    /**
     *
     * @var array 保存返回信号的子进程PID和信号对应关系
     */
    protected $signalQueue = array();

    /**
     * 构造
     */
    public function __construct($daemonName,$tag='default')
    {
        //加载daemon常量
        $config=pathRoute($daemonName);
        //daemon名
        $this->daemonName = $daemonName;
        //tag名  标记deamon的不同队列 选用
        $this->tag = $tag;

        //启动标记文件
        $this->runningFlag = DAEMON_FLAG_DIR . $this->daemonName . $this->tag .'.pid';
        //停止标记文件
        $this->stopFlag = DAEMON_FLAG_DIR . $this->daemonName . $this->tag .'.stop';
    }

    /**
     * 检测Daemon是否在运行
     * @return boolean
     */
    protected function isRunning()
    {
        clearstatcache();
        //是否存在pid文件
        if ( !file_exists($this->runningFlag) ) {
            return FALSE;
        }
        //检测pid对应进程是否存在
        $pid = file_get_contents($this->runningFlag);
        $krtn=posix_kill($pid, 0);
        $delRtn=$krtn?"del done":"can't del";
        textRecord(LOG_PATH.date('Y-m-d')."deamon.log", "kill:$delRtn pid: $pid");
        //exec("kill -9 $pid");

        return $krtn;
    }

    /**
     * 是否接收到退出通知
     * @return boolean
     */
    protected function isStop()
    {
        clearstatcache();
        return file_exists($this->stopFlag);
    }

    /**
     * 关闭daemon
     */
    public function stop()
    {
        touch($this->stopFlag);
    }

    /**
     *
     * @param type $msg 错误信息
     */
    protected function error($msg)
    {

    }

    /**
     * 运行Daemon
     */
    public function run()
    {

        //检测是否运行
        if ( $this->isRunning() ) {
            echo "Daemon already start\n";
            $this->error('Daemon already start');
            exit;
        }
        if ( file_exists($this->stopFlag) ) {
            unlink($this->stopFlag);
        }
        $this->error('run!!!');
        //启动子进程，驻留后台
        $pid = pcntl_fork();
        if ( -1 === $pid ) {
            $this->error('Could not fork daemon process');
            return FALSE;
        } else if ( $pid ) {
            $this->error('Master exit .' . getmypid() . ' pid=' . $pid);
            //退出启动进程
            usleep(500);
            exit();
        }
        //提升为session leader
        if ( !posix_setsid() ) {
            $this->error("Could not set sid");
            exit();
        }
        //关闭所有输出，驻留后台
        if ( defined('STDIN') ) {
            fclose(STDIN);
        }
        if ( defined('STDOUT') ) {
            fclose(STDOUT);
        }
        if ( defined('STDERR') ) {
            fclose(STDERR);
        }

        //写入运行标志位
        file_put_contents($this->runningFlag, getmypid());
        pcntl_signal(SIGCHLD, array($this, "childSignalHandler"));
        while ( !$this->isStop() ) {
            //判断子进程是否已满，满了则不再fork
            if ( count($this->currentJobs) < $this->maxProcesses ) {
                //echo "child process reach maxProcesses Limit\n";
                //启动子进程
                $this->launchJob();
            }

            //处理挂起的信号
            if ( version_compare(PHP_VERSION, '5.3.0') >= 0 ) {
                pcntl_signal_dispatch();
            }
            usleep(10000);
        }

        //等待子进程结束
        while ( count($this->currentJobs) ) {
            //echo "Waiting for current jobs to finish... \n";
            sleep(1);
        }

        $delR=unlink($this->runningFlag);
        $delRtn=$delR?"del done":"can't del";
        textRecord("/data/app/api.apns.ios.ijinshan.com/deamon/log/deamon.log", "del:$delRtn  $this->runningFlag");
	   @exec("rm -f $this->runningFlag");@exec("rm -f $this->stopFlag");
        @unlink($this->stopFlag);
    }

    /**
     * 启动子进程
     */
    protected function launchJob()
    {
        $pid = pcntl_fork();
        if ( $pid === -1 ) {
            $this->error('Could not launch new job, exiting');
        } else if ( $pid ) {
            // Parent process
            // Sometimes you can receive a signal to the childSignalHandler function before this code executes if
            // the child script executes quickly enough!
            //$this->error("fork process pid=$pid");
            $this->currentJobs[$pid] = 1;

            // In the event that a signal for this pid was caught before we get here, it will be in our signalQueue array
            // So let's go ahead and process it now as if we'd just received the signal
            if ( isset($this->signalQueue[$pid]) ) {
                $this->childSignalHandler(SIGCHLD, $pid, $this->signalQueue[$pid]);
                unset($this->signalQueue[$pid]);
            }
        } else {
            //echo "in process pid=" . getmypid() . "\n";
            //启动子进程任务
            $this->requestCount = 0;
            $this->daemonFunc();
            //$this->error("exit process pid=" . getmypid());
            exit(0);
        }
    }

    /**
     * 处理子进程信号
     * @param type $signo
     * @param type $pid
     * @param type $status
     * @return type
     */
    public function childSignalHandler($signo, $pid=null, $status=null)
    {

        //If no pid is provided, that means we're getting the signal from the system.  Let's figure out
        //which child process ended
        if ( !$pid ) {
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }
        //echo "got signal from pid=$pid, sig=$signo \n";
        //Make sure we get all of the exited children
        while ( $pid > 0 ) {
            if ( $pid && isset($this->currentJobs[$pid]) ) {
                $exitCode = pcntl_wexitstatus($status);
                if ( $exitCode != 0 ) {
                    $this->error("$pid exited with status " . $exitCode);
                }
                unset($this->currentJobs[$pid]);
            } else if ( $pid ) {
                //Oh no, our job has finished before this parent process could even note that it had been launched!
                //Let's make note of it and handle it when the parent process is ready for it
                $this->signalQueue[$pid] = $status;
            }
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }
        return true;
    }
    /**
     *检测子进程是否还需要运行
     * @return boolean TRUE表示可以继续运行，FALSE表示应该中止运行
     */
    protected function subProcessCheck() {
        /**
         * 3个检测条件
         * 1.主进程被结束，子进程被pid=1的init接管，应该退出
         * 2.子进程请求数达到上限
         * 3.接收到退出标记
         */
        if (1 === posix_getppid() || ($this->requestCount >= $this->maxRequestsPerChild) || $this->isStop()) {
            //$this->error('sub process' . getmypid() . ' should stop. gid= ' . posix_getppid() . ', req=' . $this->requestCount);
            return FALSE;
        }
        return TRUE;
    }
    /**
     *设置最大子进程
     * @param type $maxProcess 最大子进程
     */
    public function setMaxProcess($maxProcess) {
        $this->maxProcesses = $maxProcess;
    }
    /**
     *获取设置的最大子进程数
     * @return int
     */
    public function getMaxProcess() {
        return $this->maxProcesses;
    }
    /**
     *设置每个子进程最大处理的请求数
     * @param int $maxRequestsPerChild
     */
    public function setMaxRequestPerChild($maxRequestsPerChild) {
        $this->maxRequestsPerChild = $maxRequestsPerChild;
    }
    /**
     *获取设置的每个子进程最大处理的请求数
     * @return int
     */
    public function getMaxRequestPerChild() {
        return $this->maxRequestsPerChild;
    }
    /**
     * 队列方法
     */
    protected function daemonFunc(){;}

	public function getConfig($prj) {
		// TODO Auto-generated method stub

	}

}