<?php 
	define('CLI_SCRIPT', true);
    require_once('/var/www/moodle/config.php');
    require_once($CFG->dirroot.'/google/lib.php');
    require_once($CFG->dirroot.'/google/gauth.php');
    $files = array(
    			'mltest.csv','MathQuest.csv','AdmQuest.csv', 'musSection.csv',
    			'ModLangQuest.csv','admGrades.csv','katieDeposited.csv', 'housing.csv', 'katieDepositedMusic.csv'
    			);
	$owner = 'puffro01@luther.edu';
	$file_type = 'application/vnd.ms-excel';
	$params = array('xoauth_requestor_id' => $owner);
	foreach ($files as $title) {
		$feed = get_gdocquery($title, $owner);
		$base = explode('?',$feed);
		$base_feed = $base[0];		
		$getfile = '/var/lib/mysql/moodle/' . $title;
		$sentfile = file_get_contents($getfile);
		// update
    	$query  = twolegged($base_feed, $params, 'PUT', $sentfile, '3.0', $file_type, $title);
	}	
function get_gdocquery($title, $owner) {
	$base_feed = 'https://docs.google.com/feeds/' . urlencode($owner) . '/private/full';
//	$base_feed = 'https://docs.google.com/feeds/private/full';
	$params = array('xoauth_requestor_id' => $owner, 'title' => $title, 'title-exact' => 'true');
    $contenttype = 'application/x-www-form-urlencoded';
    $query  = twolegged($base_feed, $params, 'GET', null, '3.0', $contenttype);
	$feed = simplexml_load_string($query->response);
	$rel = 'edit-media';
	if ($feed->entry) {
		return get_href($feed, $rel);
	} else {
		return get_href_noentry($feed, $rel);
	}
}


?>