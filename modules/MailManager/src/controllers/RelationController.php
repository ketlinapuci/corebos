<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.1
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
include_once 'include/Webservices/DescribeObject.php';
include_once 'include/Webservices/Query.php';
require_once 'include/utils/utils.php';
include_once 'modules/Settings/MailScanner/core/MailScannerAction.php';
include_once 'modules/Settings/MailScanner/core/MailAttachmentMIME.php';
require_once __DIR__ . '/RelationControllerAction.php';

/**
 * Class used to manage the emails relationship with vtiger records
 */
class MailManager_RelationController extends MailManager_Controller {

	/**
	* Used to check the MailBox connection
	* @var Boolean
	*/
	protected $skipConnection = false;

	/** To avoid working with mailbox */
	protected function getMailboxModel() {
		if ($this->skipConnection) {
			return false;
		}
		return parent::getMailboxModel();
	}

	/**
	* List of modules used to match the Email address
	* @var array
	*/
	public static $MODULES = array('Contacts', 'Accounts', 'Leads', 'HelpDesk', 'Project', 'Potentials', 'ProjectTask');

	/**
	* Process the request to perform relationship operations
	* @global Users Instance $current_user
	* @param MailManager_Request $request
	* @return boolean
	*/
	public function process(MailManager_Request $request) {
		global $current_user, $currentModule;
		$response = new MailManager_Response(true);
		$viewer = $this->getViewer();

		if ('find' == $request->getOperationArg()) {
			$this->skipConnection = true; // No need to connect to mailbox here, improves performance

			$msguid = $request->get('_msguid');
			$results = array();
			$modules = array();
			$allowedModules = $this->getCurrentUserMailManagerAllowedModules();
			foreach (self::$MODULES as $MODULE) {
				if (!in_array($MODULE, $allowedModules)) {
					continue;
				}

				$from = $request->get('_mfrom');
				if (empty($from)) {
					continue;
				}

				$results[$MODULE] = $this->lookupModuleRecordsWithEmail($MODULE, $from, $msguid);
				$describe = $this->wsDescribe($MODULE);
				$modules[$MODULE] = array('label' => $describe['label'], 'name' => textlength_check($describe['name']), 'id' => $describe['idPrefix'] );
			}
			$viewer->assign('LOOKUPS', $results);
			$viewer->assign('MODULES', $modules);
			$viewer->assign('LINKEDTO', array());

			$viewer->assign('LinkToAvailableActions', $this->linkToAvailableActions());
			$viewer->assign('AllowedModules', $allowedModules);
			$viewer->assign('MSGNO', $request->get('_msgno'));
			$viewer->assign('FOLDER', $request->get('_folder'));
			$response->setResult(array('ui' => $viewer->fetch($this->getModuleTpl('Relationship.tpl'))));
		} elseif ('link' == $request->getOperationArg()) {
			$linkto = $request->get('_mlinkto');
			$foldername = $request->get('_folder');
			$connector = $this->getConnector($foldername);

			// This is to handle larger uploads
			$memory_limit = ConfigPrefs::get('MEMORY_LIMIT');
			ini_set('memory_limit', $memory_limit);

			$mail = $connector->openMail($request->get('_msgno'));
			$mail->attachments(); // Initialize attachments
			$linkedto = MailManager_RelationControllerAction::associate($mail, $linkto);

			$viewer->assign('LinkToAvailableActions', $this->linkToAvailableActions());
			$viewer->assign('AllowedModules', $this->getCurrentUserMailManagerAllowedModules());
			$viewer->assign('LINKEDTO', $linkedto);
			$viewer->assign('MSGNO', $request->get('_msgno'));
			$viewer->assign('FOLDER', $foldername);
			$response->setResult(array('ui' => $viewer->fetch($this->getModuleTpl('Relationship.tpl'))));
		} elseif ('create_wizard' == $request->getOperationArg()) {
			$moduleName = $request->get('_mlinktotype');
			$parent = $request->get('_mlinkto');
			$foldername = $request->get('_folder');

			$connector = $this->getConnector($foldername);
			$mail = $connector->openMail($request->get('_msgno'));

			$qcreate_array = QuickCreate($moduleName);
			$validationData = $qcreate_array['data'];
			$data = split_validationdataArray($validationData);

			$qcreate_array['form'] = $this->processFormData($qcreate_array['form'], $mail);
			$viewer->assign('QUICKCREATE', $qcreate_array['form']);
			if ($moduleName == 'HelpDesk') {
				$viewer->assign('QCMODULE', getTranslatedString('Ticket', 'HelpDesk'));
			} else {
				$viewer->assign('QCMODULE', getTranslatedString('SINGLE_'.$moduleName, $moduleName));
			}
			$viewer->assign('PARENT', $parent);
			$viewer->assign('MODULE', $moduleName);
			$viewer->assign('MSGNO', $request->get('_msgno'));
			$viewer->assign('FOLDER', $foldername);
			$viewer->assign('VALIDATION_DATA_FIELDNAME', $data['fieldname']);
			$viewer->assign('VALIDATION_DATA_FIELDDATATYPE', $data['datatype']);
			$viewer->assign('VALIDATION_DATA_FIELDLABEL', $data['fieldlabel']);
			$viewer->assign('MASS_EDIT', '0');
			$viewer->display($this->getModuleTpl('Relationship.CreateWizard.tpl'));
			$response = false;
		} elseif ('create' == $request->getOperationArg()) {
			$linkModule = $request->get('_mlinktotype');
			$parent = $request->get('_mlinkto');

			$focus = CRMEntity::getInstance($linkModule);

			// This is added as ModComments module has a bug that will not initialize column_fields
			if (empty($focus->column_fields)) {
				$focus->column_fields = getColumnFields($linkModule);
			}

			setObjectValuesFromRequest($focus);

			if ($request->get('assigntype') == 'U') {
				$focus->column_fields['assigned_user_id'] = $request->get('assigned_user_id');
			} elseif ($request->get('assigntype') == 'T') {
				$focus->column_fields['assigned_user_id'] = $request->get('assigned_group_id');
			}

			$foldername = $request->get('_folder');

			if (!empty($foldername)) {
				// This is to handle larger uploads
				$memory_limit = ConfigPrefs::get('MEMORY_LIMIT');
				ini_set('memory_limit', $memory_limit);

				$connector = $this->getConnector($foldername);
				$mail = $connector->openMail($request->get('_msgno'));
				$attachments = $mail->attachments(); // Initialize attachments
			}

			$linkedto = MailManager_RelationControllerAction::getSalesEntityInfo($parent);

			switch ($linkModule) {
				case 'HelpDesk':
					$from = $mail->from();
					$focus->column_fields['parent_id'] = $this->setParentForHelpDesk($parent, $from);
					break;

				case 'ModComments':
				default:
					if (empty($focus->column_fields['assigned_user_id'])) {
						$focus->column_fields['assigned_user_id'] = $current_user->id;
					}
					$focus->column_fields['creator'] = $current_user->id;
					$focus->column_fields['related_to'] = $parent;
					break;
			}

			try {
				$focus->save($linkModule);

				// This condition is added so that emails are not created for calendar without a Parent as there is no way to relate them
				if (empty($parent) && $linkModule != 'cbCalendar') {
					$holdCM = $currentModule;
					$linkedto = MailManager_RelationControllerAction::associate($mail, $focus->id);
					$currentModule = $holdCM;
				}

				// add attachments to the tickets as Documents
				if (in_array($linkModule, array('HelpDesk','Potentials','Project','ProjectTask')) && !empty($attachments)) {
					$relationController = new MailManager_RelationControllerAction('');
					$relationController->__SaveAttachements($mail, $linkModule, $focus);
				}

				$viewer->assign('MSGNO', $request->get('_msgno'));
				$viewer->assign('LINKEDTO', $linkedto);
				$viewer->assign('AllowedModules', $this->getCurrentUserMailManagerAllowedModules());
				$viewer->assign('LinkToAvailableActions', $this->linkToAvailableActions());
				$viewer->assign('FOLDER', $foldername);

				$response->setResult(array('ui' => $viewer->fetch($this->getModuleTpl('Relationship.tpl'))));
			} catch (Exception $e) {
				$response->setResult(array('ui' => '', 'error' => $e->getMessage()));
			}
		} elseif ('savedraft' == $request->getOperationArg()) {
			$connector = $this->getConnector('__vt_drafts');
			$draftResponse = $connector->saveDraft($request);
			$response->setResult($draftResponse);
		} elseif ('saveattachment' == $request->getOperationArg()) {
			$connector = $this->getConnector('__vt_drafts');
			$uploadResponse = $connector->saveAttachment($request);
			$response->setResult($uploadResponse);
		} elseif ('commentwidget' == $request->getOperationArg()) {
			$viewer->assign('LINKMODULE', $request->get('_mlinktotype'));
			$viewer->assign('PARENT', $request->get('_mlinkto'));
			$viewer->assign('MSGNO', $request->get('_msgno'));
			$viewer->assign('FOLDER', $request->get('_folder'));
			$viewer->display($this->getModuleTpl('MailManager.CommentWidget.tpl'));
			$response = false;
		}
		return $response;
	}

	/**
	* Returns the Parent for Tickets module
	* @global Users Instance $current_user
	* @param Integer $parent - crmid of Parent
	* @param Email Address $from - Email Address of the received mail
	* @return Integer - Parent(crmid)
	*/
	public function setParentForHelpDesk($parent, $from) {
		global $current_user;
		if (empty($parent)) {
			if (!empty($from)) {
				$parentInfo = MailManager::lookupMailInVtiger($from[0], $current_user);
				if (!empty($parentInfo[0]['record'])) {
					$parentId = vtws_getIdComponents($parentInfo[0]['record']);
					return $parentId[1];
				}
			}
		} else {
			return $parent;
		}
	}

	/**
	* Function used to set the record fields with the information from mail.
	* @param Array $qcreate_array
	* @param MailManager_Model_Message $mail
	* @return Array
	*/
	public function processFormData($qcreate_array, $mail) {
		$subject = $mail->subject();
		$from = $mail->from();
		if (!empty($from)) {
			$mail_fromAddress = implode(',', $from);
		}
		if (!empty($mail_fromAddress)) {
			$name = explode('@', $mail_fromAddress);
		}
		if (!empty($name[1])) {
			$companyName = explode('.', $name[1]);
		}
		$defaultFieldValueMap = array(
			'lastname'		=> $name[0],
			'email'			=> $mail_fromAddress,
			'email1'		=> $mail_fromAddress,
			'accountname'	=> $companyName[0],
			'company'		=> $companyName[0],
			'ticket_title'	=> $subject,
			'subject'		=> $subject,
			'potentialname'	=> $subject,
			'projectname'	=> $subject,
			'projecttaskname' => $subject,
		);
		$defaultFieldValueMapKeys = array_keys($defaultFieldValueMap);
		foreach ($qcreate_array as $qc_array) {
			$new_qc_array = array();
			foreach ($qc_array as $q_array) {
				if (isset($q_array[2][0]) && in_array($q_array[2][0], $defaultFieldValueMapKeys)) {
					if ($q_array[2][0] == 'lastname') {
						$q_array[3][1] = $defaultFieldValueMap[$q_array[2][0]];
					} else {
						$q_array[3][0] = $defaultFieldValueMap[$q_array[2][0]];
					}
				}
				$new_qc_array[] = $q_array;
			}
			$new_qcreate_array[] = $new_qc_array;
		}
		return $new_qcreate_array;
	}

	/**
	* Returns the available List of accessible modules for Mail Manager
	* @return Array
	*/
	public function getCurrentUserMailManagerAllowedModules() {
		$moduleListForCreateRecordFromMail = array('Contacts', 'Accounts', 'Leads', 'HelpDesk', 'cbCalendar','Potentials','Project','ProjectTask');
		foreach ($moduleListForCreateRecordFromMail as $module) {
			if (MailManager::checkModuleWriteAccessForCurrentUser($module)) {
				$mailManagerAllowedModules[] = $module;
			}
		}
		return $mailManagerAllowedModules;
	}

	/**
	* Returns the list of accessible modules on which Actions(Relationship) can be taken.
	* @return string
	*/
	public function linkToAvailableActions() {
		$moduleListForLinkTo = array('cbCalendar','HelpDesk','ModComments','Emails','Potentials','Project','ProjectTask');
		foreach ($moduleListForLinkTo as $module) {
			if (MailManager::checkModuleWriteAccessForCurrentUser($module)) {
				$mailManagerAllowedModules[] = $module;
			}
		}
		return $mailManagerAllowedModules;
	}

	/**
	 * Helper function to scan for relations
	 */
	protected $wsDescribeCache = array();
	public function wsDescribe($module) {
		global $current_user;
		if (!isset($this->wsDescribeCache[$module])) {
			$this->wsDescribeCache[$module] = vtws_describe($module, $current_user);
		}
		return $this->wsDescribeCache[$module];
	}

	/**
	* Funtion used to build Web services query
	* @param string $module - Name of the module
	* @param string $text - Search String
	* @param string $type - Tyoe of fields Phone, Email etc
	* @return String
	*/
	public function buildSearchQuery($module, $text, $type) {
		$describe = $this->wsDescribe($module);
		$labelFields = $describe['labelFields'];
		switch ($module) {
			case 'HelpDesk':
				$labelFields = 'ticket_title';
				break;
			case 'Documents':
				$labelFields = 'notes_title';
				break;
		}
		$whereClause = '';
		foreach ($describe['fields'] as $field) {
			if (strcasecmp($type, $field['type']['name']) === 0) {
				$whereClause .= sprintf(" %s LIKE '%%%s%%' OR", $field['name'], $text);
			}
		}
		return sprintf('SELECT %s FROM %s WHERE %s order by createdtime desc limit 0,6;', $labelFields, $module, rtrim($whereClause, 'OR'));
	}

	/**
	* Returns the List of Matching records with the Email Address
	* @global Users Instance $current_user
	* @param string $module
	* @param Email Address $email
	* @return Array
	*/
	public function lookupModuleRecordsWithEmail($module, $email, $msguid) {
		global $current_user;
		$query = $this->buildSearchQuery($module, $email, 'EMAIL');
		$qresults = vtws_query($query, $current_user);
		$describe = $this->wsDescribe($module);
		$labelFields = $describe['labelFields'];
		switch ($module) {
			case 'HelpDesk':
				$labelFields = 'ticket_title';
				break;
			case 'Documents':
				$labelFields = 'notes_title';
				break;
		}
		$labelFields = explode(',', $labelFields);
		$results = array();
		foreach ($qresults as $qresult) {
			$labelValues = array();
			foreach ($labelFields as $fieldname) {
				if (isset($qresult[$fieldname])) {
					$labelValues[] = $qresult[$fieldname];
				}
			}
			$ids = vtws_getIdComponents($qresult['id']);
			$linkedto = MailManager::isEMailAssociatedWithCRMID($msguid, $ids[1]);
			$results[] = array('wsid' => $qresult['id'], 'id' => $ids[1], 'label' => implode(' ', $labelValues), 'linked'=>$linkedto);
		}
		return $results;
	}
}
?>