<?php 
//	echo $userparam/;
    require_once("../config.php");
    require_once("lib.php");
    require_once("../lib/dmllib.php");
	global $USER;
	$starttime = mktime(8,15);
	$endtime = mktime(8,20);
	$now = time();
	$coursenum = 8096;
	echo "$starttime <br /> $endtime <br />". time();
	if ($now < $startime or $now > $endtime) {
//		exit;
	} else {	
		echo "TRUE";
	}
	$sql = "SELECT g.name, COUNT(DISTINCT gr.userid) 
		FROM mdl_groups g
		LEFT JOIN mdl_groups_members m ON g.id=m.groupid
		LEFT JOIN mdl_user u ON m.userid=u.id
		LEFT JOIN mdl_grade_grades gr ON m.userid=gr.userid
		WHERE g.courseid = 8096 
		AND NOT ISNULL( gr.finalgrade)
		AND u.deleted <> 1   
		AND g.name <> 'Jon Lund'
		GROUP BY g.name";
$partresults = mysql_query($sql);
	$sql = "SELECT g.name, COUNT(m.userid), COUNT(a.choiceid) 
		FROM mdl_groups g
		LEFT JOIN mdl_groups_members m ON g.id=m.groupid
		LEFT JOIN mdl_user u ON m.userid=u.id
		LEFT JOIN mdl_choice_answers a ON m.userid=a.userid
		WHERE g.courseid = 8096  
		AND (a.choiceid=799 OR isnull(a.choiceid))    
		AND u.deleted <> 1 
		AND g.name <> 'Jon Lund'
		GROUP BY g.name";
	$completedresults = mysql_query($sql);
	$counselorstats = 0;
	$limit = mysql_num_rows($completedresults);
	$a = 0;
	$posthtml = '<table width = "80%" border="1"><tr><td width="15%" align="center">Counselor</td><td width="10%" align="center">Total Deposited</td><td width="10%" align="center">Completed All</td><td width="10%" align="center">Complete Percentage</td><td width="10%" align="center">Partially Completed</td></tr>';
	for ($a = 0; $a < $limit; $a++) {
		$comptotal += mysql_result($completedresults,$a,2);
		$deptotal += mysql_result($completedresults,$a,1);
		$parttotal += mysql_result($partresults,$a,1);
		$partstat = max(mysql_result($partresults,$a,1)-mysql_result($completedresults,$a,2),0);
		$counselorstats = mysql_result($completedresults,$a,2)/ mysql_result($completedresults,$a,1) * 100;
		$posthtml .= '<tr><td>'. mysql_result($completedresults,$a,0). '</td><td align="center">'. mysql_result($completedresults,$a,1). '</td><td align="center">'. mysql_result($completedresults,$a,2).'</td><td align="center">'. number_format($counselorstats,0). '%</td><td align="center">'. $partstat. '</td align="center"></tr>';
	}
	$parttotal -= $comptotal;
	$posthtml .= '<tr></tr><tr><td>TOTAL</td><td align="center">'. $deptotal. '</td><td align="center">'. $comptotal. '</td><td align="center">'. number_format($comptotal / $deptotal * 100,0). '%</td><td align="center">'. $parttotal. '</td></tr></table>';
//	$posttext = "$donesomething students have completed some part of the pre-registration process on KATIE".chr(10).chr(13)."From that number, $pickeddate students have picked their date for registration";
//	echo $posttext;
	$postsubject = "Pre-Registration Statistics for ".date("l, F jS, Y");
	$posttext = "";
	$teacher = get_record('user','username','prereg');
	$puffuser = get_record('user','username','puffro01');
//	echo "$teacher->email <br />";
//	echo "$puffuser->email <br />";
	if (! email_to_user($teacher, $puffuser, $postsubject, $posttext, $posthtml)){  // If it fails, oh well, too bad.
		echo "Error: Course Archive: Could not send out mail for id $form->shortname to user $newteacher->userid ($user->email)\n";
	}

?>