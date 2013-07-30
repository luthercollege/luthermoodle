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
	global $success;
	$owner = 'course-evaluations@luther.edu';
	$term = '2012FA';

	// get term for which courses are needed
	$currenttermid = get_currentterm($term);

	// get any subcategories for this term recursively
	$cats = get_cats($currenttermid->id);

	// check if $CFG->dataroot/courseevals exists, if not, create the directory
	$evaldirectory = $CFG->dataroot.'/courseevals/' . $term;
	if (!file_exists($evaldirectory)) {
		mkdir($evaldirectory, 0777, true);
	}

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

	// get all questionnaires that are to be considered for exporting
	$sql = "SELECT DISTINCT q.*, c.idnumber
			FROM {$CFG->prefix}questionnaire q
			LEFT JOIN {$CFG->prefix}questionnaire_attempts as qa on qa.qid = q.id
			JOIN {$CFG->prefix}course as c on c.id = q.course
			WHERE qa.id IS NOT NULL
			AND q.name NOT LIKE 'ALL COLLEGE%'
			AND c.category IN($cats)
			AND q.qtype = 5";
//			AND c.id = 14231
	$questionnaires = get_records_sql($sql);

	foreach ($questionnaires as $que) {

		// create an export for each questionnaire and store in /moodledata/courseevals/[current term]
		$filename = str_replace('&', 'and', str_replace('/','-',str_replace(':','-',$que->name))) . '.csv';
		if (substr($filename,0,11) == 'All_College') {
			continue;
		}

		// if csv data file doesn't exist, create it
		if (array_key_exists($filename, $data_titles)) {
			continue;
		} else {
			$getfile = $CFG->dataroot.'/courseevals/' . $term . '/' . $filename;
			$target = fopen($getfile,'w');
			$url = $CFG->wwwroot . '/mod/questionnaire/report.php?instance=' . $que->id . '&sid=' . $que->sid . '&action=\'dcsv\'&runcron=1&choicecodes=0';
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_FILE, $target);
			curl_exec($ch);
			curl_close($ch);
			fclose($target);

			// send export file up to google, will be created if not exist
			// place it in the correct collection
			$filecontents = str_replace('"',"'",file_get_contents($getfile));
			$feed = send_cefile_togoogle($filename, $filecontents,$owner);
			if (!$success) {
				continue;
			}
			$rawkey = strip_id($feed->id,'nodoc');

			// place it in the correct collection
			add_file_tocollection($base_feed, $termdatacollectionid, $feed->id,$owner);

			// remove from the root folder
			delete_file_fromcollection($base_feed, 'folder%3Aroot', $rawkey, $owner);
		}
	}
?>