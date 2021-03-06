<?php
/**
* Implements a CMIS wrapper API for KnowledgeTree.
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
* @package KTCMIS
* @version Version 0.9
*/

/**
 * Split into individual classes to handle each section of functionality.
 * This is really just a handling layer between CMIS and the web services.
 */

// TODO implement exceptions in various calls (in the underlying classes)
// FIXME none of the error handling actually does anything, it's leftover from copy/paste of some ktapi code

require_once(realpath(dirname(__FILE__) . '/../../../config/dmsDefaults.php'));
require_once(KT_DIR . '/ktapi/ktapi.inc.php');

define ('CMIS_DIR', KT_LIB_DIR . '/api/ktcmis');
require_once(CMIS_DIR . '/exceptions/PermissionDeniedException.inc.php');
require_once(CMIS_DIR . '/services/CMISRepositoryService.inc.php');
require_once(CMIS_DIR . '/services/CMISNavigationService.inc.php');
require_once(CMIS_DIR . '/services/CMISObjectService.inc.php');
require_once(CMIS_DIR . '/services/CMISVersioningService.inc.php');
require_once(CMIS_DIR . '/util/CMISUtil.inc.php');

/**
 * Base class for all KT CMIS classes
 * Handles authentication
 */
class KTCMISBase {

    // we want all child classes to share the ktapi and session instances, no matter where they are set from,
    // so we declare them as static
    static protected $ktapi;
    static protected $session;

    public function __construct(&$ktapi = null, $username = null, $password = null)
    {
        // TODO confirm KTAPI instance active??? shouldn't really be responsibility of this code
        if (is_null($ktapi) && (!is_null($username) && !is_null($password))) {
            $this->startSession($username, $password);
        }
        else {
            self::$ktapi = $ktapi;
            self::$session = self::$ktapi->get_session();
        }
    }

    // TODO this probably does not belong here??? probably should require all auth external, handled by transport protocol.
    //      perhaps simple refusal to execute without valid session?
    // NOTE left in to allow transport protocol to delegate auth to this level, but not actually used in any code at present
    public function startSession($username, $password)
    {
        // attempt to recover session if one exists
        if (!is_null(self::$session) && !PEAR::isError(self::$session))
        {
            self::$session =& self::$ktapi->get_active_session(self::$session->get_sessionid());
        }

        // start new session if no existing session or problem getting existing session (expired, etc...)
        if (is_null(self::$session) || PEAR::isError(self::$session))
        {
            self::$ktapi = new KTAPI();
            self::$session =& self::$ktapi->start_session($username, $password);
        }

        // failed authentication?
        if (PEAR::isError(self::$session))
        {
            throw new PermissionDeniedException('You must be authenticated to perform this action');
        }
        
        return self::$session;
    }

    public function setInterface(&$ktapi = null)
    {
        if (!is_null($ktapi)) {
            self::$ktapi = $ktapi;
        }
    }

    public function getInterface()
    {
        return self::$ktapi;
    }

    public function getSession()
    {
        return self::$session;
    }

    // TODO what about destroying sessions? only on logout (which is not offered by the CMIS clients tested so far)
}

/**
 * Handles low level repository information queries
 */
class KTRepositoryService extends KTCMISBase {

    protected $RepositoryService;

    public function __construct()
    {
        // instantiate underlying CMIS service
        $this->RepositoryService = new CMISRepositoryService();
    }

    /**
     * Fetch a list of all available repositories
     *
     * NOTE Since we only have one repository at the moment, this is expected to only return one result
     *
     * @return repositoryList[]
     */
    public function getRepositories()
    {
        $repositories = $this->RepositoryService->getRepositories();
        if (PEAR::isError($repositories))
        {
            return array(
                "status_code" => 1,
                "message" => "Failed getting repositories"
            );
        }

        // extract the required info fields into array format for easy encoding;
        $count = 0;
        $repositoryList = array();
        foreach ($repositories as $repository)
        {
            $repositoryList[$count]['repositoryId'] = $repository->getRepositoryId();
            $repositoryList[$count]['repositoryName'] = $repository->getRepositoryName();
            $repositoryList[$count]['repositoryURI'] = $repository->getRepositoryURI();
            ++$count;
        }

        return array(
            "status_code" => 0,
            "results" => $repositoryList
        );
    }

    /**
     * Fetches information about the selected repository
     *
     * @param string $repositoryId
     */
    public function getRepositoryInfo($repositoryId)
    {
        $repositoryInfo = $this->RepositoryService->getRepositoryInfo($repositoryId);
        if (PEAR::isError($repositoryInfo))
        {
            return array(
                "status_code" => 1,
                "message" => "Failed getting repository information"
            );
        }

        // TODO output this manually, the function works but only for some objects so rather avoid it completely?
        // NOTE the problems appear to be due to recursive objects
        return array (
            "status_code" => 0,
            "results" => CMISUtil::objectToArray($repositoryInfo)
        );
    }

    /**
     * Fetch the list of supported object types for the selected repository
     *
     * @param string $repositoryId
     */
    public function getTypes($repositoryId, $typeId = '', $returnPropertyDefinitions = false,
                             $maxItems = 0, $skipCount = 0, &$hasMoreItems = false)
    {
        try {
            $repositoryObjectTypeResult = $this->RepositoryService->getTypes($repositoryId, $typeId, $returnPropertyDefinitions,
                                                                             $maxItems, $skipCount, $hasMoreItems);
        }
        catch (Exception $e)
        {
            return array(
                "status_code" => 1,
                "message" => $e->getMessage()
            );
        }

        // format as array style output
        // NOTE only concerned with attributes at this time
        // TODO add support for properties
        foreach($repositoryObjectTypeResult as $key => $objectType)
        {
            $repositoryObjectTypes[$key] = $objectType['attributes'];
            // TODO properties
            // $repositoryObjectTypes[$key]['properties'] = $objectType['properties'];
        }

        return array (
            "status_code" => 0,
            "results" => $repositoryObjectTypes
        );
    }

    /**
     * Fetch the object type definition for the requested type
     *
     * @param string $repositoryId
     * @param string $typeId
     */
    public function getTypeDefinition($repositoryId, $typeId)
    {
        try {
            $typeDefinitionResult = $this->RepositoryService->getTypeDefinition($repositoryId, $typeId);
        }
        catch (Exception $e)
        {
            return array(
                "status_code" => 1,
                "message" => $e->getMessage()
            );
        }

        // format as array style output
        // NOTE only concerned with attributes at this time
        // TODO add support for properties
        $typeDefinition = $typeDefinitionResult['attributes'];

        return array (
            "status_code" => 0,
            "results" => $typeDefinition
        );
    }

}

/*
 * Handles repository navigation
 */
class KTNavigationService extends KTCMISBase {

    protected $NavigationService;

    public function __construct(&$ktapi = null, $username = null, $password = null)
    {
        parent::__construct($ktapi, $username, $password);
        // instantiate underlying CMIS service
        $this->NavigationService = new CMISNavigationService();
        $this->setInterface();
    }

    public function startSession($username, $password)
    {
        parent::startSession($username, $password);
        $this->setInterface();
        return self::$session;
    }

    public function setInterface(&$ktapi = null)
    {
        parent::setInterface($ktapi);
        $this->NavigationService->setInterface(self::$ktapi);
    }

    /**
     * Get descendents of the specified folder, up to the depth indicated
     *
     * @param string $repositoryId
     * @param string $folderId
     * @param boolean $includeAllowableActions
     * @param boolean $includeRelationships
     * @param string $typeID
     * @param int $depth
     * @param string $filter
     * @return array $descendants
     */
    public function getDescendants($repositoryId, $folderId, $includeAllowableActions, $includeRelationships,
                            $depth = 1, $typeID = 'Any', $filter = '')
    {
        // TODO optional parameters
        $descendantsResult = $this->NavigationService->getDescendants($repositoryId, $folderId, $includeAllowableActions,
                                                                        $includeRelationships, $depth);

        if (PEAR::isError($descendantsResult))
        {
            return array(
                "status_code" => 1,
                "message" => "Failed getting descendants for folder"
            );
        }

        // format for webservices consumption
        // NOTE this will almost definitely be changing in the future, this is just to get something working
        $descendants = CMISUtil::decodeObjectHierarchy($descendantsResult, 'child');

        return array (
            "status_code" => 0,
            "results" => $descendants
        );
    }

    /**
     * Get direct children of the specified folder
     *
     * @param string $repositoryId
     * @param string $folderId
     * @param boolean $includeAllowableActions
     * @param boolean $includeRelationships
     * @param string $typeID
     * @param string $filter
     * @param int $maxItems
     * @param int $skipCount
     * @return array $descendants
     */
    public function getChildren($repositoryId, $folderId, $includeAllowableActions, $includeRelationships,
                         $typeID = 'Any', $filter = '', $maxItems = 0, $skipCount = 0)
    {
        // TODO paging
        // TODO optional parameters
        $childrenResult = $this->NavigationService->getChildren($repositoryId, $folderId, $includeAllowableActions, $includeRelationships);

        if (PEAR::isError($childrenResult))
        {
            return array(
                "status_code" => 1,
                "message" => "Failed getting descendants for folder"
            );
        }

        $children = CMISUtil::decodeObjectHierarchy($childrenResult, 'child');

        return array(
			"status_code" => 0,
			"results" => $children
		);
    }

    /**
     * Gets the parent of the selected folder
     *
     * @param string $repositoryId
     * @param string $folderId
     * @param boolean $includeAllowableActions
     * @param boolean $includeRelationships
     * @param boolean $returnToRoot
     * @param string $filter
     * @return ancestry[]
     */
    public function getFolderParent($repositoryId, $folderId, $includeAllowableActions, $includeRelationships, $returnToRoot, $filter = '')
    {
        $ancestryResult = $this->NavigationService->getFolderParent($repositoryId, $folderId, $includeAllowableActions,
                                                              $includeRelationships, $returnToRoot);

        if (PEAR::isError($ancestryResult))
        {
            return array(
                "status_code" => 1,
                "message" => "Failed getting ancestry for folder"
            );
        }

        $ancestry = CMISUtil::decodeObjectHierarchy($ancestryResult, 'child');

        return array(
			"status_code" => 0,
			"results" => $ancestry
		);
    }

    /**
     * Gets the parents for the selected object
     *
     * @param string $repositoryId
     * @param string $folderId
     * @param boolean $includeAllowableActions
     * @param boolean $includeRelationships
     * @param string $filter
     * @return ancestry[]
     */
    function getObjectParents($repositoryId, $objectId, $includeAllowableActions, $includeRelationships, $filter = '')
    {
        $ancestryResult = $this->NavigationService->getObjectParents($repositoryId, $objectId, $includeAllowableActions,
                                                                     $includeRelationships);

        if (PEAR::isError($ancestryResult))
        {
            return array(
                "status_code" => 1,
                "message" => "Failed getting ancestry for object"
            );
        }

        $ancestry = CMISUtil::decodeObjectHierarchy($ancestryResult, 'child');

        return array(
            "status_code" => 0,
            "results" => $ancestry
        );
    }

    /**
     * Returns a list of checked out documents from the selected repository
     *
     * @param string $repositoryId
     * @param string $folderId The folder for which checked out docs are requested
     * @param string $filter
     * @param boolean $includeAllowableActions
     * @param boolean $includeRelationships
     * @param int $maxItems
     * @param int $skipCount
     * @return array $checkedout The collection of checked out documents
     */
    function getCheckedOutDocs($repositoryId, $includeAllowableActions, $includeRelationships, $folderId = null, $filter = '', 
                               $maxItems = 0, $skipCount = 0)
    {
        $checkedout = $this->NavigationService->getCheckedOutDocs($repositoryId, $includeAllowableActions, $includeRelationships, 
                                                                  $folderId, $filter, $maxItems, $skipCount);

        if (PEAR::isError($checkedout))
        {
            return array(
                "status_code" => 1,
                "message" => "Failed getting list of checked out documents"
            );
        }

        // convert to array format for external code
        $co = array();
        foreach ($checkedout as $documentProperties)
        {
            $co[] = CMISUtil::createObjectPropertiesEntry($documentProperties);;
        }

        return array(
            "status_code" => 0,
            "results" => $co
        );
    }

}

/**
 * Handles requests for and actions on Folders and Documents
 */
class KTObjectService extends KTCMISBase {

    protected $ObjectService;

    public function __construct(&$ktapi = null, $username = null, $password = null)
    {
        parent::__construct($ktapi, $username, $password);
        // instantiate underlying CMIS service
        $this->ObjectService = new CMISObjectService();
        $this->setInterface();
    }

    public function startSession($username, $password)
    {
        parent::startSession($username, $password);
        $this->setInterface();
        return self::$session;
    }

    public function setInterface(&$ktapi = null)
    {
        parent::setInterface($ktapi);
        $this->ObjectService->setInterface(self::$ktapi);
    }

    /**
     * Gets the properties for the selected object
     *
     * @param string $repositoryId
     * @param string $objectId
     * @param boolean $includeAllowableActions
     * @param boolean $includeRelationships
     * @param string $returnVersion
     * @param string $filter
     * @return properties[]
     */
    public function getProperties($repositoryId, $objectId, $includeAllowableActions, $includeRelationships,
                           $returnVersion = false, $filter = '')
    {
        try {
            $propertyCollection = $this->ObjectService->getProperties($repositoryId, $objectId, $includeAllowableActions,
                                                                      $includeRelationships);
        }
        catch (Exception $e)
        {
            return array(
                "status_code" => 1,
                "message" => $e->getMessage()
            );
        }

        $properties = CMISUtil::createObjectPropertiesEntry($propertyCollection);

        return array(
			"status_code" => 0,
			"results" => $properties
		);
    }

    /**
     * Creates a new document within the repository
     *
     * @param string $repositoryId The repository to which the document must be added
     * @param string $typeId Object Type id for the document object being created
     * @param array $properties Array of properties which must be applied to the created document object
     * @param string $folderId The id of the folder which will be the parent of the created document object
     *                         This parameter is optional IF unfilingCapability is supported
     * @param contentStream $contentStream optional content stream data
     * @param string $versioningState optional version state value: checkedout/major/minor
     * @return string $objectId The id of the created folder object
     */
    public function createDocument($repositoryId, $typeId, $properties, $folderId = null,
                            $contentStream = null, $versioningState = null)
    {
        $objectId = null;

        try {
            $objectId = $this->ObjectService->createDocument($repositoryId, $typeId, $properties, $folderId,
                                                             $contentStream, $versioningState);
        }
        catch (Exception $e)
        {
            return array(
                "status_code" => 1,
                "message" => $e->getMessage()
            );
        }

        return array(
            'status_code' => 0,
            'results' => $objectId
        );
    }

    /**
     * Creates a new folder within the repository
     *
     * @param string $repositoryId The repository to which the folder must be added
     * @param string $typeId Object Type id for the folder object being created
     * @param array $properties Array of properties which must be applied to the created folder object
     * @param string $folderId The id of the folder which will be the parent of the created folder object
     * @return string $objectId The id of the created folder object
     */
    public function createFolder($repositoryId, $typeId, $properties, $folderId)
    {
        $objectId = null;

        try {
            $objectId = $this->ObjectService->createFolder($repositoryId, $typeId, $properties, $folderId);
        }
        catch (Exception $e)
        {
            return array(
                "status_code" => 1,
                "message" => $e->getMessage()
            );
        }

        return array(
            'status_code' => 0,
            'results' => $objectId
        );
    }
    
    /**
     * Fetches the content stream data for an object
     *  
     * @param string $repositoryId
     * @param string $objectId
     * @return string $contentStream (binary or text data)
     */
    function getContentStream($repositoryId, $objectId)
    {
        try {
            $contentStream = $this->ObjectService->getContentStream($repositoryId, $objectId);
        }
        catch (Exception $e)
        {
            return array(
                "status_code" => 1,
                "message" => $e->getMessage()
            );
        }

        return array(
            'status_code' => 0,
            'results' => $contentStream
        );
    }
    
    /**
     * Moves a fileable object from one folder to another.
     * 
     * @param object $repositoryId
     * @param object $objectId
     * @param object $changeToken [optional]
     * @param object $targetFolderId
     * @param object $sourceFolderId [optional] 
     */
    public function moveObject($repositoryId, $objectId, $changeToken = '', $targetFolderId, $sourceFolderId = null)
    {
        try {
            $this->ObjectService->moveObject($repositoryId, $objectId, $changeToken, $targetFolderId, $sourceFolderId);
        }
        catch (Exception $e)
        {
            return array(
                "status_code" => 1,
                "message" => $e->getMessage()
            );
        }

        return array(
            'status_code' => 0,
            'results' => $objectId
        );
    }
    
    /**
     * Deletes an object from the repository
     * 
     * @param string $repositoryId
     * @param string $objectId
     * @param string $changeToken [optional]
     * @return array
     */
    // NOTE Invoking this service method on an object SHALL not delete the entire version series for a Document Object. 
    //      To delete an entire version series, use the deleteAllVersions() service
    public function deleteObject($repositoryId, $objectId, $changeToken = null)
    {
        try {
            $this->ObjectService->deleteObject($repositoryId, $objectId, $changeToken);
        }
        catch (Exception $e)
        {
            return array(
                "status_code" => 1,
                "message" => $e->getMessage()
            );
        }

        return array(
            'status_code' => 0,
            'results' => $objectId
        );
    }
    
    public function deleteTree($repositoryId, $objectId, $changeToken = null, $unfileNonfolderObject = 'delete', $continueOnFailure = false)
    {
        try {
            $result = $this->ObjectService->deleteTree($repositoryId, $objectId, $changeToken, $unfileNonfolderObject, $continueOnFailure);
        }
        catch (Exception $e)
        {
            return array(
                "status_code" => 1,
                "message" => $e->getMessage()
            );
        }

        // check whether there is a list of items which did not delete
        if (count($result) > 0)
        {
            return array(
                "status_code" => 1,
                "message" => $result
            );          
        }
        
        return array(
            'status_code' => 0,
            'results' => $objectId
        );
    }

    /**
     * Sets the content stream data for an existing document
     *
     * if $overwriteFlag = TRUE, the new content stream is applied whether or not the document has an existing content stream
     * if $overwriteFlag = FALSE, the new content stream is applied only if the document does not have an existing content stream
     *
     * NOTE A Repository MAY automatically create new Document versions as part of this service method.
     *      Therefore, the documentId output NEED NOT be identical to the documentId input.
     *
     * @param string $repositoryId
     * @param string $documentId
     * @param boolean $overwriteFlag
     * @param string $contentStream
     * @param string $changeToken
     * @return string $documentId
     */
    function setContentStream($repositoryId, $documentId, $overwriteFlag, $contentStream, $changeToken = null)
    {
        try {
            $documentId = $this->ObjectService->setContentStream($repositoryId, $documentId, $overwriteFlag, $contentStream, $changeToken);
        }
        catch (Exception $e)
        {
            return array(
                "status_code" => 1,
                "message" => $e->getMessage()
            );
        }

        return array(
            'status_code' => 0,
            'results' => $documentId
        );
    }

}

/**
 * Handles requests for and actions on versionable objects
 */
class KTVersioningService extends KTCMISBase {

    protected $VersioningService;

    public function __construct(&$ktapi = null, $username = null, $password = null)
    {
        parent::__construct($ktapi, $username, $password);
        // instantiate underlying CMIS service
        $this->VersioningService = new CMISVersioningService();
        $this->setInterface();
    }

    public function startSession($username, $password)
    {
        parent::startSession($username, $password);
        $this->setInterface();
        return self::$session;
    }

    public function setInterface(&$ktapi = null)
    {
        parent::setInterface($ktapi);
        $this->VersioningService->setInterface(self::$ktapi);
    }
    
    /**
     * Deletes all Document Objects in the specified Version Series, including the Private Working Copy
     * 
     * @param string $repositoryId
     * @param string $versionSeriesId
     * @return boolean true if successful
     */
    public function deleteAllVersions($repositoryId, $versionSeriesId)
    {
        try {
            $result = $this->VersioningService->deleteAllVersions($repositoryId, $versionSeriesId);
        }
        catch (Exception $e)
        {
            return array(
                "status_code" => 1,
                "message" => $e->getMessage()
            );
        }

        return array(
            'status_code' => 0,
            'results' => $result
        );
    }
    
    /**
     * Checks out a document and creates the PWC (Private Working Copy) which will represent the checked out document
     * 
     * @param string $repositoryId
     * @param string $documentId
     * @param string $changeToken [optional]
     * @return array results
     */
    // TODO set up delivery of content stream? or is that up to the CMIS client?
    public function checkOut($repositoryId, $documentId, $changeToken = '')
    {
        try {
            $result = $this->VersioningService->checkOut($repositoryId, $documentId, $changeToken);
        }
        catch (Exception $e)
        {
            return array(
                "status_code" => 1,
                "message" => $e->getMessage()
            );
        }

        return array(
            'status_code' => 0,
            'results' => (!empty($result) ? $result : 'Document Checked Out')
        );
    }
    
    /**
     * Reverses the effect of a checkout: I.E. deletes the PWC (Private Working Copy) and re-sets the status of the document to "not checked out" 
     * 
     * @param string $repositoryId
     * @param string $documentId
     * @param string $changeToken [optional]
     */
    // TODO exceptions:
    //      •	ConstraintViolationException: The Repository SHALL throw this exception if ANY of the following conditions are met:
    //      o	The Document’s Object-Type definition’s versionable attribute is FALSE. 
    //      •	updateConflictException
    //      •	versioningException
    public function cancelCheckOut($repositoryId, $documentId, $changeToken = '')
    {
        try {
            $result = $this->VersioningService->cancelCheckOut($repositoryId, $documentId, $changeToken);
        }
        catch (Exception $e)
        {
            return array(
                "status_code" => 1,
                "message" => $e->getMessage()
            );
        }

        return array(
            'status_code' => 0,
            'results' => (!empty($result) ? $result : 'Document Checkout Cancelled')
        );
    }
    
    /**
     * Checks in a checked out document
     * 
     * @param string $repositoryId
     * @param string $documentId
     * @param boolean $major
     * @param string $changeToken [optional]
     * @param array $properties [optional]
     * @param contentStream $contentStream [optional]
     * @param string $checkinComment [optional]
     * @return string $documentId
     */
    public function checkIn($repositoryId, $documentId, $major, $changeToken = '', $properties = array(), $contentStream = null, $checkinComment = '')
    {
        try {
            $result = $this->VersioningService->checkIn($repositoryId, $documentId, $major, $changeToken, $properties, $contentStream, $checkinComment);
        }
        catch (Exception $e)
        {
            return array(
                "status_code" => 1,
                "message" => $e->getMessage()
            );
        }

        return array(
            'status_code' => 0,
            'results' => (!empty($result) ? $result : 'Document Checked In Successfully')
        );
    }

}

?>
