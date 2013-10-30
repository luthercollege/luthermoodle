<?php
	require_once('../config.php');  // if run from outside as by cron
    require_once($CFG->dirroot.'/google/lib.php');
    require_once($CFG->dirroot.'/google/gauth.php');
    require_once("$CFG->dirroot/ce/ce_lib.php");

    // set up variables
	$templatename = 'Departmental Course Evaluation Report for Sample';
	define('CE_CELLS','R1C1');
	define('ALL_CELLS','R1C2');
	$colarray = cellarray('dept');
	$getcecells = array(5,7,2,28);  // boundaries of area to get
	$getacgcells = array(5,7,2,8);  // boundaries of area to get row 5-9 col 2-8
	$deptcecells = array(1,7,1,22);  // boundaries of area to get
	$deptacgcells = array(1,7,1,8);  // boundaries of area to get row 5-9 col 2-8
	$ceraw = 'ce_calcs';
	$acgraw = 'acg_calcs';
	$owner = 'course-evaluations@luther.edu';
	$term = '2013JT';
	$replacechars = array(':','/');

	//	$term = get_field('ce_term','name','status','current');
	$ce_collection = get_collection('courseevals',$owner); // main course evaluations collection id
	if (($termcollectionid = get_collection($term,$owner, $ce_collection)) == false) { // specific term subcollection id
		echo 'Report collection not setup';
		die;
	}
	$base_feed = 'https://docs.google.com/feeds/' . urlencode($owner) . '/private/full';

	// define the template spreadsheet from which all reports are copied
	$hyperlink = 'https://docs.google.com/a/luther.edu/spreadsheet/ccc?key=';
	$rel = 'self';
	$temp = get_sheet_link($owner, $templatename, 'self');
	$templatefilelink = strip_id($temp, 'nodoc');

	// get all the files in the report directory that are already created so we don't have to go get them all one by one
	$term_feed = get_doc_feed($owner, $termcollectionid, 1200);
	foreach ($term_feed->entry as $entry) {
		$term_titles[s($entry->title)] = $entry;
	}
	ksort($term_titles);

	// get all departments
	$depts = get_records('ce_dept_head');
	foreach ($depts as $dept) {


		$department = $dept->department; // store new department name
		$rows = array(); //  clear $rows
		$acgrows = array(); //  clear $rows

		$rel = 'edit';


		// create the (new) departmental report
		// if it exists, skip so we can fill in just what we need to refresh
		$reportname = "Departmental Course Evaluation Report for $department - $term";
		if (array_key_exists($reportname, $term_titles)) {
			continue;
		}

		// get all evaluation reports by department
		$sql = "SELECT CONCAT('CE-Report for ', fullname, ' - ', teacherfullname) as reportname FROM mdl_ce_course
			WHERE term = '$term'
			AND department = '$dept->department'";
 		$ce_recs = get_records_sql($sql);
		if ($ce_recs === false) {
			continue;
		}

		// check if any report are available for this department
		foreach ($ce_recs as $name=>$value) {
			if (array_key_exists($name, $term_titles)) {
				break;
			}
		}
		if (!array_key_exists($name, $term_titles)) {
			continue;
		}

		$query = copy_spreadsheet($reportname,$owner, $templatefilelink);
		$deptfeed = simplexml_load_string($query->response);  // need this so we can get the id for the new sheet
		$success = add_file_tocollection($base_feed, $termcollectionid, $deptfeed->id,$owner); // place it in the correct collection

		// get departmental spreadsheet key
		$deptkey = strip_id($deptfeed->id,'nodoc');

		// remove from the root folder
		delete_file_fromcollection($base_feed, 'folder%3Aroot', $deptkey, $owner);

		// get the worksheet feed for this spreadsheet so we can get the worksheet id
		$rel = 'http://schemas.google.com/spreadsheets/2006#worksheetsfeed';
		$deptworksheetsfeed = get_href_noentry($deptfeed, $rel);

		// get cells feed for worksheet
		$rel = 'http://schemas.google.com/spreadsheets/2006#cellsfeed';
		$deptcellsfeed = get_feed($deptworksheetsfeed, $owner);

		$deptsheetid = get_sheetid($deptcellsfeed,'CE-Report'); // worksheet id for departmental ce-report
		$deptacgsheetid = get_sheetid($deptcellsfeed,'ACG-Report'); // worksheet id for departmental ce-report
		$cellsfeed = 'https://spreadsheets.google.com/feeds/cells/' . $deptkey . '/' . $deptsheetid . '/private/full/R1C2';

		// print out the reportname for use throughout the sheets
		$success = update_import_cell($cellsfeed, $owner, $deptkey, $deptsheetid, 1, 2, $reportname, $cellsfeed);

		foreach($ce_recs as $rec) { // cycle through the reports for this department, getting roll-up lines and number of responses for each, placing them in an array
//			$rec->reportname = str_replace('&', 'and', $rec->reportname);
//			$rec->reportname = str_replace($replacechars, '-', $rec->reportname);
			if (!array_key_exists($rec->reportname, $term_titles)) {
				continue;
			}
/*
			$evalbasefeed = 'https://spreadsheets.google.com/feeds/worksheets/' . $key . '/private/full';

			//		$evalworksheetsfeed = get_href_noentry($evalbasefeed, $rel);
*/
			$rel = 'http://schemas.google.com/spreadsheets/2006#worksheetsfeed';
			$evalbasefeed = get_href_noentry($term_titles[$rec->reportname], $rel);
			$key = strip_id($term_titles[$rec->reportname]->id, 'noinclude');

			// get the worksheet feed for this spreadsheet so we can get the worksheet id
			$evalworksheetsfeed = get_feed($evalbasefeed, $owner);

			// ***** store CE-Report summary row contents to array
			$sheetid = get_sheetid($evalworksheetsfeed,'CE-Report'); // worksheet id for ce-report
			$ce_cellbase = 'https://spreadsheets.google.com/feeds/cells/' . $key . '/' . $sheetid . '/private/full/';
			$evalteach = substr($rec->reportname,13,100);
//			$evalteach = $rec->shortname . ' - ' . $rec->teacherfullname;
			$rows[$evalteach] = get_cell_contents($ce_cellbase, $owner, $getcecells, $evalteach);

			// ***** store ACG-Report summary row contents to array
			$acgsheetid = get_sheetid($evalworksheetsfeed,'ACG-Report'); // worksheet id for ce-report
			$acg_cellbase = 'https://spreadsheets.google.com/feeds/cells/' . $key . '/' . $acgsheetid . '/private/full/';
			$acgrows[$evalteach] = get_cell_contents($acg_cellbase, $owner, $getacgcells, $evalteach);

			// TODO: extra code for creating report name into hyperlink
			//		$rows[$evalteach]['A5'] = '<a href="' . $hyperlink . $key . '" target="_blank">' . $evalteach . '</a>';
			/*
			 $cellfeed = get_cell_feed($cellbase, $owner, $cells);
			*/
		}

		// if change in department and not first department write out the contents of $rows to departmental report
		if ($rows !== null && $acgrows !== null) {
			ce_writedeptresults($rows, $owner, $deptsheetid, $deptkey,$colarray);
			ce_writedeptresults($acgrows, $owner, $deptacgsheetid, $deptkey, $colarray);

			/*
			 * BEGIN PROCESS OF COPYING VALUES SO SHEETS DON'T RELY ON FORMULAS
			 */
/*
			$deptce_cellbase = 'https://spreadsheets.google.com/feeds/cells/' . $deptkey . '/' . $deptsheetid . '/private/full/';
			$deptacg_cellbase = 'https://spreadsheets.google.com/feeds/cells/' . $deptkey . '/' . $deptacgsheetid . '/private/full/';

			// ***** store CE-Report summary row conotents to array
			$rows = get_cell_contents($deptce_cellbase, $owner, $deptcecells);

			// ***** store ACG-Report summary row contents to array
			$acgrows = get_cell_contents($deptacg_cellbase, $owner, $deptacgcells);

			// write out value to same sheets from which we got them through the formulas
			ce_write_archiveresults($rows, $owner, $deptsheetid, $deptkey,$colarray);
			//		truncate_sheet($sheetid, $owner, $key, $ce_maxrow, $ce_editlink);
			ce_write_archiveresults($acgrows, $owner, $deptacgsheetid, $deptkey, $colarray);
			//		truncate_sheet($acgsheetid, $owner, $key, $acg_maxrow, $acg_editlink);

			// remove all old sheets including hidden ones
			$ce_calcsheetid = get_sheetid($deptcellsfeed, $ceraw); // worksheet id for ce-report
			$cellbase = 'https://spreadsheets.google.com/feeds/worksheets/' . $deptkey . '/private/full/' . $ce_calcsheetid;
			$success = delete_sheet($owner, $cellbase);
			$acg_calcsheetid = get_sheetid($deptcellsfeed, $acgraw); // worksheet id for ce-report
			$cellbase = 'https://spreadsheets.google.com/feeds/worksheets/' . $deptkey . '/private/full/' . $acg_calcsheetid;
			$success = delete_sheet($owner, $cellbase);


			// make copy of the spreadsheet in the original
//			$origfilelink = strip_id(get_sheet_link($owner, $reportname, 'self'), 'nodoc');
			$query = copy_spreadsheet($reportname . '-orig',$owner, $deptkey);
			$feed = simplexml_load_string($query->response);  // need this so we can get the id for the new sheet

			// place it in the correct collection
			$success = add_file_tocollection($base_feed, $termorigcollectionid, $feed->id,$owner);

			// get copied spreadsheet key
			$copiedkey = strip_id($feed->id,'nodoc');
*/

			// remove from the root folder
//			delete_file_fromcollection($base_feed, 'folder%3Aroot', $copiedkey, $owner);
			// assign permsissions to the editable version
			ce_assignpermissions($department, $owner, $term, $deptkey, $reportname, null);
		}
	}
?>