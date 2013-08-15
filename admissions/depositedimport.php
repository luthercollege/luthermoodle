<?php // $Id$
// Import enrollments from csv file

	define('CLI_SCRIPT', true);
	require_once(dirname(dirname(__FILE__)).'/config.php'); // global moodle config file.
    require_once("$CFG->libdir/blocklib.php");
    require_once("$CFG->dirroot/group/lib.php");
    global $DB;
	$regcourse = 'REG-PLACE-2013';
	$regcourseid = $DB->get_field('course', 'id', array('idnumber'=>$regcourse));
	$transfercourse = 'ADM-TRANSFER-2013';
//	$transfercourseid = $DB->get_field('course', 'id', array('idnumber'=>$transfercourse));
//	$roleid = $DB->get_field('role', 'id', array('shortname'=>'student'));
//	$regcontext = context_course::instance($regcourseid);
//	$transfercontext = context_course::instance($transfercourseid);
	$targetfile = "$CFG->dirroot/admissions/old_rosters/katie_deposited" . mktime() . ".csv";
    $sourcefile = "$CFG->dirroot/admissions/katie_deposited.csv";
    copy($sourcefile,$targetfile);
	$results = $DB->delete_records('katie_deposited');
	echo date('Y-m-d-h:i') . "\n";
	if(!$importFile = fopen($sourcefile,'r')) {
        error("ERROR: File katie_deposited.csv doesn\'t exist");
    }
    $groups = array();
    // get array of group ids and names so we can add students to their group
    $sql = "SELECT g.id, g.name from mdl_groups g
    		JOIN mdl_course c on c.id = g.courseid
    		WHERE c.idnumber = '$regcourse'";
    $results = $DB->get_records_sql($sql);
    foreach($results as $row) {
    	$groups[$row->name] = $row->id;
    }
	$cur_records = array();
	$password = 'xxxxxxxx';
    $import_names = array('Student_ID','First_Name','Last_Name','Address_Line_1','Address_Line_2','City','State','Zip',
    		'Email_Address','Marital_Status','Current_High_School','Rank_Numerator','Rank_Denominator',
    		'Rank_Percentage','HS_GPA','ACT_English','ACT_Math','ACT_Read','ACT_Science','ACT_Comp',
    		'Admissions_Counselor','First_Generation','Pell_Eligible','Risk1','Risk2','IEP_501','Home_Phone',
    		'Transfer','International','SAT_Writing','SAT_Math','SAT_Reading','SAT_Comp','Ethnicity','Incoming_Email');
    unlink("CFG->dataroot/1/FYstudentEnrollmentErrors.csv");
    $errorFile = fopen("$CFG->dataroot/1/FYstudentEnrollmentErrors.csv",'w');

	// peel off the header line
	$importTemp = fgetcsv($importFile,1000,",");
    while (($importTemp = fgetcsv($importFile,1000,",")) <>  FALSE) {
    	$importstudent = array_combine($import_names,$importTemp);
		if (empty($importstudent['Email_Address'])) {
			continue;
		}
		// katie_deposited
		if ($success = $DB->insert_record('katie_deposited', $importstudent, true)) { // create katie_deposited record
			echo 'added ' . $importstudent['First_Name'] . ' ' . $importstudent['Last_Name'] . '\n';
		}

		// create a moodle user if doesn't already exist for some reason
		$userinfo = split("@", $importstudent['Email_Address']);
		if (!$isfound = $DB->get_record('user', array('username'=>$userinfo[0]))) {
			if ($success = create_user_record($userinfo[0], $password, 'ldap')) {
//			if ($success = create_user_record($userinfo[0], $password, 'manual')) {
				$importstudent['id'] = $success->id;
				echo "$userinfo[0] created in katie\n <br />";
			}
		} else {
			$importstudent['id'] = $isfound->id;
			echo "$userinfo[0] ALLREADY CREATED in katie\n <br />";
		}
		$enroll->username = $userinfo[0];
		$enroll->courseid = ($importstudent['Transfer'] == 'FR') ? $regcourse : $transfercourse;
		$enroll->role = 'student';
		$result = $DB->insert_record('datatelenrollments',$enroll,true);


//		$contextid = ($importstudent['Transfer'] == 'FR') ? $regcontext : $transfercontext;
//		role_assign($roleid, $importstudent['id'], $contextid);

		// group_members records
		if ($importstudent['Transfer'] == 'FR') {
			$member->userid = $importstudent['id'];
			if (!array_key_exists($importstudent['Admissions_Counselor'], $groups)) {
				$data = new stdClass();
				$data->courseid = $regcourseid;
				$data->name = $importstudent['Admissions_Counselor'];
				$data->descriptionformat = 1;
				$data->desccription = '';
				$data->idnumber = '';
				$member->groupid = groups_create_group($data);
				$groups[$data->name] = $member->groupid;
			} else {
				$member->groupid = $groups[$importstudent['Admissions_Counselor']];
			}
			$member->timeadded = time();
			if (!$groupexists = $DB->get_record('groups_members', array('userid'=>$member->userid,'groupid'=>$member->groupid))) {
				$success = groups_add_member($member->groupid, $importstudent['id']);
			}
		}
	}
    fclose($importFile);
    fclose($errorFile);
//    unlink($sourcefile);

    exit;

?>