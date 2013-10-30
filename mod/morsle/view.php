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
 * morsle module main user interface
 *
 * @package    mod
 * @subpackage morsle
 * @copyright  2009 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("$CFG->dirroot/mod/morsle/locallib.php");
require_once($CFG->libdir . '/completionlib.php');

$id       = optional_param('id', 0, PARAM_INT);        // Course module ID
$u        = optional_param('u', 0, PARAM_INT);         // morsle instance id
$redirect = optional_param('redirect', 0, PARAM_BOOL);

if ($u) {  // Two ways to specify the module
    $morsle = $DB->get_record('morsle', array('id'=>$u), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('morsle', $morsle->id, $morsle->course, false, MUST_EXIST);

} else {
    $cm = get_coursemodule_from_id('morsle', $id, 0, false, MUST_EXIST);
    $morsle = $DB->get_record('morsle', array('id'=>$cm->instance), '*', MUST_EXIST);
}

$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = get_context_instance(CONTEXT_MODULE, $cm->id);
require_capability('mod/morsle:view', $context);

add_to_log($course->id, 'morsle', 'view', 'view.php?id='.$cm->id, $morsle->id, $cm->id);

// Update 'viewed' state if required by completion system
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url('/mod/morsle/view.php', array('id' => $cm->id));

// Make sure morsle exists before generating output - some older sites may contain empty urls
// Do not use PARAM_URL here, it is too strict and does not support general URIs!
$exturl = trim($morsle->externalurl);
if (empty($exturl) or $exturl === 'http://') {
    morsle_print_header($morsle, $cm, $course);
    morsle_print_heading($morsle, $cm, $course);
    morsle_print_intro($morsle, $cm, $course);
    notice(get_string('invalidstoredurl', 'morsle'), new moodle_url('/course/view.php', array('id'=>$cm->course)));
    die;
}
unset($exturl);

$displaytype = morsle_get_final_display_type($morsle);
if ($displaytype == RESOURCELIB_DISPLAY_OPEN) {
    // For 'open' links, we always redirect to the content - except if the user
    // just chose 'save and display' from the form then that would be confusing
    if (!isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], 'modedit.php') === false) {
        $redirect = true;
    }
}

if ($redirect) {
    // coming from course page or morsle index page,
    // the redirection is needed for completion tracking and logging
    $fullurl = morsle_get_full_url($morsle, $cm, $course);
    redirect(str_replace('&amp;', '&', $fullurl));
}

switch ($displaytype) {
    case RESOURCELIB_DISPLAY_EMBED:
        morsle_display_embed($morsle, $cm, $course);
        break;
    case RESOURCELIB_DISPLAY_FRAME:
        morsle_display_frame($morsle, $cm, $course);
        break;
    default:
        morsle_print_workaround($morsle, $cm, $course);
        break;
}
