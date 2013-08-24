<?php
include CLASS_PATH . "Deamon.php";

class defaultDeamon extends Deamon
{

    public $prj = 'default';

    public $queue;

    public $tag;

    public $queueList;

    private $task;

    private $sleepTime = "100"; // micro s for block time
    private $doTimes = "10000"; // do how many per-time
    private $restTime = "2"; // s
    protected $queuePrefix = 'default_';

    public function __construct ($tag)
    {
        $this->tag = $tag;
        parent::__construct($this->prj, $tag);

        $this->queueList[$this->getQueueName('a')] = new queue('redisDriver',
                $this->prj, $config['redis']['host']['a'],
                $config['redis']['port']['a']);
        switch ($this->tag) {
            case 'a':
                $this->setMaxProcess(2); // set how many procs U wish
                break;
            default:
                $this->setMaxProcess(5);
        }

        $this->setMaxRequestPerChild($this->doTimes);
    }

    public function getQueueName ($tag)
    {
        return $this->queuePrefix . $tag;
    }

    public function flashConnect ()
    {
        $this->closeConnect();
        foreach ($this->queueList as $queueName => $driver) {
            $this->queueList[$queueName]->dataDriver->connect();
        }
    }

    public function closeConnect ()
    {
        foreach ($this->queueList as $queueName => $driver) {
            $this->queueList[$queueName]->dataDriver->closeConnect();
        }
    }

    public function dispatchNotice ($task, $params = '')
    {
        $_task = json_decode($task, true);
        if ($_task['params'] !== $params) {
            $this->dealException($task . " is no right task");
            return false;
        }
        // do something with task

        /*
         * should add func model
         */

        return json_decode($task, true);
    }

    public function fetchFromQueue ($queueName)
    {
        $return = json_decode($this->queueList[$queueName]->outPut($queueName),
                true);
        return $return;
    }

    public function insertToQueue ($queueName, $data)
    {
        $return = $this->queueList[$queueName]->inPut($queueName, $data);
        try {
            $return = $this->queueList[$queueName]->inPut($queueName, $data);
            throw new exception("can't insert into:" . $queueName . " data:" .
                     $data);
        } catch (Exception $e) {
            // $return2=$this->queueList[$this->getQueueName('bak')]->inPut($this->getQueueName('bak'),$data);
            $this->dealException($e->getMessage());
        }
        return $return;
    }

    public function daemonFunc ()
    {
        $this->dealdefaultTask();
    }

    /**
     * main function
     *
     * @param unknown $tag
     */
    public function dealdefaultTask ()
    {
        while ($this->subProcessCheck()) {

            $this->requestCount ++;
            if ($this->requestCount >= $this->doTimes) {
                if ($this->tag == 'a') {
                    usleep($this->sleepTime);
                }

                $this->requestCount = 0;
                $this->flashConnect(); // it's depends on queue
                continue;
            }

            $protoTask = $this->fetchFromQueue($this->getQueueName($this->tag));
            if ($protoTask) {
                $pushRtn = $this->dispatchNotice($protoTask);
                if ($pushRtn) {
                    textRecord(LOG_PATH . date('Y-m-d') . "send.log",
                            date('Y-m-d H:i:s') . microtime() .
                                     json_encode($pushRtn));
                }
            } else {
                if ($this->tag == 'a') {
                    sleep($this->restTime);
                }
                $this->flashConnect();
            }
        }
    }

    public function infoRecord ($content)
    {
        textRecord(LOG_PATH . date('Y-m-d') . "dispatch.log",
                date('Y-m-d H:i:s') . time() . "content:" . $content);
    }

    private function dealException ($content)
    {
        textRecord(LOG_PATH . date('Y-m-d') . "dispatch_error.log",
                date('Y-m-d H:i:s') . time() . "content:" . $content);
    }

    public function __destruct ()
    {
        $this->closeConnect();
    }
}
