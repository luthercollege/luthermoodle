<?php 
	require_once('../config.php');  // if run from outside as by cron
    require_once($CFG->dirroot.'/google/lib.php');
    require_once($CFG->dirroot.'/google/gauth.php');
    require_once("ce-lib.php");
    
    // set up variables
	define('TEMPLATENAME', 'Departmental Course Evaluation Report for Sample');
	define('CE_CELLS','R1C1');
	define('ALL_CELLS','R1C2');
	$owner = 'course-evaluations@luther.edu';
    $term = get_field('ce_term','name','status','current');  // name of current term
	$ce_collection = get_collection('courseevals',$owner); // main course evaluations collection id
	$termcollectionid = get_collection($term,$owner, $ce_collection); // specific term subcollection id
	$termdatacollectionid = get_collection($term . '-data',$owner, $ce_collection); // specific term subcollection id
	$base_feed = 'https://docs.google.com/feeds/' . urlencode($owner) . '/private/full';
    
	// define the template spreadsheet from which all reports are copied
	$rel = 'self';
	$templink = explode('?',get_sheet_link($owner, TEMPLATENAME, $rel));    
	$templatefilelink = $templink[0];    
	
    
    // get all evaluation reports by department
	$currentterm = get_record('ce_term','status','Current');
	$sql = "SELECT * FROM mdl_ce_course 
			WHERE term = '$term'
			AND shortname LIKE '%-185-%'
			AND docid IS NOT NULL
			ORDER BY shortname";
	$ce_recs = get_records_sql($sql);
	$rows = array();
	$acgrows = array();
	$department = 'First Year Seminars'; // store new department name
	$reportname = 'First-Year Seminar Course Evaluation Report for ' . $term;
	$rel = 'edit';
	// if departmental report doesn't already exist 
	if (!$query = get_sheet_link($owner, $reportname, $rel)) {
		// create the (new) departmental report
		$query = copy_spreadsheet($reportname,$owner, $templatefilelink);
		$deptfeed = simplexml_load_string($query->response);  // need this so we can get the id for the new sheet
		$success = add_file_tocollection($base_feed, $termcollectionid, $deptfeed->id,$owner); // place it in the correct collection
		
		// get departmental spreadsheet key
		$tempkey = explode('/',$deptfeed->id);
		$deptkey = substr($tempkey[sizeof($tempkey) - 1],14,200);
		
		// get the worksheet feed for this spreadsheet so we can get the worksheet id
		$rel = 'http://schemas.google.com/spreadsheets/2006#worksheetsfeed';
		$deptworksheetsfeed = get_href_noentry($deptfeed, $rel);
		
		// get cells feed for worksheet
		$rel = 'http://schemas.google.com/spreadsheets/2006#cellsfeed';
		$deptcellsfeed = get_feed($deptworksheetsfeed, $owner);
		
		$deptsheetid = get_sheetid($deptcellsfeed,'CE-Report'); // worksheet id for departmental ce-report
		$deptacgsheetid = get_sheetid($deptcellsfeed,'ACG-Report'); // worksheet id for departmental ce-report
		$url = 'https://spreadsheets.google.com/feeds/cells/' . $deptkey . '/' . $deptsheetid . '/private/full/R1C1';

		// print out the reportname for use throughout the sheets
		$success = update_import_cell($url, $owner, $deptkey, $deptsheetid, 1, 1, $reportname, $url);
	}
	foreach($ce_recs as $rec) {
		// cycle through the reports for this department, getting roll-up lines and number of responses for each, placing them in an array
		$key = substr($rec->docid,14,200);
		$evalbasefeed = 'https://spreadsheets.google.com/feeds/worksheets/' . $key . '/private/full';

		// get the worksheet feed for this spreadsheet so we can get the worksheet id
		$rel = 'http://schemas.google.com/spreadsheets/2006#worksheetsfeed';
		$evalworksheetsfeed = get_feed($evalbasefeed, $owner);
//		$evalworksheetsfeed = get_href_noentry($evalbasefeed, $rel);
		
		// ***** store CE-Report summary row contents to array
		$sheetid = get_sheetid($evalworksheetsfeed,'CE-Report'); // worksheet id for ce-report
		$cells = array(5,7,2,28);  // boundaries of area to get
		$cellbase = 'https://spreadsheets.google.com/feeds/cells/' . $key . '/' . $sheetid . '/private/full/';
		$cellfeed = get_cell_feed($cellbase, $owner, $cells);
		$evalteach = $rec->shortname . ' - ' . $rec->teacherfullname;
		foreach ($cellfeed->entry as $cell) {
			$rows[$evalteach][s($cell->title)] = $cell->content;
		}
		
		// ***** store ACG-Report summary row contents to array
		$acgsheetid = get_sheetid($evalworksheetsfeed,'ACG-Report'); // worksheet id for ce-report
		$cells = array(5,7,2,8);  // boundaries of area to get row 5-9 col 2-8
		$cellbase = 'https://spreadsheets.google.com/feeds/cells/' . $key . '/' . $acgsheetid . '/private/full/';
		$cellfeed = get_cell_feed($cellbase, $owner, $cells);
		foreach ($cellfeed->entry as $cell) {
			$acgrows[$evalteach][s($cell->title)] = $cell->content;
		}
		// get cells feed for worksheet
//		$rel = 'http://schemas.google.com/spreadsheets/2006#cellsfeed';
//		$evalcellsfeed = get_feed($evalworksheetsfeed, $owner);
//		}
		
	}
	if ($ce_recs !== null) {
		ce185_writedeptresults($rows, $owner, $deptsheetid, $deptkey);
		ce185_writedeptresults($acgrows, $owner, $deptacgsheetid, $deptkey);
		ce_assignpermissions($department, $owner, $term, $deptkey, $reportname, null);
	}
	
// write out the roll-up lines to the departmental report
// move to current term collection
// assign permissions

function ce185_writedeptresults($rows, $owner, $worksheetid, $key) {
	$colarray = array(
		'B' => '3',
		'C' => '4',
		'D' => '5',
		'E' => '6',
		'F' => '7',
		'G' => '8',
		'H' => '9',
		'I' => '10',
		'K' => '11',
		'L' => '12',
		'M' => '13',
		'N' => '14',
		'P' => '17',
		'Q' => '18',
		'R' => '19',
		'S' => '20',
		'T' => '21',
		'U' => '22',
		'W' => '23',
		'X' => '24',
		'Y' => '25',
		'Z' => '26',
		'AA' => '27',
		'AB' => '28',
		'AC' => '29',
		'AD' => '30',
		'AE' => '31',
		'AF' => '32',
		'AG' => '33',
		'AH' => '34',
		'AI' => '35'
	);
	$startrow = 0;
	$base_feed = 'https://spreadsheets.google.com/feeds/cells/' . $key . '/' . $worksheetid . '/private/full';
	$batchcellpost = '<feed xmlns="http://www.w3.org/2005/Atom"
      					xmlns:batch="http://schemas.google.com/gdata/batch"
      					xmlns:gs="http://schemas.google.com/spreadsheets/2006">
  							<id>' . $base_feed . '</id>';
    $params = array('xoauth_requestor_id' => $owner);
	foreach ($rows as $evalteach => $title) {
		$startrow += 3;	// starts out at row 8 so becomes $startrow + $row to get there	
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

function cell_post($url, $id, $row, $col, $value) {
	return '<entry>
	    <id>' . $url . '</id>
	    <link rel="edit" type="application/atom+xml"
	    	href="' . $url . '"/>
	    <gs:cell row="' . s($row) . '" col="' . s($col) . '" inputValue="' . $value . '"/>
		</entry>';
}

function batch_cell_post($base_feed, $id, $row, $col, $value) {
	$url = $base_feed . '/R' . $row . 'C' . $col;
	return '<entry>
	    <batch:id>' . $id . '</batch:id>
	    <batch:operation type="update"/>
	    <id>' . $url . '</id>
	    <link rel="edit" type="application/atom+xml"
	    	href="' . $url . '/version"/>
	    <gs:cell row="' . s($row) . '" col="' . s($col) . '" inputValue="' . $value . '"/>
		</entry>';
}
?>