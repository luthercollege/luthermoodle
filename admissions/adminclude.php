<?php
	// used by regdatetally.php
	$optionid = 1; // first id of options for date selection choice
	$dates = array('Saturday, May 4, 2013','Friday, June 7, 2013','Tuesday, June 11, 2013','Wednesday, June 12, 2013', 'Friday, August 30, 2013');

	// shared
    $choiceid = 1; // new choice id for this year's registration date choice activity

	// grade item ids
	$quests = array(
			'mlchoice'=>1279,
			'reg_date'=>917);
/*
	$allgrades = implode(',',array_values($quests));
	$valuesarray = array_values($quests);	
	$scales = array('mus'=>144,
					'ml'=>145,
					'adm'=>143,
					'hous'=>142,
					'date'=>7,
					'mlexam'=>146
					);
	// mathexam is for the math exam choice page
	// muschoice is for choosing whether you have to take the exam
	// musexam is for the actual music quiz
	// math quizzes are handled through the math exam choice page
	// dateselected is the resource id for the choice for date selection
*/
	$ids = array(
			'course'=>899,
			'admque'=>225394,
			'mathque'=>225395,
			'mlque'=>225396,
			'musque'=>226375,
			'mathexam'=>225398,
			'musexam'=>225388,
			'musexam2'=>226375,  // piano exam
			'musexam3'=>226376,  // honors theory exam
			'muschoice'=>225400,
			'mlchoice'=>225399,
			'mlexam'=>225392,
			'dateselected'=>2656);  
	// questionnaire instances needed to redirect to the report
	$instances = array('adm'=>3823,'math'=>3824,'ml'=>3825);

	// don't need changing
	define('REQUIRED',1);
	define('EXEMPT',2);
	
	// for local cron portion that deals with setting of grades
	$choice_id = $choiceid;
	$choice_grade_itemid = $quests['reg_date'];
	$choice_subtraction = $optionid - 1;
	$choice_max = 5;
	$choice_scale = 7;

?>