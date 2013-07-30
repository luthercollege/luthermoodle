<?php 
	require_once('../config.php');
	require_once($CFG->dirroot . '/group/lib.php');

	// get list of courses for current term that are due to populated with course evaluations
	$currentterm = get_record('ce_term','status','Current');
	$grouping = new stdClass();
	$sql = "SELECT * FROM mdl_ce_course 
			WHERE term = '$currentterm->name' 
			AND department = 'Music'
			ORDER BY courseid";
	$ce_recs = get_records_sql($sql);
	$grouping->description = null;
	foreach($ce_recs as $rec) {
		if (sizeof($teachers = get_teachers($rec->courseid)) < 2) {
			continue;
//		} elseif ($dept = $rec->department !== 'Music') {
//			continue;
		} else {
			$grouping->name = $rec->teacherfullname;
			$grouping->courseid = $rec->courseid;
			groups_create_group($grouping);
			echo 'grouping created for ' . $rec->fullname . ' with teacher = ' . $rec->teacherfullname . '<br />';	
		}
	}

function get_teachers($courseid) {
	$coursecontext = get_context_instance(CONTEXT_COURSE, $courseid);
	$sql = "SELECT u.* from mdl_user u
			JOIN mdl_role_assignments ra on ra.userid = u.id
			JOIN mdl_role r on r.id = ra.roleid
			WHERE ra.contextid = " . $coursecontext->id 
			. " AND r.shortname = 'editingteacher'";
	return get_records_sql($sql);
}
?>