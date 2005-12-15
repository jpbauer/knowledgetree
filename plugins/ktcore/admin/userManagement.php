<?php

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
    function do_main() {
        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _('User Management'));
        $this->oPage->setBreadcrumbDetails(_('select a user'));
        $this->oPage->setTitle(_("User Management"));
        
        $name = KTUtil::arrayGet($_REQUEST, 'name');
        $show_all = KTUtil::arrayGet($_REQUEST, 'show_all', false);
        $user_id = KTUtil::arrayGet($_REQUEST, 'user_id');
    
        
        $search_fields = array();
        $search_fields[] =  new KTStringWidget(_('Username'),_("Enter part of the person's username.  e.g. <strong>ra</strong> will match <strong>brad</strong>."), 'name', $name, $this->oPage, true);
        
        // FIXME handle group search stuff.
        $search_results = null;
        if (!empty($name)) {
            $search_results =& User::getList('WHERE username LIKE "%' . DBUtil::escapeSimple($name) . '%"');
        } else if ($show_all !== false) {
            $search_results =& User::getList();
        }
        
        $oTemplating = new KTTemplating;        
        $oTemplate = $oTemplating->loadTemplate("ktcore/principals/useradmin");
        $aTemplateData = array(
            "context" => $this,
            "search_fields" => $search_fields,
            "search_results" => $search_results,
        );
        return $oTemplate->render($aTemplateData);
    }


    function do_addUser() {
        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _('User Management'));
        $this->oPage->setBreadcrumbDetails(_('add a new user'));
        $this->oPage->setTitle(_("Modify User Details"));
        
        $name = KTUtil::arrayGet($_REQUEST, 'name');
        $show_all = KTUtil::arrayGet($_REQUEST, 'show_all', false);
        $add_user = KTUtil::arrayGet($_REQUEST, 'add_user', false);
        if ($add_user !== false) { $add_user = true; }
        $edit_user = KTUtil::arrayGet($_REQUEST, 'edit_user', false);
    
        
        $add_fields = array();
        $add_fields[] =  new KTStringWidget(_('Username'),_('The username the user will enter to gain access to the KnowledgeTree.  e.g. <strong>jsmith</strong>'), 'username', null, $this->oPage, true);
        $add_fields[] =  new KTStringWidget(_('Name'),_('The full name of the user.  This is shown in reports and listings.  e.g. <strong>John Smith</strong>'), 'name', null, $this->oPage, true);        
        $add_fields[] =  new KTStringWidget(_('Email Address'), _('The email address of the user.  Notifications and alerts are mailed to this address if <strong>email notifications</strong> is set below. e.g. <strong>jsmith@acme.com</strong>'), 'email_address', null, $this->oPage, false);        
        $add_fields[] =  new KTCheckboxWidget(_('Email Notifications'), _("If this is specified then the user will have notifications sent to the email address entered above.  If it isn't set, then the user will only see notifications on the <strong>Dashboard</strong>"), 'email_notifications', true, $this->oPage, false);        
        $add_fields[] =  new KTPasswordWidget(_('Password'), _('Specify an initial password for the user.'), 'password', null, $this->oPage, true);        
        $add_fields[] =  new KTPasswordWidget(_('Confirm Password'), _('Confirm the password specified above.'), 'confirm_password', null, $this->oPage, true);        
        // nice, easy bits.
        $add_fields[] =  new KTStringWidget(_('Mobile Number'), _("The mobile phone number of the user.  If the system is configured to send notifications to cellphones, then this number will be SMS'd with notifications.  e.g. <strong>999 9999 999</strong>"), 'mobile_number', null, $this->oPage, false);        
        $add_fields[] =  new KTStringWidget(_('Maximum Sessions'), _('As a safety precaution, it is useful to limit the number of times a given account can log in, before logging out.  This prevents a single account being used by many different people.'), 'max_sessions', '3', $this->oPage, true);        

        $aAuthenticationSources =& KTAuthenticationSource::getList();
        
        $oTemplating = new KTTemplating;
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

        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _('User Management'));
        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'] . '?action=addUser', 'name' => _('add a new user'));
        $oProvider->aBreadcrumbs = $this->aBreadcrumbs;
        $oProvider->oPage->setBreadcrumbDetails($oSource->getName());
        $oProvider->oPage->setTitle(_("Modify User Details"));

        $oProvider->dispatch();
        exit(0);
    }

    function do_editUser() {
        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _('User Management'));
        $this->oPage->setBreadcrumbDetails(_('modify user details'));
        $this->oPage->setTitle(_("Modify User Details"));
        
        $user_id = KTUtil::arrayGet($_REQUEST, 'user_id');
        $oUser =& User::get($user_id);
        
        if (PEAR::isError($oUser) || $oUser == false) {
            $this->errorRedirectToMain(_('Please select a user first.'));
            exit(0);
        }
        
        $this->aBreadcrumbs[] = array('name' => $oUser->getName());
        
        $edit_fields = array();
        $edit_fields[] =  new KTStringWidget(_('Username'),_('The username the user will enter to gain access to the KnowledgeTree.  e.g. <strong>jsmith</strong>'), 'username', $oUser->getUsername(), $this->oPage, true);
        $edit_fields[] =  new KTStringWidget(_('Name'), _('The full name of the user.  This is shown in reports and listings.  e.g. <strong>John Smith</strong>'), 'name', $oUser->getName(), $this->oPage, true);        
        $edit_fields[] =  new KTStringWidget(_('Email Address'),_('The email address of the user.  Notifications and alerts are mailed to this address if <strong>email notifications</strong> is set below. e.g. <strong>jsmith@acme.com</strong>'), 'email_address', $oUser->getEmail(), $this->oPage, false);        
        $edit_fields[] =  new KTCheckboxWidget(_('Email Notifications'), _('If this is specified then the user will have notifications sent to the email address entered above.  If it is not set, then the user will only see notifications on the <strong>Dashboard</strong>'), 'email_notifications', $oUser->getEmailNotification(), $this->oPage, false);        
        $edit_fields[] =  new KTStringWidget(_('Mobile Number'),_("The mobile phone number of the user.  If the system is configured to send notifications to cellphones, then this number will be SMS'd with notifications.  e.g. <strong>999 9999 999</strong>"), 'mobile_number', $oUser->getMobile(), $this->oPage, false);        
        $edit_fields[] =  new KTStringWidget(_('Maximum Sessions'), _('As a safety precaution, it is useful to limit the number of times a given account can log in, before logging out.  This prevents a single account being used by many different people.'), 'max_sessions', $oUser->getMaxSessions(), $this->oPage, true);        
        
        $oAuthenticationSource = KTAuthenticationSource::getForUser($oUser);
        if (is_null($oAuthenticationSource)) {
            $oProvider =& new KTBuiltinAuthenticationProvider;
        } else {
            $sProvider = $oAuthenticationSource->getAuthenticationProvider();
            $oRegistry =& KTAuthenticationProviderRegistry::getSingleton();
            $oProvider = $oRegistry->getAuthenticationProvider($sProvider);
        }
        
        $oTemplating = new KTTemplating;        
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
        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _('User Management'));
        $this->oPage->setBreadcrumbDetails(_('change user password'));
        $this->oPage->setTitle(_("Change User Password"));
        
        $user_id = KTUtil::arrayGet($_REQUEST, 'user_id');
        $oUser =& User::get($user_id);
        
        if (PEAR::isError($oUser) || $oUser == false) {
            $this->errorRedirectToMain(_('Please select a user first.'));
            exit(0);
        }
        
        $this->aBreadcrumbs[] = array('name' => $oUser->getName());
        
        $edit_fields = array();
        $edit_fields[] =  new KTPasswordWidget(_('Password'),_('Specify an initial password for the user.'), 'password', null, $this->oPage, true);        
        $edit_fields[] =  new KTPasswordWidget(_('Confirm Password'),_('Confirm the password specified above.'), 'confirm_password', null, $this->oPage, true);        
        
        $oTemplating = new KTTemplating;        
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
        
        if (empty($password)) { 
            $this->errorRedirectToMain(_("You must specify a password for the user."));
        } else if ($password !== $confirm_password) {
            $this->errorRedirectToMain(_("The passwords you specified do not match."));
        }
        // FIXME more validation would be useful.
        // validated and ready..
        $this->startTransaction();
        
        $oUser =& User::get($user_id);
        if (PEAR::isError($oUser) || $oUser == false) {
            $this->errorRedirectToMain(_("Please select a user to modify first."));
        }
        
        
        // FIXME this almost certainly has side-effects.  do we _really_ want 
        $oUser->setPassword(md5($password)); // 
        
        $res = $oUser->update(); 
        //$res = $oUser->doLimitedUpdate(); // ignores a fix blacklist of items.
        
        
        if (PEAR::isError($res) || ($res == false)) {
            $this->errorRedirectoToMain(_('Failed to update user.'));
        }
        
        $this->commitTransaction();
        $this->successRedirectToMain(_('User information updated.'));
        
    }

    function do_editUserSource() {
        $user_id = KTUtil::arrayGet($_REQUEST, 'user_id');
        $oUser =& $this->oValidator->validateUser($user_id);
        $this->aBreadcrumbs[] = array('name' => $oUser->getName());

        $oAuthenticationSource = KTAuthenticationSource::getForUser($oUser);
        if (is_null($oAuthenticationSource)) {
            $oProvider =& new KTBuiltinAuthenticationProvider;
        } else {
            $sProvider = $oAuthenticationSource->getAuthenticationProvider();
            $oRegistry =& KTAuthenticationProviderRegistry::getSingleton();
            $oProvider = $oRegistry->getAuthenticationProvider($sProvider);
        }

        $oProvider->dispatch();
        exit();
    }
    
    function do_editgroups() {
        $user_id = KTUtil::arrayGet($_REQUEST, 'user_id');
        $oUser = User::get($user_id);
        if ((PEAR::isError($oUser)) || ($oUser === false)) {
            $this->errorRedirectToMain(_('No such user.'));
        }
        
        $this->aBreadcrumbs[] = array('name' => $oUser->getName());
        $this->oPage->setBreadcrumbDetails(_('edit groups'));
        $this->oPage->setTitle(sprintf(_("Edit %s's groups"), $oUser->getName()));
        // generate a list of groups this user is authorised to assign.
        
        /* FIXME there is a nasty side-effect:  if a user cannot assign a group
        * to a user, and that user _had_ that group pre-edit, 
        * then their privilidges are revoked.
        * is there _any_ way to fix that?
        */
        
        // FIXME move this to a transfer widget
        // FIXME replace OptionTransfer.js.  me no-likey.
        
        // FIXME this is hideous.  refactor the transfer list stuff completely.
        $initJS = 'var optGroup = new OptionTransfer("groupSelect[]","chosenGroups[]"); ' .
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
        
        $oTemplating = new KTTemplating;        
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
        
        $name = KTUtil::arrayGet($_REQUEST, 'name');
        $username = KTUtil::arrayGet($_REQUEST, 'username');
        $email_address = KTUtil::arrayGet($_REQUEST, 'email_address');
        $email_notifications = KTUtil::arrayGet($_REQUEST, 'email_notifications', false);
        if ($email_notifications !== false) $email_notifications = true;
        $mobile_number = KTUtil::arrayGet($_REQUEST, 'mobile_number');
        $max_sessions = KTUtil::arrayGet($_REQUEST, 'max_sessions', '3');
        // FIXME more validation would be useful.
        // validated and ready..
        $this->startTransaction();
        
        $oUser =& User::get($user_id);
        if (PEAR::isError($oUser) || $oUser == false) {
            $this->errorRedirectToMain(_("Please select a user to modify first."));
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
        // $res = $oUser->update(); 
        $res = $oUser->doLimitedUpdate(); // ignores a fix blacklist of items.
        
        
        
        if (PEAR::isError($res) || ($res == false)) {
            $this->errorRedirectoToMain(_('Failed to update user.'));
        }
        
        $this->commitTransaction();
        $this->successRedirectToMain(_('User information updated.'));
    }
    
    function do_createUser() {
        // FIXME generate and pass the error stack to adduser.
        
        $name = KTUtil::arrayGet($_REQUEST, 'name');
        if (empty($name)) { $this->errorRedirectToMain(_('You must specify a name for the user.')); }
        $username = KTUtil::arrayGet($_REQUEST, 'username');
        if (empty($name)) { $this->errorRedirectToMain(_('You must specify a new username.')); }
        // FIXME check for non-clashing usernames.
        
        $email_address = KTUtil::arrayGet($_REQUEST, 'email_address');
        $email_notifications = KTUtil::arrayGet($_REQUEST, 'email_notifications', false);
        if ($email_notifications !== false) $email_notifications = true;
        $mobile_number = KTUtil::arrayGet($_REQUEST, 'mobile_number');
        $max_sessions = KTUtil::arrayGet($_REQUEST, 'max_sessions', '3');
        // FIXME check for numeric max_sessions... db-error else?
        $password = KTUtil::arrayGet($_REQUEST, 'password');
        $confirm_password = KTUtil::arrayGet($_REQUEST, 'confirm_password');        
        
        if (empty($password)) { 
            $this->errorRedirectToMain(_("You must specify a password for the user."));
        } else if ($password !== $confirm_password) {
            $this->errorRedirectToMain(_("The passwords you specified do not match."));
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
            $this->errorRedirectToMain(_("failed to create user."));
            exit(0);
        }
        
        $oUser->create();
        
        $this->successRedirectToMain(_('Created new user') . ': "' . $oUser->getUsername() . '"', 'name=' . $oUser->getUsername());
    }
    
    function do_deleteUser() {
        $user_id = KTUtil::arrayGet($_REQUEST, 'user_id');
        $oUser = User::get($user_id);
        if ((PEAR::isError($oUser)) || ($oUser === false)) {
            $this->errorRedirectToMain(_('Please select a user first.'));
        }
        $oUser->delete();
        
        $this->successRedirectToMain(_('User deleted') . ': ' . $oUser->getName());
    }
    
    function do_updateGroups() {
        $user_id = KTUtil::arrayGet($_REQUEST, 'user_id');
        $oUser = User::get($user_id);
        if ((PEAR::isError($oUser)) || ($oUser === false)) {
            $this->errorRedirectToMain(_('Please select a user first.'));
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
        
        foreach ($aGroupToAddIDs as $iGroupID ) {
            if ($iGroupID > 0) {
                $oGroup = Group::get($iGroupID);
                $res = $oGroup->addMember($oUser);
                if (PEAR::isError($res) || $res == false) {
                    $this->errorRedirectToMain(sprintf(_('Unable to add user to group "%s"'), $oGroup->getName()));
                } else { $groupsAdded[] = $oGroup->getName(); }
            }
        }
    
        // Remove groups
        foreach ($aGroupToRemoveIDs as $iGroupID ) {
            if ($iGroupID > 0) {
                $oGroup = Group::get($iGroupID);
                $res = $oGroup->removeMember($oUser);
                if (PEAR::isError($res) || $res == false) {
                    $this->errorRedirectToMain(sprintf(_('Unable to remove user from group "%s"'), $oGroup->getName()));
                } else { $groupsRemoved[] = $oGroup->getName(); }
            }
        }        
        
        $msg = '';
        if (!empty($groupsAdded)) { $msg .= ' ' . _('Added to groups') . ': ' . join(', ', $groupsAdded) . ', <br />'; }
        if (!empty($groupsRemoved)) { $msg .= ' ' . _('Removed from groups') . ': ' . join(', ',$groupsRemoved) . '.'; }
        
        $this->commitTransaction();
        $this->successRedirectToMain($msg);
    }

}

?>
