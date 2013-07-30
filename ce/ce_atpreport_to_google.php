<?php
	require_once('../config.php');
    require_once($CFG->dirroot.'/google/lib.php');
    require_once($CFG->dirroot.'/google/gauth.php');
    require_once("ce-lib.php");
	global $success;

	$owner = 'course-evaluations@luther.edu';
	$base_feed = 'https://docs.google.com/feeds/' . urlencode($owner) . '/private/full';

	//	$term = get_field('ce_term','name','status','current');
	$ce_collection = get_collection('courseevals',$owner); // main course evaluations collection id

	if (($atpcollectionid = get_collection('2012-atp',$owner, $ce_collection)) == false) { // specific term subcollection id
		echo 'Report collection not setup';
		die;
	}

	$term = '2011FA';
	if (($termcollectionid = get_collection($term,$owner, $ce_collection)) == false) { // specific term subcollection id
		echo 'Report collection not setup';
		die;
	}
	$term_feed = get_doc_feed($owner, $termcollectionid, 1200);
	foreach ($term_feed->entry as $entry) {
		$term_titles[s($entry->title)] = $entry;
	}

	$term = '2012SP';
	if (($termcollectionid = get_collection($term,$owner, $ce_collection)) == false) { // specific term subcollection id
		echo 'Report collection not setup';
		die;
	}
	$term_feed = get_doc_feed($owner, $termcollectionid, 1200);
	foreach ($term_feed->entry as $entry) {
		$term_titles[s($entry->title)] = $entry;
	}
	ksort($term_titles);

	$candids = array('mtisri01',
						'stedel01',
						'hawleyja',
						'merritri',
						'narveska',
						'njusdavi',
						'goulkr01',
						'kopfg',
						'carlsosc',
						'enosbejo',
						'iudinnel',
						'highal01',
						'greeje02'
			);
	// cycle through candidates
	foreach ($candids as $candidate) {

		// query for their reports in 2011FA and 2012SP
		$select = " teacherusername = '$candidate'
					AND (term = '2011FA'
					OR term = '2012SP') ";
		$reports = get_records_select('ce_course', $select);
		foreach ($reports as $report) {
			$reportname = 'CE-Report for ' . $report->fullname . ' - ' . $report->teacherfullname;

			// if report exists, add to collections
			if (array_key_exists($reportname, $term_titles)) {

				// place it in the correct collection
				$success = add_file_tocollection($base_feed, $atpcollectionid, $term_titles[$reportname]->id,$owner);
			}
		}
	}

?>