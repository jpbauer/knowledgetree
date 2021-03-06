<?php
/**
* Unix Lucene Service Controller. 
*
* KnowledgeTree Community Edition
* Document Management Made Simple
* Copyright(C) 2008,2009 KnowledgeTree Inc.
* Portions copyright The Jam Warehouse Software(Pty) Limited
*
* This program is free software; you can redistribute it and/or modify it under
* the terms of the GNU General Public License version 3 as published by the
* Free Software Foundation.
*
* This program is distributed in the hope that it will be useful, but WITHOUT
* ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
* FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
* details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* You can contact KnowledgeTree Inc., PO Box 7775 #87847, San Francisco,
* California 94120-7775, or email info@knowledgetree.com.
*
* The interactive user interfaces in modified source and object code versions
* of this program must display Appropriate Legal Notices, as required under
* Section 5 of the GNU General Public License version 3.
*
* In accordance with Section 7(b) of the GNU General Public License version 3,
* these Appropriate Legal Notices must retain the display of the "Powered by
* KnowledgeTree" logo and retain the original copyright notice. If the display of the
* logo is not reasonably feasible for technical reasons, the Appropriate Legal Notices
* must display the words "Powered by KnowledgeTree" and retain the original
* copyright notice.
*
* @copyright 2008-2009, KnowledgeTree Inc.
* @license GNU General Public License version 3
* @author KnowledgeTree Team
* @package Installer
* @version Version 0.1
*/

class unixLucene extends unixService {
	public $util;
	private $shutdownScript;
	private $indexerDir;
	private $lucenePidFile;
	private $luceneDir;
	private $luceneSource;
	private $luceneSourceLoc;
	private $javaXms;
	private $javaXmx;
	
	public function __construct() {
		$this->name = "KTLuceneTest";
		$this->util = new InstallUtil();
	}
	
	public function load() {
		$this->setLuceneDir(SYSTEM_DIR."bin".DS."luceneserver".DS);
		$this->setIndexerDir(SYSTEM_DIR."search2".DS."indexing".DS."bin".DS);
		$this->setLucenePidFile("lucene_test.pid");
		$this->setJavaXms(512);
		$this->setJavaXmx(512);
		$this->setLuceneSource("ktlucene.jar");
		$this->setLuceneSourceLoc("ktlucene.jar");
		$this->setShutdownScript("shutdown.php");
	}
	
	public function setIndexerDir($indexerDir) {
		$this->indexerDir = $indexerDir;
	}
	
	private function getIndexerDir() {
		return $this->indexerDir;
	}
	
	private function setShutdownScript($shutdownScript) {
		$this->shutdownScript = $shutdownScript;
	}
	
	public function getShutdownScript() {
		return $this->shutdownScript;
	}
	
	private function setLucenePidFile($lucenePidFile) {
		$this->lucenePidFile = $lucenePidFile;
	}
	
	private function getLucenePidFile() {
		return $this->lucenePidFile;
	}
	
	private function setLuceneDir($luceneDir) {
		$this->luceneDir = $luceneDir;
	}
	
	public function getLuceneDir() {
		return $this->luceneDir;
	}
	
	private function setJavaXms($javaXms) {
		$this->javaXms = "-Xms$javaXms";
	}
	
	public function getJavaXms() {
		return $this->javaXms;
	}
	
	private function setJavaXmx($javaXmx) {
		$this->javaXmx = "-Xmx$javaXmx";
	}
	
	public function getJavaXmx() {
		return $this->javaXmx;
	}
	
	private function setLuceneSource($luceneSource) {
		$this->luceneSource = $luceneSource;
	}
	
	public function getLuceneSource() {
		return $this->luceneSource;
	}
	
	private function setLuceneSourceLoc($luceneSourceLoc) {
		$this->luceneSourceLoc = $this->getLuceneDir().$luceneSourceLoc;
	}
	
	public function getLuceneSourceLoc() {
		return $this->luceneSourceLoc;
	}
	
	public function getJavaOptions() {
		return " {$this->getJavaXmx()} {$this->getJavaXmx()} -jar ";
	}
	
  	public function stop() {
  		// TODO: Breaks things
		$state = $this->status();
		if($state != '') {
			$cmd = "pkill -f ".$this->getLuceneSource();
    		$response = $this->util->pexec($cmd);
			return $response;
		}

    }

    public function install() {
    	$status = $this->status();
    	if($status == '') {
			return $this->start();
    	} else {
    		return $status;
    	}
    }
    
    public function status() {
    	$cmd = "ps ax | grep ".$this->getLuceneSource();
    	$response = $this->util->pexec($cmd);
    	if(is_array($response['out'])) {
    		if(count($response['out']) > 1) {
    			foreach ($response['out'] as $r) {
    				preg_match('/grep/', $r, $matches); // Ignore grep
    				if(!$matches) {
    					return 'STARTED';
    				}
    			}
    		} else {
    			return '';
    		}
    	}
    	
    	return '';
    }
    
    public function uninstall() {
    	$this->stop();
    }
    
    public function start() {
    	$state = $this->status();
    	if($state != 'STARTED') {
	    	$cmd = "cd ".$this->getLuceneDir()."; ";
	    	$cmd .= "nohup java -jar ".$this->getLuceneSource()." > ".SYS_LOG_DIR."lucene.log 2>&1 & echo $!";
	    	$response = $this->util->pexec($cmd);
	    	
	    	return $response;
    	} elseif ($state == '') {
    		// Start Service
    		return true;
    	} else {
    		// Service Running Already
    		return true;
    	}
    	
    	return false;
    }
    
	
}
?>