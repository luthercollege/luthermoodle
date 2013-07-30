<?php

/*
* gets the client auth for use in administering google apps
*
*/
function clientauth($username=null, $password=null, $service=null, $savetocookie=true) {
//    global $CFG;
//	require_once("$CFG->dirroot/google/oauth.php");
	require_once('oauth.php');
    if ( !$CONSUMER_KEY = get_config('morsle','consumer_key')) {
        exit;
    }
    if(is_null($username)) {
	    if ( !$username = get_config('morsle','admin_account')) {
	        exit;
	    } else {
	    	$username = $username . '@' . $CONSUMER_KEY;
	    }
	    if ( !$password = get_config('morsle','admin_password')) {
	        exit;
	    }
    	$password = morsle_decode($password);
    }
    if(is_null($service)) {
        $service = 'apps';
    }
    if (isset($_SESSION['SESSION']->$username->g_authtimeout)
    		&& isset($_SESSION['SESSION']->$username->service)
    		&& $_SESSION['SESSION']->$username->service == $service
    		&& time() < $_SESSION['SESSION']->$username->g_authtimeout
    		&& !is_null($_SESSION['SESSION']->$username->g_auth)) {
	    $auth = $_SESSION['SESSION']->$username->g_auth;
		return $auth;
	}

    $clientlogin_url = "https://www.google.com/accounts/ClientLogin";
    $clientlogin_post = array(
    "accountType" => "HOSTED_OR_GOOGLE",
    "Email" => $username,
    "Passwd" => $password,
    "service" => $service
    );
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
	    if ($savetocookie) {
	    	$_SESSION['SESSION']->$username = new stdClass();
			$_SESSION['SESSION']->$username->g_auth = $auth;
			$_SESSION['SESSION']->$username->service = $service;
			$_SESSION['SESSION']->$username->g_authtimeout = time() + 86400;
	    }
	    return $auth;
    }
}

   /**
   * Uses two-legged OAuth to respond to a Google documents list API request
   * @param string $base_feed Full URL of the resource to access
   * @param array $params (optional) parameters to be added to url line
   * @param string $type The HTTP method (GET, POST, PUT, DELETE)
   * @param string $postData (optional) POST/PUT request body
   * @param string $version (optional) if not sent will be set to 3.0
   * @param string $content_type (optional) what kind of content is being sent
   * @param string $slug (optional) used in determining the revision of a document
   * @param boolean $batch is this a batch transmission?
   * @return string $response body from the server
   */
	function twolegged($base_feed, $params, $type, $postdata = null, $version = null, $content_type = null, $slug=null, $batch=null) {
	    global $CFG;
	    require_once($CFG->dirroot . '/repository/morsle/lib.php'); // for morsle_decode
	    require_once($CFG->dirroot . '/google/oauth.php');
	    // Establish an OAuth consumer based on our admin 'credentials'
	    if ( !$CONSUMER_KEY = get_config('morsle','consumer_key')) {
	        return NULL;
	    }

	    if( !$CONSUMER_SECRET = get_config('morsle','oauthsecretstr') ) {
	        return NULL;
	    }
	    $CONSUMER_SECRET = morsle_decode($CONSUMER_SECRET);
	    $consumer = new OAuthConsumer($CONSUMER_KEY, $CONSUMER_SECRET, NULL);
	    // Create an Atom entry
	    $contactAtom = new DOMDocument();
	//    $contactAtom = null;
	    $request = OAuthRequest::from_consumer_and_token($consumer, NULL, $type, $base_feed, $params);
	    // Sign the constructed OAuth request using HMAC-SHA1
	    $request->sign_request(new OAuthSignatureMethod_HMAC_SHA1(), $consumer, NULL);
	    //  scope=https://docs.google.com/feeds/%20http://spreadsheets.google.com/feeds/%20https://docs.googleusercontent.com/
	    // Make signed OAuth request to the Contacts API server
		if (!is_null($params)) {
		    $url = $base_feed . '?' . implode_assoc('=', '&', $params);
		} else {
			$url = $base_feed;
		}
	    $header_request = $request->to_header();
	    $response = send_request($request->get_normalized_http_method(), $url, $header_request, $contactAtom, $postdata, $version, $content_type, $slug, $batch);
	    return $response;
	}


   /**
   * Makes an HTTP request to the specified URL
   * @param string $http_method The HTTP method (GET, POST, PUT, DELETE)
   * @param string $url Full URL of the resource to access
   * @param string $auth_header (optional) Authorization header
   * @param DOM $contactAtom (optional) DOM document coming from an OAuth setup
   * @param string $postData (optional) POST/PUT request body
   * @param string $version (optional) if not sent will be set to 3.0
   * @param string $content_type (optional) what kind of content is being sent
   * @param string $slug (optional) used in determining the revision of a document
   * @param boolean $batch is this a batch transmission?
   * @return string $returnval body from the server
   */
  function send_request($http_method, $url, $auth_header=null, $contactAtom=null, $postData=null, $version=null, $content_type = null, $slug=null, $batch=null) {
    global $success;
    $returnval = new stdClass();
  	$curl = curl_init($url);
    $version = $version == null ? 'Gdata-Version: 3.0' : 'Gdata-Version: ' . $version;
    if (is_null($content_type)) {
	    $content_type = 'Content-Type: application/atom+xml';
    } else {
	    $content_type = 'Content-Type: ' . $content_type;
    }
	$postarray = array($content_type, $auth_header, $version);
    // change this to be an array of values
    if(!is_null($postData)) {
        $length = strlen($postData);
        $postarray[] = 'Content-Length: ' . s($length);
    }
    if (!is_null($slug)) {
    	$postarray[] = 'Slug: ' . $slug;
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FAILONERROR, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    switch($http_method) {
      case 'GET':
        if ($auth_header) {
          curl_setopt($curl, CURLOPT_HTTPHEADER, array($auth_header,
              $version));
        }
        break;
      case 'POST':
		if ($batch !== null) {
			$postarray[] = 'If-Match: *';
		}
		curl_setopt($curl, CURLOPT_HTTPHEADER, $postarray);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        break;
      case 'PUT':
      	$postarray[] = 'If-Match: *';
        curl_setopt($curl, CURLOPT_HTTPHEADER, $postarray);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $http_method);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        break;
      case 'DELETE':
        curl_setopt($curl, CURLOPT_HTTPHEADER, array($auth_header,
            $version, 'If-Match: *'));
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $http_method);
        break;
    }
    $response = curl_exec($curl);

    // this usually only happens with calendar and calendar events
    if (strpos($response, 'gsessionid')) {
		preg_match("(https://([^\"']+))i",$response,$match);
		$url = $match[0];
	    curl_close($curl);
		$response = send_request($http_method, $url, $auth_header, $contactAtom, $postData, '2', null, $slug, $batch);
//	    curl_close($curl);
//		$returnval = $response;
//	    return $returnval;
    }
    if (isset($response->info)) { // we're in the second time around and the build of the return value has already occurred
    	return $response;
    } else {
		$info = curl_getinfo($curl);
	    curl_close($curl);
	    if (!is_null($response)) {
			$returnval->response = $response;
			$returnval->info = $info;
	    }
		if ($returnval->info['http_code'] == 200 || $returnval->info['http_code'] == 201) {
			$success = true;
		} else {
			$success = false;
		}
	    return $returnval;
    }
}

/**
   * Joins key:value pairs by inner_glue and each pair together by outer_glue
   * @param string $inner_glue The HTTP method (GET, POST, PUT, DELETE)
   * @param string $outer_glue Full URL of the resource to access
   * @param array $array Associative array of query parameters
   * @return string Urlencoded string of query parameters
   */
  function implode_assoc($inner_glue, $outer_glue, $array) {
    $output = array();
    foreach($array as $key => $item) {
      $output[] = $key . $inner_glue . urlencode($item);
    }
    return implode($outer_glue, $output);
  }

/*
 * Uses two-legged OAuth to respond to a Google documents list API request
 */
function twolegged_x($base_feed, $params, $type, $postdata = null, $version = null, $content_type = null, $slug=null, $x_upload_type = null, $x_upload_content = null) {
    global $CFG;
    require_once($CFG->dirroot . '/google/oauth.php');
    // Establish an OAuth consumer based on our admin 'credentials'
    if ( !$CONSUMER_KEY = get_config('morsle','consumer_key')) {
        return NULL;
    }

    if( !$CONSUMER_SECRET = get_config('morsle','oauthsecret') ) {
        return NULL;
    }
    $consumer = new OAuthConsumer($CONSUMER_KEY, $CONSUMER_SECRET, NULL);
    // Create an Atom entry
    $contactAtom = new DOMDocument();
    $request = OAuthRequest::from_consumer_and_token($consumer, NULL, $type, $base_feed, $params);
    // Sign the constructed OAuth request using HMAC-SHA1
    $request->sign_request(new OAuthSignatureMethod_HMAC_SHA1(), $consumer, NULL);
    // Make signed OAuth request to the Contacts API server
	if (!is_null($params)) {
	    $url = $base_feed . '?' . implode_assoc('=', '&', $params);
	} else {
		$url = $base_feed;
	}
    $header_request = $request->to_header();
    $response = send_request_x($request->get_normalized_http_method(), $url, $header_request, $contactAtom, $postdata, $version, $content_type, $slug, $x_upload_type, $x_upload_content);
    return $response;
}



   /**
   * Makes an HTTP request to the specified URL
   * @param string $http_method The HTTP method (GET, POST, PUT, DELETE)
   * @param string $url Full URL of the resource to access
   * @param string $auth_header (optional) Authorization header
   * @param string $postData (optional) POST/PUT request body
   * @return string Response body from the server
   */
function send_request_x($http_method, $url, $auth_header=null, $contactAtom=null, $postData=null, $version=null, $content_type = null, $slug=null, $x_upload_type = null, $x_upload_content = null, $content_range = null) {
    $curl = curl_init($url);
    $version = $version == null ? 'Gdata-Version: 3.0' : 'Gdata-Version: ' . $version;
    if (!is_null($x_upload_type)) {
		$content_type =  'Content-Type: ' . $x_upload_type;
    } elseif (is_null($content_type)) {
    	$content_type = 'Content-Type: application/atom+xml';
    } else {
	    $content_type = 'Content-Type: ' . $content_type;
    }
    $postarray = array($content_type, $auth_header, $version);
    // change this to be an array of values
    if(!is_null($postData)) {
        $length = strlen($postData);
        $postarray[] = 'Content-Length: ' . s($length);
    }
    if(!is_null($x_upload_type)) { // first post of multi-part upload
        $postarray[] = 'X-Upload-Content-Type: ' . $x_upload_type;
    }
    if(!is_null($x_upload_content)) {
    	if($x_upload_content === 0) {
	        $postarray[] = 'X-Upload-Content-Length: 0';
    	} else {
	        $x_length = strlen($x_upload_content);
	        $postarray[] = 'X-Upload-Content-Length: ' . s($x_length);
    	}
    }
    if (!is_null($content_range)) {
    	$postarray[] = 'Content-Range: bytes ' . s($content_range) . '-' . s($content_range + $length - 1) . '/' . s($x_length);
    }
    if (!is_null($slug)) {
    	$postarray[] = 'Slug: ' . $slug;
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FAILONERROR, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    switch($http_method) {
      case 'GET':
        if ($auth_header) {
          curl_setopt($curl, CURLOPT_HTTPHEADER, array($auth_header,
              $version));
        }
        break;
      case 'POST':
//      	$postarray[] = 'If-Match: *';
      	curl_setopt($curl, CURLOPT_HTTPHEADER, $postarray);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
    	// geet only the headers
        if (is_null($content_range)) {
//        	curl_setopt($curl, CURLOPT_NOBODY, true);
			curl_setopt($curl, CURLOPT_HEADER, true);
       	} else {
//        	curl_setopt($curl, CURLOPT_NOBODY, false);
			curl_setopt($curl, CURLOPT_HEADER, false);
       	}
        break;
      case 'PUT':
      	$postarray[] = 'If-Match: *';
        curl_setopt($curl, CURLOPT_HTTPHEADER, $postarray);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $http_method);
		curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        break;
      case 'DELETE':
        curl_setopt($curl, CURLOPT_HTTPHEADER, array($auth_header,
            $version, 'If-Match: *'));
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $http_method);
        break;
    }
    $response = curl_exec($curl);

    // this usually only happens with calendar and calendar events
    if (strpos($response, 'gsessionid')) {
		preg_match("(https://([^\"']+))i",$response,$match);
		$url = $match[0];
	    curl_close($curl);
		$response = send_request($http_method, $url, $auth_header, $contactAtom, $postData, '2.0', null, $slug);
	    curl_close($curl);
		$returnval = $response;
	    return $returnval;
    } elseif (!is_null($x_upload_content)) {
		$info = curl_getinfo($curl);
		curl_close($curl);
	    switch ($info['http_code']) {
	    	case 201: // successfully completed file transfer
	    		break;
	    	case 200: // send the first chunk
//			    $feed = simplexml_load_string($response);
				$content_range = 0;
		    	$putlength = min(524288,$x_length - $content_range);  //allow for last part which may be less than 512k
				$postData = substr($x_upload_content,$content_range,$putlength);
				preg_match("(https://([^\s\"']+))i",$response,$match);
				$url = $match[0];
	    		$response = send_request_x('PUT', $url, $auth_header, $contactAtom, $postData, $version, $content_type, $slug, null, $x_upload_content, $content_range);
				break;
	    	case 308: // send the next chunk
//			    $feed = simplexml_load_string($response);
		    	$putlength = min(524288,$x_length - $content_range);  //allow for last part which may be less than 512k
			    $content_range += $putlength;
				$postData = substr($x_upload_content,$content_range,$putlength);
//				preg_match("(https://([^\s\"']+))i",$response,$match);
//				$url = $match[0];
	    		$response = send_request_x('PUT', $url, $auth_header, $contactAtom, $postData, $version, $content_type, $slug, null, $x_upload_content, $content_range);
				break;
	    	default: // do nothing but allow a resend of what was sent before
				break;
	    }
    } else {
		$info = curl_getinfo($curl);
	    curl_close($curl);
		$returnval->response = $response;
		$returnval->info = $info;
	    return $returnval;
    }
}

function morsle_encode($value) {
	global $CFG;
	$salt = isset($CFG->passwordsaltmain) ? $CFG->passwordsaltmain : 'morsle';
	$ret = base64_encode(rc4decrypt($value, $salt));
	return $ret;
}

function morsle_decode($value) {
	global $CFG;
	$salt = isset($CFG->passwordsaltmain) ? $CFG->passwordsaltmain : 'morsle';
	$ret = trim(rc4decrypt(base64_decode($value),$salt));
	return $ret;
}

?>