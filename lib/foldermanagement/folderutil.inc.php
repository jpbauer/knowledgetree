<?php /* vim: set expandtab softtabstop=4 shiftwidth=4 foldmethod=marker: */
/**
 * $Id$
 *
 * High-level folder operations
 *
 * Copyright (c) 2005 Jam Warehouse http://www.jamwarehouse.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
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
 * @version $Revision$
 * @author Neil Blakey-Milner, Jam Warehouse (Pty) Ltd, South Africa
 */

require_once(KT_LIB_DIR . '/storage/storagemanager.inc.php');
require_once(KT_LIB_DIR . "/subscriptions/subscriptions.inc.php"); 

require_once(KT_LIB_DIR . '/permissions/permission.inc.php');
require_once(KT_LIB_DIR . '/permissions/permissionutil.inc.php');
require_once(KT_LIB_DIR . '/users/User.inc');

require_once(KT_LIB_DIR . '/database/dbutil.inc');

class KTFolderUtil {
    function _add ($oParentFolder, $sFolderName, $oUser) {
        $oStorage =& KTStorageManagerUtil::getSingleton();
        $oFolder =& Folder::createFromArray(array(
            'name' => $sFolderName,
            'description' => $sFolderName,
            'parentid' => $oParentFolder->getID(),
            'creatorid' => $oUser->getID(),
        ));
        if (PEAR::isError($oFolder)) {
            return $oFolder;
        }
        $res = $oStorage->createFolder($oFolder);
        if (PEAR::isError($res)) {
            $oFolder->delete();
            return $res;
        }
        return $oFolder;
    }

    function add ($oParentFolder, $sFolderName, $oUser) {
        $oFolder = KTFolderUtil::_add($oParentFolder, $sFolderName, $oUser);
        if (PEAR::isError($oFolder)) {
            return $oFolder;
        }

        // fire subscription alerts for the new folder
        $oSubscriptionEvent = new SubscriptionEvent();
        $oSubscriptionEvent->AddFolder($oFolder, $oParentFolder);
        return $oFolder;
    }

    function move($oFolder, $oNewParentFolder, $oUser) {
        if (KTFolderUtil::exists($oNewParentFolder, $oFolder->getName())) {
            return PEAR::raiseError("Folder with the same name already exists in the new parent folder");
        }
        $oStorage =& KTStorageManagerUtil::getSingleton();

        // First, deal with SQL, as it, at least, is guaranteed to be atomic
        $table = "folders";
        
        // Update the moved folder first...
        $sQuery = "UPDATE $table SET full_path = ?, parent_folder_ids = ?, parent_id = ? WHERE id = ?";
        $aParams = array(
            sprintf("%s/%s", $oNewParentFolder->getFullPath(), $oNewParentFolder->getName()),
            sprintf("%s,%s", $oNewParentFolder->getParentFolderIDs(), $oNewParentFolder->getID()),
            $oNewParentFolder->getID(),
            $oFolder->getID(),
        );
        $res = DBUtil::runQuery(array($sQuery, $aParams));
        if (PEAR::isError($res)) {
            return $res;
        }
        
        $sQuery = "UPDATE $table SET full_path = CONCAT(?, SUBSTRING(full_path FROM ?)), parent_folder_ids = CONCAT(?, SUBSTRING(parent_folder_ids FROM ?)) WHERE full_path LIKE ?";
        $aParams = array(
            sprintf("%s/%s", $oNewParentFolder->getFullPath(), $oNewParentFolder->getName()),
            strlen($oFolder->getFullPath()) + 1,
            sprintf("%s,%s", $oNewParentFolder->getParentFolderIDs(), $oNewParentFolder->getID()),
            strlen($oFolder->getParentFolderIDs()) + 1,
            sprintf("%s/%s%%", $oFolder->getFullPath(), $oFolder->getName()),
        );
        $res = DBUtil::runQuery(array($sQuery, $aParams));
        if (PEAR::isError($res)) {
            return $res;
        }

        $table = "documents";
        $sQuery = "UPDATE $table SET full_path = CONCAT(?, SUBSTRING(full_path FROM ?)) WHERE full_path LIKE ?";
        $aParams = array(
            sprintf("%s/%s", $oNewParentFolder->getFullPath(), $oNewParentFolder->getName()),
            strlen($oFolder->getFullPath()) + 1,
            sprintf("%s/%s%%", $oFolder->getFullPath(), $oFolder->getName()),
        );
        $res = DBUtil::runQuery(array($sQuery, $aParams));
        if (PEAR::isError($res)) {
            return $res;
        }

        $res = $oStorage->moveFolder($oFolder, $oNewParentFolder);
        if (PEAR::isError($res)) {
            return $res;
        }
        return;
    }
    
    function rename($oFolder, $sNewName, $oUser) {
        $oStorage =& KTStorageManagerUtil::getSingleton();
        
        // First, deal with SQL, as it, at least, is guaranteed to be atomic
        $table = "folders";
        
        $sQuery = "UPDATE $table SET full_path = CONCAT(?, SUBSTRING(full_path FROM ?)) WHERE full_path LIKE ?";
        $aParams = array(
            sprintf("%s/%s", $oFolder->getFullPath(), $sNewName),
            strlen($oFolder->getFullPath() . '/' . $oFolder->getName()) + 1,
            sprintf("%s/%s%%", $oFolder->getFullPath(), $oFolder->getName()),
        );
        $res = DBUtil::runQuery(array($sQuery, $aParams));
        if (PEAR::isError($res)) {
            return $res;
        }

        $table = "documents";
        $sQuery = "UPDATE $table SET full_path = CONCAT(?, SUBSTRING(full_path FROM ?)) WHERE full_path LIKE ?";
        $aParams = array(
            sprintf("%s/%s", $oFolder->getFullPath(), $sNewName),
            strlen($oFolder->getFullPath() . '/' . $oFolder->getName()) + 1,
            sprintf("%s/%s%%", $oFolder->getFullPath(), $oFolder->getName()),
        );
        $res = DBUtil::runQuery(array($sQuery, $aParams));
        if (PEAR::isError($res)) {
            return $res;
        }

        $res = $oStorage->renameFolder($oFolder, $sNewName);
        if (PEAR::isError($res)) {
            return $res;
        }
        
        $oFolder->setName($sNewName);
        $res = $oFolder->update();

        return $res;
    }

    function exists($oParentFolder, $sName) {
        return Folder::folderExistsName($sName, $oParentFolder->getID());
    }
    
    /* folderUtil::delete
     *
     * this function is _much_ more complex than it might seem.
     * we need to:
     *   - recursively identify children
     *   - validate that permissions are allocated correctly.
     *   - step-by-step delete.
     */
    
    function delete($oStartFolder, $oUser, $sReason, $aOptions = null) {
        // FIXME: we need to work out if "write" is the right perm.
        $oPerm = KTPermission::getByName('ktcore.permissions.write');

        $bIgnorePermissions = KTUtil::arrayGet($aOptions, 'ignore_permissions');
        
        $aFolderIds = array(); // of oFolder
        $aDocuments = array(); // of oDocument
        $aFailedDocuments = array(); // of String
        $aFailedFolders = array(); // of String
        
        $aRemainingFolders = array($oStartFolder->getId());
        
        DBUtil::startTransaction();
        
        while (!empty($aRemainingFolders)) {
            $iFolderId = array_pop($aRemainingFolders);
            $oFolder = Folder::get($iFolderId);
            if (PEAR::isError($oFolder) || ($oFolder == false)) {
                DBUtil::rollback();
                return PEAR::raiseError(sprintf('Failure resolving child folder with id = %d.', $iFolderId));
            }
            
            // don't just stop ... plough on.
            if ($bIgnorePermissions || KTPermissionUtil::userHasPermissionOnItem($oUser, $oPerm, $oFolder)) {
                $aFolderIds[] = $iFolderId;
            } else {
                $aFailedFolders[] = $oFolder->getName();
            }
            
            // child documents
            $aChildDocs = Document::getList(array('folder_id = ?',array($iFolderId)));
            foreach ($aChildDocs as $oDoc) {
                if ($bIgnorePermissions || KTPermissionUtil::userHasPermissionOnItem($oUser, $oPerm, $oFolder)) {
                    $aDocuments[] = $oDoc;
                } else {
                    $aFailedDocuments[] = $oDoc->getName();
                }
            }
            
            // child folders.
            $aCFIds = Folder::getList(array('parent_id = ?', array($iFolderId)), array('ids' => true));
            $aRemainingFolders = array_merge($aRemainingFolders, $aCFIds);
        }
        
        // FIXME we could subdivide this to provide a per-item display (viz. bulk upload, etc.)
        
        if ((!empty($aFailedDocuments) || (!empty($aFailedFolders)))) {
            $sFD = '';
            $sFF = '';
            if (!empty($aFailedDocuments)) {
                $sFD = _('Documents: ') . array_join(', ', $aFailedDocuments) . '. ';
            }
            if (!empty($aFailedFolders)) {
                $sFF = _('Folders: ') . array_join(', ', $aFailedFolders) . '.';
            }
            return PEAR::raiseError(_('You do not have permission to delete these items. ') . $sFD . $sFF);
        }
        
        // now we can go ahead.
        foreach ($aDocuments as $oDocument) {
            $res = KTDocumentUtil::delete($oDocument, $sReason);
            if (PEAR::isError($res)) {
                DBUtil::rollback();
                return PEAR::raiseError(_('Delete Aborted. Unexpected failure to delete document: ') . $oDocument->getName() . $res->getMessage());
            }
        }

        $oStorage =& KTStorageManagerUtil::getSingleton();
        $oStorage->removeFolderTree($oStartFolder);

        // documents all cleared.
        $sQuery = 'DELETE FROM ' . KTUtil::getTableName('folders') . ' WHERE id IN (' . DBUtil::paramArray($aFolderIds) . ')';
        $aParams = $aFolderIds;
        
        $res = DBUtil::runQuery(array($sQuery, $aParams));

        if (PEAR::isError($res)) {
            DBUtil::rollback();
            return PEAR::raiseError(_('Failure deleting folders.'));
        }
        
        // and store
        DBUtil::commit();
        
        return true;
    }
    
    function copy($oFolder, $oDestFolder, $oUser, $sReason) {
        //
        // FIXME the failure cleanup code here needs some serious work.
        //
        $oPerm = KTPermission::getByName('ktcore.permissions.read');
        $oBaseFolderPerm = KTPermission::getByName('ktcore.permissions.addFolder');
        
        if (!KTPermissionUtil::userHasPermissionOnItem($oUser, $oPerm, $oDestFolder)) {
            return PEAR::raiseError(_('You are not allowed to create folders in the destination.'));
        }
        
        $aFolderIds = array(); // of oFolder
        $aDocuments = array(); // of oDocument
        $aFailedDocuments = array(); // of String
        $aFailedFolders = array(); // of String
        
        $aRemainingFolders = array($oFolder->getId());
        
        DBUtil::startTransaction();
        
        while (!empty($aRemainingFolders)) {
            $iFolderId = array_pop($aRemainingFolders);
            $oFolder = Folder::get($iFolderId);
            if (PEAR::isError($oFolder) || ($oFolder == false)) {
                DBUtil::rollback();
                return PEAR::raiseError(sprintf('Failure resolving child folder with id = %d.', $iFolderId));
            }
            
            // don't just stop ... plough on.
            if (KTPermissionUtil::userHasPermissionOnItem($oUser, $oPerm, $oFolder)) {
                $aFolderIds[] = $iFolderId;
            } else {
                $aFailedFolders[] = $oFolder->getName();
            }
            
            // child documents
            $aChildDocs = Document::getList(array('folder_id = ?',array($iFolderId)));
            foreach ($aChildDocs as $oDoc) {
                if (KTPermissionUtil::userHasPermissionOnItem($oUser, $oPerm, $oFolder)) {
                    $aDocuments[] = $oDoc;
                } else {
                    $aFailedDocuments[] = $oDoc->getName();
                }
            }
            
            // child folders.
            $aCFIds = Folder::getList(array('parent_id = ?', array($iFolderId)), array('ids' => true));
            $aRemainingFolders = array_merge($aRemainingFolders, $aCFIds);
        }
              
        if ((!empty($aFailedDocuments) || (!empty($aFailedFolders)))) {
            $sFD = '';
            $sFF = '';
            if (!empty($aFailedDocuments)) {
                $sFD = _('Documents: ') . array_join(', ', $aFailedDocuments) . '. ';
            }
            if (!empty($aFailedFolders)) {
                $sFF = _('Folders: ') . array_join(', ', $aFailedFolders) . '.';
            }
            return PEAR::raiseError(_('You do not have permission to copy these items. ') . $sFD . $sFF);
        }
        
        // first we walk the tree, creating in the new location as we go.
        // essentially this is an "ok" pass.
        
        $aFolderMap = array();
        
        $sTable = KTUtil::getTableName('folders');
        $sGetQuery = 'SELECT * FROM ' . $sTable . ' WHERE id = ? ';
        $aParams = array($oFolder->getId());
        $aRow = DBUtil::getOneResult(array($sGetQuery, $aParams));
        unset($aRow['id']);
        $aRow['parent_id'] = $oDestFolder->getId();
        $id = DBUtil::autoInsert($sTable, $aRow);
        if (PEAR::isError($id)) {
            DBUtil::rollback();
            return $id;
        }
        $aFolderMap[$oFolder->getId()] = $id;
        
        $aRemainingFolders = Folder::getList(array('parent_id = ?', array($oFolder->getId())), array('ids' => true));
        
        
        while (!empty($aRemainingFolders)) {
            $iFolderId = array_pop($aRemainingFolders);
            
            $aParams = array($iFolderId);
            $aRow = DBUtil::getOneResult(array($sGetQuery, $aParams));
            unset($aRow['id']);
            
            // since we are nested, we will have solved the parent first.
            $aRow['parent_id'] = $aFolderMap[$aRow['parent_id']]; 
            
            $id = DBUtil::autoInsert($sTable, $aRow);
            if (PEAR::isError($id)) {
                DBUtil::rollback();
                return $id;
            }
            $aFolderMap[$iFolderId] = $id;
            
            $aCFIds = Folder::getList(array('parent_id = ?', array($iFolderId)), array('ids' => true));
            $aRemainingFolders = array_merge($aRemainingFolders, $aCFIds);
        }
        
        // now we can go ahead.
        foreach ($aDocuments as $oDocument) {
            $oChildDestinationFolder = Folder::get($aFolderMap[$oDocument->getFolderID()]);
            $res = KTDocumentUtil::copy($oDocument, $oChildDestinationFolder);
            if (PEAR::isError($res)) {
                DBUtil::rollback();
                return PEAR::raiseError(_('Delete Aborted. Unexpected failure to delete document: ') . $oDocument->getName() . $res->getMessage());
            }
        }

        $oStorage =& KTStorageManagerUtil::getSingleton();
        $oStorage->removeFolderTree($oStartFolder);

        // documents all cleared.
        $sQuery = 'DELETE FROM ' . KTUtil::getTableName('folders') . ' WHERE id IN (' . DBUtil::paramArray($aFolderIds) . ')';
        $aParams = $aFolderIds;
        
        $res = DBUtil::runQuery(array($sQuery, $aParams));

        if (PEAR::isError($res)) {
            DBUtil::rollback();
            return PEAR::raiseError(_('Failure deleting folders.'));
        }
        
        // and store
        DBUtil::commit();
        
        return true;    
    }
}

?>
