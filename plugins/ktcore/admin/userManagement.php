<?php

/**
 * $Id$
 *
 * Copyright (c) 2006 Jam Warehouse http://www.jamwarehouse.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; using version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * -------------------------------------------------------------------------
 *
 * You can contact the copyright owner regarding licensing via the contact
 * details that can be found on the KnowledgeTree web site:
 *
 *         http://www.ktdms.com/
 */

require_once(KT_LIB_DIR . '/database/dbutil.inc');

require_once(KT_LIB_DIR . '/users/User.inc');
require_once(KT_LIB_DIR . '/groups/GroupUtil.php');
require_once(KT_LIB_DIR . '/groups/Group.inc');

require_once(KT_LIB_DIR . "/templating/templating.inc.php");
require_once(KT_LIB_DIR . "/dispatcher.inc.php");
require_once(KT_LIB_DIR . "/templating/kt3template.inc.php");
require_once(KT_LIB_DIR . "/widgets/fieldWidgets.php");

require_once(KT_LIB_DIR . "/authentication/authenticationsource.inc.php");
require_once(KT_LIB_DIR . "/authentication/authenticationproviderregistry.inc.php");
require_once(KT_LIB_DIR . "/authentication/builtinauthenticationprovider.inc.php");

class KTUserAdminDispatcher extends KTAdminDispatcher {
var $sHelpPage = 'ktcore/admin/manage users.html';
    function do_main() {
        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _kt('User Management'));
        $this->oPage->setBreadcrumbDetails(_kt('select a user'));
        $this->oPage->setTitle(_kt("User Management"));
		
		$KTConfig =& KTConfig::getSingleton();
        $alwaysAll = $KTConfig->get("alwaysShowAll");
		
        $name = KTUtil::arrayGet($_REQUEST, 'name');
        $show_all = KTUtil::arrayGet($_REQUEST, 'show_all', $alwaysAll);
        $user_id = KTUtil::arrayGet($_REQUEST, 'user_id');
    
        $no_search = true;
        
        if (KTUtil::arrayGet($_REQUEST, 'do_search', false) != false) {
            $no_search = false;
        }
        
        
        
        $search_fields = array();
        $search_fields[] =  new KTStringWidget(_kt('Username'), _kt("Enter part of the person's username.  e.g. <strong>ra</strong> will match <strong>brad</strong>."), 'name', $name, $this->oPage, true);
        
        // FIXME handle group search stuff.
        $search_results = null;
        if (!empty($name)) {
            $search_results =& User::getList('WHERE username LIKE \'%' . DBUtil::escapeSimple($name) . '%\' AND id > 0');
        } else if ($show_all !== false) {
            $search_results =& User::getList('id > 0');
            $no_search = false;
        }
        
        $oTemplating =& KTTemplating::getSingleton();        
        $oTemplate = $oTemplating->loadTemplate("ktcore/principals/useradmin");
        $aTemplateData = array(
            "context" => $this,
            "search_fields" => $search_fields,
            "search_results" => $search_results,
            'no_search' => $no_search,
        );
        return $oTemplate->render($aTemplateData);
    }


    function do_addUser() {
        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _kt('User Management'));
        $this->oPage->setBreadcrumbDetails(_kt('add a new user'));
        $this->oPage->setTitle(_kt("Add New User"));
        
        $name = KTUtil::arrayGet($_REQUEST, 'name');
        $show_all = KTUtil::arrayGet($_REQUEST, 'show_all', false);
        $add_user = KTUtil::arrayGet($_REQUEST, 'add_user', false);
        if ($add_user !== false) { $add_user = true; }
        $edit_user = KTUtil::arrayGet($_REQUEST, 'edit_user', false);
    
        $aOptions = array('autocomplete' => false);
        
        // sometimes even admin is restricted in what they can do.

		$KTConfig =& KTConfig::getSingleton();
		$minLength = ((int) $KTConfig->get('user_prefs/passwordLength', 6));
		$restrictAdmin = ((bool) $KTConfig->get('user_prefs/restrictAdminPasswords', false));
		$passwordAddRequirement = '';
		if ($restrictAdmin) {
		     $passwordAddRequirement = ' ' . sprintf('Password must be at least %d characters long.', $minLength);
		}
        
        $add_fields = array();
        $add_fields[] =  new KTStringWidget(_kt('Username'), _kt('The username the user will enter to gain access to KnowledgeTree.  e.g. <strong>jsmith</strong>'), 'newusername', null, $this->oPage, true, null, null, $aOptions);
        $add_fields[] =  new KTStringWidget(_kt('Name'), _kt('The full name of the user.  This is shown in reports and listings.  e.g. <strong>John Smith</strong>'), 'name', null, $this->oPage, true, null, null, $aOptions);        
        $add_fields[] =  new KTStringWidget(_kt('Email Address'), _kt('The email address of the user.  Notifications and alerts are mailed to this address if <strong>email notifications</strong> is set below. e.g. <strong>jsmith@acme.com</strong>'), 'email_address', null, $this->oPage, false, null, null, $aOptions);        
        $add_fields[] =  new KTCheckboxWidget(_kt('Email Notifications'), _kt("If this is specified then the user will have notifications sent to the email address entered above.  If it isn't set, then the user will only see notifications on the <strong>Dashboard</strong>"), 'email_notifications', true, $this->oPage, false, null, null, $aOptions);        
        $add_fields[] =  new KTPasswordWidget(_kt('Password'), _kt('Specify an initial password for the user.') . $passwordAddRequirement, 'password', null, $this->oPage, true, null, null, $aOptions);        
        $add_fields[] =  new KTPasswordWidget(_kt('Confirm Password'), _kt('Confirm the password specified above.'), 'confirm_password', null, $this->oPage, true, null, null, $aOptions);        
        // nice, easy bits.
        $add_fields[] =  new KTStringWidget(_kt('Mobile Number'), _kt("The mobile phone number of the user.  e.g. <strong>999 9999 999</strong>"), 'mobile_number', null, $this->oPage, false, null, null, $aOptions);        
        $add_fields[] =  new KTStringWidget(_kt('Maximum Sessions'), _kt('As a safety precaution, it is useful to limit the number of times a given account can log in, before logging out.  This prevents a single account being used by many different people.'), 'max_sessions', '3', $this->oPage, true, null, null, $aOptions);        

        $aAuthenticationSources =& KTAuthenticationSource::getList();
        
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate("ktcore/principals/adduser");
        $aTemplateData = array(
            "context" => &$this,
            "add_fields" => $add_fields,
            "authentication_sources" => $aAuthenticationSources,
        );
        return $oTemplate->render($aTemplateData);
    }    
    
    function do_addUserFromSource() {
        $oSource =& KTAuthenticationSource::get($_REQUEST['source_id']);
        $sProvider = $oSource->getAuthenticationProvider();
        $oRegistry =& KTAuthenticationProviderRegistry::getSingleton();
        $oProvider =& $oRegistry->getAuthenticationProvider($sProvider);

        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _kt('User Management'));
        $this->aBreadcrumbs[] = array('url' => KTUtil::addQueryStringSelf('action=addUser'), 'name' => _kt('add a new user'));
        $oProvider->aBreadcrumbs = $this->aBreadcrumbs;
        $oProvider->oPage->setBreadcrumbDetails($oSource->getName());
        $oProvider->oPage->setTitle(_kt("Add New User"));

        $oProvider->dispatch();
        exit(0);
    }

    function do_editUser() {
        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _kt('User Management'));
        $this->oPage->setBreadcrumbDetails(_kt('modify user details'));
        $this->oPage->setTitle(_kt("Modify User Details"));
        
        $user_id = KTUtil::arrayGet($_REQUEST, 'user_id');
        $oUser =& User::get($user_id);
        
        if (PEAR::isError($oUser) || $oUser == false) {
            $this->errorRedirectToMain(_kt('Please select a user first.'));
            exit(0);
        }
        
        $this->aBreadcrumbs[] = array('name' => $oUser->getName());
        
        $edit_fields = array();
        $edit_fields[] =  new KTStringWidget(_kt('Username'), _kt('The username the user will enter to gain access to KnowledgeTree.  e.g. <strong>jsmith</strong>'), 'newusername', $oUser->getUsername(), $this->oPage, true);
        $edit_fields[] =  new KTStringWidget(_kt('Name'), _kt('The full name of the user.  This is shown in reports and listings.  e.g. <strong>John Smith</strong>'), 'name', $oUser->getName(), $this->oPage, true);        
        $edit_fields[] =  new KTStringWidget(_kt('Email Address'), _kt('The email address of the user.  Notifications and alerts are mailed to this address if <strong>email notifications</strong> is set below. e.g. <strong>jsmith@acme.com</strong>'), 'email_address', $oUser->getEmail(), $this->oPage, false);        
        $edit_fields[] =  new KTCheckboxWidget(_kt('Email Notifications'), _kt('If this is specified then the user will have notifications sent to the email address entered above.  If it is not set, then the user will only see notifications on the <strong>Dashboard</strong>'), 'email_notifications', $oUser->getEmailNotification(), $this->oPage, false);        
        $edit_fields[] =  new KTStringWidget(_kt('Mobile Number'), _kt("The mobile phone number of the user.  e.g. <strong>999 9999 999</strong>"), 'mobile_number', $oUser->getMobile(), $this->oPage, false);        
        $edit_fields[] =  new KTStringWidget(_kt('Maximum Sessions'), _kt('As a safety precaution, it is useful to limit the number of times a given account can log in, before logging out.  This prevents a single account being used by many different people.'), 'max_sessions', $oUser->getMaxSessions(), $this->oPage, true);        
        
        $oAuthenticationSource = KTAuthenticationSource::getForUser($oUser);
        if (is_null($oAuthenticationSource)) {
            $oProvider =& new KTBuiltinAuthenticationProvider;
        } else {
            $sProvider = $oAuthenticationSource->getAuthenticationProvider();
            $oRegistry =& KTAuthenticationProviderRegistry::getSingleton();
            $oProvider = $oRegistry->getAuthenticationProvider($sProvider);
        }
        
        $oTemplating =& KTTemplating::getSingleton();        
        $oTemplate = $oTemplating->loadTemplate("ktcore/principals/edituser");
        $aTemplateData = array(
            "context" => $this,
            "edit_fields" => $edit_fields,
            "edit_user" => $oUser,
            "provider" => $oProvider,
            "source" => $oAuthenticationSource,
        );
        return $oTemplate->render($aTemplateData);
    }


    function do_setPassword() {
        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _kt('User Management'));
        $this->oPage->setBreadcrumbDetails(_kt('change user password'));
        $this->oPage->setTitle(_kt("Change User Password"));
                
        $user_id = KTUtil::arrayGet($_REQUEST, 'user_id');
        $oUser =& User::get($user_id);
        
        if (PEAR::isError($oUser) || $oUser == false) {
            $this->errorRedirectToMain(_kt('Please select a user first.'));
            exit(0);
        }
        
        $this->aBreadcrumbs[] = array('name' => $oUser->getName());
        
        $edit_fields = array();
        $edit_fields[] =  new KTPasswordWidget(_kt('Password'), _kt('Specify an initial password for the user.'), 'password', null, $this->oPage, true);        
        $edit_fields[] =  new KTPasswordWidget(_kt('Confirm Password'), _kt('Confirm the password specified above.'), 'confirm_password', null, $this->oPage, true);        
        
        $oTemplating =& KTTemplating::getSingleton();        
        $oTemplate = $oTemplating->loadTemplate("ktcore/principals/updatepassword");
        $aTemplateData = array(
            "context" => $this,
            "edit_fields" => $edit_fields,
            "edit_user" => $oUser,
        );
        return $oTemplate->render($aTemplateData);
    }
    
    function do_updatePassword() {
        $user_id = KTUtil::arrayGet($_REQUEST, 'user_id');
        
        $password = KTUtil::arrayGet($_REQUEST, 'password');
        $confirm_password = KTUtil::arrayGet($_REQUEST, 'confirm_password');        
        
   		$KTConfig =& KTConfig::getSingleton();
		$minLength = ((int) $KTConfig->get('user_prefs/passwordLength', 6));
		$restrictAdmin = ((bool) $KTConfig->get('user_prefs/restrictAdminPasswords', false));   
        
        if ($restrictAdmin && (strlen($password) < $minLength)) {
		    $this->errorRedirectToMain(sprintf(_kt("The password must be at least %d characters long."), $minLength));
		} else if (empty($password)) { 
            $this->errorRedirectToMain(_kt("You must specify a password for the user."));
        } else if ($password !== $confirm_password) {
            $this->errorRedirectToMain(_kt("The passwords you specified do not match."));
        } 
        // FIXME more validation would be useful.
        // validated and ready..
        $this->startTransaction();
        
        $oUser =& User::get($user_id);
        if (PEAR::isError($oUser) || $oUser == false) {
            $this->errorRedirectToMain(_kt("Please select a user to modify first."));
        }
        
        
        // FIXME this almost certainly has side-effects.  do we _really_ want 
        $oUser->setPassword(md5($password)); // 
        
        $res = $oUser->update(); 
        //$res = $oUser->doLimitedUpdate(); // ignores a fix blacklist of items.
        
        if (PEAR::isError($res) || ($res == false)) {
            $this->errorRedirectoToMain(_kt('Failed to update user.'));
        }
        
        $this->commitTransaction();
        $this->successRedirectToMain(_kt('User information updated.'));
        
    }

    function do_editUserSource() {
        $user_id = KTUtil::arrayGet($_REQUEST, 'user_id');
        $oUser =& $this->oValidator->validateUser($user_id);
        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _kt('User Management'));
        $this->aBreadcrumbs[] = array('name' => $oUser->getName());

        $oAuthenticationSource = KTAuthenticationSource::getForUser($oUser);
        if (is_null($oAuthenticationSource)) {
            $oProvider =& new KTBuiltinAuthenticationProvider;
        } else {
            $sProvider = $oAuthenticationSource->getAuthenticationProvider();
            $oRegistry =& KTAuthenticationProviderRegistry::getSingleton();
            $oProvider = $oRegistry->getAuthenticationProvider($sProvider);
        }

        $oProvider->subDispatch($this);
        exit();
    }
    
    function do_editgroups() {
        $user_id = KTUtil::arrayGet($_REQUEST, 'user_id');
        $oUser = User::get($user_id);
        if ((PEAR::isError($oUser)) || ($oUser === false)) {
            $this->errorRedirectToMain(_kt('No such user.'));
        }
        
        $this->aBreadcrumbs[] = array('name' => $oUser->getName());
        $this->oPage->setBreadcrumbDetails(_kt('edit groups'));
        $this->oPage->setTitle(sprintf(_kt("Edit %s's groups"), $oUser->getName()));
        // generate a list of groups this user is authorised to assign.
        
        /* FIXME there is a nasty side-effect:  if a user cannot assign a group
        * to a user, and that user _had_ that group pre-edit, 
        * then their privileges are revoked.
        * is there _any_ way to fix that?
        */
        
        // FIXME move this to a transfer widget
        // FIXME replace OptionTransfer.js.  me no-likey.
        
        // FIXME this is hideous.  refactor the transfer list stuff completely.
        $initJS = 'var optGroup = new OptionTransfer("groupSelect","chosenGroups"); ' .
        'function startTrans() { var f = getElement("usergroupform"); ' .
        ' optGroup.saveAddedRightOptions("groupAdded"); ' .
        ' optGroup.saveRemovedRightOptions("groupRemoved"); ' .
        ' optGroup.init(f); }; ' .
        ' addLoadEvent(startTrans); '; 
        $this->oPage->requireJSStandalone($initJS);
        
        $aInitialGroups = GroupUtil::listGroupsForUser($oUser);
        $aAllGroups = GroupUtil::listGroups();
        
        $aUserGroups = array();
        $aFreeGroups = array();
        foreach ($aInitialGroups as $oGroup) {
            $aUserGroups[$oGroup->getId()] = $oGroup;
        }
        foreach ($aAllGroups as $oGroup) {
            if (!array_key_exists($oGroup->getId(), $aUserGroups)) {
                $aFreeGroups[$oGroup->getId()] = $oGroup;
            }
        }
        
        $oTemplating =& KTTemplating::getSingleton();        
        $oTemplate = $oTemplating->loadTemplate("ktcore/principals/usergroups");
        $aTemplateData = array(
            "context" => $this,
            "unused_groups" => $aFreeGroups,
            "user_groups" => $aUserGroups,
            "edit_user" => $oUser,
        );
        return $oTemplate->render($aTemplateData);        
    }    
    
    function do_saveUser() {
        $user_id = KTUtil::arrayGet($_REQUEST, 'user_id');

        $aErrorOptions = array(
                'redirect_to' => array('editUser', sprintf('user_id=%d', $user_id))
        );
        
        $name = $this->oValidator->validateString(
                KTUtil::arrayGet($_REQUEST, 'name'),
                KTUtil::meldOptions($aErrorOptions, array('message' => _kt("You must provide a name")))
        );
        
        $username = $this->oValidator->validateString(
                KTUtil::arrayGet($_REQUEST, 'newusername'),
                KTUtil::meldOptions($aErrorOptions, array('message' => _kt("You must provide a username")))
        );
        
        $email_address = KTUtil::arrayGet($_REQUEST, 'email_address');
        if(strlen(trim($email_address))) {
                $email_address = $this->oValidator->validateEmailAddress($email_address, $aErrorOptions);
        }
        
        $email_notifications = KTUtil::arrayGet($_REQUEST, 'email_notifications', false);
        if ($email_notifications !== false) $email_notifications = true;
        
        $mobile_number = KTUtil::arrayGet($_REQUEST, 'mobile_number');
        
        $max_sessions = KTUtil::arrayGet($_REQUEST, 'max_sessions', '3', false);
        
        // FIXME more validation would be useful.
        // validated and ready..
        $this->startTransaction();
        
        $oUser =& User::get($user_id);
        if (PEAR::isError($oUser) || $oUser == false) {
            $this->errorRedirectToMain(_kt("Please select a user to modify first."));
        }
        
        $oUser->setName($name);
        $oUser->setUsername($username);  // ?
        $oUser->setEmail($email_address);
        $oUser->setEmailNotification($email_notifications);
        $oUser->setMobile($mobile_number);
        $oUser->setMaxSessions($max_sessions);
        
        // old system used the very evil store.php.
        // here we need to _force_ a limited update of the object, via a db statement.
        //
        $res = $oUser->update(); 
        // $res = $oUser->doLimitedUpdate(); // ignores a fix blacklist of items.
        
        
        
        if (PEAR::isError($res) || ($res == false)) {
            $this->errorRedirectoToMain(_kt('Failed to update user.'));
        }
        
        $this->commitTransaction();
        $this->successRedirectToMain(_kt('User information updated.'));
    }
    
    function do_createUser() {
        // FIXME generate and pass the error stack to adduser.
        
        $aErrorOptions = array(
                'redirect_to' => array('addUser')
        );
        
        $username = $this->oValidator->validateString(
                KTUtil::arrayGet($_REQUEST, 'newusername'),
                KTUtil::meldOptions($aErrorOptions, array('message' => _kt("You must specify a new username.")))
        );

        $name = $this->oValidator->validateString(
                KTUtil::arrayGet($_REQUEST, 'name'),
                KTUtil::meldOptions($aErrorOptions, array('message' => _kt("You must provide a name")))
        );

        
        $email_address = KTUtil::arrayGet($_REQUEST, 'email_address');
        $email_notifications = KTUtil::arrayGet($_REQUEST, 'email_notifications', false);
        if ($email_notifications !== false) $email_notifications = true;
        $mobile_number = KTUtil::arrayGet($_REQUEST, 'mobile_number');

        $max_sessions = $this->oValidator->validateInteger(
                KTUtil::arrayGet($_REQUEST, 'max_sessions'),
                KTUtil::meldOptions($aErrorOptions, array('message' => _kt("You must specify a numeric value for maximum sessions.")))
        );

        $password = KTUtil::arrayGet($_REQUEST, 'password');
        $confirm_password = KTUtil::arrayGet($_REQUEST, 'confirm_password');        
        
        $KTConfig =& KTConfig::getSingleton();
	$minLength = ((int) $KTConfig->get('user_prefs/passwordLength', 6));
	$restrictAdmin = ((bool) $KTConfig->get('user_prefs/restrictAdminPasswords', false));
        
        if ($restrictAdmin && (strlen($password) < $minLength)) {
	    $this->errorRedirectTo('addUser', sprintf(_kt("The password must be at least %d characters long."), $minLength));
	} else if (empty($password)) { 
            $this->errorRedirectTo('addUser', _kt("You must specify a password for the user."));
        } else if ($password !== $confirm_password) {
            $this->errorRedirectTo('addUser', _kt("The passwords you specified do not match."));
        }
        
        $dupUser =& User::getByUserName($username);
        if(!PEAR::isError($dupUser)) {
            $this->errorRedirectTo('addUser', _kt("A user with that username already exists"));
        }
        
        $oUser =& User::createFromArray(array(
            "sUsername" => $username,
            "sName" => $name,
            "sPassword" => md5($password),
            "iQuotaMax" => 0,
            "iQuotaCurrent" => 0,
            "sEmail" => $email_address,
            "bEmailNotification" => $email_notifications,
            "bSmsNotification" => false,   // FIXME do we auto-act if the user has a mobile?
            "iMaxSessions" => $max_sessions,
        ));
        
        if (PEAR::isError($oUser) || ($oUser == false)) {
            $this->errorRedirectToMain(_kt("failed to create user."));
            exit(0);
        }
        
        $oUser->create();
        
        $this->successRedirectToMain(_kt('Created new user') . ': "' . $oUser->getUsername() . '"', 'name=' . $oUser->getUsername());
    }
    
    function do_deleteUser() {
        $user_id = KTUtil::arrayGet($_REQUEST, 'user_id');
        $oUser = User::get($user_id);
        if ((PEAR::isError($oUser)) || ($oUser === false)) {
            $this->errorRedirectToMain(_kt('Please select a user first.'));
        }
        $oUser->delete();
        
        $this->successRedirectToMain(_kt('User deleted') . ': ' . $oUser->getName());
    }
    
    function do_updateGroups() {
        $user_id = KTUtil::arrayGet($_REQUEST, 'user_id');
        $oUser = User::get($user_id);
        if ((PEAR::isError($oUser)) || ($oUser === false)) {
            $this->errorRedirectToMain(_kt('Please select a user first.'));
        }
        $groupAdded = KTUtil::arrayGet($_REQUEST, 'groupAdded','');
        $groupRemoved = KTUtil::arrayGet($_REQUEST, 'groupRemoved','');
        
        
        $aGroupToAddIDs = explode(",", $groupAdded);
        $aGroupToRemoveIDs = explode(",", $groupRemoved);
        
        // FIXME we need to ensure that only groups which are allocatable by the admin are added here.
        
        // FIXME what groups are _allocatable_?
        
        $this->startTransaction();
        $groupsAdded = array();
        $groupsRemoved = array();
		
		$addWarnings = array();
		$removeWarnings = array();
        
        foreach ($aGroupToAddIDs as $iGroupID ) {
            if ($iGroupID > 0) {
                $oGroup = Group::get($iGroupID);
				$memberReason = GroupUtil::getMembershipReason($oUser, $oGroup);
				//var_dump($memberReason);
				if (!(PEAR::isError($memberReason) || is_null($memberReason))) {
					$addWarnings[] = $memberReason;
				}				
                $res = $oGroup->addMember($oUser);
                if (PEAR::isError($res) || $res == false) {
                    $this->errorRedirectToMain(sprintf(_kt('Unable to add user to group "%s"'), $oGroup->getName()));
                } else { 
				    $groupsAdded[] = $oGroup->getName(); 

				}
            }
        }
    
        // Remove groups
        foreach ($aGroupToRemoveIDs as $iGroupID ) {
            if ($iGroupID > 0) {
                $oGroup = Group::get($iGroupID);
                $res = $oGroup->removeMember($oUser);
                if (PEAR::isError($res) || $res == false) {
                    $this->errorRedirectToMain(sprintf(_kt('Unable to remove user from group "%s"'), $oGroup->getName()));
                } else { 
				   $groupsRemoved[] = $oGroup->getName(); 
					$memberReason = GroupUtil::getMembershipReason($oUser, $oGroup);
					//var_dump($memberReason);
					if (!(PEAR::isError($memberReason) || is_null($memberReason))) {
						$removeWarnings[] = $memberReason;
					}
				}
            }
        }
		
		if (!empty($addWarnings)) {
		    $sWarnStr = _kt('Warning:  the user was already a member of some subgroups') . ' &mdash; ';
			$sWarnStr .= implode(', ', $addWarnings);
			$_SESSION['KTInfoMessage'][] = $sWarnStr;
		}
		
		if (!empty($removeWarnings)) {
		    $sWarnStr = _kt('Warning:  the user is still a member of some subgroups') . ' &mdash; ';
			$sWarnStr .= implode(', ', $removeWarnings);
			$_SESSION['KTInfoMessage'][] = $sWarnStr;
		}
        
        $msg = '';
        if (!empty($groupsAdded)) { $msg .= ' ' . _kt('Added to groups') . ': ' . implode(', ', $groupsAdded) . ' <br />'; }
        if (!empty($groupsRemoved)) { $msg .= ' ' . _kt('Removed from groups') . ': ' . implode(', ',$groupsRemoved) . '.'; }

	if (!Permission::userIsSystemAdministrator($_SESSION['userID'])) {
	    $this->rollbackTransaction();
	    $this->errorRedirectTo('editgroups', _kt('For security purposes, you cannot remove your own administration priviledges.'), sprintf('user_id=%d', $oUser->getId()));
	    exit(0);
	}
	
        
        $this->commitTransaction();
        $this->successRedirectToMain($msg);
    }

	function getGroupStringForUser($oUser) {
		$aGroupNames = array();
		$aGroups = GroupUtil::listGroupsForUser($oUser);
		$MAX_GROUPS = 6;
		$add_elipsis = false;
		if (count($aGroups) == 0) { return _kt('User is currently not a member of any groups.'); }
		if (count($aGroups) > $MAX_GROUPS) { 
		    $aGroups = array_slice($aGroups, 0, $MAX_GROUPS); 
			$add_elipsis = true;
		}
		foreach ($aGroups as $oGroup) { 
		    $aGroupNames[] = $oGroup->getName();
		}
		if ($add_elipsis) {
		    $aGroupNames[] = '&hellip;';
		}
		
		return implode(', ', $aGroupNames);
	}

}

?>
