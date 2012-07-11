<?php

/*-------------------------------------+
|         Process Asterisk Output      |
|              show queues             |
|          into useful PHP arrays      |
+--------------------------------------+
| POST Variables Required:             |
|    submitAsterisk = true             |
|    queueData = base64 encoded output |
|                of 'show queues'      |
|                command from Asterisk |
+-------------------------------------*/

$submitAsterisk	= @$_POST['submitAsterisk'] == '' ? '' : $_POST['submitAsterisk'];
$queueData		= @$_POST['queueData'] == '' ? '' : $_POST['queueData'];
$queueData		= base64_decode($queueData);

if (!empty($queueData) && $submitAsterisk == 'true'){
	
	$eachqueue = explode("\n\n", $queueData);
	
	for ($i = 0;$i < (count($eachqueue)-1);$i++){
		$worker = new Asterisk_QueueCacheUpdate($eachqueue[$i]);
		$worker->setCacheFolder('/tmp/cache/asterisk');
		$worker->processData();
	}
	
} else {
	
	die('Invalid Data Provided');
	
}

?>