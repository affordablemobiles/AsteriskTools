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

require "classes/class.AsteriskCacheUpdate.php";
require "classes/class.AsteriskQueueRegex.php";

if (!empty($queueData) && $submitAsterisk == 'true'){
	
	$eachqueue = explode("\n\n", $queueData);
	
	for ($i = 0;$i < (count($eachqueue)-1);$i++){
		$worker = new AsteriskCacheUpdate($eachqueue[$i]);
		$worker->setCacheFolder('/tmp/cache/asterisk');
		$worker->processData();
	}
	
} else {
	
	die('Invalid Data Provided');
	
}

?>