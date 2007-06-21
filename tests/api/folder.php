<?
/**
 * $Id$
 *
 * The contents of this file are subject to the KnowledgeTree Public
 * License Version 1.1.2 ("License"); You may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://www.knowledgetree.com/KPL
 * 
 * Software distributed under the License is distributed on an "AS IS"
 * basis, WITHOUT WARRANTY OF ANY KIND, either express or implied.
 * See the License for the specific language governing rights and
 * limitations under the License.
 *
 * All copies of the Covered Code must include on each user interface screen:
 *    (i) the "Powered by KnowledgeTree" logo and
 *    (ii) the KnowledgeTree copyright notice
 * in the same form as they appear in the distribution.  See the License for
 * requirements.
 * 
 * The Original Code is: KnowledgeTree Open Source
 * 
 * The Initial Developer of the Original Code is The Jam Warehouse Software
 * (Pty) Ltd, trading as KnowledgeTree.
 * Portions created by The Jam Warehouse Software (Pty) Ltd are Copyright
 * (C) 2007 The Jam Warehouse Software (Pty) Ltd;
 * All Rights Reserved.
 * Contributor( s): ______________________________________
 *
 */

require_once(dirname(__FILE__) . '/../test.php');
require_once(KT_DIR .  '/ktapi/ktapi.inc.php');

class APIFolderTestCase extends KTUnitTestCase 
{
	/**
	 * @var KTAPI
	 */
	var $ktapi;
	var $session;
	
	 function setUp() 
	 {
	 	$this->ktapi = new KTAPI();
	 	$this->session = $this->ktapi->start_system_session();
	 }
	
	 function tearDown() 
	 {
	 	$this->session->logout();
	 }
	 
	 function testCreateDuplicate()
	 {
	 	$root=$this->ktapi->get_root_folder();
	 	$this->assertTrue(is_a($root,'KTAPI_Folder'));
	 	
	 	$folder = $root->add_folder('temp1');
	 	$this->assertTrue(is_a($folder,'KTAPI_Folder'));
	 	
	 	$folder2 = $root->add_folder('temp1');
	 	$this->assertFalse(is_a($folder2,'KTAPI_Folder'));
	 	
	 	$folder->delete('because');
	 	if (is_a($folder2,'KTAPI_Folder'))
	 	{
	 		$folder2->delete('because');
	 	}
	 	
	 }
	 
	 function testCreateFolders()
	 {
	 	$root=$this->ktapi->get_root_folder();
	 	$this->assertTrue(is_a($root,'KTAPI_Folder'));
	 	
	 	$folder = $root->add_folder('temp1');
	 	$this->assertTrue(is_a($folder,'KTAPI_Folder'));
	 	
	 	$folder2 = $folder->add_folder('temp2');
	 	$this->assertTrue(is_a($folder,'KTAPI_Folder'));
	 	
	 	$folder3 = $root->add_folder('temp3');
	 	$this->assertTrue(is_a($folder,'KTAPI_Folder'));

	 	$folder4 = $folder3->add_folder('temp4');
	 	$this->assertTrue(is_a($folder,'KTAPI_Folder'));
	 	
	 	$folderids = array(
	 		'temp1'=>$folder->get_folderid(),
	 		'temp2'=>$folder2->get_folderid(),
	 		'temp3'=>$folder3->get_folderid(),
	 		'temp4'=>$folder4->get_folderid()	 	
	 	);
	 	
	 	unset($folder);	unset($folder2); unset($folder3); unset($folder4);
	 	
	 	$paths = array(
	 		'temp1'=>'/temp1',
	 		'temp2'=>'/temp1/temp2',
	 		'temp3'=>'/temp3',
	 		'temp4'=>'/temp3/temp4',
	 	
	 	);
	 	
	 	// test reference by name	 	
	 	foreach($paths as $key=>$path)
	 	{
	 		$folder = $root->get_folder_by_name($path);
	 		$this->assertTrue(is_a($folder,'KTAPI_Folder'));
	 		if (!is_a($folder, 'KTAPI_Folder'))
	 			continue;
	 		
	 		$this->assertTrue($folder->get_folderid() == $folderids[$key]);
	 		$this->assertTrue($folder->get_full_path() == 'Root Folder' . $path);
	 	}

	 	// lets clean up
	 	foreach($paths as $key=>$path)
	 	{
	 		$folder = $root->get_folder_by_name($path);
	 		if (is_a($folder,'KTAPI_Folder'))
	 		{
	 			$folder->delete('because ' . $path);
	 		}
	 		$folder = $root->get_folder_by_name($path);
	 		$this->assertTrue(is_a($folder,'PEAR_Error'));
	 		
	 	}
	 }
	 
	 function testRename()
	 {
		$root=$this->ktapi->get_root_folder();
	 	$this->assertTrue(is_a($root,'KTAPI_Folder'));
	 	
	 	// add a sample folder
	 	$folder = $root->add_folder('newFolder');
	 	$this->assertTrue(is_a($folder,'KTAPI_Folder'));
	 	
	 	$folderid = $folder->get_folderid();
	 	
	 	// rename the folder
	 	$response = $folder->rename('renamedFolder');
	 	$this->assertTrue(!is_a($response,'PEAR_Error'));
	 	
	 	// get the folder by id
	 	$folder=$this->ktapi->get_folder_by_id($folderid);
	 	$this->assertTrue(is_a($folder,'KTAPI_Folder'));
	 	
	 	$this->assertTrue($folder->get_folder_name() == 'renamedFolder');

	 	$folder->delete('cleanup');
	 	
	 }
	 
	 
	 function getSystemListing()
	 {
	 	// TODO .. can do anything as admin...
	 }

	 function getAnonymousListing()
	 {
	 	// TODO
		// probably won't be able to do unless the api caters for setting up anonymous...	 	
	 }

	 function getUserListing()
	 {
	 	// TODO
	 	
	 }
	 
	 
	 
	 function copy()
	 {
	 	// TODO
	 }
	 
	 function move()
	 {
	 	// TODO
	 	
	 }
}

?>