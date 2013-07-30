<?php 
	require_once('../config.php');
	$currentterm = get_record('ce_term','status','Current');
	$sql = "SELECT c.courseid, c.fullname, c.teacherfullname FROM mdl_ce_course c, mdl_groups g
			WHERE c.department = 'Music'
			AND g.courseid = c.courseid
			AND g.name = c.teacherfullname";
//			AND g.name = c.teacherfullname
//			AND g.courseid = c.courseid
//			AND c.term = '" . $currentterm->name . "'";
	$ce_recs = get_records_sql($sql);
	$rec_keys = implode(',', array_keys($ce_recs));
	$select = " courseid IN($rec_keys) ";
	$groups = get_records_select('groups', $select);
	foreach($groups as $group) {
		$select = " groupid = $group->id ";
		$members = get_record_select('groups_members',$select);
		$courseid = $group->courseid;
		$notice = ' members for group for ' . $ce_recs[$courseid]->fullname. ': ' . $ce_recs[$courseid]->teacherfullname . '<br />';
		if (!$members) {
			$addnotice = 'No';
		} else {
			$addnotice = 'FOUND';
		}
		echo $addnotice . $notice;
	}	
?>