<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Morsle Plugin
 *
 * @since 2.2
 * @package    repository
 * @subpackage googledocs
 * @copyright  since 2011 Bob Puffer puffro01@luther.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir.'/googleapi.php');
require_once("$CFG->dirroot/repository/morsle/locallib.php");
require_once("$CFG->dirroot/repository/lib.php");
require_once("$CFG->dirroot/google/gauth.php");
require_once("$CFG->dirroot/google/lib.php");

class repository_morsle extends repository {
    private $subauthtoken = '';

    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        global $USER, $COURSE, $DB;
        if ( !$this->domain = get_config('morsle','consumer_key')) {
        	throw new moodle_exception('Consumer key not set up');
        }
        parent::__construct($repositoryid, $context, $options);
        // days past last enrollment day that morsle resources are retained
        $this->expires = get_config('morsle','morsle_expiration') == 0 ? 600 * 24 * 60 * 60: get_config('blocks/morsle','morsle_expiration') * 24 * 60 * 60;

        $this->curtime = time();

        // set basefeeds
        $this->prov_feed = "https://apps-apis.google.com/a/feeds/$this->domain/user/2.0/";
        $this->site_feed = "https://sites.google.com/feeds/site/$this->domain";
        $this->docs_feed = 'https://docs.google.com/feeds/default/private/full/';
        $this->alias_feed = "https://apps-apis.google.com/a/feeds/alias/2.0/$this->domain/?start=aaaarnold@luther.edu";
        $this->group_feed = 'https://apps-apis.google.com/a/feeds/group/2.0/' . $this->domain . '/';
        $this->id_feed = 'https://docs.google.com/feeds/id/';
        $this->cal_feed = 'https://www.google.com/calendar/feeds/default/private/full';
		$this->owncalendars_feed = 'https://www.google.com/calendar/feeds/default/owncalendars/full';

        // set authorization
        $auth = clientauth();
        $this->authstring = "Authorization: GoogleLogin auth=" . $auth;
        $this->headers = array($this->authstring, "GData-Version: 2.0");
        $this->disregard = "'Past Term','DELETED'"; // skip marked for delete, unapproved and past term

//        $this->cron();
        // TODO: I wish there was somewhere we could explicitly put this outside of constructor..
//        $googletoken = optional_param('token', false, PARAM_RAW);
//        if($googletoken){
//            $gauth = new google_authsub(false, $googletoken); // will throw exception if fails
//            google_docs::set_sesskey($gauth->get_sessiontoken(), $USER->id);
//        }
//        $this->check_login();
    }

    /*
     * here's where the fun is going to start getting google docs for
     * the course
     * the user
     * the department, if exists
     */
    public function get_listing($path='', $page = '', $query = null) {
		global $CFG, $USER, $OUTPUT, $COURSE, $DB;
		require_once("$CFG->dirroot/google/constants.php");
		require_once('course_constants.php');
		$ret = array();
        $ret['dynload'] = true;
        $user = build_user();
        $course = $COURSE;
 //    	$user = $USER->email; // TODO: uncomment

        $useraccount = $USER->email;
        $user = $useraccount;

        $deptstr = get_string('departmentaccountstring', 'repository_morsle');

        // get departmental folder if exists

        $shortname = is_number(substr($course->shortname,0,5)) ? substr($course->shortname, 6) : $course->shortname;
        $morsleaccount = strtolower($course->shortname . '@' . $this->domain);

        // SPLIT INTO DEPARTMENTAL CODES

        $dept = explode("-",$shortname);
        if (defined($dept[0])) {

	        $deptstr = CONSTANT($dept[0]) . $deptstr;
	        $deptshortstr = strtolower(substr($deptstr,0,6));
	        $deptaccount = strtolower($deptstr . '@' . $this->domain);
        } else {
	        $deptstr = 'nodept';
	        $deptshortstr = 'nodept';
        	$deptaccount = 'nodept';
        }

        // get course level folders or documents
	    $maxfiles = get_config('morsle','maxfilesreturned');

        // get a docid if available
	    $path = base64_decode($path);

	    if ($path == '') {
	    	$root_path = null;

	    	$pathleftover = null;

	    	$search_path = null;
	    } elseif ($path == $deptstr) {
	    	$root_path = $deptstr;
	    	$pathleftover = null;
	    	$search_path = null;
	    } elseif (strpos($path, '|')) {
			list($docid,$pathleftover) = explode('|', $path);
	        $search_path = 'folder%3A' . $docid;

	        $root_path = strtolower(substr($pathleftover, 0, 6));
		} else {
			$root_path = strtolower(substr($path,0,6));
			$pathleftover = $path;
			$search_path = null;
		}

		// handle a search instance
		if ($query !== null) {
			$root_path = 'queryi';
		}

		switch ($root_path) {
			case null: // empty: get only the readonly and writeable folders plus any files and user folder and (if available) department folder
		        $user =  $morsleaccount;
		        $search = array(
						'xoauth_requestor_id' => $user,
//						'foldersonly' => 'true', // identifies that we're just looking for the special morsle folders
		        		'showfolders' => 'true',
		        		'folder' => 'folder%3Aroot',
						'max-results'=>$maxfiles
						);
	        	if ($search_path !== null) { // looking for another folder's contents
	        		$search['folder'] = $search_path;
	        	}
/*		    	if ($query !== null) {
		    		$search['q'] = $query;
		    	} */ //not ever going to get here
				$mauth = new morsle_oauth_request(null, null, $search); // subauthtoken ignored
//				unset($search['repo_id']);
		        $mdocs = new morsle_docs($mauth);

		        $ret['list'] = $mdocs->get_file_list($search, $this);

		    	// get user level folders or documents
		        $user = $useraccount;
		        $title = get_string('useraccountstring', 'repository_morsle') . $user;
		        $url = DOCUMENTFEED_URL;
	            $ret['list'][] =  array(
	            	'title' => $title,
	                'url' => $url,
	                'source' => $url,
	                'date'   => usertime(strtotime(time())),
	                'children' => array(),
	                'path' => base64_encode('User Files'),
	                'thumbnail' => (string) $OUTPUT->pix_url('f/folder-64')
	             	);

		        // check to see if we even have a departmental account for this department but don't show the departmental collection if we're already in it indicated by $wdir
		        // TODO: this needs to change if we eliminate morsle table, but if the read-only or writeable folders get renamed then we need the table

	            // department account if exists

	            $conditions = " shortname = '$deptstr' ";
		        $user = $deptaccount;
		        $title = get_string('deptaccountstring', 'repository_morsle') . $user;
		        if (strpos($path,$deptstr) === false && $is_morsle_dept = $DB->get_record_select('morsle_active', $conditions)) {
		            $ret['list'][] =  array(
		            	'title' => $deptstr,
		                'url' => $url,
		                'source' => $url,
		                'date'   => usertime(strtotime(time())),
		                'children' => array(),
		                'path' => base64_encode($deptstr),
		                'thumbnail' => (string) $OUTPUT->pix_url('f/folder-64')
		             	);
	            }

	            $ret['path'][]['name'] = 'Morsle Files';
	            break;
			case 'queryi':
			case 'user f':  // user account google files
	        	$search = array(

	        			'xoauth_requestor_id' => $user,

	        			'path' => $pathleftover,
	        			'showfolders' => 'true',
		        		'repo_id'=>5,
	        			'max-results'=>$maxfiles

	        	);
	        	if ($search_path !== null) { // looking for another folder's contents
	        		$search['folder'] = $search_path;
	        	}

		    	if ($query !== null) {
		    		$search['q'] = $query;
		    	}
	        	$mauth = new morsle_oauth_request(null, null, $search); // subauthtoken ignored

				unset($search['repo_id']);
	        	$mdocs = new morsle_docs($mauth);



	        	$ret['list'] = $mdocs->get_file_list($search, $this);

	        	$ret['path'][]['name'] = $pathleftover;
	        	break;
			case '/': // TODO: what does this get
				$search = array(

	        			'xoauth_requestor_id' => $user,
	        			'folder' => $search_path,

		        		'repo_id'=>5,
	        			'max-results'=>$maxfiles

	        	);
				$mauth = new morsle_oauth_request(null, null, $search); // subauthtoken ignored
				unset($search['repo_id']);
				$mdocs = new morsle_docs($mauth);

		        $ret['list'] = $mdocs->get_file_list($search, $this);
		        break;
			case $deptstr: // department account google files, if we got here it means department files exist
		        $user =  $deptaccount;
	        	$search = array(
	        			'xoauth_requestor_id' => $user,
		                'path' => $pathleftover,
	        			'showfolders' => 'true',
//						'foldersonly' => 'true', // identifies that we're just looking for the special morsle folders
	        			'repo_id'=>5,
	        			'max-results'=>$maxfiles
	        	);
	        	if ($search_path !== null) { // looking for another folder's contents
	        		$search['folder'] = $search_path;
	        	}
	        	$mauth = new morsle_oauth_request(null, null, $search); // subauthtoken ignored
				unset($search['repo_id']);
	        	$mdocs = new morsle_docs($mauth);

	        	$ret['list'] = $mdocs->get_file_list($search, $this);
	        	$ret['path'][]['name'] = $pathleftover;
	        	break;
			case 'morsle': // only way we'd get here is if the read-only or writeable folder got clicked
		        $user =  $morsleaccount;
				$search = array(
	        			'xoauth_requestor_id' => $user,
		                'path' => 'Morsle Files',
        				'max-results'=>$maxfiles
	        	);
	        	if ($search_path !== null) { // looking for another folder's contents
	        		$search['folder'] = $search_path;
	        	}
				$mauth = new morsle_oauth_request(null, null, $search); // subauthtoken ignored
				unset($search['repo_id']);
				$mdocs = new morsle_docs($mauth);

		        $ret['list'] = $mdocs->get_file_list($search);
	            $ret['path'][]['name'] = $pathleftover;
	            break;
			default: // empty: get only the readonly and writeable folders user folder and (if available) department folder
		        $user =  $morsleaccount;
//		        list($title, $domain) = explode('@',$user);

		        $search = array(
						'xoauth_requestor_id' => $user,
		        		'showfolders' => 'true',
						'max-results'=>$maxfiles
						);
	        	if ($search_path !== null) { // looking for another folder's contents
	        		$search['folder'] = $search_path;
	        	}
/*
		        $search = array(
						'xoauth_requestor_id' => $user,
		                'path' => 'Morsle Files',
						'foldersonly' => 'true', // identifies that we're just looking for the special morsle folders
						'title'=>$title,
		        		'repo_id'=>5,
						'max-results'=>$maxfiles
						);
*/
/*		    	if ($query !== null) {
		    		$search['q'] = $query;
		    	} */ //not ever going to get here
				$mauth = new morsle_oauth_request(null, null, $search); // subauthtoken ignored
//				unset($search['repo_id']);
		        $mdocs = new morsle_docs($mauth);

		        $ret['list'] = $mdocs->get_file_list($search, $this);
/*
		    	// get user level folders or documents
		        $user = $useraccount;
		        $title = get_string('useraccountstring', 'repository_morsle') . $user;
		        $url = DOCUMENTFEED_URL;
	            $ret['list'][] =  array(
	            	'title' => $title,
	                'url' => $url,
	                'source' => $url,
	                'date'   => usertime(strtotime(time())),
	                'children' => array(),
	                'path' => base64_encode('User Files'),
	                'thumbnail' => (string) $OUTPUT->pix_url('f/folder-64')
	             	);

*/		        // check to see if we even have a departmental account for this department but don't show the departmental collection if we're already in it indicated by $wdir
		        // TODO: this needs to change if we eliminate morsle table
		        $conditions = " shortname = '$deptstr' ";
		        $user = $deptaccount;
		        $title = get_string('deptaccountstring', 'repository_morsle') . $user;

		        if (strpos($path,$deptstr) === false && $is_morsle_dept = $DB->get_record_select('morsle_active', $conditions)) {

		            $ret['list'][] =  array(
		            	'title' => $deptstr,
		                'url' => $url,
		                'source' => $url,
		                'date'   => usertime(strtotime(time())),
		                'children' => array(),
		                'path' => base64_encode($deptstr),
		                'thumbnail' => (string) $OUTPUT->pix_url('f/folder-64')
		             	);
	            }


	            $ret['path'][]['name'] = 'Morsle Files';
		}
	    return $ret;
    }

    // TODO: where's this get called from?  there's not much we need here, could also add in the parameters from get_listing instead of calling it
    public function search($search_text, $page = 0) {
        $list = $this->get_listing(null, $page, $search_text);
        return $list;
    }

    public function get_file($url, $filename = '') {
        global $CFG;
        $path = $this->prepare_file($filename);
        $fp = fopen($path, 'w');
        $c = new curl;
        $c->download(array(array('url'=>$url, 'file'=>$fp)));
        // Close file handler.
        fclose($fp);
        return array('path'=>$path, 'url'=>$url);
    }

    public function get_link($encoded) {
		return $encoded;
/*
		$info = array();

        $browser = get_file_browser();

        // the final file
        $params = unserialize(base64_decode($encoded));
        $contextid  = clean_param($params['contextid'], PARAM_INT);
        $fileitemid = clean_param($params['itemid'], PARAM_INT);
        $filename = clean_param($params['filename'], PARAM_FILE);
        $filepath = clean_param($params['filepath'], PARAM_PATH);;
        $filearea = clean_param($params['filearea'], PARAM_AREA);
        $component = clean_param($params['component'], PARAM_COMPONENT);
        $context = get_context_instance_by_id($contextid);

        $file_info = $browser->get_file_info($context, $component, $filearea, $fileitemid, $filepath, $filename);
        return $file_info->get_url();
*/
    }

    /**
     * Defines operations that happen occasionally on cron
     * @return boolean
     */
    public function cron() {
        global $CFG;
    /// We are going to measure execution times

        // set this up so it runs at your desired interval
	    srand ((double) microtime() * 10000000);
	    $random100 = rand(0,100);
	    if ($random100 < 45) {     // Approximately every hour.
	        mtrace(date(DATE_ATOM) . " - Running m_cook...");
			$status = $this->m_cook();
	        mtrace(date(DATE_ATOM) . " - Done m_cook: $status");
	    }
	    if ($random100 > 0 && $random100 < 100) {     // Approximately every hour
	        mtrace(date(DATE_ATOM) . " - Running m_maintain...");
			$status = $this->m_maintain();
	        mtrace(date(DATE_ATOM) . " - Done m_maintain: $status");
	    }

	    if ($random100 > 55) {     // Approximately every hour.
	        mtrace(date(DATE_ATOM) . " -Running m_calendar...");
			$status = $this->m_calendar();
	        mtrace(date(DATE_ATOM) . " -Done m_calendar: $status");
	    }
   }
/*
    public function supported_filetypes() {
       return array('*');
    }
*/
    public function supported_returntypes() {
//        return FILE_REFERENCE;
//        return (FILE_INTERNAL | FILE_EXTERNAL);
        return FILE_EXTERNAL;
    }
    public static function get_type_option_names() {
        return array('admin_account', 'admin_password', 'oauthsecretstr', 'consumer_key', 'pluginname', 'maxfilesreturned', 'morsle_expiration');
    }

    public static function type_config_form($mform, $classname = 'repository') {
        parent::type_config_form($mform);
        $default = get_config('morsle', 'admin_account');
        if (empty($default)) {
            $default = '';
        }
        $mform->addElement('text', 'admin_account', get_string('admin_account_info', 'repository_morsle'));
        $mform->setDefault('admin_account', $default);

        $default = get_config('morsle', 'admin_password');
        if (isset($_POST['admin_password'])) {
        	$default = $_POST['admin_password'];
        	$_POST['admin_password'] = morsle_encode($default);
        } elseif (empty($default)) {
            $default = '';
        } else {
        	$mform->_constantValues['admin_password'] = morsle_decode($default);
        }
        $mform->addElement('passwordunmask', 'admin_password', get_string('admin_password_info', 'repository_morsle'));
        $mform->setDefault('admin_password', $default);

        $default = get_config('morsle', 'oauthsecretstr');
        if (isset($_POST['admin_password'])) {
        	$default = $_POST['oauthsecretstr'];
        	$_POST['oauthsecretstr'] = morsle_encode($default);
        } elseif (empty($default)) {
            $default = '';
        } else {
        	$mform->_constantValues['oauthsecretstr'] = morsle_decode($default);
        }
        $mform->addElement('passwordunmask', 'oauthsecretstr', get_string('oauthsecretstr', 'repository_morsle'));
        $mform->setDefault('oauthsecretstr', $default);

        $default = get_config('morsle', 'consumer_key');
        if (empty($default)) {
            $default = '';
        }
        $mform->addElement('text', 'consumer_key', get_string('consumer_key_info', 'repository_morsle'));
        $mform->setDefault('consumer_key', $default);

        $default = get_config('morsle', 'maxfilesreturned');
        if (empty($default)) {
            $default = 100;
        }
        $mform->addElement('text', 'maxfilesreturned', get_string('maxfilesreturned', 'repository_morsle'));
        $mform->setDefault('maxfilesreturned', $default);

        $default = get_config('morsle', 'morsle_expiration');
            if (empty($default)) {
            $default = 600;
        }
        $mform->addElement('text', 'morsle_expiration', get_string('morsle_expiration', 'repository_morsle'));
        $mform->setDefault('morsle_expiration', $default);

        $strrequired = get_string('required');
        $mform->addRule('admin_account', $strrequired, 'required', null, 'client');
        $mform->addRule('admin_password', $strrequired, 'required', null, 'client');
        $mform->addRule('oauthsecretstr', $strrequired, 'required', null, 'client');
        $mform->addRule('consumer_key', $strrequired, 'required', null, 'client');
    }
/*
    public function check_login() { // not likely to be used
        global $USER;
		return true;
        $sesskey = google_docs::get_sesskey($USER->id);

        if($sesskey){
            try{
                $gauth = new google_authsub($sesskey);
                $this->subauthtoken = $sesskey;
                return true;
            }catch(Exception $e){
                // sesskey is not valid, delete store and re-auth
                google_docs::delete_sesskey($USER->id);
            }
        }

        return false;
    }

    public function print_login($ajax = true){ // not likely to be used
        global $CFG;
        if($ajax){
            $ret = array();
            $popup_btn = new stdClass();
            $popup_btn->type = 'popup';
            $returnurl = $CFG->dirroot.'/repository/repository_callback.php?callback=yes&repo_id='.$this->id;
            $popup_btn->url = google_authsub::login_url($returnurl, google_docs::REALM);
            $ret['login'] = array($popup_btn);
            return $ret;
        }
    }

    public function logout(){
        global $USER;

        $token = google_docs::get_sesskey($USER->id);

        $gauth = new google_authsub($token);
        // revoke token from google
        $gauth->revoke_session_token();

        google_docs::delete_sesskey($USER->id);
        $this->subauthtoken = '';

        return parent::logout();
    }
*/

	/*
	 * Processes all current morsle_active records, creating or deleting components as necessary
	 * Any course that is current (mdl_course.startdate > now and is visible gets resources created (if not already done)
	 * Any course whose mdl_course.startdate + config.morsle_expiration (converted to seconds) < now gets its resources deleted
	 * Any course falling inbetween is ignored (we don't update courses beyond their startdate)
	 */
	function m_cook() {

	    // get all the morsle records that should be deleted
	    global $CFG, $DB;
	    $deletion_clause = " m.status NOT IN($this->disregard)
		    AND c.startdate + $this->expires < $this->curtime";
	    $sql = "SELECT m.*, c.startdate + $this->expires AS expire, c.id AS coursepresent from " . $CFG->prefix . "morsle_active m
		    LEFT JOIN " . $CFG->prefix . "course c on m.courseid = c.id
		    WHERE $deletion_clause
		    AND (m.readfolderid IS NOT NULL
		    OR m.writefolderid IS NOT NULL
		    OR m.groupname IS NOT NULL
		    OR m.password IS NOT NULL
		    OR m.siteid IS NOT NULL) "; // don't care to consider any records who've already been deleted
	    $this->deleterecs = $DB->get_records_sql($sql);
	    foreach ($this->deleterecs as $record) {
		    $this->user= $record->shortname . '@' . $this->domain;
		    $this->params = array('xoauth_requestor_id' => $this->user, 'delete' => 'true');
		    if ($status = $this->m_barf($record)) {
			    $record->status = 'DELETED';
			    $deleted = $DB->update_record(morsle_active, $record);
			    if (!$deleted) {
				    $this->log('RECORD NOT DELETED - FAILURE', $record->courseid);
			    } else {
				    $this->log('RECORD DELETED -  SUCCESS', $record->courseid);
			    }
		    }
	    }

	    // CREATE OR COMPLETE CREATION OF CURRENT COURSES
	    /* sql criteria:
	    //  course visible OR INVISIBLE
	    //	c.enrolenddate > $curtime (for creation)
	    //	one of the fields - password, readfolderid, writefolderid, groupname, siteid must be NULL (creation)
	    */
	    $creation_clause = " m.status NOT IN($this->disregard)
	    		AND c.startdate > $this->curtime - $this->expires
	       		AND (m.readfolderid IS NULL OR m.writefolderid IS NULL OR m.groupname IS NULL OR m.siteid IS NULL)";
   		$sql = 'SELECT m.* from ' . $CFG->prefix . 'morsle_active m
	    		JOIN ' . $CFG->prefix . 'course c on m.courseid = c.id
	    		WHERE ' . $creation_clause;
   		$todigest = $DB->get_records_sql($sql);
			// process each record
		foreach ($todigest as $record) {
			$this->user= $record->shortname . '@' . $this->domain;
			$this->params = array('xoauth_requestor_id' => $this->user);
			$status = $this->m_digest($record);
	    }
    }

	/*
	* Add components to google for morsle_active record
	* Checks condition of each item field in record and creates if not present
	*/
	public function m_digest($record) {
	    global $success, $CFG, $DB;
		$this->shortname = $record->shortname;
		$this->courseid = $record->courseid;
	    $groupname = $this->shortname . '-group';
	    $groupfullname = $groupname . '@' . $this->domain;
		$this->sitename = urlencode('Course Site for ' . strtoupper($this->shortname));
	    $stats = array(
			'password' => $record->password,
			'groupname' => $record->groupname,
			'readfolderid' => $record->readfolderid,
			'writefolderid' => $record->writefolderid,
			'siteid' => $record->siteid
			);
		foreach ($stats as $key=>$stat) {
			if (is_null($stat)) {
				switch ($key) {
					case 'password': // create user account
						$returnval = $this->useradd(); // either password coming back or $response->response
						break;
					case 'groupname': // add group
						$returnval = $this->groupadd($groupname);
						break;
					case 'readfolderid': // create readonly folder
						$returnval = createcollection($this->shortname . '-read', $this->user);
						break;
					case 'writefolderid': // create writeable folder
						$returnval = createcollection($this->shortname . '-write', $this->user);
						break;
					case 'siteid': // create site
						$returnval = $this->createsite();
						break;
				}
				if ($success) {
					$this->log('added ' . $key . " SUCCESS", $record->courseid, null, s($returnval));
					$record->$key = s($returnval);
					$updaterec = $DB->update_record('morsle_active', $record);
				} else {
					$this->log('added ' . $key . " FAILURE", $record->courseid, null, s($returnval));
					if ($key == 'password') {
						break;
					}
				}
			}
		}
		return $returnval;
	}


	// checks condition of each item field in record (stats) and deletes if present
	public function m_barf($record) {
		global $success, $DB;

		// we're deleting because the course has expired
	    $stats = array(
	    	'user' => $record->password,
		    'group' => $record->groupname,
		    'readonly folder' => $record->readfolderid,
		    'writeable folder' => $record->writefolderid
		    );
		$stats = array_reverse($stats, true);
	    foreach ($stats as $key=>$stat) {
			if (!is_null($stat)) {
				$status = $key;
				switch ($key) {
					// sites not deleteable through API yet
					case 'site': // create site
		//				$siteid = sitedelete($shortname, $record, $user, $this->domain);
		//				$record->siteid = null;
		//					$writeassigned = sitepermissions($shortname, $record, $groupfullname, $courseid, $this->domain);
						break;
					case 'writeable folder': // delete writeable folder
					    $base_feed = $this->docs_feed . $record->writefolderid;
				        $params = array('xoauth_requestor_id' => $this->user, 'delete' => 'true');
				        $response  = twolegged($base_feed, $params, 'DELETE');
				        if ($success) {
							$record->writefolderid = null;
				        }
						break;
					case 'readonly folder': // delete readonly folder
						$base_feed = $this->docs_feed . $record->readfolderid;
				        $params = array('xoauth_requestor_id' => $this->user, 'delete' => 'true');
						$response  = twolegged($base_feed, $params, 'DELETE');
				        if ($success) {
				           	$record->readfolderid = null;
				        }
				        break;
			        case 'group': // delete group
			        	$base_feed = $this->group_feed . $record->groupname;
					    $response = send_request('DELETE', $base_feed, $this->authstring, null, null, '2.0');
				        if ($success) {
							$record->groupname = null;
						}
						break;
					case 'user': // delete user account
					    $base_feed = $this->prov_feed . $record->shortname;
					    $response = send_request('DELETE', $base_feed, $this->authstring, null, null, '2.0');
				        if ($success) {
							$record->password = null;
						}
						break;
				}
				if ($success) {
					$updaterec = $DB->update_record('morsle_active', $record);
					$this->log($key . ' DELETED', $record->courseid, null, s($response->response));
				} else {
					$this->log($key . ' DELETE FAILED', $record->courseid, null, s($response->response));
					return false;
				}
			}
		}
		return true;
	}

	/********** PROVISIONING - ACCOUNTS - GROUPS - ****************/
	/*
	 * creates a new user on google domain for the course
	* important to note that if the account is never accessed by anything other than the API it will
	*    never initiate the first challenge typical of a new account.  This additionally allows people who've
	*    editor rights to the calendar to add and delete events
	*    NOTE: ALL OF THESE FUNCTIONS LOG FROM THEIR CALLING PROGRAM
	* TODO: uses clientlogin
	*/
	function useradd() {
		global $CFG, $success;
		require_once($CFG->dirroot.'/google/gauth.php');
		$password = genrandom_password(12);

		// form the xml for the post
		$usercreate =
			'<?xml version="1.0" encoding="UTF-8"?>
			<atom:entry xmlns:atom="http://www.w3.org/2005/Atom"
			xmlns:apps="http://schemas.google.com/apps/2006">
			<atom:category scheme="http://schemas.google.com/g/2005#kind"
			term="http://schemas.google.com/apps/2006#user"/>
			<apps:login userName="' . $this->shortname . '"
			password="' . $password . '" suspended="false"/>
			<apps:name familyName="' . $this->shortname . '" givenName="m"/>
			</atom:entry>';
		// Make the request
		$response = send_request('POST', $this->prov_feed, $this->authstring, null, $usercreate, '2.0');
		if ($success ) {
			return morsle_encode($password);
		} else {
			return $response->response;
		}
	}

	/*
	 * creates a new group on google domain for the course
	* @param $this->headers - precreated clientlogin headers for authentication
	* @param $groupname - name of group to be created
	* @param $morslerec entire morsle record for this course NOT NEEDED
	* @param $this->domain - constant representing domain name
	* TODO: uses Clientlogin
	*/
	function groupadd($groupname) {
		global $success;
		// form the xml for the post
		$groupcreate =
			'<atom:entry xmlns:atom="http://www.w3.org/2005/Atom" xmlns:apps="http://schemas.google.com/apps/2006" xmlns:gd="http://schemas.google.com/g/2005">
			<apps:property name="groupId" value="' . $groupname . '"></apps:property>
			<apps:property name="groupName" value="' . $groupname . '"></apps:property>
			<apps:property name="description" value="Morsle-created Course Group"></apps:property>
			<apps:property name="emailPermission" value="Member"></apps:property>
			</atom:entry>';

		// Make the request
		$response = send_request('POST', $this->group_feed, $this->authstring, null, $groupcreate, '2.0');
		// check if successful
		if ($success ) {
			return $groupname;
		} else {
			return $response->response;
		}
	}

	/**********  SITE FUNCTIONS ***********************/
	// TODO: should be able to combine these two functions
	/*
	 * creates base site for course name
	*/
	function createsite() {
		global $success;
		// form the xml for the post
		$sitecreate =
		'<entry xmlns="http://www.w3.org/2005/Atom" xmlns:sites="http://schemas.google.com/sites/2008">
		<title>' . $this->sitename . '</title>
		<summary>Morsle course site for collaboration</summary>
		<sites:theme>microblueprint</sites:theme>
		</entry>';
		// Make the request
		$response  = twolegged($this->site_feed, $this->params, 'POST', $sitecreate, '1.4');
		if ($success ) {
			$feed = simplexml_load_string($response->response);
			return get_href_noentry($feed, 'alternate');
		} else {
			return $response->response;
		}
	}

	/*
	 * creates a portfolio site for a student based on the HPE portfolio template
	*/
	function createportfoliosite($title) {
		global $success;

		// form the xml for the post
		$sitecreate =
		'<entry xmlns="http://www.w3.org/2005/Atom" xmlns:sites="http://schemas.google.com/sites/2008">
		<title>' . $title . '</title>
		<link rel="http://schemas.google.com/sites/2008#source" type="application/atom+xml"
		href="https://sites.google.com/feeds/site/luther.edu/puffer-s-temp-hpe2"/>
		<summary>HPE ePortfolio Site For Assessment</summary>
		</entry>';

		// Make the request
		$response  = twolegged($this->site_feed, $this->params, 'POST', $sitecreate, '1.4');
		if ($success ) {
			$feed = simplexml_load_string($response->response);
			return get_href_noentry($feed, 'alternate');
		} else {
			return $response->response;
		}
	}

	/*
	 * gets a list of portfolios sites to which a particular user (usually a teacher) has access -- not used all that much
	* mostly for troubleshooting
	*/
	function getportfoliosites() {
		global $success;
		$portfolio = array();

		// Make the request
		$response  = twolegged($this->site_feed, $this->params, 'GET', null, '1.4');
		if ($success ) {
			$feed = simplexml_load_string($response->response);
			foreach ($feed->entry as $entry) {
				if(strpos($entry->title,$this->portfoliobase) === 0) {
					$portfolio[] = trim(substr($entry->title, strlen($portfolioname),70));
				}
			}
			return $portfolio;
		} else {
			return false;
		}
	}

	/************** PERMISSION FUNCTIONS ******************/
	/*
	 * adds or deletes members and owners to a group on google domain for the course
	* this maintains "membership" and thereby deletes owners if they're no longer in the course
	* as well as plain members
	*/

	function m_maintain() {
		global $CFG, $DB;
		$this->get_aliases();

		$sql = 'SELECT m.*, c.visible as visible, c.category as category from ' . $CFG->prefix . 'morsle_active m
			JOIN ' . $CFG->prefix . 'course c on m.courseid = c.id
			WHERE m.status NOT IN(' . $this->disregard . ')
			AND c.id = 2549
			AND c.startdate + ' . $this->expires . ' > ' . $this->curtime;
		$chewon = $DB->get_records_sql($sql);
		$random = rand(0,9);
		foreach ($chewon as $record) {
			// set so only 10% of courses get done each time
//			if ($record->courseid % 10 == $random) {
				$this->shortname = $record->shortname;
				$this->courseid = $record->courseid;
				$this->user = strtolower($this->shortname . '@' . $this->domain);
				$this->urluser = urlencode($this->user);
				$this->params = array('xoauth_requestor_id' => $this->user);
				$this->groupname = $this->shortname . '-group';
				$this->groupfullname= $this->groupname . '@' . $this->domain;
				$this->visible = $record->visible;
				$this->sitename = str_replace(' ', '-',strtolower('Course Site for ' . $this->shortname));
				$this->site_acl_feed = "https://sites.google.com/feeds/acl/site/$this->domain/$this->sitename";
				$this->cal_acl_feed = "https://www.google.com/calendar/feeds/$this->urluser/acl/full/";
				$this->term = $DB->get_field('course_categories', 'name', array('id' => $record->category));

				// determine rosters for group members regardless of visibility of course
				//    $rosters = get_roster($courseid, 1);

				// determine rosters for everything else based on visibility of course, removing students if not visible
				$rosters = $this->get_roster(); // if course is invisible we don't give students permission or they could get at resources from the google side

				// maintain members and owners for group
				if (!is_null($this->groupname)) {
					$garray = array('editingteacher' => 'owner','teacher' => 'owner','student' => 'member');
					// if full resources have been switched off, this will remove all permissions but the calendar
					$allusers = $rosters;
					array_walk($allusers,'set_googlerole', $garray);
					$this->membersmaintain($allusers);
				}

				// now we need to substitute real email for alias because folders use real (groups use alias)
				foreach ($rosters as $key=>$value) {
					if (isset($this->aliases[$key])) {
						$rosters[$this->aliases[$key]] = $value;
						unset($rosters[$key]);
					}
				}

				// Calendar permissions
				if (!is_null($record->password)) {
					$garray = array('editingteacher' => 'editor','teacher' => 'editor','student' => 'read','guest' => 'read');
					$allusers = $rosters;
					array_walk($allusers,'set_googlerole', $garray);
					$calassigned = $this->set_calpermissions($allusers, $record);
				}

				// read-only folder permissions
				if (!is_null($record->readfolderid)) {
					$garray = array('editingteacher' => 'writer','teacher' => 'writer','student' => 'reader');
					// if full resources have been switched off, this will remove all permissions but the calendar
					$allusers = $rosters;
					array_walk($allusers,'set_googlerole', $garray);
					$readassigned = $this->set_folderpermissions('reader', $allusers, 'folder%3A' . $record->readfolderid);
				}

				// writeable folder permissions (everyone writes)
				if (!is_null($record->writefolderid)) {
					$garray = array('editingteacher' => 'writer','teacher' => 'writer','student' => 'writer');
					// if full resources have been switched off, this will remove all permissions but the calendar
					$allusers = $rosters;
					array_walk($allusers,'set_googlerole', $garray);
					$writeassigned = $this->set_folderpermissions('writer', $allusers, 'folder%3A' . $record->writefolderid);
				}

				// Site permissions
				if (!is_null($record->siteid)) {
					$garray = array('editingteacher' => 'owner','teacher' => 'owner','student' => 'writer');
					// if full resources have been switched off, this will remove all permissions but the calendar
					$allusers = $rosters;
					array_walk($allusers,'set_googlerole', $garray);
					$writeassigned = $this->set_sitepermissions($allusers);
				}
			}
//		}
	}

	function membersmaintain($members) {
		global $success;
		$gmembers = get_group_permissions($this->groupname, $this->domain, $this->authstring);

		// check differences
		$deleted = array_diff_assoc($gmembers, $members);
		$added = sizeof($members) > 200 ? array() : array_diff_assoc($members, $gmembers);
		// delete first due to aliases
		foreach($deleted as $member=>$permission) {
			$this->base_feed = $this->group_feed . $this->groupname . '/' . $permission . '/' . $member;
			$response = send_request('DELETE', $this->base_feed, $this->authstring, null, null, '2.0');
			if ($success) {
				$this->log("$member delete SUCCESS", $this->courseid, null, $permission . ':' . s($response->response));
			} else {
				$this->log("$member delete FAILED", $this->courseid, null, $permission . ':' . s($response->response));
			}
		}

		// then add
		foreach($added as $member=>$permission) {
			// if owner, make a member first
			if ($permission === 'owner') {
				$permissiondata = group_acl_post($member);
				$this->base_feed = $this->group_feed . $this->groupname . '/member';
				$response = send_request('POST', $this->base_feed, $this->authstring, null, $permissiondata, '2.0');
				if ($success) {
					$this->log("$member add SUCCESS", $this->courseid, null, 'Member:' . s($response->response));
				} else {
					$this->log("$member ADD FAILED ", $this->courseid, null, 'Member:' . s($response->response));
				}
			}

			$permissiondata = group_acl_post($member);
			$this->base_feed = $this->group_feed . $this->groupname . '/' . $permission;
			$response = send_request('POST', $this->base_feed, $this->authstring, null, $permissiondata, '2.0');
			if ($success) {
				$this->log("$member add SUCCESS", $this->courseid, null, $permission . ':' . s($response->response));
			} else {
				$this->log("$member ADD FAILED ", $this->courseid, null, $permission . ':' . s($response->response));
			}
		}
	}

	function set_folderpermissions($readwrite, $members, $folderid) {
		// what does google say we have for permissions on this folder
		if ($gmembers = get_docspermissions($folderid,$this->user)) {
			unset($gmembers[$this->user]);
			//        unset($gmembers[$groupfullname]);
			//        $groupadd = true;
			$this->base_feed = $this->id_feed . $folderid . '/acl';
			$batchpermpost = '<?xml version="1.0" encoding="UTF-8"?>
					<feed xmlns="http://www.w3.org/2005/Atom"
					xmlns:gAcl="http://schemas.google.com/acl/2007"
					xmlns:batch="http://schemas.google.com/gdata/batch">
					<category scheme="http://schemas.google.com/g/2005#kind"
					term="http://schemas.google.com/acl/2007#accessRule"/>';
			$foldername = $this->shortname . '-' . $readwrite;

			$deleted = array_diff_assoc($gmembers, $members);
			$added =  array_diff_assoc($members, $gmembers);

			// add new members first in case we need an owner
			foreach($added as $member=>$permission) {
				$batchpermpost .= batchdocpermpost($member, $permission, 'add', $this->base_feed);
			}

			if (count($deleted) + count($added) == 0) {
			    return true;
			}

			// delete
			foreach($deleted as $member=>$permission) {
				$batchpermpost .= batchdocpermpost($member, $permission, 'delete', $this->base_feed);
			}

			$batchpermpost .= '</feed>';

			// reset base_feed
			$this->base_feed = $this->docs_feed . $folderid . '/acl';
			$this->params['send-notification-emails'] = 'false';
			$response  = twolegged($this->base_feed . '/batch', $this->params, 'POST', $batchpermpost, null, null, null, 'batch');
			$feed = simplexml_load_string($response->response);
			foreach ($feed->entry as $entry) {
				$member = preg_replace("/https(.*?)user%3A/",'',$entry->id);
				if ($entry->title == 'Error') {
					$this->log("ACTION FAILED FOR $member", $this->courseid, null, 'Folder:' . "error: $entry->content for $foldername");
				} else {
					$this->log("Action succeeded for $member", $this->courseid, null, 'Folder' . "answer: $entry->title for $foldername");
				}
			}
			unset($this->params['send-notification-emails']);

			// link the folder to the user's DRIVE folder
			foreach ($added as $member=>$permission) {
				if ($permission == 'writer') {
					echo $this->docs_feed . $folderid . $member;
//					$this->link_to_drive($member, $this->term, $this->docs_feed, $folderid);
					add_file_tocollection($this->docs_feed, 'folder%3Aroot', $folderid, $member);
				}
			}

			return true;
		}
		return false;
	}

	/*
	 * write out batch of acls for documents or folders
	* DON'T USE THIS ANYMORE
	*/
	function batch_doc_acls($permissions) {
		$limit = 50;
		if (sizeof($permissions) > $limit) {
			$chunkperms = array_chunk($permissions, $limit, true);
			for ($i = 0; $i < sizeof($chunkrows); $i++) {
				batch_doc_acls($resourceid, $chunkperms);
			}
		}
		$batchpermpost = '<?xml version="1.0" encoding="UTF-8"?>
		<feed xmlns="http://www.w3.org/2005/Atom"
		xmlns:gAcl="http://schemas.google.com/acl/2007"
		xmlns:batch="http://schemas.google.com/gdata/batch">
		<category scheme="http://schemas.google.com/g/2005#kind"
		term="http://schemas.google.com/acl/2007#accessRule"/>';
		foreach ($permission as $email => $value) {
			$batchpermpost .= batchdocpermpost($email, $value->role, $value->action, $this->base_feed);
		}
		$batchpermpost .= '</feed>';
		$response  = twolegged($this->base_feed . '/batch', $this->params, 'POST', $batchpermpost, null, null, null, 'batch');
	}

	function link_to_drive($owner, $title, $base_feed, $docid) {
		if (!$collectionid = get_collection($title, $owner)) {
			$collectionid = 'folder%3A' . createcollection($title, $owner);
		}

		add_file_tocollection($base_feed, $collectionid, $docid, $owner);
	}

	function set_sitepermissions($members) {
		$this->base_feed = $this->site_acl_feed;

		// what does google say we currently have for permissions?
		if ($gmembers = $this->get_sitepermissions()) {

			// don't process the group acl or the course owner acl
			unset($gmembers[$this->user]);
			unset($gmembers[$this->group]);

			$deleted = array_diff_assoc($gmembers, $members);
			$added =  array_diff_assoc($members, $gmembers);

			// delete first because we may be dealing with aliases
			foreach($deleted as $member=>$permission) {
				$delete_base_feed = $this->base_feed . '/user%3A' . $member;
				$response = twolegged($delete_base_feed, $this->params, 'DELETE', null, '1.4');
				if ($response->info['http_code'] == 200) {
					$this->log("$member Deleted $this->sitename", $this->courseid, null, $this->sitename . ':');
				} else {
					$this->log("DELETE FAILED $member $this->sitename", $this->courseid, null, $this->sitename . ':' . s($response->response));
				}
			}
			foreach($added as $member=>$permission) {
				$siteacldata = acl_post($member, $permission, 'user');
				$response = twolegged($this->base_feed, $this->params, 'POST', $siteacldata, '1.4');
				if ($response->info['http_code'] == 201) {
					$this->log("$member added $this->sitename", $this->courseid, null, $this->sitename . ':');
				} else {
					$this->log("ADD FAILED $member $this->sitename", $this->courseid, null, $this->sitename . ':' . s($response->response));
				}
			}
			return true;
		}
		return false;
	}

	function get_sitepermissions() {
		$role = array();
		$response = twolegged($this->base_feed, $this->params, 'GET',null,'1.4');
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


	function set_calpermissions($members, $record) {
		global $success;
		$this->base_feed = $this->cal_acl_feed;
		// calendar currently needs clientauth because redirects always go to a clientauth site
		$service = 'cl';
		$password = morsle_decode($record->password);
		$username = $this->user;
//		$calauth = $this->cal_auth($username, $password, $service);
		$calauth = clientauth(null, null, $service);
		$this->calauthstring = "Authorization: GoogleLogin auth=" . $calauth;
		$gmembers = $this->get_calendarpermissions();

//		if ($gmembers = $this->get_calendarpermissions()) { // can't use this because by default, at the beginning there are no acls

			// don't process the domain acl (you don't get a course owner acl)
			unset($gmembers[$this->domain]);
			unset($gmembers[$username]);

			$deleted = array_diff_assoc($gmembers, $members);
			$added =  array_diff_assoc($members, $gmembers);

			// delete first because we may be dealing with aliases
			foreach($deleted as $member=>$permission) {
				$urluser = str_replace('@', '%40', $member);
				$delete_base_feed = $this->base_feed . "user%3A$urluser";
				$response = send_request('DELETE', $delete_base_feed, $this->calauthstring, null, null, '2', null, null);
				if ($success) {
					$this->log("Deleted $member calendar", $this->courseid, null, $this->user . ':');
				} else {
					$this->log("DELETE FAILED $member calendar", $this->courseid, null,  $this->user . ':' . s($response->response));
				}
			}
			// add new permissions
			foreach($added as $member=>$permission) {
				$calacldata = cal_acl_post($member, $permission);
				$response = send_request('POST', $this->base_feed, $this->calauthstring, null, $calacldata, '2', null, null);
				if ($success) {
					$this->log("Added $member calendar", $this->courseid, null,  $this->user . ':');
				} else {
					$this->log("ADD FAILED $member calendar", $this->courseid, null,  $this->user . ':' . s($response->response));
				}
			}
			return true;
//		}
	}

	function cal_auth($username, $password, $service) {
		$clientlogin_url = "https://www.google.com/accounts/ClientLogin";
		$clientlogin_post = array(
				"accountType" => "HOSTED_OR_GOOGLE",
				"Email" => $username,
				"Passwd" => $password,
				"service" => $service
		);

		if (isset($_SESSION['SESSION']->$username->g_authtimeout)
				&& isset($_SESSION['SESSION']->$username->service)
				&& $_SESSION['SESSION']->$username->service == $service
				&& time() < $_SESSION['SESSION']->$username->g_authtimeout
				&& !is_null($_SESSION['SESSION']->$username->g_auth)) {
			$auth = $_SESSION['SESSION']->$username->g_auth;
			return $auth;
		}

		// Initialize the curl object
		$curl = curl_init($clientlogin_url);

		// Set some options (some for SHTTP)
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $clientlogin_post);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		// Execute
		$response = curl_exec($curl);
		$info = curl_getinfo($curl);
		if ($info['http_code'] <> 200) {
			return false;
		} else {
			// Get the Auth string and save it
			preg_match("/Auth=([a-z0-9_\-]+)/i", $response, $matches);
			$auth = $matches[1];
			curl_close($curl);
			$_SESSION['SESSION']->$username->g_auth = $auth;
			$_SESSION['SESSION']->$username->service = $service;
			$_SESSION['SESSION']->$username->g_authtimeout = time() + 86400;
			return $auth;
		}
	}

	// calendar currently needs clientauth because redirects always go to a clientauth site
	function get_calendarpermissions() {
		$role = array();
//		$owner = str_replace('@', '%40', $this->user);
		$response = send_request('GET', $this->base_feed, $this->calauthstring, null, null, 2);
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


	/**************** ROSTER FUNCTIONS ***************/
	/*
	 * gets all participants in a moodle course with optional role filtering
	* returns array of user->email keys and role values
	*/
	function get_roster($onlyrole=null) {
		$coursecontext = get_context_instance(CONTEXT_COURSE, $this->courseid);
		$allroles = get_roles_used_in_context($coursecontext);
		arsort($allroles);
		if (!$this->visible) {
			foreach ($allroles as $key=>$role) {
				if ($role->shortname <> 'editingteacher') {
					unset($allroles[$key]);
				}
			}
		}
		$roles = array_keys($allroles);

		// can't used canned function as its likely to return a student role when the user has both a student and a teacher role
		// so this bit will allow the lower roleid (higher value role) to overwrite the lower one
		foreach ($roles as $role) {
			if (($temp = get_role_users($role,$coursecontext)) !== false) {
				$course->users = isset($course->users) ? array_merge($course->users,$temp) : $temp;
			}
		}
		$members = array();
		foreach ($course->users as $cuser) {
			if ($onlyrole === null || $onlyrole == $allroles[$cuser->roleid]->shortname) {
				$members[strtolower($cuser->email)] = $allroles[$cuser->roleid]->shortname;
			}
		}
		return $members;
	}
	/*
	 * Get aliases so we can avoid constantly adding and deleting them in the activity
	*/
	function get_aliases() {
		global $CFG, $success;
		require_once($CFG->dirroot.'/google/gauth.php');
		$this->aliases = array();
		// Make the request
		$response = send_request('GET', $this->alias_feed, $this->authstring, null, null, '2.0');
		$this->aliases = array_merge($this->aliases,$this->process_aliases($response->response));
		while (($newalias_feed = get_href_noentry(simplexml_load_string($response->response), 'next')) !== false) {
			$response = send_request('GET', $newalias_feed, $this->authstring, null, null, '2.0');
			$this->aliases = array_merge($this->aliases,$this->process_aliases($response->response));
		}
	}

	function process_aliases($response) {
		$a_namepattern = "#apps:property name='alias[^/.]+\.?[^/.]*\.[^/.]*#";
		$u_namepattern = "#apps:property name='user[^/.]+\.?[^/.]*\.[^/.]*#";
		//		$a_namepattern = "#apps:property name='alias[^/.]+\.[^/.]+#";
		//		$u_namepattern = "#apps:property name='user[^/.]+\.[^/.]+#";
		preg_match_all($a_namepattern, $response, $a_names);
		preg_match_all($u_namepattern, $response, $u_names);
		for ($i=0;$i<sizeof($a_names[0]);$i++) {
			$split = explode("'",$a_names[0][$i]);
			$a_names[0][$i] = $split[3];
			$split = explode("'",$u_names[0][$i]);
			$u_names[0][$i] = $split[3];
		}
		return array_combine($a_names[0], $u_names[0]);
	}

	/************ CALENDAR FUNCTIONS ******************/
	/*
	 * Author: Bob Puffer
	 * Process calendar events from mdl_event to mdl_morsle_event
	 * and, in turn, from mdl_morsle_event to Google calendar
	 */
	function m_calendar() {
		global $CFG, $DB, $success;
//		require_once('../../../config.php');
		require_once($CFG->dirroot.'/google/lib.php');
		require_once($CFG->dirroot.'/google/gauth.php');
		//$chewon = $DB->get_records('morsle_active');
		$service = 'cl';
		// TODO: this is unlikely to fly and needs to be adjusted for two-legged

		//TODO: where in this are morsle_event records deleted that belong to a course that has expired?
		// here's the code, when does it get turned on?
		// first delete all morsle_event records past expiration
		// don't worry about deleting the corresponding Google records as they will leave when the calendar gets deleted
		// (which has the same expiration time)
		//$select = "timestart + GREATEST(900,timeduration) + $this->expires < $this->curtime";
		//$success = delete_records_select('morsle_event',$select);



		// get all the records from morsle_events that need to be deleted from Google
		// morsle_active record not in disregard list
		// and
		// no longer in event
		// or event visible
		$eventsql = 'SELECT me.*, ma.shortname as shortname, ma.password as password FROM ' . $CFG->prefix . 'morsle_event me
			JOIN ' . $CFG->prefix . 'course c on me.courseid = c.id
			JOIN ' . $CFG->prefix . 'morsle_active ma on ma.courseid = c.id
			LEFT JOIN ' . $CFG->prefix . 'event e on me.eventid = e.id
			WHERE ma.status NOT IN(' . $this->disregard . ')
			AND ( ISNULL(e.id)
			OR e.visible = 0
			OR e.timemodified <> me.timemodified)
			ORDER BY me.courseid ASC';
		$deleted = $DB->get_records_sql($eventsql);

		// get unique list of courses involved in this action (delete)
		$coursesql = 'SELECT DISTINCT me.courseid as courseid, ma.shortname as shortname, ma.password as password FROM ' . $CFG->prefix . 'morsle_event me
			JOIN ' . $CFG->prefix . 'course c on me.courseid = c.id
			JOIN ' . $CFG->prefix . 'morsle_active ma on ma.courseid = c.id
			LEFT JOIN ' . $CFG->prefix . 'event e on me.eventid = e.id
			WHERE ma.status NOT IN(' . $this->disregard . ')
			AND ( ISNULL(e.id)
			OR e.visible = 0
			OR e.timemodified <> me.timemodified
			OR e.timestart + GREATEST(900,e.timeduration) + ' . $this->expires . ' < ' . $this->curtime
			. ') ORDER BY me.courseid ASC';
		$courseids = $DB->get_records_sql($coursesql);

		// cycle through each course that has records to be deleted
		foreach($courseids as $coursekey=>$courseid) {
			$owner = $courseid->shortname . '@' . $this->domain;
			$calowner = str_replace('@','%40',$owner);
			$password = morsle_decode($courseid->password);
			$this->authstring = "Authorization: GoogleLogin auth=" . clientauth($owner, $password, $service);
			foreach ($deleted as $event) {
				if ($event->courseid == $coursekey) {
					// TODO: this needs to be the edit link for the event
					$base_feed = "https://www.google.com/calendar/feeds/$calowner/private/full/$event->googleid";
					$response = send_request('DELETE', $base_feed, $this->authstring, null, null, '2.0');
					// only deleted from morsle_event if successfully deleted from google
					if ($success) {
						$feed = simplexml_load_string($response->response);
						$success = $DB->delete_records('morsle_event',array('eventid' => $event->eventid));
						$this->log("$event->name deleted", $coursekey, null, s($response->response));
					} else {
						$this->log("$event->name NOT DELETED", $coursekey, null, s($response->response));
					}
				}
			}
		}

		// this query should get all records in event that are
		// not yet in morsle_event
		// course visible or invisible (only instructors see calendars of invisible courses)
		// course startdate plus expiration time is greater than current time
		// event visible
		// event is in the future
		// morsle_active record not in disregard list
		// get all the records from events that need to be added to morsle_events
		$sql = 'SELECT e.*, ma.shortname as shortname, ma.password as password FROM ' . $CFG->prefix . 'event e
			JOIN ' . $CFG->prefix . 'course c on e.courseid = c.id
			JOIN ' . $CFG->prefix . 'morsle_active ma on ma.courseid = c.id
			LEFT JOIN ' . $CFG->prefix . 'morsle_event me on me.eventid = e.id
			WHERE ISNULL(me.id)
			AND c.startdate + ' . $this->expires . ' > ' . $this->curtime
			. ' AND e.visible = 1
			AND e.timestart + GREATEST(900,e.timeduration) > ' . $this->curtime
			. ' AND ma.status NOT IN(' . $this->disregard . ')
			ORDER BY e.courseid ASC';
		$added = $DB->get_records_sql($sql);
		$sql = 'SELECT ma.courseid as courseid, ma.shortname as shortname, ma.password as password FROM ' . $CFG->prefix . 'event e
			JOIN ' . $CFG->prefix . 'course c on e.courseid = c.id
			JOIN ' . $CFG->prefix . 'morsle_active ma on ma.courseid = c.id
			LEFT JOIN ' . $CFG->prefix . 'morsle_event me on me.eventid = e.id
			WHERE ISNULL(me.id)
			AND c.startdate + ' . $this->expires . ' > ' . $this->curtime
			. ' AND e.visible = 1
			AND e.timestart + GREATEST(900,e.timeduration) > ' . $this->curtime
			. ' AND ma.status NOT IN(' . $this->disregard . ')
			ORDER BY e.courseid ASC';
		$courseids = $DB->get_records_sql($sql);

		// cycle through each course that has records to be added
		foreach($courseids as $coursekey=>$courseid) {
			$owner = $courseid->shortname . '@' . $this->domain;
			$calowner = str_replace('@','%40',$owner);
			$password = morsle_decode($courseid->password); // TODO: why do we need this, were not using it?
			$this->authstring = "Authorization: GoogleLogin auth=" . clientauth($owner, $password, $service);

			// single processing
			foreach ($added as $key=>$event) {
				if ($event->courseid == $coursekey) {
					$event->name = str_replace('&','and',$event->name);
					$event->description = str_replace('&','and',strip_tags($event->description));
					$event->eventid = $key;
					$caleventdata = get_cal_event_post($event);
					$base_feed = $this->cal_feed;
					$response = send_request('POST', $base_feed, $this->authstring, null, $caleventdata, '2');
					if ($success) {
						$feed = simplexml_load_string($response->response);
						unset($event->id);
						$event->description = addslashes($event->description);
						$event->name = addslashes($event->name);
						$event->eventid = $key;
						$event->googleid = substr($feed->id,strpos($feed->id,'events/') + 7,50);
						$eventtime = date(DATE_ATOM,$event->timestart);
						$success = $DB->insert_record('morsle_event',$event);
						if ($success) {
							$this->log('added ' . $key . " SUCCESS", $event->courseid, null, s($response->response));
						} else {
							$this->log('added ' . $key . " FAILURE", $event->courseid, null, s($response->response));
						}
					}
					unset($added[$key]);
				}
			}



		/* batch processing
			// build the post
			$caleventdata = "<feed xmlns='http://www.w3.org/2005/Atom'
		      xmlns:app='http://www.w3.org/2007/app'
		      xmlns:batch='http://schemas.google.com/gdata/batch'
		      xmlns:gCal='http://schemas.google.com/gCal/2005'
		      xmlns:gd='http://schemas.google.com/g/2005'>
			  <category scheme='http://schemas.google.com/g/2005#kind' term='http://schemas.google.com/g/2005#event' />";
			foreach ($added as $key=>$event) {
				if ($event->courseid == $coursekey) {
					$event->description = strip_tags($event->description);
					$event->eventid = $key;
					$caleventdata .= batch_get_cal_event_post($event);
				}
			}
			$caleventdata .= '</feed>';
			$temp = split("<",$caleventdata);
			print_r($temp);

			// add to Google
		//	$base_feed = get_cal_base_feed("https://www.google.com/calendar/feeds/$owner/private/full/batch", $headers);
			$base_feed = "https://www.google.com/calendar/feeds/$calowner/private/full/batch";
			$response = send_request('POST', $base_feed, $authstring, null, $caleventdata, '2.0');
			$feed = simplexml_load_string($response->response);

			// add to morsle_event
			if ($response->info['http_code'] == 200) {
				$grecnum = 0;
				foreach ($added as $key=>$event) {
					if ($event->courseid == $coursekey) {
						unset($event->id);
						$event->description = addslashes($event->description);
						$event->eventid = $key;
						$event->googleid = substr($feed->entry[$grecnum]->id,strpos($feed->entry[$grecnum]->id,'events/') + 7,50);
						$eventtime = date(DATE_ATOM,$event->timestart);
						$grecnum++;
						$success = $DB->insert_record('morsle_event',$event);
						if ($success) {
							add_to_log($courseid, 'Morsle', "Added", null, "$event->name added to calendar eventtime = $eventtime");
						} else {
							add_to_log($courseid, 'Morsle', "NOT ADDED", null, "$event->name NOT ADDED to calendar eventtime = $eventtime");
						}
						unset($added[$key]);
					}
				}
			}
		*/
		}


		// need these so we can delete any that no longer exist in event
		// do this first before we add a bunch more morsle_event records
		/*
		$morsle_events = $DB->get_records_select('morsle_event',"timestart > $curtime",$courseid,'eventid,shortname');
		foreach ($morsle_events as $key=>$event) {
			// delete any morsle_event that no longer exists in event
			// we assume a morsle_active record exists for this course
			if (!array_key_exists($key,$eventrecs)) {
				$owner = $event->shortname . '@' . $this->domain;
				// TODO: this is unlikely to fly and needs to be adjusted for two-legged
				$base_feed = get_cal_base_feed("https://www.google.com/calendar/feeds/$owner/private/full", $headers);
				// delete from Google
				$response = send_request('DELETE', $base_feed, $authstring, null, null, 2);
				// only deleted from morsle_event if successfully deleted from google
				if ($response == 201) {
					$success = delete_records('morsle_event','eventid', $key);
					add_to_log($courseid, 'Morsle', "Deleted", null, "event $key deleted from $event->shortname calendar");
				}
			}
		}
		foreach ($eventrecs as $key=>$event) {
			// TODO: compare timemodified and update if changed
			// morsle_event could just store eventid, courseid, shortname, and timemodified
			// remove all invisible events that don't exist in morsle_event
			// for each event by course -- if not exist, add, if exist in morsle_event, update
			// if morsle_event records with timestart + max(timeduration, 15 minutes) > now() not in event then delete from Google
			if (null) {
			} elseif ($event->visible = 0) { // delete from morsle_event

			} else { // update morsle_event
				$success = $DB->update_record('morsle_event',$eventrecs[$event]);
			}
				if (array_key_exists($key,$morsle_events)) {

			} else { // set up for adding event records to morsle_event
			}
		}
		// set up any morsle events that are no longer in event for deletion
		// still need to do this
		// any eventrecs leftover should be added
		foreach( $eventrecs as $event) {
			//        $params = array('xoauth_requestor_id' => $user);
		//        $caleventsucess = twolegged($base_feed, $params, 'POST', $caleventdata,'Version 2');
		}



		foreach ($chewon as $record) {
		//    if ($record->status == 'Google write folder permissions created' && $record->courseid == 11240) {
			if ($record->courseid == 10897) {
				$owner = $record->shortname . '@luther.edu';
				$user = 'bobpuffer@gmail.com';
				$group = $record->shortname . '-group' . '@luther.edu';
				$password = rc4decrypt($record->password);
			    $auth = clientauth($owner,$password,$service);
		//		$auth = '';
			    $role = 'editor';
		//		$response = morsle_update_eater($owner,$user, $auth, $role);
		//	    $response = get_acl_feed($owner, $auth);
		//		$response = morsle_delete_eater($owner,$user, $auth);
		//		$response = morsle_add_eater($owner,$user, $auth, $role);
		//		$response = get_event_feed($owner, $auth);
				$response = morsle_upchuck_events($owner, $record->courseid, $auth);
		//	    $response = morsle_cal_retrieve($auth);
		    }
		}
		*/
	}

	function calmassdelete() {
		global $CFG, $DB, $success;
		//		require_once('../../../config.php');
		require_once($CFG->dirroot.'/google/lib.php');
		require_once($CFG->dirroot.'/google/gauth.php');
		//$chewon = $DB->get_records('morsle_active');

		// get course record from which events are to be deleted
		$coursesql = 'SELECT ma.* FROM mdl_morsle_active ma
						JOIN mdl_course c on c.id = ma.courseid
						WHERE c.id = 692';
		$courseid = $DB->get_record_sql($coursesql);

		// authenticate
		$service = 'cl';
		$owner = $courseid->shortname . '@' . $this->domain;
		$calowner = str_replace('@','%40',$owner);
		$password = morsle_decode($courseid->password);
		$this->authstring = "Authorization: GoogleLogin auth=" . clientauth($owner, $password, $service);
//		$password = rc4decrypt($courseid->password);

		// set up get of feed
		$base_feed = $this->cal_feed;
		$counter = 0;
		while ($counter < 100) {
			$response = send_request('GET', $base_feed, $this->authstring, null, null, '2.0');
			if ($success) {
				$feed = simplexml_load_string($response->response);
				if (!isset($feed->entry)) {
					$counter = 101;
				} else {
					$counter++;
					foreach ($feed->entry as $entry) {
						$event->googleid = substr($entry->id,strpos($entry->id,'events/') + 7,50);
						$delete_feed = "https://www.google.com/calendar/feeds/default/private/full/$event->googleid";
						$response = send_request('DELETE', $delete_feed, $this->authstring, null, null, '2.0');
						if ($success) {
							echo $entry->title . ' DELETED <br />';
						}
					}
				}
			}
		}
		// TODO: this is unlikely to fly and needs to be adjusted for two-legged

		//TODO: where in this are morsle_event records deleted that belong to a course that has expired?
		// here's the code, when does it get turned on?
		// first delete all morsle_event records past expiration
		// don't worry about deleting the corresponding Google records as they will leave when the calendar gets deleted
		// (which has the same expiration time)
		//$select = "timestart + GREATEST(900,timeduration) + $this->expires < $this->curtime";
		//$success = delete_records_select('morsle_event',$select);



		// get all the records from morsle_events that need to be deleted from Google
		// morsle_active record not in disregard list
		// and
		// no longer in event
		// or event visible
/*
		$eventsql = 'SELECT me.*, ma.shortname as shortname, ma.password as password FROM ' . $CFG->prefix . 'morsle_event me
			JOIN ' . $CFG->prefix . 'course c on me.courseid = c.id
			JOIN ' . $CFG->prefix . 'morsle_active ma on ma.courseid = c.id
			LEFT JOIN ' . $CFG->prefix . 'event e on me.eventid = e.id
			WHERE ma.status NOT IN(' . $this->disregard . ')
			AND ( ISNULL(e.id)
			OR e.visible = 0
			OR e.timemodified <> me.timemodified)
			ORDER BY me.courseid ASC';
		$deleted = $DB->get_records_sql($eventsql);

		// get unique list of courses involved in this action (delete)
		$coursesql = 'SELECT DISTINCT me.courseid as courseid, ma.shortname as shortname, ma.password as password FROM ' . $CFG->prefix . 'morsle_event me
			JOIN ' . $CFG->prefix . 'course c on me.courseid = c.id
			JOIN ' . $CFG->prefix . 'morsle_active ma on ma.courseid = c.id
			LEFT JOIN ' . $CFG->prefix . 'event e on me.eventid = e.id
			WHERE ma.status NOT IN(' . $this->disregard . ')
			AND ( ISNULL(e.id)
			OR e.visible = 0
			OR e.timemodified <> me.timemodified
			OR e.timestart + GREATEST(900,e.timeduration) + ' . $this->expires . ' < ' . $this->curtime
			. ') ORDER BY me.courseid ASC';
		$courseids = $DB->get_records_sql($coursesql);

		// cycle through each course that has records to be deleted
		foreach($courseids as $coursekey=>$courseid) {
			$owner = $courseid->shortname . '@' . $this->domain;
			$calowner = str_replace('@','%40',$owner);
			$password = morsle_decode($courseid->password);
			foreach ($deleted as $event) {
				if ($event->courseid == $coursekey) {
					// TODO: this needs to be the edit link for the event
					$base_feed = "https://www.google.com/calendar/feeds/$calowner/private/full/$event->googleid";
					$response = send_request('DELETE', $base_feed, $this->authstring, null, null, '2.0');
					// only deleted from morsle_event if successfully deleted from google
					if ($success) {
						$feed = simplexml_load_string($response->response);
						$success = $DB->delete_records('morsle_event',array('eventid' => $event->eventid));
						$this->log("$event->name deleted", $coursekey, null, s($response->response));
					} else {
						$this->log("$event->name NOT DELETED", $coursekey, null, s($response->response));
					}
				}
			}
		}

		// this query should get all records in event that are
		// not yet in morsle_event
		// course visible or invisible (only instructors see calendars of invisible courses)
		// course startdate plus expiration time is greater than current time
		// event visible
		// event is in the future
		// morsle_active record not in disregard list
		// get all the records from events that need to be added to morsle_events
		$sql = 'SELECT e.*, ma.shortname as shortname, ma.password as password FROM ' . $CFG->prefix . 'event e
		JOIN ' . $CFG->prefix . 'course c on e.courseid = c.id
		JOIN ' . $CFG->prefix . 'morsle_active ma on ma.courseid = c.id
		LEFT JOIN ' . $CFG->prefix . 'morsle_event me on me.eventid = e.id
		WHERE ISNULL(me.id)
		AND c.startdate + ' . $this->expires . ' > ' . $this->curtime
		. ' AND e.visible = 1
		AND e.timestart + GREATEST(900,e.timeduration) > ' . $this->curtime
		. ' AND ma.status NOT IN(' . $this->disregard . ')
		ORDER BY e.courseid ASC';
		$added = $DB->get_records_sql($sql);
		$sql = 'SELECT ma.courseid as courseid, ma.shortname as shortname, ma.password as password FROM ' . $CFG->prefix . 'event e
		JOIN ' . $CFG->prefix . 'course c on e.courseid = c.id
		JOIN ' . $CFG->prefix . 'morsle_active ma on ma.courseid = c.id
		LEFT JOIN ' . $CFG->prefix . 'morsle_event me on me.eventid = e.id
		WHERE ISNULL(me.id)
		AND c.startdate + ' . $this->expires . ' > ' . $this->curtime
		. ' AND e.visible = 1
		AND e.timestart + GREATEST(900,e.timeduration) > ' . $this->curtime
		. ' AND ma.status NOT IN(' . $this->disregard . ')
		ORDER BY e.courseid ASC';
		$courseids = $DB->get_records_sql($sql);

		// cycle through each course that has records to be added
		foreach($courseids as $coursekey=>$courseid) {
			$owner = $courseid->shortname . '@' . $this->domain;
			$calowner = str_replace('@','%40',$owner);
			$password = morsle_decode($courseid->password); // TODO: why do we need this, were not using it?
			$this->authstring = "Authorization: GoogleLogin auth=" . clientauth($owner, $password, $service);

			// single processing
			foreach ($added as $key=>$event) {
				if ($event->courseid == $coursekey) {
					$event->name = str_replace('&','and',$event->name);
					$event->description = str_replace('&','and',strip_tags($event->description));
					$event->eventid = $key;
					$caleventdata = get_cal_event_post($event);
					$base_feed = $this->cal_feed;
					$response = send_request('POST', $base_feed, $this->authstring, null, $caleventdata, '2');
					if ($success) {
						$feed = simplexml_load_string($response->response);
						unset($event->id);
						$event->description = addslashes($event->description);
						$event->name = addslashes($event->name);
						$event->eventid = $key;
						$event->googleid = substr($feed->id,strpos($feed->id,'events/') + 7,50);
						$eventtime = date(DATE_ATOM,$event->timestart);
						$success = $DB->insert_record('morsle_event',$event);
						if ($success) {
							$this->log('added ' . $key . " SUCCESS", $event->courseid, null, s($response->response));
						} else {
							$this->log('added ' . $key . " FAILURE", $event->courseid, null, s($response->response));
						}
					}
					unset($added[$key]);
				}
			}
		}
	*/
	}


	function log($message, $course, $url=null, $info=null) {
		$success = add_to_log($course, 'morsle', $message, $url, $info);
	}
}
/*
 * generates a twelve character password for new (course) accounts only using characters acceptable to google
* @param $length optional length, defaults to 12 characters long
*/
function genrandom_password($length=12) {
	$str='abcdefghijkmnopqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ-_$^*#:(){}[]23456789';
	$max=strlen($str);
	$length=@round($length);
	if(empty($length)) {
		$length=rand(8,12);
	}
	$password='';
	for($i=0; $i<$length; $i++){
		$password.=$str{rand(0,$max-1)};
	}
	return $password;
}

/*
 * callback function for array_walk
* @param $var passed by reference, role to be set
* @param $key TODO: why do we need this?
* @param $garray - contains google roles that will be returned based on value of $var
*/
function set_googlerole(&$var,$key, $garray) {
	$var = array_key_exists($var, $garray) ? $garray[$var] : $garray['student'];

}

/**
 * Morsle plugin cron task
 */
function repository_morsle_cron() {
    $instances = repository::get_instances(array('type'=>'morsle'));
    foreach ($instances as $instance) {
        $instance->cron();
    }
}


//Icon from: http://www.iconspedia.com/icon/google-2706.html