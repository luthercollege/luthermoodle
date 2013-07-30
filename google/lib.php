<?php
global $CFG;
require_once("$CFG->dirroot/google/constants.php");
require_once("$CFG->dirroot/google/gauth.php");


/***** COLLECTIONS *****/

/*
* creates a read or a write folder for specified user (course)
* optionally makes it a subcollection of collectionid
*/
function createcollection($foldername, $user, $collectionid = null) {
//        $base_feed = 'http://docs.google.com/feeds/documents/private/full';
	$base_feed = 'https://docs.google.com/feeds/default/private/full';
	if ($collectionid !== null) {
		$base_feed .= '/' . $collectionid . '/contents';
	}
	$folderdata =
			'<?xml version=\'1.0\' encoding=\'UTF-8\'?>
			<atom:entry xmlns:atom="http://www.w3.org/2005/Atom">
			  <atom:category scheme="http://schemas.google.com/g/2005#kind"
				  term="http://schemas.google.com/docs/2007#folder" label="folder"/>
			  <atom:title>' . $foldername . '</atom:title>
			</atom:entry>';
	$params = array('xoauth_requestor_id' => $user);
	$response  = twolegged($base_feed, $params, 'POST', $folderdata);
    if ($response->info['http_code'] <> 201) {
    	return false;
    } else {
		$feed = simplexml_load_string($response->response);
		// return the id for the created folder
		return substr($feed->id,strpos($feed->id,'folder%3A') + 9);
    }
}

/*
 * returns the collectionid for a collection optionally looking in a collection for the collection
 */
function get_collection($title,$owner, $collectionid = null) {
	$base_feed = 'https://docs.google.com/feeds/' . urlencode($owner) . '/private/full';
	if ($collectionid !== null) {
		$base_feed .= '/' . $collectionid . '/contents';
	}
	$params = array('xoauth_requestor_id' => $owner, 'title' => $title, 'showfolders' => 'true', 'title-exact' => 'true');
    $query  = twolegged($base_feed, $params, 'GET', null, '3.0');
    if ($query->info['http_code'] == 200) {
    	$feed = simplexml_load_string($query->response);
		if (!is_null($feed->entry)) {
	    	$link = explode('/',$feed->entry->id);
		    return $link[sizeof($link)-1];
		}
	}
	return false;
}
/*
 * expects $collectionid to be a folderid not name
 */
function add_file_tocollection($base_feed, $collectionid, $docid,$owner) {
	if (substr($base_feed,-1,1) !== '/') {
		$base_feed .= '/';
	}
	$base_feed .= $collectionid . '/contents';
	$file_to_collection = '<?xml version=\'1.0\' encoding=\'UTF-8\'?>
				<entry xmlns="http://www.w3.org/2005/Atom">
					<id>' . $docid . '</id>
				</entry>';
	$params = array('xoauth_requestor_id' => $owner);
    $response = twolegged($base_feed, $params, 'POST', $file_to_collection, '3.0');
}


    function send_foldercontents_togoogle($path,$collectionid, $link, $user, $collectionname) {
    	$directory = opendir($path);             // Find all files
	    while (false !== ($file = readdir($directory))) {
	        if ($file == "." || $file == ".." || $file == ".DS_Store" || $file == "moddata" || $file == "backupdata") {
	            continue;
	        }

	        if (is_dir($path."/".$file)) {
	            $dirlist[] = $file;
	        } else {
	            $filelist[] = $file;
	        }
	    }
	    closedir($directory);
		$base_feed = 'https://docs.google.com/feeds/default/private/full';
//	    $today = str_replace(' ','',userdate(time(),"%Y-%m-%d"));
	    // Create a 2D array of files and sort
		foreach ($filelist as $file) {
	    	$today = date(DATE_RFC3339,time()-(60*10));
	    	$filename = $path."/".$file; // file to get
	        $filedate = userdate(filemtime($filename), "%d %b %Y, %I:%M %p");
	        $filesize = filesize($filename);
	        // determine mime type
	        $filetype = mimeinfo('type', strtolower($file));
	        // get file contents
			$filecontents = file_get_contents($filename);
			$params = array(
				'xoauth_requestor_id' => $user,
				'title' => 'Untitled',
				'title-exact' => 'true',
				'updated-min' => $today,
				'strict' => 'true'
			);

			send_file_togoogle($file, $filecontents, $user, $filetype, $link, $collectionid);
/*
			$result = get_feed($base_feed,$user,$params);
			$links = explode('?',get_href($result, 'edit'));
			$editlink = $links[0];
			// give it title
			send_file_togoogle($file, null, $user, $filetype, $editlink, $collectionid);
*/
//			echo $file . ' uploaded to your Norse Docs account in ' . $collectionname . ' collection <br />';
	    }
		// Create a 2D array of directories and sort
	    foreach ($dirlist as $dir) {
			// create collection
			// TODO: create collection in nested collection ability
	    	if (!$folderid = get_collection($dir, $user,$collectionid)) {
				$folderid = createcollection($dir, $user, $collectionid);
			}
			$fullpath = $path."/".$dir;
			send_foldercontents_togoogle($fullpath, $folderid, $link, $user, $collectionname . '/' . $dir);
	    }

	}


/**** FILES ****/
/*
 *  transfer file to google and optionally adds to a collection
 *  determines mime type and corresponding icon
 *  uses googles resumable file uploader protocol
 */

function send_file_togoogle($title, $filecontents,$owner, $filetype, $writelink, $collectionid = null) {

	// determine params
    $convertarray =  array (
        'csv'  => 'text/csv',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'docm' => 'application/vnd.ms-word.document.macroEnabled.12',
    	'html' => 'text/html',
    	'xhtml'=> 'application/xhtml+xml',
        'htm'  => 'text/html',
        'odp'  => 'application/vnd.oasis.opendocument.presentation',
        'otp'  => 'application/vnd.oasis.opendocument.presentation-template',
        'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        'ots'  => 'application/vnd.oasis.opendocument.spreadsheet-template',
        'pdf'  => 'application/pdf',
        'png'  => 'image/png',
    	'pps'  => 'application/vnd.ms-powerpoint',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'pptm' => 'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
        'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
        'rtf'  => 'text/rtf',
        'sxw'  => 'application/vnd.sun.xml.writer',
    	'tsv'  => 'text/tab-separated-values',
    	'wmv'  => 'video/x-ms-wmv',
	    'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12'
    );
	$mimearray =  array (
        'csv'  => 'text/csv',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'docm' => 'application/vnd.ms-word.document.macroEnabled.12',
        'gif'  => 'image/gif',
		'html' => 'text/html',
        'xhtml'=> 'application/xhtml+xml',
        'htm'  => 'text/html',
        'jpe'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg'  => 'image/jpeg',
		'odp'  => 'application/vnd.oasis.opendocument.presentation',
        'otp'  => 'application/vnd.oasis.opendocument.presentation-template',
        'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        'ots'  => 'application/vnd.oasis.opendocument.spreadsheet-template',
        'pdf'  => 'application/pdf',
        'png'  => 'image/png',
		'pps'  => 'application/vnd.ms-powerpoint',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'pptm' => 'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
        'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
        'rtf'  => 'text/rtf',
        'sxw'  => 'application/vnd.sun.xml.writer',
    	'tsv'  => 'text/tab-separated-values',
    	'wmv'  => 'video/x-ms-wmv',
	    'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12'
    );
	$params = array('xoauth_requestor_id' => $owner);
	if(!in_array($filetype, $convertarray)){
		$params['convert'] = 'false';
	}
	// TODO: get all valid mimetypes included here so all conversions can take place
	switch ($filetype) {
		case 'application/msword':
		case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
		case 'application/vnd.ms-word.document.macroEnabled.12':
        case 'text/rtf':
			$doctype = 'document';
			break;
		case 'application/vnd.ms-powerpoint':
			$doctype = 'presentation';
			break;
        case 'text/csv':
		case 'application/vnd.ms-excel':
        case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
        case 'application/vnd.ms-excel.sheet.macroEnabled.12':
        	$doctype = 'spreadsheet';
        	break;
        case 'image/jpeg':
        case 'image/jpeg':
        case 'image/jpeg':
        case 'image/png':
        case 'image/gif':
           	$doctype = 'drawing';
        	break;
        default:
        	$doctype = 'document';
        	break;
	}
	// create the file
	// TODO: check to see if the file exists first
	// make initial post
	$createdocdata =
		'<?xml version="1.0" encoding="UTF-8"?>
		<entry xmlns="http://www.w3.org/2005/Atom" xmlns:docs="http://schemas.google.com/docs/2007">
		  <category scheme="http://schemas.google.com/g/2005#kind"
		      term="http://schemas.google.com/docs/2007#' . $doctype . '"/>
		<title>' . $title . '</title>
		</entry>';
//	$query  = twolegged($writelink, $params, 'POST', $createdocdata, '3.0', null, null, $filetype, $filecontents);
	if ($filecontents !== null) {
		$query  = twolegged_x($writelink, $params, 'POST', $createdocdata, '3.0', null, null, $filetype, $filecontents);
	}
	// we're getting the info to do something with the file note its going to twolegged with no 'x' which actually returns a value
	$base_feed = 'https://docs.google.com/feeds/default/private/full';
   	$today = date(DATE_RFC3339,time()-(60*10));
	$params = array(
		'xoauth_requestor_id' => $owner,
		'title' => 'Untitled',
		'title-exact' => 'true',
		'updated-min' => $today,
		'strict' => 'true'
	);
	$result = get_feed($base_feed,$user,$params);
	$links = explode('?',get_href($result, 'edit'));
	$editlink = $links[0];
	// give it title
//	send_file_togoogle($file, null, $user, $filetype, $editlink, $collectionid);
	$params = array('xoauth_requestor_id' => $owner);
	$query  = twolegged($editlink, $params, 'PUT', $createdocdata, '3.0');
	$feed = simplexml_load_string($query->response);  // need this so we can get the id for the new item
	if ($collectionid !== null) {
		// TODO: place file in collection -- test this out
	    $base_feed = 'https://docs.google.com/feeds/' . urlencode($owner) . '/private/full';
	    $split = explode('/',$feed->id);
	    $docid = $split[sizeof($split)-1];
		$query = add_file_tocollection($base_feed, $collectionid, $docid,$owner);
		$feed = simplexml_load_string($query->response);  // need this so we can get the id for the new item
		return $feed->id;
	} else {
		return $feed;
	}
	//	$query  = twolegged_x($writelink, $params, 'POST', $createdocdata, '3.0', null, null, null, 0);
}

/**********  SITE FUNCTIONS ***********************/

/*
 * creates base site for course name
 */
function createsite($owner, $morslerec, $user, $CONSUMER_KEY) {

    // form the xml for the post
    $title = 'Course Site for ' . strtoupper($morslerec->shortname);
    $sitecreate =
            '<entry xmlns="http://www.w3.org/2005/Atom" xmlns:sites="http://schemas.google.com/sites/2008">
				  <title>' . $title . '</title>
				  <summary>Morsle course site for collaboration</summary>
				  <sites:theme>microblueprint</sites:theme>
			</entry>';

    // Make the request
    $base_feed = 'https://sites.google.com/feeds/site/' . $CONSUMER_KEY;
    $params = array('xoauth_requestor_id' => $user);
    $response  = twolegged($base_feed, $params, 'POST', $sitecreate, '1.4');
    if ($response->info['http_code'] <> 201) {
    	return false;
    } else {
	    $stored = simplexml_load_string($response->response);
	    foreach ($stored->link as $link) {
	        if ($link['rel'] == 'alternate') {
	            $href = $link['href'];
	            break;
	        }
	    }
	    return $href;
    }
}

/*
 * gets a list of portfolios sites to which a particular user (usually a teacher) has access
 */
function getportfoliosites($user, $portfolioname, $CONSUMER_KEY) {
	$portfolio = array();

    // Make the request
    $base_feed = 'https://sites.google.com/feeds/site/' . $CONSUMER_KEY;
    $params = array('xoauth_requestor_id' => $user, 'max-results'=>'1000');
    $response  = twolegged($base_feed, $params, 'GET', null, '1.4');
    if ($response->info['http_code'] <> 200) {
    	return false;
    } else {
	    $stored = simplexml_load_string($response->response);
	    foreach ($stored->entry as $entry) {
	    	if(strpos($entry->title,$portfolioname) === 0) {
	    		$portfolio[] = trim(substr($entry->title, strlen($portfolioname),70));
	    	}
	    }
	    return $portfolio;
    }
}

/*
 * creates a portfolio site for a student based on the HPE portfolio template
 */
function createportfoliosite($studentname, $portfolioname, $user, $CONSUMER_KEY) {

    // form the xml for the post
    $title = $portfolioname . $studentname;
    $sitecreate =
            '<entry xmlns="http://www.w3.org/2005/Atom" xmlns:sites="http://schemas.google.com/sites/2008">
				  <title>' . $title . '</title>
				  <link rel="http://schemas.google.com/sites/2008#source" type="application/atom+xml"
      				href="https://sites.google.com/feeds/site/luther.edu/puffer-s-temp-hpe2"/>
				  <summary>HPE ePortfolio Site For Assessment</summary>
			</entry>';

    // Make the request
    $base_feed = 'https://sites.google.com/feeds/site/' . $CONSUMER_KEY;
    $params = array('xoauth_requestor_id' => $user);
    $response  = twolegged($base_feed, $params, 'POST', $sitecreate, '1.4');
    if ($response->info['http_code'] <> 201) {
    	return false;
    } else {
	    return true;
    }
}

/*
 * CALENDAR FUNCTIONS
 */

function query_event($owner, $auth, $googleid) {
	$owner = str_replace('@', '%40', $owner);
	$authstring = "Authorization: GoogleLogin auth=" . $auth;
	$headers = array($authstring, "GData-Version: 2.0");
	$base_feed = "https://www.google.com/calendar/feeds/$owner/private/full/$googleid";
//	$base_feed = "https://www.google.com/calendar/feeds/$owner/private/full?title=$name";
//	$base_feed = get_cal_base_feed("https://www.google.com/calendar/feeds/default/private/full?title=$name", $headers);
//	$base_feed = get_cal_base_feed("https://www.google.com/calendar/feeds/$owner/private/full", $headers);
//	$base_feed = get_cal_base_feed("https://www.google.com/calendar/feeds/$owner/private/full?start-min=2011-02-01T00:00:00&start-max=2011-03-30T23:59:59", $headers);
//	$base_feed = get_cal_base_feed('https://www.google.com/calendar/feeds/default/owncalendars/full', $headers);
//    $calacldata = cal_acl_post($user);
//	return send_request('GET', $base_feed, $authstring, null, null, '2.0');
	$response = send_request('GET', $base_feed, $authstring, null, null, '2.0');
	return $response;
}


function get_event_feed($owner, $auth) {
//	$owner = str_replace('@', '%40', $owner);
	$authstring = "Authorization: GoogleLogin auth=" . $auth;
	$headers = array($authstring, "GData-Version: 2.0");
	$base_feed = get_cal_base_feed("https://www.google.com/calendar/feeds/default/$owner/full", $headers);
//	$base_feed = get_cal_base_feed("https://www.google.com/calendar/feeds/$owner/private/full", $headers);
//	$base_feed = get_cal_base_feed("https://www.google.com/calendar/feeds/$owner/private/full?start-min=2011-02-01T00:00:00&start-max=2011-03-30T23:59:59", $headers);
//	$base_feed = get_cal_base_feed('https://www.google.com/calendar/feeds/default/owncalendars/full', $headers);
//    $calacldata = cal_acl_post($user);
	$response = send_request('GET', $base_feed, $authstring, null, null, 2);
	return $response;
}

/* not currently used for anything */
/*
 * TODO: rewrite using low-level google functions
 */
function morsle_cal_retrieve($auth) {
    global $CFG;
    require_once($CFG->dirroot.'/google/gauth.php');
//    $params = array('xoauth_requestor_id' => $user);
//    $calgetsuccess = twolegged($base_feed, $params, 'GET', null, 2);
    $headers = array(
        "Authorization: GoogleLogin auth=" . $auth,
        "GData-Version: 2.0"
    );
	$base_feed = get_cal_base_feed('https://www.google.com/calendar/feeds/default/owncalendars/full', $headers);
    $curl = curl_init($base_feed);
    curl_setopt($curl, CURLOPT_URL, $base_feed);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_POST, false);
    curl_setopt($curl, CURLOPT_GET, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_FAILONERROR, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
    $response = curl_exec($curl);
    $feed = simplexml_load_string($response);
}

function get_acl_feed($owner, $auth) {
	$owner = str_replace('@', '%40', $owner);
	$authstring = "Authorization: GoogleLogin auth=" . $auth;
	$headers = array($authstring, "GData-Version: 2.0");
	$base_feed = get_cal_base_feed("https://www.google.com/calendar/feeds/$owner/acl/full", $headers);
//	$base_feed = get_cal_base_feed('https://www.google.com/calendar/feeds/default/owncalendars/full', $headers);
//    $calacldata = cal_acl_post($user);
	$response = send_request('GET', $base_feed, $authstring, null, null, 2);
}

/*
 * not used at this time
 */
function batch_get_cal_event_post($eventrec) {
	$name = $eventrec->name;
	// TODO: description should start with eventid
	// need to compare what Google has against what we have now in case its changed
	// TODO: how to determine where the authority is, what if they change the google event?
	$description = strip_tags($eventrec->description);
	$quarterhour = 900;
	if ($eventrec->timeduration == 0) {
		$starttime = date(DATE_ATOM,$eventrec->timestart - $quarterhour);
		$endtime = date(DATE_ATOM,$eventrec->timestart);
	} else {
		$starttime = date(DATE_ATOM,$eventrec->timestart);
		$endtime = date(DATE_ATOM,$eventrec->timestart + $eventrec->timeduration);
	}
	$temp = "<entry>
		<batch:id>$eventrec->eventid</batch:id>
    	<batch:operation type='insert' />
		<category scheme='http://schemas.google.com/g/2005#kind' term='http://schemas.google.com/g/2005#event' />
		<title type='text'>$name</title>
		<content type='text'>$description</content>
		<gd:transparency
		    value='http://schemas.google.com/g/2005#event.opaque'>
		</gd:transparency>
		<gd:eventStatus
		    value='http://schemas.google.com/g/2005#event.confirmed'>
		</gd:eventStatus>
		<gd:where valueString=''></gd:where>
		<gd:when startTime='$starttime'
		  endTime='$endtime'>
			<gd:reminder hours='4' method='email' />
	    	<gd:reminder minutes='30' method='alert' />
	    </gd:when>
	</entry>";
	return $temp;
}


/*
 * not used at this time
 */
function get_cal_event_post($eventrec) {
	$name = $eventrec->name;
	// TODO: description should start with eventid
	// need to compare what Google has against what we have now in case its changed
	// TODO: how to determine where the authority is, what if they change the google event?
	$description = strip_tags($eventrec->description);
	$quarterhour = 900;
	if ($eventrec->timeduration == 0) {
		$starttime = date(DATE_ATOM,$eventrec->timestart - $quarterhour);
		$endtime = date(DATE_ATOM,$eventrec->timestart);
	} else {
		$starttime = date(DATE_ATOM,$eventrec->timestart);
		$endtime = date(DATE_ATOM,$eventrec->timestart + $eventrec->timeduration);
	}
	return "<entry xmlns='http://www.w3.org/2005/Atom'
    xmlns:gd='http://schemas.google.com/g/2005'>
  <category scheme='http://schemas.google.com/g/2005#kind'
    term='http://schemas.google.com/g/2005#event'></category>
  <title type='text'>$name</title>
  <content type='text'>$description</content>
  <gd:transparency
    value='http://schemas.google.com/g/2005#event.opaque'>
  </gd:transparency>
  <gd:eventStatus
    value='http://schemas.google.com/g/2005#event.confirmed'>
  </gd:eventStatus>
  <gd:where valueString=''></gd:where>
  <gd:when startTime='$starttime'
    endTime='$endtime'>
    <gd:reminder hours='4' method='email' />
    <gd:reminder minutes='30' method='alert' />
    </gd:when>
</entry>";
}

/*
 * SPREADSHEET FUNCTIONS
 */

/*
 *
 */
function copy_spreadsheet($title,$owner, $template) {
//    $headers = "Authorization: GoogleLogin auth=" . $auth;
	$copydata = '<?xml version=\'1.0\' encoding=\'UTF-8\'?>
				<entry xmlns="http://www.w3.org/2005/Atom">
					<id>' . $template . '</id>
					<title>' . $title . '</title>
				</entry>';
	$base_feed = 'https://docs.google.com/feeds/' . urlencode($owner) . '/private/full';
//	$base_feed = 'https://docs.google.com/feeds/private/full';
	$params = array('xoauth_requestor_id' => $owner);
    return twolegged($base_feed, $params, 'POST', $copydata, '3.0');
}

function delete_sheet($owner,$base_feed) {
	$params = array('xoauth_requestor_id' => $owner, 'delete' => 'true');
    return twolegged($base_feed, $params, 'DELETE');
}


function create_empty_sheet($title,$owner) {
	$sheetdata = '<?xml version="1.0" encoding="UTF-8"?>
					<entry xmlns="http://www.w3.org/2005/Atom" xmlns:docs="http://schemas.google.com/docs/2007">
						  <category scheme="http://schemas.google.com/g/2005#kind"
						      term="http://schemas.google.com/docs/2007#spreadsheet"/>
						  <title>' . $title . '</title>
					</entry>';
	$base_feed = 'https://docs.google.com/feeds/' . urlencode($owner) . '/private/full';
	$params = array('xoauth_requestor_id' => $owner);
    return twolegged($base_feed, $params, 'POST', $sheetdata, '3.0');
}

/*
 * GET SHEET INFORMATION FUNCTIONS (ID, FEEDS, KEY, LINK, ETC)
 */

function get_sheetid($feed, $title) {
	foreach ($feed->entry as $entry) {
		if ($entry->title == $title) {
			$temp = substr($entry->id,strrpos($entry->id,'/')+1,10);
			return $temp;
		}
	}
}

function get_sheet_key($sheet, $delim = '%3A') {
	$rawtemp = substr($sheet,strpos($sheet,$delim)+strlen($delim),100);
	$links = explode('?',$rawtemp);
	return $links[0]; // id for raw data file for ce-report
}

function get_cell_contents($base_feed, $owner, $cells) {
	$contents = array();
	$params = array('xoauth_requestor_id' => $owner, 'min-row' => $cells[0], 'max-row' => $cells[1], 'min-col' => $cells[2], 'max-col' => $cells[3]);
//    $contenttype = 'application/x-www-form-urlencoded';
    $query  = twolegged($base_feed, $params, 'GET', null, '3.0', null);
    if (!$query->info['http_code'] == 200) {
    	error('Got bad return from Google sheet query');
    } else {
		$feed = simplexml_load_string($query->response);
		// this works because if the query comes back empty for the search there is no entry element
    }
	foreach ($feed->entry as $cell) {
		$contents[s($cell->title)] = $cell->content;
	}
	return $contents;
}


/*
 *  function get_sheet_link
 *  parameter $owner: the account from which to do the query
 *  parameter $title: document to look for
 *  TODO: how is this used and could we have a conflict with the null parameters?
 *  parameter $rel (optional) the link "rel" identifier in the returned xml if we want a link returned
 */
function get_sheet_link($owner, $title, $rel = null, $collection = null, $entry = null) {
    $base_feed = 'https://docs.google.com/feeds/' . urlencode($owner) . '/private/full';
    $base_feed = $collection !== null ? $base_feed . '/' . $collection . '/contents' : $base_feed; // search in a collection if necessary
	$params = array('xoauth_requestor_id' => $owner, 'title' => $title, 'title-exact' => 'true');
    $contenttype = 'application/x-www-form-urlencoded';
    $query  = twolegged($base_feed, $params, 'GET', null, '3.0', $contenttype);
    if (!$query->info['http_code'] == 200) {
    	error('Got bad return from Google sheet query');
    } elseif (is_null($rel)) {
    	return $query;
    } else {
		$feed = simplexml_load_string($query->response);
//		var_export($query->response);
		// this works because if the query comes back empty for the search there is no entry element
		if ($entry === null) {
			return get_href($feed, $rel, $entry);
		} else {
			return get_href_noentry($feed, $rel);
		}
    }
}

/*
 * UPDATE CELL CONTENTS FUNCTIONS
 */

function update_import_cell($base_feed, $owner, $key, $sheetid, $row, $col, $formula, $celledit) {
	$updatedata = '<?xml version=\'1.0\' encoding=\'UTF-8\'?>
					<entry xmlns="http://www.w3.org/2005/Atom" xmlns:docs="http://schemas.google.com/docs/2007" xmlns:gs="http://schemas.google.com/spreadsheets/2006">
						    <id>https://spreadsheets.google.com/feeds/cells/' . $key . '/' . $sheetid . '/private/full/R' . $row . 'C' . $col . '</id>
					    <link rel="edit" type="application/atom+xml"
						       href="' . $celledit . '"/>
						<gs:cell row="' . $row . '" col="' . $col . '" inputValue="' . $formula . '"/>
					</entry>';
	$params = array('xoauth_requestor_id' => $owner);
//    return send_request('PUT', $base_feed, $headers, null, $updatedata, '3.0');
	return twolegged($base_feed, $params, 'PUT', $updatedata, '3.0');
}

// write out the roll-up lines to the departmental report
// move to current term collection
// assign permissions
function ce_writedeptresults($rows, $owner, $worksheetid, $key, $colarray, $startrow = 0) {
    if (sizeof($rows) > 40) {
		$chunkrows = array_chunk($rows, 40, true);
		for ($i = 0; $i < sizeof($chunkrows); $i++) {
	    	ce_writedeptresults($chunkrows[$i], $owner, $worksheetid, $key, $colarray, $i * 120);
		}
	}
	$base_feed = 'https://spreadsheets.google.com/feeds/cells/' . $key . '/' . $worksheetid . '/private/full';
	$batchcellpost = '<feed xmlns="http://www.w3.org/2005/Atom"
      					xmlns:batch="http://schemas.google.com/gdata/batch"
      					xmlns:gs="http://schemas.google.com/spreadsheets/2006">
  							<id>' . $base_feed . '</id>';
    $params = array('xoauth_requestor_id' => $owner);
	foreach ($rows as $evalteach => $title) {
		$startrow += 3;	// starts out at row 8 so becomes $startrow + $row to get there
		// add the course/ teacher entry for column A
		$joinedvalue = '';
		$col = 1;
		$row = 5;
		$url = $base_feed . '/R' . s($startrow + $row) . 'C' . $col;
		$batchcellpost .= batch_cell_post($url, 'A' . s($startrow + $row), $startrow + $row, $col, $evalteach);
		foreach ($title as $location => $value) {
			// split location into row/ col
			$col = preg_replace('/(\d+)/', '', $location);
			$row = preg_replace('/([A-Z]+)/', '', $location);
			if ($location == 'B5') {
				$row = 7;
				$col = 'A';
/*			} elseif ($location == 'F7') {
				$joinedvalue .= '  :  ' . $value;
				$value = $joinedvalue;
				$row = 7;
				$col = 'A';
*/			} else {
//				$value = $row == 7 ? "'" . $value : sprintf("%.2f",$value);
				$value = $row == 7 && $col <> 'F' ? "'" . $value : sprintf("%.2f",$value);
			}
			//			$value = $row == 7 ? "'" . $value : preg_replace('/(\d+)\.(\d+)/','/(\d+)\.(\d{2})/',$value);
			if (array_key_exists($col, $colarray)) {
				$url = $base_feed . '/R' . s($startrow + $row) . 'C' . $colarray[$col];
				$batchcellpost .= batch_cell_post($url, $location, $startrow + $row, $colarray[$col], $value);
			}
		}
	}
	$batchcellpost .= '</feed>';
    $response  = twolegged($base_feed . '/batch', $params, 'POST', $batchcellpost, null, null, null, 'batch');
//    $response = send_request('PUT', $base_feed . '/batch', $headers, null, $batchcellpost, '3.0');
}

function cell_post($url, $id, $row, $col, $value) {
	return '<entry>
	    <id>' . $url . '</id>
	    <link rel="edit" type="application/atom+xml"
	    	href="' . $url . '"/>
	    <gs:cell row="' . s($row) . '" col="' . s($col) . '" inputValue="' . $value . '"/>
		</entry>';
}

function batchdocpermpost($email, $role, $action, $base_feed) {
	$id = $base_feed . '/' . urlencode('user:' . $email);
	switch ($action) {
		case 'add':
			return '<entry>
			<batch:id>' . $id . '</batch:id>
			<batch:operation type="insert"/>
			<gAcl:role value="' . $role . '"/>
			<gAcl:scope type="user" value="' . $email . '"/>
			</entry>';
		case 'delete':
			return '<entry>
			<id>' . $id . '</id>
			<batch:operation type="delete"/>
			<gAcl:role value="' . $role . '"/>
			<gAcl:scope type="user" value="' . $email . '"/>
			</entry>';
		case 'update':
			return '<entry>
			<id>' . $id . '</id>
			<batch:operation type="update"/>
			<gAcl:role value="' . $role . '"/>
			<gAcl:scope type="user" value="' . $email . '"/>
			</entry>';
	}
}

function batch_cell_post($url, $id, $row, $col, $value) {
//	$url = $base_feed . '/R' . $row . 'C' . $col;
	return '<entry>
	    <batch:id>' . $id . '</batch:id>
	    <batch:operation type="update"/>
	    <id>' . $url . '</id>
	    <link rel="edit" type="application/atom+xml"
	    	href="' . $url . '/version"/>
	    <gs:cell row="' . s($row) . '" col="' . s($col) . '" inputValue="' . $value . '"/>
		</entry>';
}

function cellarray($context=null) {
	switch ($context) {
		case 'dean':
			return array(
				'A' => '1',
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
		default:
			return array(
				'A' => '1',
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
	}
}

/*
 * NO LONGER USED
 */
function get_cell_href($admin, $owner,$base_feed, $ref) {

//	$params = array('xoauth_requestor_id' => $admin);
//    $contenttype = 'application/x-www-form-urlencoded';
//	$contenttype = null;
    $query = send_request('GET', $base_feed, $headers, null, null, '3.0');
//	$query  = twolegged($base_feed, $params, 'GET', null, '3.0', $contenttype);
    if (!$query->info['http_code'] == 200) {
    	error('Got bad return from Google sheet query');
    } else {
		$feed = simplexml_load_string($query->response);
		$edittemp = substr($query->response,strpos($query->response,"<link rel='edit'"),400);
		$edittemp2 = substr($edittemp,strpos($edittemp,'https://sp'),200);
		$edithref = substr($edittemp2,0,strpos($edittemp2,"'/><gs:"));
		return $edithref;
    }
}



/* OLDER VERSION, MAY NEED TO REUSE
function membersmaintain($auth,$groupname,$members, $CONSUMER_KEY, $courseid) {
    $headers = "Authorization: GoogleLogin auth=" . $auth;
    $gmembers = get_group_permissions($groupname, $CONSUMER_KEY, $headers);

    // check differences
	$deleted = array_diff_assoc($gmembers, $members);
	$added = sizeof($members) > 200 ? array() : array_diff_assoc($members, $gmembers);

    // delete first due to aliases
    foreach($deleted as $member=>$permission) {
        $base_feed = 'https://apps-apis.google.com/a/feeds/group/2.0/' . $CONSUMER_KEY . '/' . $groupname . '/' . $permission . '/' . $member;
	    $response = send_request('DELETE', $base_feed, $headers, null, null, '2.0');
	    if ($response->info['http_code'] == 200) {
		   	add_to_log($courseid, 'morsle', "Deleted", null, "$member as $permissions from $groupname");
		} else {
		   	add_to_log($courseid, 'morsle', "DELETE FAILED FOR ", null, "$member as $permission from $groupname");
		}
    }

    foreach($added as $member=>$permission) {
   		// if owner, make a member first
		if ($permission === 'owner') {
			$permissiondata = group_acl_post($member);
			$this->base_feed = $this->group_feed . $this->groupname . '/member';
			$response = send_request('POST', $this->base_feed, $this->authstring, null, $permissiondata, '2.0');
			if ($success) {
				$this->log("added $member as member to $this->groupname", $this->courseid);
			} else {
				$this->log("ADD FAILED FOR $member as member to $this->groupname", $this->courseid);
			}
		}

    	$permissiondata = group_acl_post($member);
        $base_feed = 'https://apps-apis.google.com/a/feeds/group/2.0/' . $CONSUMER_KEY . '/' . $groupname . '/' . $permission;
 		$response = send_request('POST', $base_feed, $headers, null, $permissiondata, '2.0');
	    if ($response->info['http_code'] == 201) {
		   	add_to_log($courseid, 'morsle', "added", null, "$member as $permission to $groupname");
		} else {
		   	add_to_log($courseid, 'morsle', "ADD FAILED FOR ", null, "$member as $permission to $groupname");
		}
    }
}
*/

/*
* assigns permissions to the folder for specified user (course)
*
*/
/* OLD VERSION MAY NEED TO BE REUSED
function folderpermissions($shortname, $readwrite, $user, $members, $folderid, $groupfullname, $courseid) {
	// what does google say we have for permissions on this folder
	$gmembers = get_folderpermissions($folderid,$user);
//        unset($gmembers[$groupfullname]);
//        $groupadd = true;
	$base_feed = 'https://docs.google.com/feeds/default/private/full/folder%3A' . $folderid . '/acl';
	$foldername = $shortname . '-' . $readwrite;

	$deleted = array_diff_key($gmembers, $members);
	$added =  array_diff_key($members, $gmembers);
	// delete first because we may be dealing with aliases
	foreach($deleted as $member=>$permission) {
		$delete_base_feed = 'https://docs.google.com/feeds/default/private/full/' . $folderid . '/acl/user%3A' . $member;
		$params = array('xoauth_requestor_id' => $user);
		$response = twolegged($delete_base_feed, $params, 'DELETE');
		if ($response->info['http_code'] == 200) {
			add_to_log($courseid, 'morsle', "Deleted",null,"$member from $permission for $foldername");
		} else {
			add_to_log($courseid, 'morsle', "DELETE FAILED FOR ", null, "$member as $permission for $foldername");
		}
	}

	// add new members
	foreach($added as $member=>$permission) {
		// add owners to permissions if they don't already exist
		// TODO: pull this out and combine with other post creation statements
		$permissiondata = acl_post($member, $permission, 'user');
		$params = array('xoauth_requestor_id' => $user,'send-notification-emails' => 'false');
		$response = twolegged($base_feed, $params, 'POST', $permissiondata);
		if ($response->info['http_code'] == 201) {
			add_to_log($courseid, 'morsle', "Added ", null, "$member as $permission from $foldername");
		} else {
			add_to_log($courseid, 'morsle', "ADD FAILED FOR ", null, "$member as $permission from $foldername");
		}
	}
	return true;
}

function portfoliositepermissions($portfolioname, $studentname, $studentemail, $owners, $user, $CONSUMER_KEY) {
	$portfolioname = $CONSUMER_KEY . '/' . $portfolioname . '/';
	$base_feed = 'https://sites.google.com/feeds/acl/site/' . $portfolioname;
	$gmembers = get_sitepermissions($portfolioname,$user, $CONSUMER_KEY);
	$params = array('xoauth_requestor_id' => $user);


	// temporarily add student to owners to get a proper comparison
	$owners[$studentemail] = '';
	$deleted = array_diff_key($gmembers, $owners);
	$added =  array_diff_key($owners, $gmembers);
	unset($owners[$studentemail]);

	// delete first because we may be dealing with aliases
	foreach($deleted as $owner=>$permission) {
		$delete_base_feed = $base_feed . 'user%3A' . $owner;
		$response = twolegged($delete_base_feed, $params, 'DELETE', null, '1.4');
		if ($response->info['http_code'] == 200) {
			add_to_log($courseid, 'morsle', "Deleted", null, "$owner from site for $sitename");
		} else {
			add_to_log($courseid, 'morsle', "DELETE FAILED FOR ", null, "$owner from site for $sitename");
		}
	}


	// add owners
	foreach($added as $owner=>$permission) {
		$siteacldata = acl_post($owner, 'owner', 'user');
		$response = twolegged($base_feed, $params, 'POST', $siteacldata, '1.4');
		if ($response->info['http_code'] == 201) {
			add_to_log($courseid, 'HPE', "Added", null, "$owner from site for $studentname");
		} else {
			add_to_log($courseid, 'morsle', "ADD FAILED FOR ", null, "$owner from site for $studentname");
		}
	}

	// add student as editor
	$siteacldata = acl_post($studentemail, 'writer', 'user');
	$response = twolegged($base_feed, $params, 'POST', $siteacldata, '1.4');
	if ($response->info['http_code'] == 201) {
		add_to_log($courseid, 'HPE', "Added", null, "$studentname from site for $studentname");
	} else {
		add_to_log($courseid, 'morsle', "ADD FAILED FOR ", null, "$studentname from site for $studentname");
	}
	return true;
}


function sitepermissions($owner, $members, $user, $morslerec, $group, $courseid, $CONSUMER_KEY) {
	// what does google say we currently have for permissions?
	$sitename = substr($morslerec->siteid,strpos($morslerec->siteid,$CONSUMER_KEY),100);
	$gmembers = get_sitepermissions($sitename,$user, $CONSUMER_KEY);
	$params = array('xoauth_requestor_id' => $user);

	// don't process the group acl or the course owner acl
	unset($gmembers[$user]);
	unset($gmembers[$group]);

	$deleted = array_diff_key($gmembers, $members);
	$added =  array_diff_key($members, $gmembers);
	$base_feed = 'https://sites.google.com/feeds/acl/site/' . $sitename;

	// delete first because we may be dealing with aliases
	foreach($deleted as $member=>$permission) {
		$delete_base_feed = $base_feed . 'user%3A' . $member;
		$response = twolegged($delete_base_feed, $params, 'DELETE', null, '1.4');
		if ($response->info['http_code'] == 200) {
			add_to_log($courseid, 'morsle', "Deleted", null, "$member from site for $sitename");
		} else {
			add_to_log($courseid, 'morsle', "DELETE FAILED FOR ", null, "$member from site for $sitename");
		}
	}
	foreach($added as $member=>$permission) {
		$siteacldata = acl_post($member, $permission, 'user');
		$response = twolegged($base_feed, $params, 'POST', $siteacldata, '1.4');
		if ($response->info['http_code'] == 201) {
			add_to_log($courseid, 'morsle', "Added", null, "$member for site for $sitename");
		} else {
			add_to_log($courseid, 'morsle', "ADD FAILED FOR ", null, "$member for site for $sitename");
		}
	}
	return true;
}


function calpermissions($owner, $members, $user, $morslerec, $group, $courseid, $CONSUMER_KEY) {
	// calendar currently needs clientauth because redirects always go to a clientauth site
	$urlowner = str_replace('@', '%40', $owner);
	$service = 'cl';
	$password = rc4decrypt($morslerec->password);
	$auth = clientauth($owner,$password,$service);
	$gmembers = get_calendarpermissions($owner,$auth);
	$authstring = "Authorization: GoogleLogin auth=" . $auth;
	$headers = array($authstring, "GData-Version: 2.0");

	// don't process the domain acl or the course owner acl
	unset($gmembers[$owner]);
	unset($gmembers[$CONSUMER_KEY]);

	$deleted = array_diff_key($gmembers, $members);
	$added =  array_diff_key($members, $gmembers);

	// delete first because we may be dealing with aliases
	foreach($deleted as $member=>$permission) {
		$urluser = str_replace('@', '%40', $member);
		$base_feed = "https://www.google.com/calendar/feeds/$urlowner/acl/full/user%3A$urluser";
		$response = send_request('DELETE', $base_feed, $authstring, null, null, 2);
		if ($response->info['http_code'] == 201 || $response->info['http_code'] == 200) {
			add_to_log($courseid, 'morsle', "Deleted", null, "$member from calendar for $owner");
		} else {
			add_to_log($courseid, 'morsle', "DELETE FAILED FOR ", null, "$member from calendar for $owner");
		}
	}
	// add new permissions
	foreach($added as $member=>$permission) {
		$base_feed = "https://www.google.com/calendar/feeds/$urlowner/acl/full";
    	$calacldata = cal_acl_post($member, $permission);
		$response = send_request('POST', $base_feed, $authstring, null, $calacldata, 2);
    	if ($response->info['http_code'] == 201) {
			add_to_log($courseid, 'morsle', "Added", null, "$member for calendar for $owner");
		} else {
			add_to_log($courseid, 'morsle', "ADD FAILED FOR ", null, "$member for calendar for $owner");
		}
	}
	return true;
}
*/

function assign_file_permissions($member, $permission, $user, $base_feed) {
	$permissiondata = acl_post($member, $permission, 'user');
	$params = array('xoauth_requestor_id' => $user,'send-notification-emails' => 'false');
	$response = twolegged($base_feed, $params, 'POST', $permissiondata);
}

/***** GET PERMISSIONS FUNCTIONS *****/
//, 'max-results'=>'1000'
function get_group_permissions($groupname, $CONSUMER_KEY, $headers){

	// ADD AND DELETE GROUP MEMBERS
    // accumulate all what Google has for existing membership
    $gmembers = array();
   	$base_feed = 'https://apps-apis.google.com/a/feeds/group/2.0/' . $CONSUMER_KEY . '/' . $groupname . '/member';
    $response = send_request('GET', $base_feed, $headers, null, null, '2.0');
	if ($response->info['http_code'] <> 200) {
		add_to_log($courseid, 'morsle', "GET FAILED FOR ", null, "$key from $groupname");
		return array();
	} else {
	    $feed = simplexml_load_string($response->response);
	    foreach ($feed->entry as $exist) {
	    	$parts = explode('/', $exist->id);
	    	$email = str_replace('%40','@',$parts[sizeof($parts)-1]);
//	        $email = str_replace('%40','@',substr(strrchr($exist->id,'/'),1,80));
			$gmembers[$email] = $parts[sizeof($parts) - 2];
//			$gmembers[$email] = strpos(substr($exist->id,strrpos($exist->id,'/') - 10,10),'member') ? 'member' : 'owner';
	    }
	}
   	$base_feed = 'https://apps-apis.google.com/a/feeds/group/2.0/' . $CONSUMER_KEY . '/' . $groupname . '/owner';
    $response = send_request('GET', $base_feed, $headers, null, null, '2.0');
	if ($response->info['http_code'] <> 200) {
		add_to_log($courseid, 'morsle', "GET FAILED FOR ", null, "$key from $groupname");
		return array();
	} else {
	    $feed = simplexml_load_string($response->response);
	    foreach ($feed->entry as $exist) {
	    	$parts = explode('/', $exist->id);
	    	$email = str_replace('%40','@',$parts[sizeof($parts)-1]);
//	        $email = str_replace('%40','@',substr(strrchr($exist->id,'/'),1,80));
			$gmembers[$email] = $parts[sizeof($parts) - 2];
//			$gmembers[$email] = strpos(substr($exist->id,strrpos($exist->id,'/') - 10,10),'member') ? 'member' : 'owner';
	    }
	}
	return $gmembers;
}


function get_folderpermissions($folderid, $user) {
	$role = array();
	$base_feed = 'https://docs.google.com/feeds/default/private/full/folder%3A' . $folderid . '/acl';
	$params = array('xoauth_requestor_id' => $user);
	$response = twolegged($base_feed, $params, 'GET');
	$permissions = $response->response;
	preg_match_all("/<gAcl:role[^>]+>/", $permissions, $roles);
	preg_match_all("/<gAcl:scope[^>]+>/", $permissions, $scopes);
    $scopestring = 'value=';
    $rolestring = 'value=';
    foreach ($scopes[0] as $key=>$value) {
        $scope = substr($value,strpos($value,$scopestring) + strlen($scopestring) + 1, -3);
		// need this line because the return comes back with an additional attribute for added users
        $scope = substr($scope,0,strpos($scope,"'"));
        if (!empty($scope)) {
            $role[$scope] = substr($roles[0][$key],strpos($roles[0][$key],$rolestring) + strlen($rolestring) + 1,-3);
        }
	}
	return $role;
}


/*
 * can be folder or document resource
*/
function get_docspermissions($id, $user) {
	global $COURSE;
	$role = array();
	$base_feed = 'https://docs.google.com/feeds/default/private/full/' . $id . '/acl';
	$params = array('xoauth_requestor_id' => $user);
	$response = twolegged($base_feed, $params, 'GET');
	$feed = simplexml_load_string($response->response);
	if ($response->info['http_code'] <> 200) {
		//		add_to_log($COURSE, 'morsle', "GET FAILED FOR ", null, "$user: folder:$id");
		return false;
	} else {
		//		add_to_log($courseid, 'morsle', "GET SUCCEEDED FOR ", null, "$key from $groupname");
		$permissions = $response->response;
		preg_match_all("/<gAcl:role(?:(?!'\/>).)*/", $permissions, $roles);
		preg_match_all("/<gAcl:scope(?:(?!'\/>).)*/", $permissions, $scopes);
		$scopestring = "/<gAcl:scope type='user' value='/";
		$scopeend = "/'\s*name(.)*/";
		$rolestring = "/<gAcl:role value='/";
		foreach ($scopes[0] as $key=>$value) {
			$scope = preg_replace($scopestring, '', $value);
			$scope = preg_replace($scopeend,'',$scope);
			$roletemp = preg_replace($rolestring, '', $roles[0][$key]);
			$role[$scope] = $roletemp;
			//			$scope = substr($value,strpos($value,$scopestring) + strlen($scopestring) + 1, -3);
			// need this line because the return comes back with an additional attribute for added users
			//	        $scope = substr($scope,0,strpos($scope,"'"));
			//	        if (!empty($scope)) {
			//	        }
		}
	}
	return $role;
}

function get_sitepermissions($sitename, $user, $CONSUMER_KEY) {
	$role = array();
//	$owner = str_replace('@', '%40', $owner);
	$base_feed = 'https://sites.google.com/feeds/acl/site/' . $sitename;
	$params = array('xoauth_requestor_id' => $user);
	$response = twolegged($base_feed, $params, 'GET',null,'1.4');
	$permissions = $response->response;
	preg_match_all("/<gAcl:role[^>]+>/", $permissions, $roles);
    preg_match_all("/<gAcl:scope[^>]+>/", $permissions, $scopes);
    $rolestring = 'value=';
    $scopestring = 'value=';
    foreach ($scopes[0] as $key=>$value) {
        $scope = substr($value,strpos($value,$scopestring) + strlen($scopestring) + 1, -3);
        if (!empty($scope)) {
            $role[$scope] = substr($roles[0][$key],strpos($roles[0][$key],$rolestring) + strlen($rolestring) + 1,-3);
        }
    }
    return $role;
}

// calendar currently needs clientauth because redirects always go to a clientauth site
function get_calendarpermissions($owner, $auth) {
	$role = array();
	$owner = str_replace('@', '%40', $owner);
	$base_feed = "https://www.google.com/calendar/feeds/$owner/acl/full";
	$authstring = "Authorization: GoogleLogin auth=" . $auth;
	$headers = array($authstring, "GData-Version: 2.0");
	$response = send_request('GET', $base_feed, $authstring, null, null, 2);
	$permissions = $response->response;
	preg_match_all("/<gAcl:role[^>]+>/", $permissions, $roles);
    preg_match_all("/<gAcl:scope[^>]+>/", $permissions, $scopes);
    $rolestring = 'gCal/2005#';
    $scopestring = 'value=';
    foreach ($scopes[0] as $key=>$value) {
        $scope = substr($value,strpos($value,$scopestring) + strlen($scopestring) + 1, -3);
        if (!empty($scope)) {
            $role[$scope] = substr($roles[0][$key],strpos($roles[0][$key],$rolestring) + strlen($rolestring),-3);
        }
    }
    return $role;
}

/**** ACL POST CREATION FUNCTIONS ****/

function cal_acl_post($user, $role) {
	$returnval =  "<entry xmlns='http://www.w3.org/2005/Atom'
xmlns:gAcl='http://schemas.google.com/acl/2007'>
<category scheme='http://schemas.google.com/g/2005#kind'
term='http://schemas.google.com/acl/2007#accessRule'/>
<gAcl:scope type='user' value='$user'></gAcl:scope>
<gAcl:role value='http://schemas.google.com/gCal/2005#$role'></gAcl:role>
</entry>";
	return $returnval;
}

function group_acl_post($member) {
	return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<atom:entry xmlns:atom="http://www.w3.org/2005/Atom"
            xmlns:apps="http://schemas.google.com/apps/2006"
            xmlns:gd="http://schemas.google.com/g/2005">
            <apps:property name="email" value="' . $member . '"/>
            <apps:property name="memberId" value="' . $member . '"/></atom:entry>';
}

function acl_post($user, $role, $type) {
	return "<entry xmlns=\"http://www.w3.org/2005/Atom\"
			xmlns:gAcl='http://schemas.google.com/acl/2007'>
			<category scheme='http://schemas.google.com/g/2005#kind'
			term='http://schemas.google.com/acl/2007#accessRule'/>
			<gAcl:role value='$role'/>
			<gAcl:scope type='$type' value='$user'/>
			</entry>";
}


/**** FEEDS AND LINKS ****/
/*
 * @$owner = the user being interrogated
 * @ $collection = the collection id (if any) to be searched
 * TODO: combine with get_doc_feed_match_name
 */
function get_doc_feed($owner, $collection = null) {
	$base_feed = 'https://docs.google.com/feeds/default/private/full';
	if ($collection !== null) {
		$base_feed .= '/' . $collection . '/contents';
	}
	$params = array('xoauth_requestor_id' => $owner, 'max-results' => 500);
    $query  = twolegged($base_feed, $params, 'GET', null, '3.0');
    if ($query->info['http_code'] == 200) {
    	$feed = simplexml_load_string($query->response);
    	return $feed;
	} else {
		return false;
	}
}

function get_doc_id($link) {
	if (substr($link,-5,5) == '/edit') {
		$split = explode('/', $link);
		return $split[sizeof($split) - 2];
	} else {
	    $split = explode('=', $link);
		return $split[1];
	}
}

function get_feed_by_id($owner, $id) {
//	global $CFG;
//	require_once("$CFG->dirroot/google/constants.php");
//	require_once("$CFG->dirroot/google/gauth.php");
	$url = DOCUMENTFEED_URL . '/' . $id;
	$params = array('xoauth_requestor_id' => $owner);
	$query  = twolegged($url, $params, 'GET', null, '3.0');
	if ($query->info['http_code'] == 200) {
		$feed = new SimpleXMLElement($query->response);
		return $feed;
	} else {
		return false;
	}
}

/*
 * @$owner = the user being interrogated
 * @ $collection = the collection id (if any) to be searched
 */
function get_doc_feed_match_name($owner, $name, $collection = null) {
	$base_feed = 'https://docs.google.com/feeds/default/private/full';
	if ($collection !== null) {
		$base_feed .= '/' . $collection . '/contents';
	}
	$params = array(
					'xoauth_requestor_id' => $owner,
					'title' => $name,
					'showfolders' => 'true');
    $query  = twolegged($base_feed, $params, 'GET', null, '3.0');
    if ($query->info['http_code'] == 200) {
    	$feed = simplexml_load_string($query->response);
    	return $feed;
	} else {
		return false;
	}
}

/*
 * TODO: do we need this with get_href?
 */

function get_feed_edit_link($owner) {
	$rel = 'http://schemas.google.com/g/2005#resumable-create-media';
	if ($feed = get_doc_feed($owner)) {
		$links = explode('?',get_href_noentry($feed, $rel));
		return ($links[0]);
	} else {
		return false;
	}
}


/*
 * TODO: why is this function needed?
 */

function get_feed($base_feed, $owner, $xparams = null, $contenttype = null) {
	$params = array('xoauth_requestor_id' => $owner);
	if (!is_null($xparams)) {
		$params = $xparams;
	}
    $query  = twolegged($base_feed, $params, 'GET', null, '3.0', $contenttype);
    if (!$query->info['http_code'] == 200) {
    	error('Got bad return from Google sheet query');
    } else {
		$feed = simplexml_load_string($query->response);
//		return get_href($feed, $rel);
		return $feed;
    }
}

/*
 * TODO: can we combine the two get_href functions?
 */

function get_href_noentry($feed, $rel) {
    foreach($feed->link as $link) {
        if ($link['rel'] == $rel) {
			return $link['href'];
        }
	}
	return false;
}

/*
 * Now handles no-entry also
 * TODO: remove the use of the no-entry separate function
 */
function get_href($feed, $rel, $entry) {
	if (isset($feed->entry) && $entry == null) {
		foreach($feed->entry->link as $link) {
	        if ($link['rel'] == $rel) {
				return $link['href'];
	        }
		}
	} else {
	    foreach($feed->link as $link) {
	        if ($link['rel'] == $rel) {
				return $link['href'];
	        }
		}
	}
	return false;
}
?>