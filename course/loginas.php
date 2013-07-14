<?php
// Allows a teacher/admin to login as another user (in stealth mode)

require_once('../config.php');
require_once('lib.php');

$id       = optional_param('id', SITEID, PARAM_INT);   // course id
$redirect = optional_param('redirect', 0, PARAM_BOOL);

$url = new moodle_url('/course/loginas.php', array('id'=>$id));
$PAGE->set_url($url);

/// Reset user back to their real self if needed, for security reasons you need to log out and log in again
if (session_is_loggedinas()) {
    require_sesskey();
    $SESSION->load_navigation_admin = true;
    $USER = get_complete_user_data('id', $_SESSION['USER']->realuser);
    load_all_capabilities();   // load all this user's normal capabilities

    if (isset($SESSION->oldcurrentgroup)) {      // Restore previous "current group" cache.
        $SESSION->currentgroup = $SESSION->oldcurrentgroup;
        unset($SESSION->oldcurrentgroup);
    }
    if (isset($SESSION->oldtimeaccess)) {        // Restore previous timeaccess settings
        $USER->timeaccess = $SESSION->oldtimeaccess;
        unset($SESSION->oldtimeaccess);
    }
    if (isset($SESSION->grade_last_report)) {    // Restore grade defaults if any
        $USER->grade_last_report = $SESSION->grade_last_report;
        unset($SESSION->grade_last_report);
    }
	if (isset($_SERVER["HTTP_REFERER"])) { // That's all we wanted to do, so let's go back
		redirect($_SERVER["HTTP_REFERER"]);
	} else {
		redirect($CFG->wwwroot);
	}
}

if ($redirect) {
    if ($id and $id != SITEID) {
        $SESSION->wantsurl = "$CFG->wwwroot/course/view.php?id=".$id;
    } else {
        $SESSION->wantsurl = "$CFG->wwwroot/";
    }
}

///-------------------------------------
/// We are trying to log in as this user in the first place

$userid = required_param('user', PARAM_INT);         // login as this user

require_sesskey();
$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);

/// User must be logged in

$systemcontext = context_system::instance();
$coursecontext = context_course::instance($course->id);

require_login();

if (has_capability('moodle/user:loginas', $systemcontext)) {
    if (is_siteadmin($userid)) {
        print_error('nologinas');
    }
    $context = $systemcontext;
    $PAGE->set_context($context);
} else {
    require_login($course);
    require_capability('moodle/user:loginas', $coursecontext);
    if (is_siteadmin($userid)) {
        print_error('nologinas');
    }
    if (!is_enrolled($coursecontext, $userid)) {
        print_error('usernotincourse');
    }
    $context = $coursecontext;
}

/// Login as this user and return to course home page.
$oldfullname = fullname($USER, true);
session_loginas($userid, $context);
$newfullname = fullname($USER, true);

add_to_log($course->id, "course", "loginas", "../user/view.php?id=$course->id&amp;user=$userid", "$oldfullname -> $newfullname");

$strloginas    = get_string('loginas');
$strloggedinas = get_string('loggedinas', '', $newfullname);
notice($strloggedinas, "$CFG->wwwroot/course/view.php?id=$course->id");

if (isset($_SERVER["HTTP_REFERER"])) { // That's all we wanted to do, so let's go back
    redirect($_SERVER["HTTP_REFERER"]);
} else {
    redirect($CFG->wwwroot);
}