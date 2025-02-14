<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
include_once 'config.inc.php';
require_once 'include/logging.php';
require_once 'include/database/PearDatabase.php';

/** This class is used to track the recently viewed items on a per user basis.
 * It is intended to be called by each module when rendering the detail form.
 */
class Tracker {
	public $db;
	public $table_name = 'vtiger_tracker';
	public $history_max_viewed = 10;

	// Tracker table
	public $column_fields = array(
		'id',
		'user_id',
		'module_name',
		'item_id',
		'item_summary'
	);

	public function __construct() {
		global $adb;
		$this->db = $adb;
		$this->history_max_viewed = GlobalVariable::getVariable('Application_TrackerMaxHistory', 10);
	}

	/**
	 * Add this new item to the tracker table. If there are to many items then remove the oldest item.
	 * If there is more than one extra item, log an error.
	 * If the new item is the same as the most recent item then do not change the list
	 */
	public function track_view($user_id, $current_module, $item_id, $item_summary) {
		global $adb, $default_charset;
		$this->delete_history($user_id, $item_id);
		// change the query so that it puts the tracker entry whenever you touch on the DetailView of the required entity
		// get the first name and last name from the respective modules
		if ($current_module != '') {
			$result = $adb->pquery('select fieldname,tablename,entityidfield from vtiger_entityname where modulename=?', array($current_module));
			$fieldsname = $adb->query_result($result, 0, 'fieldname');
			$tablename = $adb->query_result($result, 0, 'tablename');
			$entityidfield = $adb->query_result($result, 0, 'entityidfield');
			if (strpos($fieldsname, ',')) {
				// concatenate multiple fields with an whitespace between them
				$fieldlists = explode(',', $fieldsname);
				$fl = array();
				foreach ($fieldlists as $c) {
					if (count($fl)) {
						$fl[] = "' '";
					}
					$fl[] = $c;
				}
				$fieldsname = $adb->sql_concat($fl);
			}
			$query1 = "select $fieldsname as entityname from $tablename where $entityidfield = ?";
			$result = $adb->pquery($query1, array($item_id));
			$item_summary = html_entity_decode($adb->query_result($result, 0, 'entityname'), ENT_QUOTES, $default_charset);
			$item_summary = textlength_check($item_summary);
		}
		//if condition added to skip faq in last viewed history
		$query = "INSERT into $this->table_name (user_id, module_name, item_id, item_summary) values (?,?,?,?)";
		$qparams = array($user_id, $current_module, $item_id, $item_summary);
		$this->db->pquery($query, $qparams, true);
		$this->prune_history($user_id);
	}

	/**
	 * @param integer id of the user to retrieve the history for
	 * @param string filter the history to only return records from the specified module. If not specified all records are returned
	 * @return array of result set rows from the query. All of the table fields are included
	 */
	public function get_recently_viewed($user_id, $module_name = '') {
		$list = array();
		if (empty($user_id)) {
			return $list;
		}
		global $current_user;

		$crmTable = 'vtiger_crmentity';
		if ($module_name != '') {
			$mod = CRMEntity::getInstance($module_name);
			$crmTable = $mod->crmentityTable;
		}
		$query = "SELECT *
			from {$this->table_name}
			inner join {$crmTable} as vtiger_crmentity on vtiger_crmentity.crmid=vtiger_tracker.item_id WHERE user_id=? and vtiger_crmentity.deleted=0 ORDER BY id DESC";
		$result = $this->db->pquery($query, array($user_id), true);
		while ($row = $this->db->fetchByAssoc($result, -1, false)) {
			// If the module was not specified or the module matches the module of the row, add the row to the list
			if ($module_name == '' || $row['module_name'] == $module_name) {
				//Adding Security check
				require_once 'include/utils/utils.php';
				require_once 'include/utils/UserInfoUtil.php';
				$entity_id = $row['item_id'];
				$module = $row['module_name'];
				if ($module == 'Users' && is_admin($current_user)) {
					$per = 'yes';
				} else {
					$per = isPermitted($module, 'DetailView', $entity_id);
				}
				if ($per == 'yes') {
					$curMod = CRMEntity::getInstance($module);
					$row['__ICONLibrary'] = $curMod->moduleIcon['library'];
					$row['__ICONContainerClass'] = $curMod->moduleIcon['containerClass'];
					$row['__ICONClass'] = $curMod->moduleIcon['class'];
					$row['__ICONName'] = $curMod->moduleIcon['icon'];
					$list[] = $row;
				}
			}
		}
		return $list;
	}

	/**
	 * This method cleans out any entry for a record for a user.
	 * It is used to remove old occurances of previously viewed items.
	 */
	private function delete_history($user_id, $item_id) {
		$this->db->pquery("DELETE from $this->table_name WHERE user_id=? and item_id=?", array($user_id, $item_id), true);
	}

	/**
	 * This method cleans out any entry for a record.
	 */
	public function delete_item_history($item_id) {
		$this->db->pquery("DELETE from $this->table_name WHERE item_id=?", array($item_id), true);
	}

	/**
	 * This function will clean out old history records for this user if necessary.
	 */
	private function prune_history($user_id) {
		// Check to see if the number of items in the list is now greater than the config max.
		$rs = $this->db->pquery("SELECT count(*) from {$this->table_name} WHERE user_id=?", array($user_id));
		$count = $this->db->query_result($rs, 0, 0);
		$query = "SELECT * from $this->table_name WHERE user_id='$user_id' ORDER BY id ASC";
		while ($count >= $this->history_max_viewed) {
			// delete the last one. This assumes that entries are added one at a time > we should never add a bunch of entries
			$result = $this->db->limitQuery($query, 0, 1);
			$oldest_item = $this->db->fetchByAssoc($result, -1, false);
			$this->db->pquery("DELETE from $this->table_name WHERE id=?", array($oldest_item['id']), true);
			$count--;
		}
	}
}
?>
