<?php
/*
 * WARNING!!! This report cannot be run if debugging is turned on in katie
 */

	// TODO: rewrite as a class
	// TODO: are we now getting all of the cross-referenced courses correctly?
	require_once('../config.php');
    require_once($CFG->dirroot.'/google/lib.php');
    require_once($CFG->dirroot.'/google/gauth.php');
    require_once("ce-lib.php");

    // set up variables
	define('TEMPLATENAME', 'CE-Report for 00000-Template');
	define('CE_ID_CELLS','A1');
	define('CE_NAME_CELLS','J1');
	define('ACG_DATA_CELLS','M1');
	define('ALL_CELLS','B1');
	define('ACGID',977);
	define('ACGSID',1023);
	define('CRED_CELLS','F1');
	define('RESP_RATE_CELLS', 'C1');
	$colarray = cellarray('archive');
	global $success;

	$owner = 'course-evaluations@luther.edu';
	$term = '2013JT';

	//	$term = get_field('ce_term','name','status','current');
	$ce_collection = get_collection('courseevals',$owner); // main course evaluations collection id
	if (($termcollectionid = get_collection($term,$owner, $ce_collection)) == false) { // specific term subcollection id
		echo 'Report collection not setup';
		die;
	}
	if (($termdatacollectionid = get_collection($term . '-data',$owner, $ce_collection)) == false) {  // specific term subcollection id
		echo 'Data file collection not setup';
		die;
	}
	$base_feed = 'https://docs.google.com/feeds/' . urlencode($owner) . '/private/full';
	$data_feed = get_doc_feed($owner, $termdatacollectionid, 1200);
	foreach ($data_feed->entry as $entry) {
		$data_titles[s($entry->title)] = $entry;
	}
	ksort($data_titles);
	$term_feed = get_doc_feed($owner, $termcollectionid, 1200);
	foreach ($term_feed->entry as $entry) {
		$term_titles[s($entry->title)] = $entry;
	}
	ksort($term_titles);


	// get the id for the acg data sheet from which all the acg reporting comes, input into acg_raw sheet
	$acgdatasheet = 'All_College_Goals_for_Student_Learning_' . $term;
	$acgdatasheetid = strip_id(get_sheet_link($owner, $acgdatasheet, 'edit'),'nodoc');
	$sheetid = 0;

	// read in the csv file
    $importnames = array('course_sections', 'section_name','short_title','synonym',
            'current_status','primary_section','cross_listed_section_1','cross_listed_section_2','cross_listed_section_3',
            'cross_listed_section_4','cross_listed_section_5','cross_listed_section_6','term','description1','department',
            'start_date','end_date','added_on','changed_on','status_date','subj_chg_date','inst_methods','credit_hours');
    $importFile = fopen("$CFG->dirroot/course/moodlecourseautoimport.csv",'r');
    // PEEL OFF THE HEADER LINE
    $importsyn = array();
    $importid = array();
    $importtemp = fgetcsv($importFile,1000,",");
    while (($importtemp = fgetcsv($importFile,1000,",")) !==  FALSE) {
		// only add crosslisted non-primary courses to this array
    	if ($importtemp[5] !== '' && $importtemp[0] !== $importtemp[5]) {
	    	$importsyn[$importtemp[3]] = array_combine($importnames,$importtemp); // creates an array keyed on the course synonym number
    	}
		// array used to get synonym for cross-listed courses
    	$importid[$importtemp[0]] = array_combine($importnames,$importtemp); // creates an array keyed on the course id number
    	$synkey[$importtemp[3]] = array_combine($importnames,$importtemp); // creates an array keyed on the course id number
    }
    fclose($importFile);


/*

	// TODO: create an export for All College Goals questionnaire and store in /moodledata/courseevals/[current term]
	$filename = 'All_College_Goals_for_Student_Learning';
	$getfile = $CFG->dataroot.'/courseevals/' . $term . '/' . $filename;
	$target = fopen($getfile,'w');
	$url = $CFG->wwwroot . '/mod/questionnaire/report.php?instance=' . ACGID . '&sid=' . ACGSID . '&action=\'dcsv\'&runcron=1&choicecodes=0';
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_FILE, $target);
	curl_exec($ch);
	fclose($target);

	// send export file up to google, will be created if not exist
	$filecontents = file_get_contents($getfile);
	$rawlink = send_cefile_togoogle($filename, $filecontents,$owner, $termdatacollectionid);
*/

    // get term for which courses are needed
	$currenttermid = get_currentterm($term);

	// get any subcategories for this term recursively
	$cats = get_cats($currenttermid->id);

	// define the template spreadsheet from which all reports are copied
	$templatefilelink = strip_id(get_sheet_link($owner, TEMPLATENAME, 'self'), 'nodoc');

	// check if $CFG->dataroot/courseevals exists, if not, create the directory
	$evaldirectory = $CFG->dataroot.'/courseevals/' . $term;
	if (!file_exists($evaldirectory)) {
		mkdir($evaldirectory, 0777, true);
	}

	// get all questionnaires that are to be considered for exporting
/*
	$sql = "SELECT DISTINCT q.*, c.idnumber
			FROM {$CFG->prefix}questionnaire q
			LEFT JOIN {$CFG->prefix}questionnaire_attempts as qa on qa.qid = q.id
			JOIN {$CFG->prefix}course as c on c.id = q.course
			WHERE qa.id IS NOT NULL
			AND q.name NOT LIKE 'ALL COLLEGE%'
			AND c.category IN($cats)
			AND q.qtype = 5";
	$questionnaires = get_records_sql($sql);
*/
	foreach ($data_titles as $filename=>$data) {
		// create an export for each questionnaire and store in /moodledata/courseevals/[current term]
		$filename = str_replace('&', 'and', str_replace('/','-',str_replace(':',' -',$filename)));
		if (substr($filename,0,11) == 'All_College') {
			continue;
		}
		$synonym = substr($filename, strpos($filename, 'for') + 4, 5);
		// check to see if xreffed course whereby we need to substitute the primary synonym number for the course's synonym
		if (array_key_exists($synonym, $importsyn)) {
			$synalternate = $importid[$importsyn[$synonym]['primary_section']]['synonym'];
//			$filename = str_replace($synonym, $importid[$importsyn[$synonym]['primary_section']]['synonym'], $filename);
		} else {
			$synalternate = null;
		}

		// if csv data file doesn't exist, create it
//		if (array_key_exists($filename, $data_titles)) {
			$rawlink = get_href_noentry($data_titles[$filename],'self');
//		} else {
//			continue;
//		}

		// strip key from newly imported file
		$rawkey = strip_id($rawlink,'nodoc');


		// check to see if a report file already exists, will be created if not exist
		// what happens if we want to overwrite the report out there? Need to delete it first.
		$reportname = substr(str_replace('Course Evaluation','CE-Report',$filename),0,-4);
		if (array_key_exists($reportname, $term_titles)) {
			continue;
		}

		// if we get this far we're creating a report
		$query = copy_spreadsheet($reportname,$owner, $templatefilelink);
		$feed = simplexml_load_string($query->response);  // need this so we can get the id for the new sheet

		$searchsyn = substr($reportname,14,5);

		// this doesn't work for hyphenated last names of teachers, what's next?
		$searchteach = trim(substr($reportname,strrpos($reportname,'-')+1,100));
//		$sql = "SELECT * FROM mdl_ce_course WHERE LEFT(fullname,5) = '$searchsyn' AND teacherfullname = '$searchteach' ";
		$sql = "SELECT * FROM mdl_ce_course
				WHERE (LEFT(fullname,5) = '$searchsyn'
				OR LEFT(fullname,5) = '$synalternate') ";
		$ce_recs = get_records_sql($sql);
		foreach ($ce_recs as $ce_rec) {
			if (strpos($reportname, $ce_rec->teacherfullname)) {
				break;
			}
		}
//		$ce_rec->qid = $que->id;
		$department = $ce_rec->department; // needed for assigning department heads access to spreadsheet

		// place it in the correct collection
		$success = add_file_tocollection($base_feed, $termcollectionid, $feed->id,$owner);

		// remove from the root folder
		delete_file_fromcollection($base_feed, 'folder%3Aroot', strip_id($feed->id), $owner);

		// get the worksheet feed for this spreadsheet so we can get the worksheet id
		$rel = 'http://schemas.google.com/spreadsheets/2006#worksheetsfeed';
		$worksheetsfeed = get_href_noentry($feed, $rel);

		// get cells feed for worksheet
		$rel = 'http://schemas.google.com/spreadsheets/2006#cellsfeed';
		$cellsfeed = get_feed($worksheetsfeed, $owner);

		// get edit feed for report spreadsheet
		$key = strip_id(get_href_noentry($feed, 'edit'),'nodoc');

		/*
		 * BATCH PROCESS THE VARIOUS CELLS WE NEED TO FILL FOR THE FORMULAS TO WORK RIGHT
		 */
		// ***** update CE-Raw sheet with id for raw data workbook
		$sheetid = get_sheetid($cellsfeed,'CE-Raw'); // worksheet id for ce-report
		$base_cells_feed = 'https://spreadsheets.google.com/feeds/cells/' . $key . '/' . $sheetid . '/private/full';
		$cerawcells[CE_ID_CELLS] = $rawkey;

		// get number of students in this section
//		$studentcount = sizeof(get_roster($que->course, 1,'student'));
		$studentcount = sizeof(get_roster($ce_rec->courseid, 1,'student'));
		$cerawcells[RESP_RATE_CELLS] = $studentcount;

		// ***** update CE-Raw sheet with credit hours
		$cred_hours = $synkey[$synonym]['credit_hours'];
//		$cred_hours = $importid[$que->idnumber]['credit_hours'];
		$cerawcells[CRED_CELLS] = $cred_hours;

		// ***** update CE-Raw sheet with name of workbook
		$cerawcells[CE_NAME_CELLS] = $reportname;

		// ***** update CE-Raw sheet with name of workbook
		$cerawcells[ACG_DATA_CELLS] = $acgdatasheetid;

		write_batch($cerawcells, $owner, $base_cells_feed);

		/*
		 * BEGIN PROCESS OF COPYING VALUES SO SHEETS DON'T RELY ON FORMULAS
		 * THIS IS NOW HANDLED BY APPS SCRIPTING
		 */
/*
		$sheetid = get_sheetid($cellsfeed,'CE-Report'); // worksheet id for ce-report
		$acgsheetid = get_sheetid($cellsfeed,'ACG-Report'); // worksheet id for ce-report

		// set cells feed urls
		$ce_cellbase = 'https://spreadsheets.google.com/feeds/cells/' . $key . '/' . $sheetid . '/private/full/';
		$acg_cellbase = 'https://spreadsheets.google.com/feeds/cells/' . $key . '/' . $acgsheetid . '/private/full/';

		// set coordinates for data to be retrieved (different for departmental reports from ce reports
		$cells = array(8,306,2,2);
		$ce_maxrow = get_last_data_row($ce_cellbase, $owner, $cells);
		$cells = array(1,$ce_maxrow,1,29);
		$acgcells = array(9,306,2,2);
		$acg_maxrow = get_last_data_row($acg_cellbase, $owner, $acgcells);
		$acgcells = array(1,$acg_maxrow,1,8);
		$ceraw = 'CE-Raw';
		$acgraw = 'ACG-Raw';

		// ***** store CE-Report summary row contents to array
		$rows = get_cell_contents($ce_cellbase, $owner, $cells);

		// ***** store ACG-Report summary row contents to array
		$acgrows = get_cell_contents($acg_cellbase, $owner, $acgcells);

		// write out value to same sheets from which we got them through the formulas
		ce_write_archiveresults($rows, $owner, $sheetid, $key,$colarray);
//		truncate_sheet($sheetid, $owner, $key, $ce_maxrow, $ce_editlink);
		ce_write_archiveresults($acgrows, $owner, $acgsheetid, $key, $colarray);
//		truncate_sheet($acgsheetid, $owner, $key, $acg_maxrow, $acg_editlink);

		// remove all old sheets including hidden ones
		$ce_calcsheetid = get_sheetid($cellsfeed, $ceraw); // worksheet id for ce-report
		$cellbase = 'https://spreadsheets.google.com/feeds/worksheets/' . $key . '/private/full/' . $ce_calcsheetid;
		$success = delete_sheet($owner, $cellbase);
		$acg_calcsheetid = get_sheetid($cellsfeed, $acgraw); // worksheet id for ce-report
		$cellbase = 'https://spreadsheets.google.com/feeds/worksheets/' . $key . '/private/full/' . $acg_calcsheetid;
		$success = delete_sheet($owner, $cellbase);

		// make copy of the spreadsheet in the original
		// TODO: separate this off as a function to be used for any type of report
		$origfilelink = strip_id(get_sheet_link($owner, $reportname, 'self'), 'nodoc');
		$query = copy_spreadsheet($reportname . '-orig',$owner, $origfilelink);
		$feed = simplexml_load_string($query->response);  // need this so we can get the id for the new sheet

		// store id for sheet in ce_courses
		$ce_rec->docid = strip_id($feed->id);
		$success = update_record('ce_course', $ce_rec);

		// place it in the correct collection
		$base_feed = 'https://docs.google.com/feeds/' . urlencode($owner) . '/private/full';
		$success = add_file_tocollection($base_feed, $termcollectionid, $feed->id,$owner);

		// remove from the root folder
		delete_file_fromcollection($base_feed, 'folder%3Aroot', strip_id($feed->id), $owner);

		// get teacher being evaluated
//		$evalname = trim(substr($reportname,strrpos($reportname,'- ')+1,100));
//		$select = " CONCAT(firstname,' ',lastname) = '$evalname' ";
*/
		// assign permsissions to the editable version
		$select = " username = '$ce_rec->teacherusername' ";
		$teacher = get_record_select('user', $select);
		ce_assignpermissions($department, $owner, $term, $key, $reportname, $teacher);
	}
?>