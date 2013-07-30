<?php 
	$owner = 'course-evaluations@luther.edu';
    $term = get_field('ce_term','name','status','current');
	$ce_collection = get_collection('courseevals',$owner); // main course evaluations collection id
	$termcollectionid = get_collection($term,$owner, $ce_collection); // specific term subcollection id
	$termorigcollectionid = get_collection($term . '-orig',$owner, $ce_collection); // specific term subcollection id
	$termdatacollectionid = get_collection($term . '-data',$owner, $ce_collection); // specific term subcollection id
	$base_feed = 'https://docs.google.com/feeds/' . urlencode($owner) . '/private/full';
?>