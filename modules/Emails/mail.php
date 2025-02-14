<?php
/*********************************************************************************
** The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ********************************************************************************/
require_once 'modules/Emails/PHPMailerAutoload.php';
require_once 'include/utils/CommonUtils.php';
require_once 'modules/Emails/Emails.php';

/** Function used to send an email
 * $module     - module: only used to add signature if it is different than "Calendar"
 * $to_email   - "TO" email address
 * $from_name  - name that will be shown in the "From", it will also be used to search for the user signature
 * $from_email - email address from which the email will come from, if left empty we will search for a username equal to
					$from_name, if found that email will be used, if not we will use $HELPDESK_SUPPORT_EMAIL_ID
					if the "FROM EMAIL" field in set in Settings Outgoing Server, that one will be used
 * $subject    - subject of the email you want to send
 * $contents   - body of the email you want to send
 * $cc         - add email address with comma seperated. - optional
 * $bcc        - add email address with comma seperated. - optional
 * $attachment - accepted values are:
					current: get file name from $_REQUEST['filename_hidden'] or $_FILES['filename']['name']
					all: all files directly related with the crmid record, this is mostly only useful for email records
					attReports: get file name from $_REQUEST['filename_hidden_pdf'] and $_REQUEST['filename_hidden_xls']
					array of filenames or document IDs or array file name and path: array('themes/images/webcam.png','themes/images/Meetings.gif', 42525);
					array of filenames as array of name and full path: array('fname'=> {basename}, 'fpath'=> {full path including base name})
					array of direct content:
						array(
							'direct' => true,
							'files' => array(
								array(
									'name' => 'summarize.gif',
									'content' => file_get_contents('themes/images/summarize.gif')
								),
								array(
									'name' => 'jump_to_top_60.png',
									'content' => file_get_contents('themes/images/jump_to_top_60.png')
								),
							)
						);
 * $emailid    - id of the email object which will be used to get the attachments when $attachment='all'
 * $logo       - if the company logo should be added to the email, for this to work you must put
						<img src="cid:logo" />
					wherever you want the logo to appear
 * $replyto    - email address that an automatic "reply to" will be sent
 * $qrScan     - if we should load qrcode images from cache directory <img src="cid:qrcode{$fname}" />
 * $brScan     - if we should load barcode images from cache directory <img src="cid:barcode{$fname}" />
 */
function send_mail(
	$module,
	$to_email,
	$from_name,
	$from_email,
	$subject,
	$contents,
	$cc = '',
	$bcc = '',
	$attachment = '',
	$emailid = '',
	$logo = '',
	$replyto = '',
	$qrScan = '',
	$brScan = ''
) {
	global $adb, $current_user;
	$HELPDESK_SUPPORT_EMAIL_ID = GlobalVariable::getVariable('HelpDesk_Support_EMail', 'support@your_support_domain.tld', 'HelpDesk');

	$adb->println("To id => '".$to_email."'\nSubject ==>'".$subject."'\nContents ==> '".$contents."'");

	$femail = '';
	if (substr($from_email, 0, 8)=='FROM:::>') {
		$femail = substr($from_email, 8);
		$from_email = '';
	}
	if (empty($from_name) && !empty($from_email)) {
		$sql = "select user_name from vtiger_users where status='Active' and (email1=? or email2=? or secondaryemail=?)";
		$result = $adb->pquery($sql, array($from_email,$from_email,$from_email));
		if ($result && $adb->num_rows($result)>0) {
			$from_name = $adb->query_result($result, 0, 0);
		}
	}
	if ($from_email == '') {
		//if from email is not defined, then use the useremailid as the from address
		$from_email = getUserEmailId('user_name', $from_name);
	}
	if (empty($from_email)) {
		$from_email = $HELPDESK_SUPPORT_EMAIL_ID;
	}

	//if the newly defined from email field is set, then use this email address as the from address
	//and use the username as the reply-to address
	$result = $adb->pquery('select * from vtiger_systems where server_type=?', array('email'));
	$from_email_field = $adb->query_result($result, 0, 'from_email_field');
	if (empty($replyto)) {
		if (isUserInitiated()) {
			global $current_user;
			$reply_to_secondary = GlobalVariable::getVariable('Users_ReplyTo_SecondEmail', 0, 'Users', $current_user->id);
			if ($reply_to_secondary == 1) {
				$result = $adb->pquery('select secondaryemail from vtiger_users where id=?', array($current_user->id));
				$second_email = '';
				if ($result && $adb->num_rows($result)>0) {
					$second_email = $adb->query_result($result, 0, 'secondaryemail');
				}
			}
			if (!empty($second_email)) {
				$replyToEmail = $second_email;
			} else {
				$replyToEmail = $from_email;
			}
		} else {
			$replyToEmail = $from_email_field;
		}
	} else {
		$replyToEmail = $replyto;
	}
	if (isset($from_email_field) && $from_email_field!='') {
		//setting from _email to the defined email address in the outgoing server configuration
		$from_email = $from_email_field;
	}
	$user_mail_config = $adb->pquery('select og_server_username from vtiger_mail_accounts where user_id=? AND og_server_status=1', array($current_user->id));
	if ($user_mail_config && $adb->num_rows($user_mail_config)>0) {
		$from_email = $adb->query_result($user_mail_config, 0, 'og_server_username');
	}

	if ($femail!='') {
		$from_email = $femail;
	}

	// Add main HTML tags when missing
	if (!preg_match('/^\s*<\!DOCTYPE/', $contents) && !preg_match('/^\s*<html/i', $contents)) {
		$contents = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head><body>'.$contents.'</body></html>';
	}
	if ($module != 'Calendar') {
		$contents = addSignature($contents, $from_name);
	}
	$companyEmailFooter = GlobalVariable::getVariable('EMail_Company_Signature', '', $module);
	if (!empty($companyEmailFooter)) {
		$contents.= html_entity_decode('<br>'.$companyEmailFooter);
	}

	// always merge against user module
	$rs = $adb->pquery('select id from vtiger_users where email1=? or email2=?', array($from_email, $from_email));
	if ($adb->num_rows($rs) > 0) {
		$subject = getMergedDescription($subject, $adb->query_result($rs, 0, 'id'), 'Users');
		$contents = getMergedDescription($contents, $adb->query_result($rs, 0, 'id'), 'Users');
	}

	list($systemEmailClassName, $systemEmailClassPath) = cbEventHandler::do_filter(
		'corebos.filter.systemEmailClass.getname',
		array('Emails', 'modules/Emails/Emails.php')
	);
	require_once $systemEmailClassPath;
	if (!call_user_func(array($systemEmailClassName, 'useEmailHook'), $from_email, $to_email, $replyToEmail)) {
		$systemEmailClassName = 'Emails'; // default system method
	}

	$inBucketServeUrl = GlobalVariable::getVariable('Debug_Email_Send_To_Inbucket', '');

	if (!empty($inBucketServeUrl)) {
		$systemEmailClassName = 'Emails';
		$systemEmailClassPath = 'modules/Emails/Emails.php';
	}

	return call_user_func_array(
		array($systemEmailClassName, 'sendEMail'),
		array(
			$to_email,
			$from_name,
			$from_email,
			$subject,
			$contents,
			$cc,
			$bcc,
			$attachment,
			$emailid,
			$logo,
			$qrScan,
			$brScan,
			$replyto,
			$replyToEmail
		)
	);
}

/** Function to get the user Email id based on column name and column value
 * @param string column name of the vtiger_users table
 * @param string column value
 */
function getUserEmailId($name, $val) {
	global $adb;
	$adb->println('> getUserEmailId '.$name.', '.$val);
	if ($val != '') {
		//done to resolve the PHP5 specific behaviour
		$sql = "SELECT email1, email2, secondaryemail from vtiger_users WHERE status='Active' AND ". $adb->sql_escape_string($name).' = ?';
		$res = $adb->pquery($sql, array($val));
		$email = $adb->query_result($res, 0, 'email1');
		if ($email == '') {
			$email = $adb->query_result($res, 0, 'email2');
			if ($email == '') {
				$email = $adb->query_result($res, 0, 'secondaryemail ');
			}
		}
		$adb->println('< getUserEmailId '.$email);
		return $email;
	} else {
		$adb->println('< getUserEmailId User id is empty');
		return '';
	}
}

/** Funtion to add the user's signature with the content passed
 * @param string where we want to add the signature
 * @param string which user's signature will be added to the contents
 */
function addSignature($contents, $fromname) {
	global $adb, $default_charset, $current_user;
	$adb->println('> addSignature');
	$signrs = $adb->pquery('select signature from vtiger_users where user_name=? or id=?', array($fromname, $current_user->id));
	$sign = $adb->query_result($signrs, 0, 'signature');
	if ($sign != '') {
		$sign = html_entity_decode($sign, ENT_QUOTES, $default_charset);
		$contents .= '<br>'.$sign;
	}
	$adb->println('< addSignature '.$sign);
	return $contents;
}

/** Function to set all the Mailer properties
 * $mail		-- reference of the mail object
 * $subject	-- subject of the email you want to send
 * $contents	-- body of the email you want to send
 * $from_email	-- from email id which will be displayed in the mail
 * $from_name	-- from name which will be displayed in the mail
 * $to_email	-- to email address -- This can be an email in a single string, a comma separated
 * 		   list of emails or an array of email addresses
 * $attachment	-- see sendmail explanation
 * $emailid	-- id of the email object which will be used to get the attachments - optional
 */
function setMailerProperties($mail, $subject, $contents, $from_email, $from_name, $to_email, $attachment = '', $emailid = '', $logo = '', $qrScan = '', $brScan = '') {
	global $adb;
	$adb->println('> setMailerProperties');
	if ($logo == 1) {
		$cmp = retrieveCompanyDetails();
		$mail->AddEmbeddedImage($cmp['companylogo'], 'logo', 'logo.jpg', 'base64', 'image/jpg');
	}
	if ($qrScan == 1) {
		preg_match_all('/<img src="cid:(qrcode.*)"/', $contents, $matches);
		foreach ($matches[1] as $qrname) {
			$mail->AddEmbeddedImage('cache/images/'.$qrname.'.png', $qrname, $qrname.'.png', 'base64', 'image/png');
		}
	}
	if ($brScan == 1) {
		preg_match_all('/<img src="cid:(barcode.*)"/', $contents, $matches);
		foreach ($matches[1] as $brname) {
			$mail->AddEmbeddedImage('cache/images/'.$brname.'.png', $brname, $brname.'.png', 'base64', 'image/png');
		}
	}
	$mail->Subject = $subject;
	$mail->Body = $contents;
	//$mail->Body = html_entity_decode(nl2br($contents));	//if we get html tags in mail then we will use this line
	$mail->AltBody = strip_tags(preg_replace(array('/<p>/i', '/<br>/i', '/<br \/>/i'), array("\n", "\n", "\n"), $contents));

	$mail->IsSMTP();		//set mailer to use SMTP
	//$mail->Host = 'smtp1.example.com;smtp2.example.com';  // specify main and backup server

	setMailServerProperties($mail);

	//Handle the from name and email for HelpDesk
	$mail->From = $from_email;
	$rs = $adb->pquery('select first_name,last_name,ename from vtiger_users where user_name=?', array($from_name));
	$num_rows = $adb->num_rows($rs);
	if ($num_rows > 0) {
		$from_name = getFullNameFromQResult($rs, 0, 'Users');
	}

	$mail->FromName = decode_html($from_name);

	if ($to_email != '') {
		if (is_array($to_email)) {
			foreach ($to_email as $recip) {
				$mail->addAddress(str_replace(' ', '', $recip));
			}
		} else {
			foreach (explode(',', $to_email) as $recip) {
				$mail->addAddress(str_replace(' ', '', $recip));
			}
		}
	}

	//commented so that it does not add from_email in reply to
	//$mail->AddReplyTo($from_email);
	$mail->WordWrap = 50;

	//If we want to add the currently selected file only then we will use the following function
	if ($attachment == 'current' && $emailid != '') {
		if (isset($_REQUEST['filename_hidden'])) {
			$file_name = $_REQUEST['filename_hidden'];
		} else {
			$file_name = $_FILES['filename']['name'];
		}
		addAttachment($mail, $file_name, $emailid);
	}

	//This will add all the files which are related to this record or email
	if ($attachment == 'all' && $emailid != '') {
		addAllAttachments($mail, $emailid);
	}

	//If we send attachments from Reports
	if ($attachment == 'attReports') {
		if (isset($_REQUEST['filename_hidden_pdf'])) {
			$file_name = $_REQUEST['filename_hidden_pdf'];
			addAttachment($mail, $file_name, $emailid);
		}
		if (isset($_REQUEST['filename_hidden_xls'])) {
			$file_name = $_REQUEST['filename_hidden_xls'];
			addAttachment($mail, $file_name, $emailid);
		}
	}

	//If we send attachments from MarketingDashboard
	if (is_array($attachment)) {
		if (array_key_exists('direct', $attachment) && $attachment['direct']) {
			//We are sending attachments with direct content, the files are not stored
			foreach ($attachment['files'] as $file) {
				addStringAttachment($mail, $file['name'], $file['content']);
			}
		} else {
			foreach ($attachment as $file) {
				if (is_array($file)) {
					addAttachment($mail, $file['fname'], $emailid);
				} else {
					addAttachment($mail, $file, $emailid);
				}
			}
		}
	}

	$mail->IsHTML(true); // set email format to HTML
	$mail->AllowEmpty = true; //allow sent empty body.
}

/** Function to set the Mail Server Properties in the object passed
 * @param object reference of the mailobject
 */
function setMailServerProperties($mail) {
	global $adb,$default_charset, $current_user;

	$inBucketServeUrl = GlobalVariable::getVariable('Debug_Email_Send_To_Inbucket', '');
	if (!empty($inBucketServeUrl)) {
		$mail->Host = $inBucketServeUrl; // Url for InBucket Server
		$mail->Username = '';	// SMTP username
		$mail->Password = '' ;	// SMTP password
		$mail->SMTPAuth = false;
	} else {
		$adb->println('> setMailServerProperties');
		$user_mail_config = $adb->pquery('select * from vtiger_mail_accounts where user_id=? AND og_server_status=1', array($current_user->id));
		$res = $adb->pquery('select * from vtiger_systems where server_type=?', array('email'));
		if (isset($_REQUEST['server'])) {
			$server = $_REQUEST['server'];
		} else {
			if ($user_mail_config && $adb->num_rows($user_mail_config)>0) {
				$server = $adb->query_result($user_mail_config, 0, 'og_server_name');
			} else {
				$server = $adb->query_result($res, 0, 'server');
			}
		}
		if (isset($_REQUEST['server_username'])) {
			$username = $_REQUEST['server_username'];
		} else {
			if ($user_mail_config && $adb->num_rows($user_mail_config)>0) {
				$username = $adb->query_result($user_mail_config, 0, 'og_server_username');
			} else {
				$username = $adb->query_result($res, 0, 'server_username');
			}
		}
		if (isset($_REQUEST['server_password'])) {
			$password = $_REQUEST['server_password'];
		} else {
			if ($user_mail_config && $adb->num_rows($user_mail_config)>0) {
				require_once 'include/database/PearDatabase.php';
				require_once 'modules/Users/Users.php';
				$focus = new Users();
				$password = $focus->de_cryption($adb->query_result($user_mail_config, 0, 'og_server_password'));
			} else {
				$password = html_entity_decode($adb->query_result($res, 0, 'server_password'), ENT_QUOTES, $default_charset);
			}
		}
		if (isset($_REQUEST['smtp_auth'])) {
			$smtp_auth = $_REQUEST['smtp_auth'];
		} else {
			if ($user_mail_config && $adb->num_rows($user_mail_config)>0) {
				$smtp_auth = $adb->query_result($user_mail_config, 0, 'og_smtp_auth	');
			} else {
				$smtp_auth = $adb->query_result($res, 0, 'smtp_auth');
			}
		}

		$adb->println("Mail server name,username & password => '".$server."','".$username."','".$password."'");
		if ('false' != $smtp_auth) {
			$mail->SMTPAuth = true;
			if ('true' != $smtp_auth) {
				if ($smtp_auth == 'sslnc' || $smtp_auth == 'tlsnc') {
					$mail->SMTPOptions = array(
					'ssl' => array(
						'verify_peer' => false,
						'verify_peer_name' => false,
						'allow_self_signed' => true
					)
					);
					$smtp_auth = substr($smtp_auth, 0, 3);
				}
				$mail->SMTPSecure = $smtp_auth;
			}
		}
		$mail->Host = $server;		// specify main and backup server
		$mail->Username = $username ;	// SMTP username
		$mail->Password = $password ;	// SMTP password

		$debugEmail = GlobalVariable::getVariable('Debug_Email_Sending', 0);
		if ($debugEmail) {
			global $log;
			$log->fatal(array(
			'SMTPOptions' => $mail->SMTPOptions,
			'SMTPSecure' => $mail->SMTPSecure,
			'Host' => $mail->Host = $server,
			'Username' => $mail->Username = $username,
			'Password' => $mail->Password = $password,
			));
			$mail->SMTPDebug = 4;
			$mail->Debugoutput = function ($str, $level) {
				global $log;
				$log->fatal($str);
			};
		}
	}
}

/** Function to add the file as attachment with the mail object
 * @param object reference of the mail object
 * @param string filename which is going to added with the mail
 * @param integer id of the record - optional
 */
function addAttachment($mail, $filename, $record) {
	global $adb, $root_directory;
	$adb->println('> addAttachment '.$filename);

	//This is the file which has been selected in Email EditView
	if (is_file($root_directory.$filename) && ($root_directory.$filename) != '') {
		$bn = basename($filename);
		$parts = explode('_', $bn);
		if (!empty($parts) && is_attachmentid($parts[0])) {
			$name = substr($bn, strlen($parts[0])+1);
		} else {
			$name = $bn;
		}
		$mail->AddAttachment($root_directory.$filename, $name);
	} elseif (is_numeric($filename)) {
		$query = 'SELECT vtiger_attachments.path, vtiger_attachments.name, vtiger_attachments.attachmentsid
			FROM vtiger_attachments
			INNER JOIN vtiger_seattachmentsrel ON vtiger_seattachmentsrel.attachmentsid = vtiger_attachments.attachmentsid
			INNER JOIN vtiger_crmentity ON vtiger_attachments.attachmentsid=vtiger_crmentity.crmid
			WHERE deleted=0 AND vtiger_seattachmentsrel.crmid = ?';
		$docid = $filename;
		$docrs = $adb->pquery($query, array($docid));
		if ($docrs && $adb->num_rows($docrs)==1) {
			$attname = $adb->query_result($docrs, 0, 'path').$adb->query_result($docrs, 0, 'attachmentsid').'_'.$adb->query_result($docrs, 0, 'name');
			$mail->AddAttachment($attname, $adb->query_result($docrs, 0, 'name'));
		}
	}
}

/** Function to add the file as attachment with the mail object
 * @param object reference of the mail object
 * @param string filename which is going to added with the mail
 * @param string file contents to attach
 */
function addStringAttachment($mail, $filename, $data) {
	global $adb;
	$adb->println('> addStringAttachment '.$filename);
	$mail->AddStringAttachment($data, $filename);
}

/** Function to add all the files as attachment with the mail object
 * @param object reference of the mail object
 * @param integer email id, record id which is used to get all attachments from database
 */
function addAllAttachments($mail, $record) {
	global $adb, $root_directory;
	$adb->println('> addAllAttachments');

	//Retrieve the files from database where avoid the file which has been currently selected
	$sql = 'select vtiger_attachments.*
		from vtiger_attachments
		inner join vtiger_seattachmentsrel on vtiger_attachments.attachmentsid = vtiger_seattachmentsrel.attachmentsid
		inner join vtiger_crmentity on vtiger_crmentity.crmid = vtiger_attachments.attachmentsid
		where vtiger_crmentity.deleted=0 and vtiger_seattachmentsrel.crmid=?';
	$res = $adb->pquery($sql, array($record));
	$count = $adb->num_rows($res);

	for ($i=0; $i<$count; $i++) {
		$fileid = $adb->query_result($res, $i, 'attachmentsid');
		$filename = decode_html($adb->query_result($res, $i, 'name'));
		$filepath = $adb->query_result($res, $i, 'path');
		$filewithpath = $root_directory.$filepath.$fileid.'_'.$filename;

		//if the file is exist in cache/upload directory then we will add directly
		//else get the contents of the file and write it as a file and then attach (this will occur when we unlink the file)
		if (is_file($filewithpath)) {
			$mail->AddAttachment($filewithpath, $filename);
		}
	}
}

/** Function to get all the related files as attachments
 * @param integer email id: record id which is used to get all the related attachments
 */
function getAllAttachments($record) {
	global $adb, $log, $root_directory;
	$log->debug('> getAllAttachments');
	$res = $adb->pquery(
		'select vtiger_attachments.*
			from vtiger_attachments
			inner join vtiger_seattachmentsrel on vtiger_attachments.attachmentsid = vtiger_seattachmentsrel.attachmentsid
			inner join vtiger_crmentity on vtiger_crmentity.crmid = vtiger_attachments.attachmentsid
			where vtiger_crmentity.deleted=0 and vtiger_seattachmentsrel.crmid=?',
		array($record)
	);
	$attachments = array();
	while ($att = $adb->fetch_array($res)) {
		$fileid = $att['attachmentsid'];
		$filename = decode_html($att['name']);
		$filepath = $att['path'];
		$filewithpath = $root_directory.$filepath.$fileid.'_'.$filename;
		if (is_file($filewithpath)) {
			$attachments[]=array('fname'=>$filename,'fpath'=>$filewithpath);
		}
	}
	$log->debug('< getAllAttachments');
	return $attachments;
}

/** Function to set the CC or BCC addresses in the mail
 * @param object reference of the mail object
 * @param string mode to set the address ie., cc or bcc
 * @param string addresss with comma seperated to set as CC or BCC in the mail
 */
function setCCAddress($mail, $cc_mod, $cc_val) {
	global $adb;
	$adb->println('> setCCAddress');

	if ($cc_mod == 'cc') {
		$method = 'AddCC';
	}
	if ($cc_mod == 'bcc') {
		$method = 'AddBCC';
	}
	if ($cc_val != '') {
		$ccmail = explode(',', trim($cc_val, ','));
		for ($i=0; $i<count($ccmail); $i++) {
			$addr = $ccmail[$i];
			$cc_name = preg_replace('/([^@]+)@(.*)/', '$1', $addr); // First Part Of Email
			if (stripos($addr, '<')) {
				$name_addr_pair = explode('<', $ccmail[$i]);
				$cc_name = $name_addr_pair[0];
				$addr = trim($name_addr_pair[1], '>');
			}
			if ($ccmail[$i] != '') {
				$mail->$method($addr, $cc_name);
			}
		}
	}
}

/** Function to send the mail which will be called after set all the mail object values
 * @param object reference of the mail object
 */
function MailSend($mail) {
	global $log;
	$log->debug('> MailSend');
	if (!$mail->Send()) {
		$log->debug('< MailSend Error: '.$mail->ErrorInfo);
		return $mail->ErrorInfo;
	} else {
		$log->debug('< MailSend Status: '.$mail->ErrorInfo);
		return 1;
	}
}

/** Function to get the Parent email id from HelpDesk to send the details about the ticket via email
 * @param string Parent module value. Contact or Account for which we send email about the ticket details
 * @param integer id of the parent ie., contact or account
 */
function getParentMailId($parentmodule, $parentid) {
	global $adb;
	$adb->println('> getParentMailId '.$parentmodule.', '.$parentid);

	if ($parentmodule == 'Contacts') {
		$tablename = 'vtiger_contactdetails';
		$idname = 'contactid';
		$first_email = 'email';
		$second_email = 'secondaryemail';
	}
	if ($parentmodule == 'Accounts') {
		$tablename = 'vtiger_account';
		$idname = 'accountid';
		$first_email = 'email1';
		$second_email = 'email2';
	}
	if ($parentid != '') {
		$query = 'select * from '.$tablename.' where '. $idname.' = ?';
		$res = $adb->pquery($query, array($parentid));
		$mailid = $adb->query_result($res, 0, $first_email);
		$mailid2 = $adb->query_result($res, 0, $second_email);
	}
	if ($mailid == '' && $mailid2 != '') {
		$mailid = $mailid2;
	}

	return $mailid;
}

/** Function to parse and get the mail error
 * @param object reference of the mail object
 * @param string status of the mail which is sent or not
 * @return string Mail error occured during the mail sending process
 */
function getMailError($mail, $mail_status) {
	//Error types in class.phpmailer.php
	/*
	provide_address, mailer_not_supported, execute, instantiate, file_access, file_open, encoding, data_not_accepted, authenticate,
	connect_host, recipients_failed, from_failed
	*/

	global $adb;
	$adb->println('> getMailError');

	$msg = array_search($mail_status, $mail->getTranslations());
	$adb->println('Error message: '.$msg);

	if ($msg == 'connect_host') {
		$error_msg = $msg;
	} elseif (strstr($msg, 'from_failed')) {
		$error_msg = $msg;
	} elseif (strstr($msg, 'recipients_failed')) {
		$error_msg = $msg;
	} else {
		$error_msg = 'Mail error is not connect_host, from_failed nor recipients_failed';
	}
	$adb->println('< getMailError '.$error_msg);
	return $error_msg;
}

/** Function to get the mail status string (string of sent mail status)
 * @param string concatenated string with all the error messages with &&& separation
 * @return string the error status as a encoded string
 */
function getMailErrorString($mail_status_str) {
	global $adb;
	$adb->println('> getMailErrorString '.$mail_status_str);

	$mail_status_str = trim($mail_status_str, '&&&');
	$mail_status_array = explode('&&&', $mail_status_str);
	$adb->println('All Mail status ==> '.$mail_status_str);
	$mail_error_str = '';
	foreach ($mail_status_array as $val) {
		$list = explode('=', $val);
		$adb->println('Mail id & status ==> '.$list[0].' = '.$list[1]);
		if ($list[1] == 0 || strpos($list[1], 'error') || strpos($list[1], 'failed')) {
			$mail_error_str .= $list[0].'='.$list[1].'&&&';
		}
	}
	$adb->println('< getMailErrorString '.$mail_error_str);
	if ($mail_error_str != '') {
		$mail_error_str = 'mail_error='.base64_encode($mail_error_str);
	}
	return $mail_error_str;
}

/** Function to parse the error string
 * @param string base64 encoded string which contains the mail sending errors as concatenated with &&&
 * @return string Error message to display
 */
function parseEmailErrorString($mail_error_str) {
	global $adb;
	$adb->println('> parseEmailErrorString');
	$errorStyleOpen = '<br><strong><span style="color:red;">';
	$errorStyleClose = '</span></strong>';
	$mail_error = base64_decode($mail_error_str);
	$adb->println('Original error string => '.$mail_error);
	$mail_status = explode('&&&', trim($mail_error, '&&&'));
	$errorstr = '';
	foreach ($mail_status as $val) {
		$status_str = explode('=', $val);
		$adb->println('Mail id => '.$status_str[0].' status => '.(isset($status_str[1]) ? $status_str[1] : ''));
		if (isset($status_str[1]) && $status_str[1] != 1) {
			$adb->println('Error in mail sending');
			if ($status_str[1] == 'connect_host') {
				$adb->println('Mail sever is not configured');
				$errorstr .= $errorStyleOpen.getTranslatedString('MESSAGE_CHECK_MAIL_SERVER_NAME', 'Emails').$errorStyleClose;
				break;
			} elseif ($status_str[1] == '0') {
				$adb->println("assigned_to users's email is empty");
				$errorstr .= $errorStyleOpen.getTranslatedString('MESSAGE_MAIL_COULD_NOT_BE_SEND', 'Emails').' '
					.getTranslatedString('MESSAGE_PLEASE_CHECK_FROM_THE_MAILID', 'Emails').$errorStyleClose;
				//Added to display the message about the CC && BCC mail sending status
				if ($status_str[0] == 'cc_success') {
					$cc_msg = 'But the mail has been sent to CC & BCC addresses';
					$errorstr .= '<br><strong><span style="color:purple;">'.$cc_msg.$errorStyleClose;
				}
			} elseif (strstr($status_str[1], 'from_failed')) {
				$adb->println('from email id failed');
				$from = explode('from_failed', $status_str[1]);
				$errorstr .= $errorStyleOpen.getTranslatedString('MESSAGE_PLEASE_CHECK_THE_FROM_MAILID', 'Emails')." '".$from[1]."'</span></strong>";
			} else {
				$adb->println('mail send process failed');
				$errorstr .= $errorStyleOpen.getTranslatedString('MESSAGE_MAIL_COULD_NOT_BE_SEND_TO_THIS_EMAILID', 'Emails')." '".$status_str[0]."'. "
					.getTranslatedString('PLEASE_CHECK_THIS_EMAILID', 'Emails').$errorStyleClose;
			}
		}
	}
	$adb->println('< parseEmailErrorString');
	return $errorstr;
}

function isUserInitiated() {
	return (isset($_REQUEST['module']) && isset($_REQUEST['action']) && $_REQUEST['module'] == 'Emails' &&
		($_REQUEST['action'] == 'mailsend' || $_REQUEST['action'] == 'webmailsend' || $_REQUEST['action'] == 'Save'));
}

/**
 * Function to get the group users Email ids
 */
function getDefaultAssigneeEmailIds($groupId) {
	global $adb;
	$emails = array();
	if ($groupId != '') {
		require_once 'include/utils/GetGroupUsers.php';
		$userGroups = new GetGroupUsers();
		$userGroups->getAllUsersInGroup($groupId);
		if (count($userGroups->group_users) > 0) {
			$result = $adb->pquery(
				'SELECT email1,email2,secondaryemail FROM vtiger_users WHERE vtiger_users.id IN ('
					.generateQuestionMarks($userGroups->group_users).') AND vtiger_users.status= ?',
				array($userGroups->group_users, 'Active')
			);
			$rows = $adb->num_rows($result);
			for ($i = 0; $i < $rows; $i++) {
				$email = $adb->query_result($result, $i, 'email1');
				if ($email == '') {
					$email = $adb->query_result($result, $i, 'email2');
					if ($email == '') {
						$email = $adb->query_result($result, $i, 'secondaryemail');
					} else {
						$email = '';
					}
				}
				$emails[] = $email;
			}
			// Email ids are selected => implode(',', $emails)
		// else => No users found in Group id $groupId
		}
	// else Group id is empty, so return value as empty;
	}
	return $emails;
}

function createEmailRecord($element) {
	global $adb, $log, $current_user;
	include_once 'include/Webservices/Create.php';
	$elementType = 'Emails';
	$webserviceObject = VtigerWebserviceObject::fromName($adb, $elementType);
	$handlerPath = $webserviceObject->getHandlerPath();
	$handlerClass = $webserviceObject->getHandlerClass();
	require_once $handlerPath;
	$handler = new $handlerClass($webserviceObject, $current_user, $adb, $log);
	$element['activitytype'] = 'Emails';
	if (empty($element['assigned_user_id'])) {
		$element['assigned_user_id'] = vtws_getEntityId('Users').'x'.$current_user->id;
	}
	if (empty($element['date_start'])) {
		$date = new DateTimeField(null);
		$element['date_start'] = $date->getDisplayDate($current_user);
	}
	if (empty($element['time_start'])) {
		$element['time_start'] = date('H:i:s');
	}
	if (empty($element['email_flag'])) {
		$element['email_flag'] = 'SENT';
	}
	$result = $handler->create($elementType, $element);
	return $result['id'];
}

function createEmailRecordWithSave($element) {
	$reqModule = $_REQUEST['module'];
	$reqPID = isset($_REQUEST['parent_id']) ? $_REQUEST['parent_id'] : '';
	$_REQUEST['module'] = 'Emails';
	$_REQUEST['parent_id'] = $element['parent_id'];
	$focus = new Emails();
	$focus->column_fields = $element;
	$focus->column_fields['activitytype'] = 'Emails';
	$focus->save('Emails');
	$_REQUEST['module'] = $reqModule;
	$_REQUEST['parent_id'] = $reqPID;
	return $focus;
}
?>
