<?php
 /*************************************************************************************************
 * Copyright 2016 JPL TSolucio, S.L. -- This file is a part of TSOLUCIO coreBOS Customizations.
 * Licensed under the vtiger CRM Public License Version 1.1 (the "License"); you may not use this
 * file except in compliance with the License. You can redistribute it and/or modify it
 * under the terms of the License. JPL TSolucio, S.L. reserves all rights not expressly
 * granted by the License. coreBOS distributed by JPL TSolucio S.L. is distributed in
 * the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. Unless required by
 * applicable law or agreed to in writing, software distributed under the License is
 * distributed on an "AS IS" BASIS, WITHOUT ANY WARRANTIES OR CONDITIONS OF ANY KIND,
 * either express or implied. See the License for the specific language governing
 * permissions and limitations under the License. You may obtain a copy of the License
 * at <http://corebos.org/documentation/doku.php?id=en:devel:vpl11>
 *************************************************************************************************
 *  Module       : Business Mappings:: FieldDependency
 *  Version      : 5.4.0
 *  Author       : JPL TSolucio, S. L.
 *************************************************************************************************
 * The accepted format can be consulted in the wiki
 *************************************************************************************************/
require_once 'modules/cbMap/cbMap.php';
require_once 'modules/cbMap/processmap/processMap.php';

class FieldDependency extends processcbMap {
	private $mapping = array();

	public function processMap($arguments) {
		return $this->convertMap2Array($arguments);
	}

	public function readResponsibleField() {
		if (isset($this->mapping['fields']['field']['Orgfields']['Responsiblefield'])) {
			return $this->mapping['fields']['field']['Orgfields']['Responsiblefield'];
		}
		return array();
	}

	public function readOrgfield() {
		if (isset($this->mapping['fields']['field']['Orgfields']['Orgfield'])) {
			return $this->mapping['fields']['field']['Orgfields']['Orgfield'];
		}
		return array();
	}

	public function readPicklist() {
		if (isset($this->mapping['fields']['field']['Orgfields']['Picklist'])) {
			return $this->mapping['fields']['field']['Orgfields']['Picklist'];
		}
		return array();
	}

	public function getMapTargetModule() {
		if (isset($this->mapping['targetmodule'])) {
			return $this->mapping['targetmodule'];
		}
		return array();
	}

	public function getMapOriginModule() {
		if (isset($this->mapping['originmodule'])) {
			return $this->mapping['originmodule'];
		}
		return array();
	}

	private function convertMap2ArrayOld() {
		$xml = $this->getXMLContent();
		$mapping_arr=array();
		$mapping_arr['name'] = $xml->name;
		$mapping_arr['targetmodule']=array();
		$mapping_arr['targetmodule']['targetid']=$xml->targetmodule->targetid;
		$mapping_arr['targetmodule']['targetname']=$xml->targetmodule->targetname;
		$mapping_arr['originmodule']=array();
		$mapping_arr['originmodule']['originid']=$xml->originmodule->originid;
		$mapping_arr['originmodule']['originname']=$xml->originmodule->originname;
		$mapping_arr['fields']=array();
		$mapping_arr['fields']['Responsiblefield']=array();
		foreach ($xml->fields->field->Orgfields->Responsiblefield as $v) {
			$fieldname= isset($v->fieldname) ? (string)$v->fieldname : '';
			$fieldvalue= isset($v->fieldvalue) ? (string)$v->fieldvalue : '';
			$comparison= isset($v->comparison) ? (string)$v->comparison : '';
			$fieldinfo[]=array('fieldname'=>$fieldname,'fieldvalue'=>$fieldvalue,'comparison'=>$comparison);
		}
		$mapping_arr['fields']['Responsiblefield']=$fieldinfo;
		$mapping_arr['fields']['Orgfield']=array();
		foreach ($xml->fields->field->Orgfields->Orgfield as $v2) {
			$fieldnameout= isset($v2->fieldname) ? (string)$v2->fieldname : '';
			$fieldaction= isset($v2->fieldaction) ? (string)$v2->fieldaction : '';
			$fieldvalue= isset($v2->fieldvalue) ? (string)$v2->fieldvalue : '';
			$mandatory= isset($v2->mandatory) ? (string)$v2->mandatory : '';
			$fieldinfoorg[]=array('fieldname'=>$fieldnameout,
			'fieldaction'=>$fieldaction,'fieldvalue'=>$fieldvalue,'mandatory'=>$mandatory);
		}
		$mapping_arr['fields']['Orgfield']=$fieldinfoorg;
		$mapping_arr['fields']['ResponsibleMode']=array();
		foreach ($xml->fields->field->Orgfields->ResponsibleMode->values as $v3) {
			$responsiblemode[]= isset($v3) ? (string)$v3 : '';
		}
		$mapping_arr['fields']['ResponsibleMode']=$responsiblemode;
		$mapping_arr['fields']['ResponsibleRole']=array();
		foreach ($xml->fields->field->Orgfields->ResponsibleRole->values as $v4) {
			$responsiblerole[]= isset($v4) ? (string)$v4 : '';
		}
		$mapping_arr['fields']['ResponsibleRole']=$responsiblerole;
		$mapping_arr['fields']['Picklist']=array();
		foreach ($xml->fields->field->Orgfields->Picklist as $k5 => $v5) {
			$value=array();
			$fieldnamepick= isset($v5->fieldname) ? (string)$v5->fieldname : '';
			if (isset($v5->values)) {
				foreach ($v5->values as $v6) {
					$value[]= isset($v6) ? (string)$v6 : '';
				}
			} else {
				$value=array();
			}
			$fieldinfopick[]=array('fieldname'=>$fieldnamepick,'value'=>$value);
		}
		$mapping_arr['fields']['Picklist']=$fieldinfopick;
		$this->mapping = $mapping_arr;
	}

	private function expandConditionColumn($conditions, $module) {
		if (!empty($conditions)) {
			$conds = json_decode($conditions, true);
			foreach ($conds as &$cond) {
				if (strpos($cond['columnname'], ':')===false) {
					$cond['columnname'] = CustomView::getFilterFieldDefinition($cond['columnname'], $module);
				}
			}
			$conditions = json_encode($conds);
		}
		return $conditions;
	}

	private function cleanMode($dirtymode) {
		switch (strtolower($dirtymode)) {
			case 'create':
			case '1':
				$mode = 1;
				break;
			case 'edit':
			case '2':
				$mode = 2;
				break;
			case 'detail':
			case '3':
				$mode = 3;
				break;
			default:
				$mode = 0; // all
				break;
		}
		return $mode;
	}

	private function isModeAcceptable($mapmode, $realmode) {
		if (strpos($mapmode, ',')) {
			$modes = explode(',', $mapmode);
			foreach ($modes as $mi => $mv) {
				$modes[$mi] = $this->cleanMode($mv);
			}
			if (empty(array_intersect([0, $realmode], $modes))) {
				return false;
			}
		} elseif ($this->cleanMode($mapmode)!=0 && $this->cleanMode($mapmode)!=$realmode) {
			return false;
		}
		return true;
	}

	private function getXMLBranch($loadfrom) {
		global $adb;
		$bmap = $adb->pquery(
			'select content from vtiger_cbmap inner join vtiger_crmentity on crmid=cbmapid where deleted=0 and (cbmapid=? or mapname=?)',
			[$loadfrom, $loadfrom]
		);
		if ($bmap && $adb->num_rows($bmap)) {
			$xmlcontent=html_entity_decode($bmap->fields['content'], ENT_QUOTES, 'UTF-8');
			if (self::isXML($xmlcontent)) {
				return simplexml_load_string($xmlcontent, null, LIBXML_NOCDATA);
			}
		}
		return false;
	}

	public function convertMap2Array($arguments) {
		$xml = $this->getXMLContent();
		if (empty($xml)) {
			return array();
		}
		$mode = empty($arguments[0]) ? 0 : $this->cleanMode($arguments[0]);
		$mapping_arr = array();
		$mapping_arr['origin'] = (string)$xml->originmodule->originname;
		if (empty($xml->blocktriggerfields)) {
			$mapping_arr['blocktriggerfields'] = 1;
		} else {
			$mapping_arr['blocktriggerfields'] = (int)filter_var(strtolower((string)$xml->blocktriggerfields), FILTER_VALIDATE_BOOLEAN);
		}
		$mapping_arr['blockedtriggerfields'] = [];
		$target_fields = array();
		if (isset($xml->dependencies->loadfrom)) {
			$dependencies = $this->getXMLBranch((string)$xml->dependencies->loadfrom);
		} else {
			$dependencies = $xml->dependencies;
		}
		foreach ($dependencies->dependency as $v) {
			if (isset($v->loadfrom)) {
				$v = $this->getXMLBranch((string)$v->loadfrom);
				if ($v===false) {
					continue;
				}
			}
			if (isset($v->mode) && !$this->isModeAcceptable((string)$v->mode, $mode)) {
				continue; // we ignore the whole dependency
			}
			$hasBlockingAction = false;
			$conditions = $this->expandConditionColumn((string)$v->condition, $mapping_arr['origin']);
			if (isset($v->actions->loadfrom)) {
				$mapactions = $this->getXMLBranch((string)$v->actions->loadfrom);
				if ($mapactions===false) {
					continue;
				}
			} else {
				$mapactions = $v->actions;
			}
			if (isset($mapactions->mode) && !$this->isModeAcceptable((string)$mapactions->mode, $mode)) {
				continue; // we ignore the whole action
			}
			$actions=array();
			foreach ($mapactions->change as $action) {
				if (isset($action->mode) && !$this->isModeAcceptable($action->mode, $mode)) {
					continue;
				}
				$actions['change'][] = array('field'=>(string)$action->field,'value'=>(string)$action->value);
				if ($mapping_arr['blocktriggerfields']) {
					$hasBlockingAction = true;
				}
			}
			foreach ($mapactions->hide as $action) {
				if (isset($action->mode) && !$this->isModeAcceptable($action->mode, $mode)) {
					continue;
				}
				foreach ($action->field as $fld => $name) {
					$actions['hide'][] = array('field'=>(string)$name);
				}
			}
			foreach ($mapactions->readonly as $action) {
				if (isset($action->mode) && !$this->isModeAcceptable($action->mode, $mode)) {
					continue;
				}
				foreach ($action->field as $fld => $name) {
					$actions['readonly'][] = array('field'=>(string)$name);
				}
			}
			foreach ($mapactions->deloptions as $action) {
				if (isset($action->mode) && !$this->isModeAcceptable($action->mode, $mode)) {
					continue;
				}
				$opt=array();
				foreach ($mapactions->deloptions->option as $opt2) {
					$opt[]=(string)$opt2;
				}
				$actions['deloptions'][] = array('field'=>(string)$action->field,'options'=>$opt);
				if ($mapping_arr['blocktriggerfields']) {
					$hasBlockingAction = true;
				}
			}
			foreach ($mapactions->setoptions as $action) {
				if (isset($action->mode) && !$this->isModeAcceptable($action->mode, $mode)) {
					continue;
				}
				$opt=array();
				foreach ($mapactions->setoptions->option as $opt2) {
					$opt[]=(string)$opt2;
				}
				$actions['setoptions'][] = array('field'=>(string)$action->field,'options'=>$opt);
				if ($mapping_arr['blocktriggerfields']) {
					$hasBlockingAction = true;
				}
			}
			foreach ($mapactions->collapse as $action) {
				if (isset($action->mode) && !$this->isModeAcceptable($action->mode, $mode)) {
					continue;
				}
				foreach ($action->block as $block) {
					$bname = getTranslatedString((string)$block, $mapping_arr['origin']);
					$bname = str_replace(' ', '', $bname);
					$actions['collapse'][] = array('block'=>$bname);
				}
			}
			foreach ($mapactions->open as $action) {
				if (isset($action->mode) && !$this->isModeAcceptable($action->mode, $mode)) {
					continue;
				}
				foreach ($action->block as $block) {
					$bname = getTranslatedString((string)$block, $mapping_arr['origin']);
					$bname = str_replace(' ', '', $bname);
					$actions['open'][] = array('block'=>$bname);
				}
			}
			foreach ($mapactions->disappear as $action) {
				if (isset($action->mode) && !$this->isModeAcceptable($action->mode, $mode)) {
					continue;
				}
				foreach ($action->block as $block) {
					$bname = getTranslatedString((string)$block, $mapping_arr['origin']);
					$bname = str_replace(' ', '', $bname);
					$actions['disappear'][] = array('block'=>$bname);
				}
			}
			foreach ($mapactions->appear as $action) {
				if (isset($action->mode) && !$this->isModeAcceptable($action->mode, $mode)) {
					continue;
				}
				foreach ($action->block as $block) {
					$bname = getTranslatedString((string)$block, $mapping_arr['origin']);
					$bname = str_replace(' ', '', $bname);
					$actions['appear'][] = array('block'=>$bname);
				}
			}
			foreach ($mapactions->setclass as $action) {
				if (isset($action->mode) && !$this->isModeAcceptable($action->mode, $mode)) {
					continue;
				}
				foreach ($action->field as $name) {
					$actions['setclass'][] = array('field'=>(string)$name);
				}
				$actions['setclass'][] = array('fieldclass'=>(string)$action->fieldclass);
				$actions['setclass'][] = array('labelclass'=>(string)$action->labelclass);
			}
			foreach ($mapactions->function as $action) {
				if (isset($action->mode) && !$this->isModeAcceptable($action->mode, $mode)) {
					continue;
				}
				$params=array();
				if (isset($action->parameters)) {
					foreach ($action->parameters->parameter as $opt2) {
						if (isset($opt2->conditions)) {
							$optconds = array();
							foreach ($opt2->conditions->condition as $optcond) {
								$optcondparams = array();
								foreach ($optcond->parameter as $ocp) {
									$optcondparams[] = (string)$ocp;
								}
								$optconds[] = $optcondparams;
							}
							$params[]= $optconds;
						} else {
							$params[]=(string)$opt2;
						}
					}
				}
				$actions['function'][] = array(
					'field'=>(string)$action->field,
					'value'=>(string)$action->name,
					'params'=>$params
				);
				if ($mapping_arr['blocktriggerfields']) {
					$hasBlockingAction = true;
				}
			}
			if (empty($actions)) {
				continue;
			}
			foreach ($v->field as $fld) {
				$target_fields[(string)$fld][] = array('conditions'=>$conditions,'actions'=>$actions);
				if ($mapping_arr['blocktriggerfields'] && $hasBlockingAction) {
					$mapping_arr['blockedtriggerfields'][] = (string)$fld;
				}
			}
		}
		$picklistdep = Vtiger_DependencyPicklist::getMapPicklistDependencyDatasource($mapping_arr['origin']);
		foreach ($picklistdep as $key => $value) {
			if (array_key_exists($key, $target_fields)) {
				$target_fields[$key] = array_merge($value, $target_fields[$key]);
			} else {
				$target_fields[$key] = $value;
			}
			if ($mapping_arr['blocktriggerfields']) {
				$mapping_arr['blockedtriggerfields'][] = $key;
			}
		}
		$mapping_arr['fields'] = $target_fields;
		return $mapping_arr;
	}
}
?>
