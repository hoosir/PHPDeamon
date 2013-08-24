<?php
class watch {
	public function run($input)
	{
		global $p_number;
		if ($p_number <= 0)
		{
			$p_number = $this->worker_processes($p_number);
		}
		$p_number = $p_number - 1;
		$out = popen("/bin/sh ".RUNNING_LOG." \"{$input}\" &", "r");
		pclose($out);
	}

	public function worker_processes($p_number)
	{
		$limit = MAX_P;//
		while ($p_number <= 0)
		{
			$cmd = popen("ps -ef | grep ".RUNNING_LOG." | grep -v grep | wc -l", "r");
			$line = fread($cmd, 512);
			pclose($cmd);
			$p_number = $limit - $line;
			if ($p_number <= 0)
			{
				sleep(1);//
			}
		}
		return $p_number;
	}

}
?>