<?php 
    require_once("../config.php");
    require_once("lib.php");
	global $CFG;
	if (isset($questparam)) {
		$c = $questparam;
		$r = 0;		// echo output
		$v = 1;
	} else {
		$c = required_param('quest', 1, PARAM_INT);		// which questionnaire
		$r = required_param('report', 1, PARAM_INT);		// echo output
		$v = optional_param('score', 1, PARAM_INT);
	}
	$find=array(
		array(name => 'Admissions Questionnaire FIND', search => 'id', table => 'mdl_feedback_completed', field => 'feedback',value => 76, values => ',,,,,'),  // 0
		array(name => 'Math Questionnaire FIND', search => 'id', table => 'mdl_feedback_completed', field => 'feedback',value => 77, values => ',,,,,'),  // 1
		array(name => 'Modern Languages Questionnaire FIND', search => 'id', table => 'mdl_feedback_completed', field => 'feedback',value => 78, values => ',,,,,'),  // 2
		array(name => 'Other Languages Questionnaire FIND', search => 'id', table => 'mdl_feedback_completed', field => 'feedback',value => 83, values => ',,,,,'),  // 3
		array(name => 'Music Theory Exam FIND', search => 'finalgrade', table => 'mdl_grade_grades', field => 'itemid',value => 20479, values => ',,,,,'),		// 4
		array(name => 'Piano Exam FIND', search => 'finalgrade', table => 'mdl_grade_grades', field => 'itemid',value => 20480, values => ',,,,,'),	// 5
		array(name => 'Modern Language Exam', search => 'answerid', table => 'mdl_lesson_attempts', field => 'pageid',value => 432, values => ',2,1,43,2,'),  // 6
		array(name => 'Registration Date Selection FIND', search => 'optionid', table => 'mdl_choice_answers', field => 'choiceid',value => 669, values => ',5,1,39,2,')  // 7
	);
	switch ($c) {
		case 2:
		case 3:
			$v = 2;
			break;
	}
	$sql = "SELECT ".$find[$c]['search'].", userid FROM ".$find[$c]['table']." WHERE ".$find[$c]['field']." = ".$find[$c]['value'].' ORDER BY '.$find[$c]['search'];
//	echo $sql."<br />";
	$sourceresults = mysql_query($sql);
	$total = mysql_num_rows($sourceresults);
//	echo $total."<br />";
	for ($t = 0; $t < $total; $t++) {
		$userparam = mysql_result($sourceresults,$t,'userid');
		echo $find[$c]['name']." = $userparam<br />";
		require("$CFG->dirroot/course/examgrade.php");
	}
?>