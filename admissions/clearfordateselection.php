<?php 
    require_once("../config.php");
	require_once('adminclude.php');
	global $USER, $DB;
	$userid = $USER->id;
//	$query_grades = array();
	$incomp = '<span style="font-size: 16; color: rgb(255, 0, 0); text-align: center;"><strong>The registration date selection link will be available at this screen location after you have completed all items displayed above. (NOTE: as items are completed above additional items may be displayed that are also REQUIRED).</strong></span>';
	// CHECK TO SEE IF THEY'VE ALREADY MADE A CHOICE AND PRINT IT OUT AND REDIRECT
	$sql = "SELECT o.text AS text FROM mdl_choice_options o
		JOIN mdl_choice_answers a on a.optionid = o.id
		WHERE a.userid = $userid
		AND a.choiceid = $choiceid";
	$results = $DB->get_records_sql($sql);
//	$num_results = mysql_num_rows($results);
	// if they've already picked a date
	if ($results) {
		foreach($results as $key=>$value) {
			$selection = $key;
			$chosen = '<span style="font-size: 16"><strong>Your current date selection is: '.$selection.'</strong></span>';
//			$chosen = '<span style="font-size: 16"><strong><a target="_top" href="http://katie.luther.edu/moodle/mod/choice/view.php?id='
//			. $ids['dateselected'] . '">Your current date selection is: '.$selection.' -- Click here to update</a></strong></span>';
			echo $chosen;
		}
	// check all their exams and questionnaires
	} else {
		echo $incomp;
/*
		// get all grades for this student 
		$sql = "SELECT itemid from mdl_grade_grades WHERE userid = $userid AND NOT ISNULL(rawgrade) AND itemid IN($allgrades)";
		$resultstemp = get_records_sql($sql);
		unset($results);
		foreach ($resultstemp as $key=>$value) {
			$results[] = $key;
		}
		if (sizeof($results) < 6) { // need at least six grades to be worthy of continuing
			exit(0);
		} else {
//			asort($results);
			// check questionnaires, if one is missing then we're not done, no need to check further
			$diff = array_diff($valuesarray, $results); // leaving only the grades not yet completed
			if (in_array($quests['admque'],$diff) || in_array($quests['mathque'],$diff) || in_array($quests['mlque'],$diff)) {
				echo $incomp;
				exit(0);
			} else {
				// check on math exams
				if(!in_array($quests['mathexam'],$diff) || !in_array($quests['mathexam2'],$diff) || !in_array($quests['mathexam3'],$diff)) {
					// one of the math exams has a grade, do nothing and move on, its quicker to check the negative than check that all tests are absent
				} else { // none of the exams have been taken
					echo $incomp;
					exit(0);
				}
				// check on music exams
				if(!in_array($quests['musexam'],$diff) || !in_array($quests['musexam2'],$diff)) { // theory and piano have both been taken
				} elseif(!in_array($quests['musicchoice'],$diff)) { // check to see if not req
					$musicchoice = $quests['musicchoice'];
					$sql = "SELECT finalgrade from mdl_grade_grades WHERE userid = $userid AND NOT ISNULL(rawgrade) AND itemid = $musicchoice";
					$results = get_records_sql($sql);
					if ($results[0]->finalgrade == EXEMPT) {  // no need to take any music
					}
				} else { // no grade for not req and no grade for having taken the exam
					echo $incomp;
					exit(0);
				}
				// check on modern language exam
				if(in_array($quests['mlexam'],$diff)) { // student has not confirmed or indicated exempt
					echo $incomp;
					exit(0);
				}
			}
		}
		$selection = 'UNSELECTED';
		$chosen = '<span style="font-size: 16"><strong><a target="_top" href="http://katie.luther.edu/moodle/mod/choice/view.php?id=' . $ids['dateselected'] . '">Your current date selection is: ' . $selection . ' -- Click here to update</a></strong></span>';
		echo $chosen;
*/
	}
?>