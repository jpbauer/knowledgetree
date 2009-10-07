<?php
/**
* Upgrader Index.
*
* KnowledgeTree Community Edition
* Document Management Made Simple
* Copyright (C) 2008,2009 KnowledgeTree Inc.
* Portions copyright The Jam Warehouse Software (Pty) Limited
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
* @package Upgrader
* @version Version 0.1
*/
include("path.php"); // Paths

/**
 * Auto loader to bind upgrader package
 *
 * @param string $class
 * @return void
 */
function __autoload($class) { // Attempt and autoload classes
	$class = strtolower(substr($class,0,1)).substr($class,1); // Linux Systems.
	if(file_exists(UPGRADE_DIR."$class.php")) {
		require_once(UPGRADE_DIR."$class.php");
	} elseif (file_exists(STEP_DIR."$class.php")) {
		require_once(STEP_DIR."$class.php");
	} elseif (file_exists(WIZARD_LIB."$class.php")) {
		require_once(WIZARD_LIB."$class.php");
	} else {
		return null;
	}
}

class UpgradeWizard {
	/**
	* Upgrade bypass flag
	*
	* @author KnowledgeTree Team
	* @access protected
	* @var mixed
	*/
	protected $bypass = null;

	/**
	* Reference to upgrader utility object
	*
	* @author KnowledgeTree Team
	* @access protected
	* @var boolean
	*/
	protected $iutil = null;

	/**
	* Constructs upgradeation wizard object
	*
	* @author KnowledgeTree Team
	* @access public
 	*/
	public function __construct(){}

	/**
	* Check if system has been upgrade
	*
	* @author KnowledgeTree Team
	* @access private
	* @param none
	* @return boolean
 	*/
	private function isSystemUpgradeed() {
		return $this->iutil->isSystemUpgradeed();
	}
	
	/**
	* Display the wizard
	*
	* @author KnowledgeTree Team
	* @access private
	* @param string
	* @return void
 	*/
	public function displayUpgrader($response = null) {
		if($response) {
			$ins = new Upgrader(); // Instantiate the upgrader
			$ins->resolveErrors($response); // Run step
		} else {
			$ins = new Upgrader(new UpgradeSession()); // Instantiate the upgrader and pass the session class
			$ins->step(); // Run step
		}
	}
	
	/**
	* Set bypass flag
	*
	* @author KnowledgeTree Team
	* @access private
	* @param boolean
	* @return void
 	*/
	private function setBypass($bypass) {
		$this->bypass = $bypass;
	}
	
	/**
	* Set util reference
	*
	* @author KnowledgeTree Team
	* @access private
	* @param object upgrader utility
	* @return void
 	*/
	private function setIUtil($iutil) {
		$this->iutil = $iutil;
	}
	
	/**
	* Create upgrade file
	*
	* @author KnowledgeTree Team
	* @access private
	* @param none
	* @return void
 	*/
	private function createUpgradeFile() {
		@touch("upgrade");
	}
	
	/**
	* Remove upgrade file
	*
	* @author KnowledgeTree Team
	* @access private
	* @param none
	* @return void
 	*/
	private function removeUpgradeFile() {
		@unlink("upgrade");
	}
	
	/**
	* Load default values
	*
	* @author KnowledgeTree Team
	* @access private
	* @param none
	* @return void
 	*/
	function load() {
		$this->setIUtil(new UpgradeUtil());
	}
	
	/**
	* Run pre-upgradeation system checks
	*
	* @author KnowledgeTree Team
	* @access public
	* @param none
	* @return mixed
 	*/
	public function systemChecks() {
		$res = $this->iutil->checkStructurePermissions();
		if($res === true) return $res;
		switch ($res) {
			case "wizard":
					$this->iutil->error("Upgrader directory is not writable (KT_Installation_Directory/setup/upgrade/)");
					return 'Upgrader directory is not writable (KT_Installation_Directory/setup/upgrade/)';
				break;
			case "/":
					$this->iutil->error("System root is not writable (KT_Installation_Directory/)");
					return "System root is not writable (KT_Installation_Directory/)";
				break;
			default:
					return true;
				break;
		}
		
		return $res;
	}
	
	/**
	* Control all requests to wizard
	*
	* @author KnowledgeTree Team
	* @access public
	* @param none
	* @return void
 	*/
	public function dispatch() {
		$this->load();
        // is this necessary?
    	$this->createUpgradeFile();
//		if(!$this->isSystemUpgradeed()) { // Check if the systems not upgraded
			$response = $this->systemChecks();
			if($response === true) {
				$this->displayUpgrader();
			} else {
				exit();
			}
//		} else {
//			// TODO: Die gracefully
//			$this->iutil->error("System has been upgraded <div class=\"buttons\"><a href='../../'>Goto Login</a></div>");
//		}
	}
}

$ic = new UpgradeWizard();
$ic->dispatch();
?>