<?php 
	require_once('../config.php');
    require_once($CFG->dirroot.'/google/lib.php');
    require_once($CFG->dirroot.'/google/gauth.php');
    require_once("ce_lib.php");
    
    // set up variables
	define('TEMPLATENAME', 'CE-Report for 53395-Template');
	define('CE_ID_CELLS','1:1');
	define('CE_NAME_CELLS','1:1');
	define('ALL_CELLS','1:2');
	define('ACGID',977);
	define('ACGSID',1023);
	define('CRED_CELLS','1:6');
	define('RESP_RATE_CELLS', '1:3');
	include 'ce_include.php';
	$rel = 'self';
	$cells = array(1,600,1,29);  // boundaries of area to get row 1-600 col 1-29
	$acgcells = array(1,600,1,9);  // boundaries of area to get row 1-600 col 1-9
	$colarray = cellarray('archive');
	$count = 0;

    // get term for which courses are needed
//	$currenttermid = get_currentterm();
	$term = '2012JT';

	// if archive folder doesn't exist yet, create it
	$archivefolderid = get_collection($term,$owner, $ce_collection); // specific term subcollection id
//	$archivefolderid = get_collection($term . '-archive',$owner, $ce_collection); // specific term subcollection id
//	if ($archivefolderid === '') {
//		$archivefolderid = createcollection($term . '-archive', $owner, $ce_collection);
//	}
/*
	// get all the files in the current term directory
	$docfeed = get_doc_feed($owner, $termcollectionid, 1000);

	foreach ($docfeed->entry as $entry) {
		// get doc to be worked

		// make copy of the spreadsheet in the original
		$templink = explode('?',get_href_noentry($entry, $rel)); // strip parameters
		$origfilelink = $templink[0];
		$filename = $entry->title . '-archive';
		if (sheet_exists($owner, $filename, $archivefolderid)) {
			continue;
		}
		$query = copy_spreadsheet($filename,$owner, $origfilelink);
		$feed = simplexml_load_string($query->response);  // need this so we can get the id for the new sheet

		// place it in the correct collection
		$success = add_file_tocollection($base_feed, $archivefolderid, $feed->id,$owner);

		// get values of all cells in ce and acg sheets
		$key = substr($feed->id,strpos($feed->id,'%3A')+3,100); // key = edit link for sheet
		$evalbasefeed = 'https://spreadsheets.google.com/feeds/worksheets/' . $key . '/private/full';

		// remove shares
		$members = get_docspermissions('document%3A' . $key, $owner);
		unset($members[$owner]);
		$params = array('xoauth_requestor_id' => $owner);
		foreach($members as $member=>$permission) {
			$delete_base_feed = 'https://docs.google.com/feeds/default/private/full/document%3A' . $key . '/acl/user%3A' . $member;
			$response = twolegged($delete_base_feed, $params, 'DELETE');
		}

		// get the worksheet feed for this spreadsheet so we can get the worksheet id
		$evalworksheetsfeed = get_feed($evalbasefeed, $owner);

		// get editlinks
		$ce_sheet = get_sheet($evalworksheetsfeed,'CE-Report'); // object containing all of the ce_report info
		$sheetid = substr($ce_sheet->id,strrpos($ce_sheet->id,'/')+1,10); // note strRpos operating from the right of the string
		$ce_editlink = $evalbasefeed . '/' . $sheetid; // need in order to truncate
		$acg_sheet = get_sheet($evalworksheetsfeed,'ACG-Report'); // object containing all of the acg_report info
		$acgsheetid = substr($acg_sheet->id,strrpos($acg_sheet->id,'/')+1,10); // note strRpos operating from the right of the string
		$acg_editlink = $evalbasefeed . '/' . $sheetid; // need in order to truncate

		// set cells feed urls
		$ce_cellbase = 'https://spreadsheets.google.com/feeds/cells/' . $key . '/' . $sheetid . '/private/full/';
		$acg_cellbase = 'https://spreadsheets.google.com/feeds/cells/' . $key . '/' . $acgsheetid . '/private/full/';

		// set coordinates for data to be retrieved (different for departmental reports from ce reports
		if (substr($filename,0,2) == 'CE') {
			$cells = array(8,306,2,2);
			$ce_maxrow = get_last_data_row($ce_cellbase, $owner, $cells);
			$cells = array(1,$ce_maxrow,1,29);
			$acgcells = array(9,306,2,2);
			$acg_maxrow = get_last_data_row($acg_cellbase, $owner, $acgcells);
			$acgcells = array(1,$acg_maxrow,1,9);
			$ceraw = 'CE-Raw';
			$acgraw = 'ACG-Raw';
		} else {
			$cells = array(1,7,1,29);  // boundaries of area to get row 1-600 col 1-29
			$acgcells = array(1,7,1,9);  // boundaries of area to get row 1-600 col 1-9
			$ceraw = 'ce_calcs';
			$acgraw = 'acg_calcs';
		}

		// ***** store CE-Report summary row conotents to array
		$rows = get_cell_contents($ce_cellbase, $owner, $cells);

		// ***** store ACG-Report summary row contents to arrahy
		$acgrows = get_cell_contents($acg_cellbase, $owner, $acgcells);

		// write out value to same sheets from which we got them through the formulas
		ce_write_archiveresults($rows, $owner, $sheetid, $key,$colarray, $filename, $ce_editlink);
//		truncate_sheet($sheetid, $owner, $key, $ce_maxrow, $ce_editlink);
		ce_write_archiveresults($acgrows, $owner, $acgsheetid, $key, $colarray, $filename, $acg_editlink);
//		truncate_sheet($acgsheetid, $owner, $key, $acg_maxrow, $acg_editlink);

		// remove all old sheets including hidden ones
		$ce_calcsheetid = get_sheetid($evalworksheetsfeed, $ceraw); // worksheet id for ce-report
		$cellbase = 'https://spreadsheets.google.com/feeds/worksheets/' . $key . '/private/full/' . $ce_calcsheetid;
		$success = delete_sheet($owner, $cellbase);
		$acg_calcsheetid = get_sheetid($evalworksheetsfeed, $acgraw); // worksheet id for ce-report
		$cellbase = 'https://spreadsheets.google.com/feeds/worksheets/' . $key . '/private/full/' . $acg_calcsheetid;
		$success = delete_sheet($owner, $cellbase);
*/
/*		$count++;
		if ($count == 100) {
			break;
		}*/
//	}

	// archive all files in the archive directory into a zip file
	$archive = archive_sheets($archivefolderid, $owner);
	$archive_string = simplexml_load_string($archive->response);
	$source = "{$archive_string->content->attributes()->src}";
	$auth = clientauth('course-evaluations@luther.edu', '2xT3C7rL$', 'writely');
	$headers = "Authorization: GoogleLogin auth=" . $auth;
	$zipfile = send_request('GET', $source, $headers);
	$archivefilename = '/Users/puffro01/Downloads/CE-Archive-' . $term . '.zip';
	$archivefile = fopen($archivefilename,'w');
	fwrite($archivefile, $zipfile->response);
	fclose($archivefile);
?>