<?php

require_once('../config.php');
require_once("$CFG->dirroot/google/lib.php");
require_once("$CFG->dirroot/google/gauth.php");
define('TEMPLATENAME', 'CE-Report for 00000-Template');
define('ACGID',977);
define('ACGSID',1023);


class ce_report extends stdClass {
	// set up variables
	var $owner = 'course-evaluations@luther.edu';

	function _construct() {
		$term = get_field('ce_term','name','status','current');
		$ce_collection = get_collection('courseevals',$owner); // main course evaluations collection id
		$termcollectionid = get_collection($term,$owner, $ce_collection); // specific term subcollection id
		$termorigcollectionid = get_collection($term . '-orig',$owner, $ce_collection); // specific term subcollection id
		$termdatacollectionid = get_collection($term . '-data',$owner, $ce_collection); // specific term subcollection id
		$base_feed = 'https://docs.google.com/feeds/' . urlencode($owner) . '/private/full';
		$colarray = cellarray('archive');

	}
}


/*
 * GET DATABASE INFORMATION FROM MOODLE FUNCTIONS
 */

function get_currentterm($term = null) {  // TODO: check if needed
    // get term for which courses are needed
	if ($term === null) {
		$currentterm = get_field('ce_term','name', 'status','Current');
	} else {
		$currentterm = $term;
	}
	return get_record('course_categories','name',$currentterm);
}

function get_cats($term) {
	// get any subcategories for this term recursively
	$cats = array();
	get_sub_cats($cats,$term);
	return implode(',',$cats);
}

function get_sub_cats(&$cats,$term) {
	global $DB;
	$subcats = $DB->get_records('course_categories',array('parent'=>$term));
	foreach ($subcats as $subcat) {
		get_sub_cats($cats,$subcat->id);
	}
	$cats[] = $term;
}

function get_teachers($courseid) {
	global $DB;
	$sql = 'SELECT ra.userid AS id, u.username AS username, CONCAT(u.firstname, " ", u.lastname) AS fullname from mdl_context con
			JOIN mdl_role_assignments ra on ra.contextid = con.id
			JOIN mdl_user u on u.id = ra.userid
			JOIN mdl_role rol on rol.id = ra.roleid
			WHERE rol.shortname = "editingteacher"
			AND con.contextlevel = 50
			AND con.instanceid = ' . $courseid;
	return $DB->get_records_sql($sql);
}

function children_in_array($courseid, $idtargets, $courses) {
	$children = get_records('course_meta','parent',$courseid);
	foreach ($children as $child) {
		//if the idnumber associated with the child course is in the official datatel list then return true
		if (array_key_exists($idtargets[$child->child_course]->idnumber, $courses)) {
			return $courses[$idtargets[$child->child_course]->idnumber];
		}
	}
	return false;
}

/*
 * TODO: look at placing this in the the google library and genericizing
*/
// transfer exports to google and optionally adds to a collection
function send_cefile_togoogle($title, $filecontents,$owner) {

	// get link to write content to
	$rel = 'edit-media';
	if (!$writelink = get_sheet_link($owner, $title, $rel)) { //if spreadsheet doesn't already exist
		$query = create_empty_sheet($title,$owner); // create new empty spreadsheet
		$feed = simplexml_load_string($query->response);  // need this so we can get the id for the new sheet
		// this feed doesn't contain an entry element
		$writelink = get_href_noentry($feed, $rel);
	}

	// TODO: what't this for, it effectively removes the item from the collection
	// send updated data
	$base = explode('?',$writelink);
	$base_feed = $base[0];
	$file_type = 'application/vnd.ms-excel';
	$params = array('xoauth_requestor_id' => $owner);
	$query  = twolegged($base_feed, $params, 'PUT', $filecontents, '3.0', $file_type, $title);
	return $feed;
	//	return $writelink;
}

// write out the roll-up lines to the departmental report
// move to current term collection
// assign permissions
function ce_writedeptresults($rows, $owner, $worksheetid, $key, $colarray, $startrow = 0) {
	if (sizeof($rows) > 40) {
		$chunkrows = array_chunk($rows, 40, true);
		for ($i = 0; $i < sizeof($chunkrows); $i++) {
			ce_writedeptresults($chunkrows[$i], $owner, $worksheetid, $key, $colarray, $i * 120);
		}
	}
	$base_feed = 'https://spreadsheets.google.com/feeds/cells/' . $key . '/' . $worksheetid . '/private/full';
	$batchcellpost = '<feed xmlns="http://www.w3.org/2005/Atom"
	xmlns:batch="http://schemas.google.com/gdata/batch"
	xmlns:gs="http://schemas.google.com/spreadsheets/2006">
	<id>' . $base_feed . '</id>';
	$params = array('xoauth_requestor_id' => $owner);
	foreach ($rows as $evalteach => $title) {
		$startrow += 3;	// starts out at row 8 so becomes $startrow + $row to get there
		// add the course/ teacher entry for column A
		// TODO: make this a link to the original sheet
		$joinedvalue = '';
		$col = 1;
		$row = 5;
		$url = $base_feed . '/R' . s($startrow + $row) . 'C' . $col;
		$batchcellpost .= batch_cell_post($url, 'A' . s($startrow + $row), $startrow + $row, $col, $evalteach);
		foreach ($title as $location => $value) {
			// split location into row/ col
			$col = preg_replace('/(\d+)/', '', $location);
			$row = preg_replace('/([A-Z]+)/', '', $location);
			if (array_key_exists($col, $colarray)) {
				if ($location == 'B5' && sizeof($title) > 24) {  // response percentage cell on an individual report
					$row = 7;
					$col = 'A';
					$value = substr($value,0,strpos($value,'.') + 3) . '%';
				} elseif (substr($value,0,1) === "'") { // response rate that's already been made into text by the individual report
					// do nothing value remains value
				} elseif ($row == 7 && $colarray[$col] <> 7) { // response rate that's not been made into text yet (individual report)
					$value =  "'" . $value;
				} else {
					$value = sprintf("%.2f",$value);;
				}
				//			$value = $row == 7 ? "'" . $value : preg_replace('/(\d+)\.(\d+)/','/(\d+)\.(\d{2})/',$value);
				$url = $base_feed . '/R' . s($startrow + $row) . 'C' . $colarray[$col];
				$batchcellpost .= batch_cell_post($url, $location, $startrow + $row, $colarray[$col], $value);
			}
		}
	}
	$batchcellpost .= '</feed>';
	$response  = twolegged($base_feed . '/batch', $params, 'POST', $batchcellpost, null, null, null, 'batch');
	//    $response = send_request('PUT', $base_feed . '/batch', $headers, null, $batchcellpost, '3.0');
}

// preprocess cells
function ce_write_archiveresults($rows, $owner, $worksheetid, $key) {
	if (sizeof($rows) > 100) {
		$chunkrows = array_chunk($rows, 100, true);
		for ($i = 0; $i < sizeof($chunkrows); $i++) {
			$result = ce_write_archiveresults($chunkrows[$i], $owner, $worksheetid, $key);
			if (!$result) {
				return;
			}
		}
	}
	$base_feed = 'https://spreadsheets.google.com/feeds/cells/' . $key . '/' . $worksheetid . '/private/full';
	foreach ($rows as $location => $value) {
		// split location into row/ col
		$col = preg_replace('/(\d+)/', '', $location);
		$row = preg_replace('/([A-Z]+)/', '', $location);
		$value = str_replace('<-','',$value);
		$value = $row == 7 && $col <> 'F' && substr($value,0,1) !== "'" ? "'" . $value : $value;
		$cells[$col . $row] = $value;
	}
	$response  = write_batch($cells, $owner, $base_feed);
	$feed = simplexml_load_string($response->response);
	if (sizeof($feed->entry) != sizeof($rows)) { // if not getting values back
		return ce_write_archiveresults($rows, $owner, $worksheetid, $key);
	}
	return $response;
}

function write_batch($cells, $owner, $base_feed) {
	$batchcellpost = '<feed xmlns="http://www.w3.org/2005/Atom"
	xmlns:batch="http://schemas.google.com/gdata/batch"
	xmlns:gs="http://schemas.google.com/spreadsheets/2006">
	<id>' . $base_feed . '</id>';
	foreach ($cells as $location => $value) {
		// split location into row/ col
		$col = s(ord(preg_replace('/(\d+)/', '', $location))-64);
		$row = preg_replace('/([A-Z]+)/', '', $location);
		$url = $base_feed . '/R' . s($row) . 'C' . $col;
		$batchcellpost .= batch_cell_post($url, $location, $row, $col, $value);
	}
	$batchcellpost .= '</feed>';
	$params = array('xoauth_requestor_id' => $owner);
	$response  = twolegged($base_feed . '/batch', $params, 'POST', $batchcellpost, null, null, null, 'batch');
	$feed = simplexml_load_string($response->response);
	return $response;
}

function cellarray($context=null) {
	switch ($context) {
		case 'archive':
			return array(
			'A' => '1',
			'B' => '2',
			'C' => '3',
			'D' => '4',
			'E' => '5',
			'F' => '6',
			'G' => '7',
			'H' => '8',
			'I' => '9',
			'J' => '10',
			'K' => '11',
			'L' => '12',
			'M' => '13',
			'N' => '14',
			'O' => '15',
			'P' => '16',
			'Q' => '17',
			'R' => '18',
			'S' => '19',
			'T' => '20',
			'U' => '21',
			'V' => '22',
			'W' => '23',
			'X' => '24',
			'Y' => '25',
			'Z' => '26',
			'AA' => '27',
			'AB' => '28',
			'AC' => '29',
			'AD' => '30',
			'AE' => '31'
			);
		case 'dean':
			return array(
			'A' => '1',
			'C' => '3',
			'D' => '4',
			'E' => '5',
			'F' => '6',
			'G' => '7',
			'H' => '8',
			'I' => '9',
			'J' => '10',
			'K' => '11',
			'L' => '12',
			'M' => '13',
			'N' => '14',
			'Q' => '17',
			'R' => '18',
			'S' => '19',
			'T' => '20',
			'U' => '21',
			'V' => '22'
			);
		default:
			return array(
			'A' => '1',
			'B' => '3',
			'C' => '4',
			'D' => '5',
			'E' => '6',
			'F' => '7',
			'G' => '8',
			'H' => '9',
			'I' => '10',
			'K' => '11',
			'L' => '12',
			'M' => '13',
			'N' => '14',
			'P' => '17',
			'Q' => '18',
			'R' => '19',
			'S' => '20',
			'T' => '21',
			'U' => '22',
			'W' => '23',
			'X' => '24',
			'Y' => '25',
			'Z' => '26',
			'AA' => '27',
			'AB' => '28',
			'AC' => '29',
			'AD' => '30',
			'AE' => '31',
			'AF' => '32',
			'AG' => '33',
			'AH' => '34',
			'AI' => '35'
			);
	}
}

// assigns permissions to workbooks
function ce_assignpermissions($department, $owner, $term, $key, $reportname, $teacher = null) {
	$head = get_record('ce_dept_head', 'department', $department);
	$assigns[] = $head->email;
	if ($teacher !== null) {
		$assigns[] = $teacher->email;
	}
	if ($department == 'Paideia') {
		$altdepthead = get_record('ce_dept_head', 'department', $teacher->institution);
		$assigns[] = $altdepthead->email;
	}
	foreach ($assigns as $assign) {
		if ($assign !== null) {
			$acl_feed = 'https://docs.google.com/feeds/' . urlencode($owner) . '/private/full/spreadsheet%3A' . $key . '/acl';
			$permissiondata = acl_post($assign, 'writer', 'user');
			$params = array('xoauth_requestor_id' => $owner,'send-notification-emails' => 'false');
			$response = twolegged($acl_feed, $params, 'POST', $permissiondata);
			if ($response->info['http_code'] == 201) {
				add_to_log($courseid, 'CE', "Added ", null, "$assign as owner for $reportname");
			} else {
				add_to_log($courseid, 'CE', "ADD FAILED FOR ", null, "$assign as owner from $reportname");
			}
		}
	}
}

/*
 * one-time change the owner on a spreadsheet
 * make sure the new owner has no privileges before changing
 * old owner will become the editor
 */
function assign_owner($owner, $key, $assign) {
	$acl_feed = 'https://docs.google.com/feeds/' . urlencode($owner) . '/private/full/spreadsheet%3A' . $key . '/acl';
	$permissiondata = acl_post($assign, 'owner', 'user');
	$params = array('xoauth_requestor_id' => $owner,'send-notification-emails' => 'false');
	$response = twolegged($acl_feed, $params, 'POST', $permissiondata);
	$feed = simplexml_load_string($query->response);
}


?>