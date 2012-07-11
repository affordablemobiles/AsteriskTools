<?php

/*-------------------------------+
|      Asterisk Queue Regex      |
|        by Samuel Melrose       |
|            (c) 2012            |
+--------------------------------+
|  Released under GNU LGPL v3.0  |
+-------------------------------*/

class AsteriskQueueRegex {
	private $fp;
	private $results = array();
	private $pdone = false;
	
	public function __construct($filename){
		if (is_file($filename)){
			$this->fp = fopen($filename, 'r');
		} else {
			die('Invalid File');
		}
	}
	
	public function processFile(){
		$this->pdone = true;
		// Get the first row and destroy it, it's just our datetime stamp.
		//$row = fgets($this->fp);
		// Now get the next row, and then to our data.
		$row = fgets($this->fp);
			// First get the queue name.
			$this->_getQueueName($row);
			// Then get the number of calls in the queue.
			$this->_getCallsInQueue($row);
			// Other stuff
			$this->_getMaxAllowedInQueue($row);
			$this->_getStrategy($row);
			$this->_getAvgHoldtime($row);
			// Get those last bits.
			$this->_getExtras($row);
		// Now the next row... The members.
		$row = fgets($this->fp);
			// Check we have the members list here...
			if ($this->_checkMembers($row)){
				$agents = true;
				$this->results['agents'] = array();
				while ($agents == true){
					$row = fgets($this->fp);
					if (!$this->_checkAgent($row)){
						$agents = false;
						continue;
					}
					$this->_addAgent($row);
				}
			} else {
				$row = fgets($this->fp);
			}
		// Now onto the callers...
		// We've already got the row thanks to the function above, just process...
		if ($this->_checkCallers($row)){
			$callers = true;
			while ($callers == true){
				$row = @fgets($this->fp);
				if (trim($row) == '' || !$row){
					$callers = false;
					continue;
				}
				$this->_addCaller($row);
			}
		}
	}
	
	public function returnResults(){
		if (!$this->pdone){
			return false;
		} else {
			return $this->results;
		}
	}
	
	private function _getQueueName($row){
		$arr = array();

		$mat = preg_match("/^([a-zA-Z0-9]+) /", $row, $arr);
		
		if ($mat){
			$this->results['queueid'] = $arr[1];
		} else {
			$this->results['queueid'] = 'Error Matching Results';
		}
	}
	
	private function _getCallsInQueue($row){
		$arr = array();

		$mat = preg_match("/has ([0-9]+) calls \(max/", $row, $arr);
		
		if ($mat){
			$this->results['callsinqueue'] = $arr[1];
		} else {
			$this->results['callsinqueue'] = 'Error Matching Results';
		}
	}
	
	private function _getMaxAllowedInQueue($row){
		$arr = array();

		$mat = preg_match("/calls \(max ([a-zA-Z0-9]+)\) in/", $row, $arr);
		
		if ($mat){
			$this->results['maxallowedinqueue'] = $arr[1];
		} else {
			$this->results['maxallowedinqueue'] = 'Error Matching Results';
		}
	}
	
	private function _getStrategy($row){
		$arr = array();

		$mat = preg_match("/in \'([a-zA-Z0-9]+)\' strategy/", $row, $arr);
		
		if ($mat){
			$this->results['strategy'] = $arr[1];
		} else {
			$this->results['strategy'] = 'Error Matching Results';
		}
	}
	
	private function _getAvgHoldtime($row){
		$arr = array();

		$mat = preg_match("/strategy \(([0-9]+)s holdtime\)/", $row, $arr);
		
		if ($mat){
			$this->results['avgholdtime'] = $arr[1];
		} else {
			$this->results['avgholdtime'] = 'Error Matching Results';
		}
	}
	
	private function _getExtras($row){
		$arr = array();

		$mat = preg_match("/W\:([0-9]+)\, C\:([0-9]+)\, A\:([0-9]+)\, SL\:([0-9]+)\\.([0-9]+)\% within ([0-9]+)s/", $row, $arr);
		
		if ($mat){
			$this->results['qweight'] = $arr[1];
			$this->results['qanswered'] = $arr[2];
			$this->results['qdropped'] = $arr[3];
			$this->results['qsla'] = $arr[4] . '.' . $arr[5];
			$this->results['qslatime'] = $arr[6];
		} else {
			$this->results['qweight'] = 'Error Matching Results';
			$this->results['qanswered'] = 'Error Matching Results';
			$this->results['qdropped'] = 'Error Matching Results';
			$this->results['qsla'] = 'Error Matching Results';
			$this->results['qslatime'] = 'Error Matching Results';
		}
	}
	
	private function _checkMembers($row){
		$mat = preg_match("/Members:/", $row);
		
		$this->results['onlineagents'] = 0;
		$this->results['busyagents'] = 0;
		$this->results['pausedagents'] = 0;
		$this->results['freeagents'] = 0;
		
		if ($mat){
			return true;
		} else {
			$mat2 = preg_match("/No Members/", $row);
			if ($mat2){
				return false;
			} else {
				die('Invalid Format - Expecting Members');
			}
		}
	}
	
	private function _checkAgent($row){
		$mat = preg_match("/[a-zA-Z0-9]+\/[0-9]+/", $row);
		
		if ($mat){
			return true;
		} else {
			return false;
		}
	}
	
	private function _addAgent($row){
		// Increment Online Agent count.
		$this->results['onlineagents']++;
		// An array to put all the agent details into..
		$agent = array();
		// Get the agent ID..
		$agent['agentID'] = $this->_getAgentID($row);
		// First check if we can regex that they are paused...
		if ($this->_checkAgentPaused($row)){
			// Increment
			$this->results['pausedagents']++;
			// Set status...
			$agent['status'] = 'Paused';
			$agent['paused'] = true;
		} else {
		// Else
			// Check if they are busy or free, then increment the counts.
			if ($this->_checkAgentFree($row)){
				$this->results['freeagents']++;
				$agent['status'] = 'Free';
			} else if ($this->_checkAgentBusy($row)){
				$this->results['busyagents']++;
				$agent['status'] = 'Busy';
			} else {
				$agent['status'] = 'Unknown';
			}
			$agent['paused'] = false;
		}
		// Not get the call stats...
		$agent = array_merge($agent, $this->_getAgentCallStats($row));
		// And set our agent...
		$this->results['agents'][] = $agent;
	}
	
	private function _getAgentID($row){
		$arr = array();

		$mat = preg_match("/([a-zA-Z0-9]+\/[0-9]+)/", $row, $arr);
		
		if ($mat){
			return $arr[1];
		} else {
			return 'Error Matching Results';
		}
	}
	
	private function _checkAgentPaused($row){
		$mat = preg_match("/\(paused\)/", $row);
		
		if ($mat){
			return true;
		} else {
			return false;
		}
	}
	
	private function _checkAgentFree($row){
		$mat = preg_match("/\(Not in use\)/", $row);
		
		if ($mat){
			return true;
		} else {
			return false;
		}
	}
	
	private function _checkAgentBusy($row){
		$mat = preg_match("/\(Busy\)/", $row);
		
		if ($mat){
			return true;
		} else {
			return false;
		}
	}
	
	private function _getAgentCallStats($row){
		$arr = array();
		$res = array();

		$mat = preg_match("/taken ([0-9]+) calls \(last was ([0-9]+) secs ago\)/", $row, $arr);
		
		if ($mat){
			$res['callsTaken'] = $arr[1];
			$res['lastCall'] = $arr[2];
		} else {
			$res['callsTaken'] = 0;
			$res['lastCall'] = 0;
		}
		
		return $res;
	}
	
	private function _checkCallers($row){
		$mat = preg_match("/Callers:/", $row);
		
		$this->results['currlongestwait'] = 0;
		
		$this->results['callers'] = array();
		
		if ($mat){
			return true;
		} else {
			$mat2 = preg_match("/No Callers/", $row);
			if ($mat2){
				return false;
			} else {
				die('Invalid Format - Expecting Callers');
			}
		}
	}
	
	private function _addCaller($row){
		$arr = array();
		$caller = array();
		
		$mat = preg_match("/([0-9]+)\. (.*) \(wait\: ([0-9]+)\:([0-9]+)\, prio\: ([0-9]+)\)/", $row, $arr);
		
		if ($mat){
			$caller['callerID'] = $arr[1];
			$caller['callPath'] = $arr[2];
			$caller['waitTime'] = $this->_calcWait($arr[3], $arr[4]);
			$this->_checkLongestWait($caller['waitTime']);
			$caller['priority'] = $arr[5];
		}
		
		$this->results['callers'][] = $caller;
	}
	
	private function _calcWait($min, $secs){
		return $secs + ($min * 60);
	}
	
	private function _checkLongestWait($sum){
		if ($sum > $this->results['currlongestwait']){
			$this->results['currlongestwait'] = $sum;
		}
	}
}
