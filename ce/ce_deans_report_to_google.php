<?php
	require_once('../config.php');  // if run from outside as by cron
    require_once($CFG->dirroot.'/google/lib.php');
    require_once($CFG->dirroot.'/google/gauth.php');
    require_once("ce-lib.php");

	$owner = 'course-evaluations@luther.edu';
    $term = '2013JT';
	$ce_collection = get_collection('courseevals',$owner); // main course evaluations collection id
	if (($termcollectionid = get_collection($term,$owner, $ce_collection)) == false) { // specific term subcollection id
		echo 'Report collection not setup';
		die;
	}
    $base_feed = 'https://docs.google.com/feeds/' . urlencode($owner) . '/private/full';

	// set up variables
	$templatename = 'Office of the Dean Course Evaluation Report template';
	define('CE_CELLS','R1C1');
	define('ALL_CELLS','R1C2');
	$cells = array(5,7,3,28);  // boundaries of area to get
	$acgcells = array(5,7,3,9);  // boundaries of area to get row 5-9 col 2-8
	$deptresponserate = array(2,2,1,1);
	$deptcredits = array(7,7,7,7);

	// define the template spreadsheet from which all reports are copied
	$rel = 'self';
	$templink = explode('?',get_sheet_link($owner, $templatename, $rel));
	$templatefilelink = $templink[0];


    // get all evaluation reports by department
	$currentterm = get_record('ce_term','status','Current');
	$sql = "SELECT DISTINCT department FROM mdl_ce_course
			WHERE term = '$term'
			ORDER BY department";
	$ce_recs = get_records_sql($sql);
	$department = null;
	$rows = array();
	$reportname = 'Office of the Dean Course Evaluation Report for ' . $term;
	// check to see if department report exists, if it does, delete it
	if ($query = get_sheet_link($owner, $reportname, $rel)) {
		$templink = explode('?',$query);
		$deletebase = $templink[0];
		$success = delete_sheet($owner,$deletebase);
	}
	// create the (new) dean's report
	$query = copy_spreadsheet($reportname,$owner, $templatefilelink);
	$deanfeed = simplexml_load_string($query->response);  // need this so we can get the id for the new sheet
	$success = add_file_tocollection($base_feed, $termcollectionid, $deanfeed->id,$owner); // place it in the correct collection

	// get departmental spreadsheet key
	$deankey = strip_id($deanfeed->id,'nodoc');

	// remove from the root folder
	delete_file_fromcollection($base_feed, 'folder%3Aroot', $deankey, $owner);

	// get the worksheet feed for this spreadsheet so we can get the worksheet id
	$rel = 'http://schemas.google.com/spreadsheets/2006#worksheetsfeed';
	$deanworksheetsfeed = get_href_noentry($deanfeed, $rel);

	// get cells feed for worksheet
	$rel = 'http://schemas.google.com/spreadsheets/2006#cellsfeed';
	$deancellsfeed = get_feed($deanworksheetsfeed, $owner);

	$deansheetid = get_sheetid($deancellsfeed,'CE-Report'); // worksheet id for dean's ce-report
	$deanacgsheetid = get_sheetid($deancellsfeed,'ACG-Report'); // worksheet id for dean's ce-report
	$url = 'https://spreadsheets.google.com/feeds/cells/' . $deankey . '/' . $deansheetid . '/private/full/R1C2';

	// print out the reportname for use throughout the sheets
	$success = update_import_cell($url, $owner, $deankey, $deansheetid, 1, 2, $reportname, $url);
	$startrow = 0;

	foreach($ce_recs as $rec) {

			$department = $rec->department; // store new department name
			$deptreportname = 'Departmental Course Evaluation Report for ' . $rec->department . ' - ' . $term;

			$rel = 'edit';
			// cycle through the departmental reports, getting roll-up lines and number of responses for each, placing them in an array
			$deptkey = get_sheet_key(get_sheet_link($owner, $deptreportname, $rel));
			$evalbasefeed = 'https://spreadsheets.google.com/feeds/worksheets/' . $deptkey . '/private/full';

			// get the worksheet feed for this spreadsheet so we can get the worksheet id
			$rel = 'http://schemas.google.com/spreadsheets/2006#worksheetsfeed';
			$evalworksheetsfeed = get_feed($evalbasefeed, $owner);
	//		$evalworksheetsfeed = get_href_noentry($evalbasefeed, $rel);

			// ***** store CE-Report summary row contents to array
			$sheetid = get_sheetid($evalworksheetsfeed,'CE-Report'); // worksheet id for ce-report
			$cellbase = 'https://spreadsheets.google.com/feeds/cells/' . $deptkey . '/' . $sheetid . '/private/full/';
			$evalteach = $department;
			$rows[$evalteach] = get_cell_contents($cellbase, $owner, $cells, $evalteach);

			// add in the departmental response rate
			$a2 = get_cell_contents($cellbase, $owner, $deptresponserate, $evalteach);
			$rows[$evalteach]['A7'] = 'Response rate = ' . sprintf("%.2f", $a2['A2']);

			// ***** store ACG-Report summary row contents to array
			$acgsheetid = get_sheetid($evalworksheetsfeed,'ACG-Report'); // worksheet id for ce-report
			$cellbase = 'https://spreadsheets.google.com/feeds/cells/' . $deptkey . '/' . $acgsheetid . '/private/full/';
			$acgrows[$evalteach] = get_cell_contents($cellbase, $owner, $acgcells, $evalteach);
//	}
//	if ($ce_recs !== null) {
		$colarray = cellarray('dean');
		ce_writedeptresults($rows, $owner, $deansheetid, $deankey, $colarray, $startrow);
		ce_writedeptresults($acgrows, $owner, $deanacgsheetid, $deankey, $colarray,$startrow);
		$startrow += 3;	// starts out at row 8 so becomes $startrow + $row to get there
		$rows = array();
		$acgrows = array();
	}

// write out the roll-up lines to the departmental report
// move to current term collection
// assign permissions

function ce_writedeanresults($rows, $owner, $worksheetid, $key, $startrow) {
	$colarray = array(
		'C' => '3',
		'D' => '4',
		'E' => '5',
		'F' => '6',
		'G' => '7',
		'H' => '8',
		'I' => '9',
		'K' => '10',
		'L' => '11',
		'M' => '12',
		'N' => '13',
		'P' => '14',
		'Q' => '15',
		'R' => '16',
		'S' => '17',
		'T' => '18',
		'U' => '19',
		'W' => '20',
		'X' => '21',
		'Y' => '22',
		'Z' => '23',
		'AA' => '24',
		'AB' => '25',
		'AC' => '26',
		'AD' => '27',
		'AE' => '28'
	);
	$base_feed = 'https://spreadsheets.google.com/feeds/cells/' . $key . '/' . $worksheetid . '/private/full';
	$batchcellpost = '<feed xmlns="http://www.w3.org/2005/Atom"
      					xmlns:batch="http://schemas.google.com/gdata/batch"
      					xmlns:gs="http://schemas.google.com/spreadsheets/2006">
  							<id>' . $base_feed . '</id>';
    $params = array('xoauth_requestor_id' => $owner);
	foreach ($rows as $evalteach => $title) {
		// add the course/ teacher entry for column A
//		$batchcellpost .= cell_post($base_feed, s($startrow), $startrow + 5, 1, $evalteach);
		$col = 1;
		$row = 5;
		$url = $base_feed . '/R' . s($startrow + $row) . 'C' . $col;
		$success = update_import_cell($url, $owner, $key, $worksheetid, $startrow + $row, $col, $evalteach, $url);
		foreach ($title as $location => $value) {
			// split location into row/ col
			$col = preg_replace('/(\d+)/', '', $location);
			$row = preg_replace('/([A-Z]+)/', '', $location);
			$value = $row == 7 ? "'" . $value : sprintf("%.2f",$value);
//			$value = $row == 7 ? "'" . $value : preg_replace('/(\d+)\.(\d+)/','/(\d+)\.(\d{2})/',$value);
			if (array_key_exists($col, $colarray)) {
				$url = $base_feed . '/R' . s($startrow + $row) . 'C' . $colarray[$col];
	//			$batchcellpost = cell_post($url, $location, $startrow + $row, $colarray[$col], $value);
				$success = update_import_cell($url, $owner, $key, $worksheetid, $startrow + $row, $colarray[$col], $value, $url);
			}
		}
	}
//	$batchcellpost .= '</feed>';
//    $params = array('xoauth_requestor_id' => $owner);
//    $response  = twolegged($base_feed . '/batch', $params, 'POST', $batchcellpost);
//    $response = send_request('PUT', $base_feed . '/batch', $headers, null, $batchcellpost, '3.0');
//    var_dump(simple_xml_load_string($response->response));
/*
	if ($response->info['http_code'] <> 200) {
    	return false;
    } else {
	    return true;
    }
*/
}
?>