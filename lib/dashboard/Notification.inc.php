<?php

require_once(KT_LIB_DIR . "/session/control.inc");
require_once(KT_LIB_DIR . "/ktentity.inc");
require_once(KT_LIB_DIR . "/database/datetime.inc");
require_once(KT_LIB_DIR . "/dashboard/NotificationRegistry.inc.php");

require_once(KT_LIB_DIR . '/users/User.inc');
require_once(KT_LIB_DIR . '/documentmanagement/Document.inc');
require_once(KT_LIB_DIR . '/foldermanagement/Folder.inc');

/**
 * class Notification
 *
 * Represents a basic message, about an item, to a user.  This ends up on the dashboard.
 */
class KTNotification extends KTEntity {
    /** primary key value */
    var $iId = -1;
    var $iUserId;
    
    // sType and sLabel provide the title of the dashboard alert.
    var $sLabel;             // a simple label - e.g. the document's title, or so forth.
    var $sType;              // namespaced item type. (e.g. ktcore/subscriptions, word/officeupload)
                             // this is used to create the appropriate renderobj.

    var $dCreationTime = null; // the date/time of this items creation.

    // iData1 and iData2 and integers, which can be used for whatever.
    // sData1 and sData2 are similar.
    // (i.e. you get very stupid subclassing semantics with up to 4 variables this way.
    var $iData1;
    var $iData2;
    var $sData1;
    var $sData2;    
    
    var $_bUsePearError = true;
    
    function getId() { return $this->iId; }
    
    function getLabel() { return $this->sLabel; }    
    function setLabel($sLabel) { $this->sLabel = $sLabel; }
    function getType() { return $this->sType; }    
    function setType($sType) { $this->sType = $sType; }
    
    function getIntData1() { return $this->iData1; }    
    function setIntData1($iData1) { $this->iData1 = $iData1; }
    function getIntData2() { return $this->iData2; }    
    function setIntData2($iData2) { $this->iData2 = $iData2; }
    function getStrData1() { return $this->sData1; }    
    function setStrData1($sData1) { $this->sData1 = $sData1; }
    function getStrData2() { return $this->sData2; }    
    function setStrData2($sData2) { $this->sData2 = $sData2; }    

    var $_aFieldToSelect = array(
        "iId" => "id",
        "iUserId" => "user_id",
        "sLabel" => "label",        
        "sType" => "type",
        "dCreationDate" => "creation_date",
        "iData1" => "data_int_1",
        "iData2" => "data_int_2",
        "sData1" => "data_str_1",
        "sData2" => "data_str_2",
        );
    
    function _table () {
        return KTUtil::getTableName('notifications');
    }
	
	function render() {
		$notificationRegistry =& KTNotificationRegistry::getSingleton();
		$handler = $notificationRegistry->getHandler($this->sType);
		return $handler->handleNotification($this);
	}
	
	function resolve() {
	    $notificationRegistry =& KTNotificationRegistry::getSingleton();
		$handler = $notificationRegistry->getHandler($this->sType);
		return $handler->resolveNotification($this);
	}

    // Static function
    function &get($iId) { return KTEntityUtil::get('KTNotification', $iId); }
    function &getList($sWhereClause = null) { return KTEntityUtil::getList2('KTNotification', $sWhereClause);	}	
    function &createFromArray($aOptions) { return KTEntityUtil::createFromArray('KTNotification', $aOptions); }

}

/** register the base handlers. */


$notificationRegistry =& KTNotificationRegistry::getSingleton();

// abstract base-class for notification handler.
class KTNotificationHandler {

    // FIXME rename this to renderNotification
	// called to _render_ the notification.
    function handleNotification($oKTNotification) {
		$oTemplating = new KTTemplating;
		$oTemplate = $oTemplating->loadTemplate("kt3/notifications/generic");
		$aTemplateData = array(
              "context" => $oKTNotification,
		);
		return $oTemplate->render($aTemplateData);
    }
	
	// called to resolve the notification (typically from /notify.php?id=xxxxx
	function resolveNotification($oKTNotification) {
	    $_SESSION['KTErrorMessage'][] = 'This notification handler does not support publication.';
	    exit(redirect(generateControllerLink('dashboard')));
	}
}

// FIXME consider refactoring this into plugins/ktcore/ktstandard/KTSubscriptions.php

class KTSubscriptionNotification extends KTNotificationHandler {
    /* Subscription Notifications
	*
	*  Subscriptions are a large part of the notification volume.
	*  That said, notifications cater to a larger group, so there is some 
	*  degree of mismatch between the two.
	*
	*  Mapping the needs of subscriptions onto the provisions of notifications 
	*  works as:
	*
	*     $oKTN->label:      object name [e.g. Document Name]
	*     $oKTN->strData1:   event type [AddFolder, AddDocument, etc.]
	*     $oKTN->strData2:   _location_ name. (e.g. folder of the subscription.)
	*     $oKTN->intData1:   object id (e.g. document_id, folder_id)
	*     $oKTN->intData2:   actor id (e.g. user_id)
	*     
	*/
	
	var $notificationType = 'ktcore/subscriptions';

	var $_eventObjectMap = array(
		"AddFolder" => 'folder',
        "RemoveSubscribedFolder" => '', // nothing. your subscription is now gone.
        "RemoveChildFolder" => 'folder',
        "AddDocument" => 'document',
        "RemoveSubscribedDocument" => '', // nothing. your subscription is now gone.
        "RemoveChildDocument" => 'folder',
        "ModifyDocument" => 'document',
        "CheckInDocument" => 'document',
        "CheckOutDocument" => 'document',
        "MovedDocument" => 'document',
        "ArchivedDocument" => 'document', // can go through and request un-archival (?)
        "RestoredArchivedDocument" => 'document');	
		
	var $_eventTypeNames = array(
		"AddFolder" => 'Folder added',
        "RemoveSubscribedFolder" => 'Folder removed', // nothing. your subscription is now gone.
        "RemoveChildFolder" => 'Folder removed',
        "AddDocument" => 'Document added',
        "RemoveSubscribedDocument" => 'Document removed', // nothing. your subscription is now gone.
        "RemoveChildDocument" => 'Document removed',
        "ModifyDocument" => 'Document modified',
        "CheckInDocument" => 'Document checked in',
        "CheckOutDocument" => 'Document checked out',
        "MovedDocument" => 'Document moved',
        "ArchivedDocument" => 'Document archived', // can go through and request un-archival (?)
        "RestoredArchivedDocument" => 'Document restored');			

	// helper method to extract / set the various pieces of information
	function _getSubscriptionData($oKTNotification) {
		$info = array(
			'object_name' => $oKTNotification->getLabel(),
			'event_type' => $oKTNotification->getStrData1(),
			'location_name' => $oKTNotification->getStrData2(),
			'object_id' => $oKTNotification->getIntData1(),
			'actor_id' => $oKTNotification->getIntData2(),
			'has_actor' => false,
			'notify_id' => $oKTNotification->getId(),
		);
		
		$info['title'] = KTUtil::arrayGet($this->_eventTypeNames, $info['event_type'], 'Subscription alert:') .': ' . $info['object_name'];
		
		if ($info['actor_id'] !== null) {
			$oTempUser = User::get($info['actor_id']);
			if (PEAR::isError($oTempUser) || ($oTempUser == false)) {
				// no-act
				$info['actor'] = null;
			} else {
			    $info['actor'] = $oTempUser;
				$info['has_actor'] = true;
			}
		}
		
		if ($info['object_id'] !== null) {
		    $info['object'] = $this->_getEventObject($info['event_type'], $info['object_id']);
		}
		
		return $info;
	}
	
	// resolve the object type based on the alert type.
	function _getEventObject($sAlertType, $id) {
        $t = KTUtil::arrayGet($this->_eventObjectMap, $sAlertType ,'');
		if ($t == 'document') {
		    $o = Document::get($id);
			if (PEAR::isError($o) || ($o == false)) { return null; 
			} else { return $o; }
		} else if ($t == 'folder') {
		    $o = Folder::get($id);
			if (PEAR::isError($o) || ($o == false)) { return null; 
			} else { return $o; }
		} else {
			return null;
		}
	}
	
	function _getEventObjectType($sAlertType) {
		return KTUtil::arrayGet($this->_eventObjectMap, $sAlertType ,'');
	}

    function handleNotification($oKTNotification) {
		$oTemplating = new KTTemplating;
		$oTemplate = $oTemplating->loadTemplate("kt3/notifications/subscriptions");
		$aTemplateData = array(
              "context" => $oKTNotification,
			  "info" => $this->_getSubscriptionData($oKTNotification),
		);
		return $oTemplate->render($aTemplateData);
    }

	// helper to _create_ a notification, in a way that is slightly less opaque.
	function &generateSubscriptionNotification($aOptions) {
	    $creationInfo = array();
		/*
		"iId" => "id",
        "iUserId" => "user_id",
        "sLabel" => "label",        
        "sType" => "type",
        "dCreationDate" => "creation_date",
        "iData1" => "data_int_1",
        "iData2" => "data_int_2",
        "sData1" => "data_str_1",
        "sData2" => "data_str_2",
		
		'object_name' => $oKTNotification->getLabel(),
		'event_type' => $oKTNotification->getStrData1(),
		'location_name' => $oKTNotification->getStrData2(),
		'object_id' => $oKTNotification->getIntData1(),
		'actor_id' => $oKTNotification->getIntData2(),
		'has_actor' => false,		
		
		*/
		$creationInfo['sLabel'] = $aOptions['target_name'];
		$creationInfo['sData1']  = $aOptions['event_type'];
		$creationInfo['sData2']  = $aOptions['location_name'];
		$creationInfo['iData1']  = $aOptions['object_id'];
		$creationInfo['iData2']  = $aOptions['actor_id'];
		$creationInfo['iUserId']  = $aOptions['target_user'];
		$creationInfo['sType']  = 'ktcore/subscriptions';
		$creationInfo['dCreationDate'] = getCurrentDateTime(); // erk.

		global $default;
		
		//$default->log->debug('subscription notification:  from ' . print_r($aOptions, true));
		$default->log->debug('subscription notification:  using ' . print_r($creationInfo, true));
		
		$oNotification =& KTNotification::createFromArray($creationInfo);
		
		
		$default->log->debug('subscription notification:  created ' . print_r($oNotification, true));
		
		return $oNotification; // $res.
	}
	
	
	
	function resolveNotification($oKTNotification) {
	    $notify_action = KTUtil::arrayGet($_REQUEST, 'notify_action', null);
		if ($notify_action == 'clear') {
		    $_SESSION['KTInfoMessage'][] = 'Cleared notification.';
			$oKTNotification->delete();
			exit(redirect(generateControllerLink('dashboard')));
		}
	    
		// otherwise, we want to redirect the to object represented by the item.
		//  - viewDocument and viewFolder are the appropriate items.
		//  - object_id 
		$info = $this->_getSubscriptionData($oKTNotification);
		
		$object_type = $this->_getEventObjectType($info['event_type']);
		if ($object_type == '') {
		    $_SESSION['KTErrorMessage'][] = 'This notification has no "target".  Please report as a bug that this subscription should only have a clear action.' . $object_type;		
		    exit(redirect(generateControllerLink('dashboard')));
		}  
		
		if ($object_type == 'document') {
		    if ($info['object_id'] !== null) { // fails and generates an error with no doc-id.
				$params = 'fDocumentId=' . $info['object_id'];
				$url = generateControllerLink('viewDocument', $params);
				$oKTNotification->delete(); // clear the alert.
				exit(redirect($url));
			} 
		} else if ($object_type == 'folder') {
		    if ($info['object_id'] !== null) { // fails and generates an error with no doc-id.
				$params = 'fFolderId=' . $info['object_id'];
				$url = generateControllerLink('browse', $params);
				$oKTNotification->delete(); // clear the alert.
				exit(redirect($url));
			} 
		}
		$_SESSION['KTErrorMessage'][] = 'This notification has no "target".  Please inform the KnowledgeTree developers that there is a target bug with type: ' . $info['event_type'];		
		exit(redirect(generateControllerLink('dashboard')));
	}
	
}

$notificationRegistry->registerNotificationHandler("ktcore/subscriptions","KTSubscriptionNotification");

?>
