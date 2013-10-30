<?php 
    require_once("../config.php");
	require_once('adminclude.php');
	global $DB;
	for ($c= 0; $c < 4; $c++) {
		$sql = "SELECT a.* FROM mdl_choice_answers a 
								JOIN mdl_user u on u.id = a.userid
								WHERE a.choiceid = $choiceid 
								AND a.optionid = $optionid + $c
								AND u.deleted <> 1";
		$results = $DB->get_records_sql($sql);
		// these numbers get moved around during the registration season to manipulate the percentages of spots taken for the various dates
		// divisor is the limit for that date and the multiplicator is used to get a percentage
		switch ($c) {
			case 0: 
				$spaces = sizeof($results)/100*100;
				break;
			case 1:
				$spaces = sizeof($results)/150*100;
				break;
			case 2:
				$spaces = sizeof($results)/140*100;
				break;
			case 3:
				$spaces = sizeof($results)/102*100;
				break;
			default:
				$spaces = sizeof($results)/120*100;
		}
		echo $dates[$c]." - ".sprintf("%u",min(100,$spaces))." percent taken <br />";
	}
?>