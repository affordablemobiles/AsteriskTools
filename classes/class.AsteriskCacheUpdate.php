<?php

/*-------------------------------+
|     Asterisk Cache Update      |
|       by Samuel Melrose        |
|           (c) 2012             |
+--------------------------------+
|  Released under GNU LGPL v3.0  |
+-------------------------------*/

class AsteriskCacheUpdate {
	private $cachefolder = 'cache/asterisk/'
	private $qData;
	private $qResults;
	private $regexLib;
	private $index;
	private $indexfp;
	private $indexFile;
	private $queuefp;
	private $queueFile;
	
	public function __contruct($queueData){
		$this->qData = $queueData;
	
		$this->_performRegex();
		
		$this->_setIndexFile();
		$this->_setQueueFile();
		
		$this->_indexStart();
		
		// Now do the bit where we update the queue file.
		$this->_writeQueue();
		
		// Then finish the index here and write the changes to that...
		$this->_updateIndex();
		
		// Release the file locks and handles.
		$this->_closeFiles();
	}
	
	private function _performRegex(){
		$tmpfile = tempnam(sys_get_temp_dir(), 'AsteriskQueueCache');
		
		file_put_contents($tmpfile, $this->qData);
		
		$this->regexLib = new AsteriskQueueRegex($tmpfile);
		
		$this->regexLib->processFile();
		
		$this->qResults = $this->regexLib->returnResults();
		
		unset($this->regexLib);
		unlink($tmpfile);
	}
	
	private function _setIndexFile(){
		$this->indexFile = $this->cachefolder . 'index.dat';
		if (!is_file($this->indexFile))
			touch($this->indexFile);
		$this->indexfp = fopen($this->indexFile, 'r+');
		flock($this->indexfp, 'LOCK_EX');
	}
	
	private function _setQueueFile(){
		$this->queueFile = $this->cachefolder . 'queue_ ' . $this->qResults['queueid'] . '.dat';
		if (!is_file($this->queueFile))
			touch($this->queueFile);
		$this->queuefp = fopen($this->queueFile, 'r+');
		flock($this->queuefp, 'LOCK_EX');
	}
	
	private function _indexStart(){
		$this->index = @unserialize($this->_getFile($this->indexfp));
		if ((!$this->index) || (!is_array($this->index)) || (@$this->index['validdata'] != 'true')){
			$this->index = array( 'validdata' => 'true', 'queues' => array() );
		}
	}
	
	private function _closeFiles(){
		flock($this->queuefp, 'LOCK_UN');
		flock($this->indexfp, 'LOCK_UN');
		
		fclose($this->queuefp);
		fclose($this->indexfp);
	}
	
	private function _getFile(&$fp){
		rewind($fp);
		return stream_get_contents($fp);
	}
	
	private function _clearFile(&$fp){
		rewind($fp);
		ftruncate($fp, 0);
	}
	
	private function _writeQueue(){
		$this->_clearFile($this->queuefp);
		
		fwrite($this->queuefp, serialize($this->qResults));
	}
	
	private function _updateIndex(){
		$this->index['queues'][$this->qResults['queueid']] = TDATETIME;
		
		$this->_clearFile($this->indexfp);
		
		fwrite($this->indexfp, serialize($this->index));
	}
	
}
