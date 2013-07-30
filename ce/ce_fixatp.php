<?php
require_once('../config.php');  // if run from outside as by cron
    require_once("ce-lib.php");
$currentterm = '2012FA';

// get all the candidates
$sql = "SELECT DISTINCT c.teacherfullname from mdl_ce_atp a
	JOIN mdl_ce_course c on c.teacherusername = a.username
	WHERE a.term = '$currentterm'";
$candidates = get_records_sql($sql);

// get term for which courses are needed
	$currenttermid = get_currentterm($currentterm);

	// get any subcategories for this term recursively
	$cats = get_cats($currenttermid->id);
$sql = "SELECT DISTINCT q.*, c.idnumber
			FROM {$CFG->prefix}questionnaire q
			JOIN {$CFG->prefix}course as c on c.id = q.course
			WHERE q.name NOT LIKE 'ALL COLLEGE%'
			AND c.category IN($cats)
			AND q.qtype = 5";
	$questionnaires = get_records_sql($sql);
foreach($questionnaires as $que) {
	// strip the instructor name from the questionnaire name
	$instname = trim(substr($que->name,strrpos($que->name,'-')+1,100));
	// is the instructor in atp?
	if (array_key_exists($instname,$candidates)) {
		// find which course module record this is
		if ($target = get_record('course_modules','course',$que->course,'instance',$que->id)) {
			delete_records('course_modules','id',$target->id);
		}
	}
	var_dump($que);
	die;
}
/*
$sql = "SELECT c.* FROM mdl_ce_course c
JOIN mdl_ce_atp a on a.username = c.teacherusername
JOIN mdl_questionnaire on c.teacherfullname IS LIKE %
WHERE c.term = '$currentterm'
ORDER BY c.department";
$ce_recs = get_records_sql($sql);
*/
foreach($ce_recs as $rec) {
	// go find the questionnaire record for this rec

}
?>