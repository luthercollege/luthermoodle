<?php
//define('CLI_SCRIPT', true);
require_once("../config.php");
$instance = required_param('instance', PARAM_INTEGER);
$sid = required_param('sid', PARAM_INTEGER);
$USER = 0;

sendforcsv($instance, $sid);

function sendforcsv($instance, $sid) {
	global $USER, $CFG, $DB;
	$id = 2;
	$USER = $DB->get_record('user', array('id'=>$id));
//	$inst = array(69,71,72,73);
//	$sid = array(69,71,72,73);

//	foreach($inst as $key=>$inst) {
		$url = "$CFG->wwwroot/mod/questionnaire/report.php?instance=$instance&sid=$sid[$key]&action='dcsv'&currentgroupid=-1";
		redirect($url);
//	}
}
?>