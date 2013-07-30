<?php
    require_once(dirname(dirname(__FILE__)).'/config.php'); // global moodle config file.
    require_once("$CFG->dirroot/course/lib.php");
    require_once("$CFG->dirroot/ce/ce-lib.php");

	// get what datatel says are official classes so we have that additonal information

    // get term for which courses are needed
    $term = 	'2013JT';
    $currentterm = 	get_record('course_categories','name',$term);

	// CLEAN OUT ALL THE APOSTRAPHES IN THE FILE
    $importFile = file_get_contents("$CFG->dirroot/course/moodlecourseautoimport.csv");
    $replaceFile = str_replace("'","",$importFile);

    // make certain this file is writeable so it gets rid of the apostrophes
    if (!$importFile = fopen("$CFG->dirroot/course/moodlecourseautoimport.csv",'w')) {
    	error("Can't open import file for writing");
    }
    $success = fwrite($importFile,$replaceFile);
    fclose($importFile);
    // END OF APOSTRAPHE CLEANOUT

	// read in the csv file
    $importnames = array('course_sections', 'section_name','short_title','synonym',
            'current_status','primary_section','cross_listed_section_1','cross_listed_section_2','cross_listed_section_3',
            'cross_listed_section_4','cross_listed_section_5','cross_listed_section_6','term','description1','department',
            'start_date','end_date','added_on','changed_on','status_date','subj_chg_date','inst_methods','credit_hours');
        $importFile = fopen("$CFG->dirroot/course/moodlecourseautoimport.csv",'r');
    // PEEL OFF THE HEADER LINE
    $importperm = array();
    $importtemp = fgetcsv($importFile,1000,",");
    while (($importtemp = fgetcsv($importFile,1000,",")) !==  FALSE) {
    	$importperm[$importtemp[0]] = array_combine($importnames,$importtemp); // creates an array keyed on the course section (idnumber)

    	// remove all the non-current-term sections
    	if ($importperm[$importtemp[0]]['term'] !== $currentterm->name) {
    		unset($importperm[$importtemp[0]]);
    	}
    }
    ksort($importperm);
    fclose($importFile);
	// done with csv file

	// get list of courses that are cross-referenced
	$xreffields = 'xrefcourseid,courseid';
	$xref = get_records('datatelenrollxref',null,null,null, $xreffields);

	// get list of faculty members who are handled special through ATP evaluations
	$atps = get_records('ce_atp','term',$currentterm->name, null, 'username,evaltype');
	// another note, how are instructors stored in role_assignments for metacourses?

	// gets all the courses in course table in any of the categories and subcategories for the current category
	// get any subcategories for this term recursively all so we have the correct Moodle course table id for the course section
	$cats = get_cats($currentterm->id);
	$select = " category IN($cats) ";
	// for course array keyed on idnumber
	$fields = 'idnumber, id, category, fullname, shortname, summary, startdate, defaultgroupingid';
	$targets = get_records_select('course',$select, null, $fields); // courses from moodle
	ksort($targets);

	// TODO: see if we need idtargets and idfields
	// for course array keyed on courseid
	$idfields = 'id, idnumber, category, fullname, shortname, summary, startdate, defaultgroupingid';
	$idtargets = get_records_select('course',$select, null, $idfields); // courses from moodle keyed on id
	ksort($idtargets);
	unset($success);

	foreach ($importperm as $idnumber => $course) {
		if ($course['current_status'] <> 'A') {
			continue;
		} elseif (trim($course['primary_section']) == '') { // not cross-referenced, let pass
			$target = $targets[$idnumber];
		} elseif ($idnumber != trim($course['primary_section'])) { // not the primary course so skip
			continue;  // something's wrong because a xref course hasn't been properly xreffed
		} elseif (trim($course['primary_section']) !== '') { // cross-referenced course
			if (is_null($target = $targets[$course['cross_listed_section_1']])) { // get moodle course record of primary for xref course
				echo 'Cross-referenced course not found in moodle course table: ' . $idnumber . ': ' . $course['section_name'] . '<br />';
				continue;  // something's wrong because a xref course hasn't been properly xreffed
			}
		} elseif (is_null($target = $targets[$course['course_sections']])) { // get moodle course record for the import record
			echo 'Imported course not found in moodle course table: ' . $idnumber . ': ' . $course['section_name'] . '<br />';
			continue;  // something's wrong because a valid import course didn't become a moodle course
		}

		// always need to remanufacture the shortname and fullname of the course based on the import record
		$target->shortname = $course['synonym'] . '-' . $course['section_name'];
		$target->fullname = $target->shortname .' (' . $currentterm->name . ') ' . str_replace(':','-',$course['short_title']);

		// end date only exists in the datatel feed
		$target->term = $currentterm->name;
		$target->department = $course['department'];
		$target->startdate = strtotime($course['start_date']);
		$target->enddate = strtotime($course['end_date']);
//		if (is_null($target->id)) {
			$target->courseid = $target->id;
			unset($target->id);
//		}

		// insert a record for every teacher
		$teachers = get_teachers($target->courseid);

		// unique index disallows duplication of records, fullname of course plus teacherusername
		foreach ($teachers as $teacher) {
			$target->teacherid = $teacher->id;
			$target->docid = null;
			$target->teacherusername = $teacher->username;
			$target->teacherfullname = str_replace("'",'',$teacher->fullname);
			if (array_key_exists($target->teacherusername, $atps)) {
				$target->evaluationtype = $atps[$target->teacherusername]->evaltype;
			} else {
				$target->evaluationtype = 'standard';
			}
			if (!get_record('ce_course','courseid', $target->courseid, 'teacherid', $teacher->id, 'shortname', $target->shortname)) {
				$success = insert_record('ce_course', $target);
			}
			if (!$success) {
				echo 'FAILURE IN CREATING ce_course RECORD FOR ' . $target->fullname . ' WITH INSTRUCTOR: ' . $target->teacherfullname . '<br />';
			} else {
				echo 'Successfully created ce_course RECORD FOR ' . $target->fullname . ' WITH INSTRUCTOR: ' . $target->teacherfullname . '<br />';
			}
		}
	}
?>