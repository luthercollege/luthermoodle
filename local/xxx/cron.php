<?php
	require_once('/opt/local/apache2/htdocs/moodle/config.php');
//	require_once('/var/www/moodle/config.php');
	mtrace('Processing grades for admissions...', '');
	require_once("$CFG->dirroot/admissions/adminclude.php");
	require_once($CFG->libdir . '/dmllib.php');
	global $DB;
	$grades = new stdClass();
	$grades->itemid = $choice_grade_itemid;
	$grades->rawgrademax = $choice_max;
	$grades->rawgrademin = 1;
	$grades->usermodified = 2;
	$grades->rawscaleid = $choice_scale;
	srand ((double) microtime() * 10000000);
	$random100 = rand(0,100);
	echo $random100;
	mtrace('Processing grades for admissions...', '');
	if ($random100 < 1) {     // Approximately 25% of the time or evey half hour.

		mtrace('Processing grades for admissions...', '');

		// GET ALL THE CHOICE ANSWER RECORDS
		$select = " choiceid = $choice_id ";
		$records = $DB->get_records_select('choice_answers',$select, null, null, 'userid, optionid');
		foreach($records as $record) {
			$choiceuser = $record->userid;
			$choice_choice = $record->optionid - $choice_subtraction;
//			echo $choiceuser . ' --- ' . $choice_choice . '<br />';
			$grades->rawgrade = $choice_choice;
			$grades->finalgrade = $choice_choice;
			$sql = "SELECT finalgrade, id
				FROM mdl_grade_grades
				WHERE itemid = $choice_grade_itemid
				AND userid = $choiceuser";
			$grade_record = $DB->get_record_sql($sql);
			$grades->userid = $choiceuser;
			$grades->timemodified = time();

			// IF GRADE HASN'T BEEN RECORDED YET, INSERT RECORD
			if (! isset($grade_record->finalgrade)) {
				mtrace("inserting grade");
				$DB->insert_record('grade_grades',$grades, false);
			// IF GRADE ALREADY RECORDED, MAKE SURE ITS CURRENT
			} elseif ($choice_choice != $grade_record->finalgrade) {
				mtrace("updating grade");
				$grades->id = $grade_record->id;
				$DB->update_record('grade_grades',$grades, false);
			}
		}
		// check for choices that have been cancelled and delete grade_grades record
		$grades = $DB->get_records('grade_grades', array('itemid'=>$choice_grade_itemid));
		foreach($grades as $grade) {
			if(! array_key_exists($grade->userid, $records)) {
				mtrace("deleting grade $grade->userid");
				$DB->delete_records('grade_grades', array('itemid'=>$choice_grade_itemid, 'userid'=>$grade->userid));
			}
		}
	}
	
	// PROCESS ALL LOG RECORDS INTO LEARNANA DATABASE
	$sql = "SELECT MAX(id) FROM " . $CFG->prefix . "log";
	$maxrecord = $DB->get_field_sql($sql);
	$sql = "SELECT * FROM " . $CFG->prefix . "log 
			WHERE id > $maxrecord";
	$records = $DB->get_records_sql($sql);
	foreach($records as $record) {
		$DB->
	}
	