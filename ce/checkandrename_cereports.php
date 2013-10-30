<?php
	require_once('../config.php');
    require_once($CFG->dirroot.'/google/lib.php');
    require_once($CFG->dirroot.'/google/gauth.php');
    require_once("ce_lib.php");

    // set up variables
	$owner = 'course-evaluations@luther.edu';
	$term = '2012JT';
	$replacechars = array(':','/');
//	$courses = array('55824','55779','55665','55658','54666');
	$courses = array('55145');

	//	$term = get_field('ce_term','name','status','current');
	$ce_collection = get_collection('courseevals',$owner); // main course evaluations collection id
	if (($termcollectionid = get_collection($term,$owner, $ce_collection)) == false) { // specific term subcollection id
		echo 'Report collection not setup';
		die;
	}
	if (($termdatacollectionid = get_collection($term . '-data',$owner, $ce_collection)) == false) {  // specific term subcollection id
		echo 'Data file collection not setup';
		die;
	}
	$base_feed = 'https://docs.google.com/feeds/' . urlencode($owner) . '/private/full';

	// get a complete list of all documents in the collection for this term
	$term_feed = get_doc_feed($owner, $termcollectionid, 1200);
	foreach ($term_feed->entry as $entry) {
//		$term_titles[s($entry->title)] = $entry;
	}
//	ksort($term_titles);

	$data_feed = get_doc_feed($owner, $termdatacollectionid, 1200);
	foreach ($data_feed->entry as $entry) {
		$data_titles[s($entry->title)] = $entry;
	}
	ksort($data_titles);

	foreach ($data_titles as $key=>$entry) {
		$synonym = substr($key,22,5);
//		if (in_array($synonym,$courses)) {
			if (substr($entry->title,0,22) == 'Course Evaluation for ') {
				$sql = "SELECT * FROM mdl_ce_course WHERE term = '$term'
						AND SUBSTR(fullname, 1, 5) = '$synonym'";
				$ce_recs = get_records_sql($sql);
				foreach ($ce_recs as $rec) {
					if (strpos($entry->title, $rec->teacherfullname)) {
						$rec->fullname = str_replace('&', 'and', $rec->fullname);
						$rec->fullname = str_replace($replacechars, '-', $rec->fullname);
						$newreportname = 'Course Evaluation for ' . $rec->fullname . ' - ' . $rec->teacherfullname . '.csv';
						$title_feed = $base_feed . '/' . strip_id($entry->id);
						gfile_rename($newreportname, $title_feed, $owner);
						$success = update_record('ce_course', $rec);
					}
				}
			}
//		}
	}

	foreach ($term_titles as $key=>$entry) {
		$synonym = substr($key,14,5);
//		if (in_array($synonym,$courses)) {
			if (substr($entry->title,0,14) == 'CE-Report for ') {
				$sql = "SELECT * FROM mdl_ce_course WHERE term = '$term'
						AND SUBSTR(fullname, 1, 5) = '$synonym'";
				$ce_recs = get_records_sql($sql);
				foreach ($ce_recs as $rec) {
					if (strpos($entry->title, $rec->teacherfullname)) {
						$rec->fullname = str_replace('&', 'and', $rec->fullname);
						$rec->fullname = str_replace($replacechars, '-', $rec->fullname);
						$newreportname = 'CE-Report for ' . $rec->fullname . ' - ' . $rec->teacherfullname;
						$title_feed = $base_feed . '/' . strip_id($entry->id);
						gfile_rename($newreportname, $title_feed, $owner);
						$success = update_record('ce_course', $rec);
					}
				}
			}
//		}
	}

	function gfile_rename($title, $editlink, $owner) {
	$doctype = 'spreadsheet';
	$createdocdata =
	'<?xml version="1.0" encoding="UTF-8"?>
	<entry xmlns="http://www.w3.org/2005/Atom" xmlns:docs="http://schemas.google.com/docs/2007">
	<category scheme="http://schemas.google.com/g/2005#kind"
	term="http://schemas.google.com/docs/2007#' . $doctype . '"/>
	<title>' . $title . '</title>
	</entry>';

	$params = array('xoauth_requestor_id' => $owner);
	$query  = twolegged($editlink, $params, 'PUT', $createdocdata, '3.0');
}
?>