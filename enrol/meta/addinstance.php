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
 *
 * Adds new instace of enrol_meta to specified courses
 * @package    enrol
 * @subpackage meta
 * @copyright  2010 Petr Skoda {@link http://skodak.org}
 * @copyright  2013 Robert Puffer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("$CFG->dirroot/course/lib.php");
require_once("$CFG->dirroot/enrol/meta/locallib.php");

define("MAX_COURSES_PER_PAGE", 1000);
global $DB, $PAGE, $OUTPUT, $COURSE;

$id             = required_param('id', PARAM_INT); // Ccourse id.
$add            = optional_param('add', 0, PARAM_BOOL);
$remove         = optional_param('remove', 0, PARAM_BOOL);
$showall        = optional_param('showall', 0, PARAM_BOOL);
$searchtext     = optional_param('searchtext', '', PARAM_RAW); // Search string.
$previoussearch = optional_param('previoussearch', 0, PARAM_BOOL);
$previoussearch = ($searchtext != '') or ($previoussearch) ? 1:0;

if (! $site = get_site()) {
    redirect("$CFG->wwwroot/$CFG->admin/index.php");
}

$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);
$context = get_context_instance(CONTEXT_COURSE, $course->id, MUST_EXIST);

$PAGE->set_url('/enrol/meta/addinstance.php', array('id'=>$course->id));
$PAGE->set_pagelayout('admin');

navigation_node::override_active_url(new moodle_url('/enrol/instances.php', array('id'=>$course->id)));

require_login($course->id);
require_capability('moodle/course:enrolconfig', $context);
$enrol = enrol_get_plugin('meta');

$strassigncourses = 'Assign courses';
$strsearch        = get_string("search");
$strsearchresults  = get_string("searchresults");
$strcourses   = get_string("courses");
$strshowall = get_string("showall");

$stralreadycourses = 'Courses already assigned';
$strnoalreadycourses = 'No courses already assigned';
$strpotentialcourses = 'Courses available';
$strnopotentialcourses = 'No courses available';
$straddcourses = 'Add this course';
$strremovecourse = 'Remove this course';

// Print a help notice about the need to use this page.
if (!$frm = data_submitted()) {
    $note = 'Use this form to add courses to your meta course (this will import the enrolments)';
    $OUTPUT->box($note);

    // A form was submitted so process the input.
} else {
    if ($add and !empty($frm->addselect) and confirm_sesskey()) {
        $timestart = $timeend = 0;
        foreach ($frm->addselect as $addcourse) {
            $eid = $enrol->add_instance($COURSE, array('customint1'=>$addcourse));
            enrol_meta_sync($COURSE->id);
        }
    } else if ($remove and !empty($frm->removeselect) and confirm_sesskey()) {
        foreach ($frm->removeselect as $removecourse) {
            $select = "courseid = $COURSE->id AND customint1 = $removecourse";
            $enroltodelete = $DB->get_record_select('enrol', $select);
            $eid = $enrol->delete_instance($enroltodelete);
        }

    } else if ($showall and confirm_sesskey()) {
        $searchtext = '';
        $previoussearch = 0;
    }
}

// Get all existing students and teachers for this course.
if (!$alreadycourses = $DB->get_records('enrol', array('courseid'=>$course->id, 'enrol'=>'meta'),
    'sortorder,courseid', 'customint1, courseid, enrol, id')) {
    $alreadycourses = array();
} else {
    foreach ($alreadycourses as $key => $acourse) {
        $alreadycourses[$key] = $DB->get_record('course', array('id'=>$key));
    }
}

$numcourses = 0;

// Get search results excluding any users already in this course.
if (($searchtext != '') and $previoussearch and confirm_sesskey()) {
    if ($searchcourses = get_courses_search(explode(" ", $searchtext), 'fullname ASC', 0, 99999, $numcourses)) {
        foreach ($searchcourses as $tmp) {
            if (array_key_exists($tmp->id, $alreadycourses)) {
                unset($searchcourses[$tmp->id]);
            }
            if (!empty($tmp->metacourse)) {
                unset($searchcourses[$tmp->id]);
            }
        }
        if (array_key_exists($course->id, $searchcourses)) {
            unset($searchcourses[$course->id]);
        }
        $numcourses = count($searchcourses);
    }
}

// If no search results then get potential students for this course excluding users already in course.
if (empty($searchcourses)) {
    $courses = get_courses('all', null, 'c.id, c.fullname, c.visible, c.shortname');
    foreach ($alreadycourses as $key => $acourse) {
        unset($courses[$key]);
    }
    foreach ($courses as $c) {
        if ($c->id == SITEID or $c->id == $course->id or isset($existing[$c->id])) {
            unset($courses[$c->id]);
            continue;
        }
        $coursecontext = get_context_instance(CONTEXT_COURSE, $c->id);
        if (!$c->visible and !has_capability('moodle/course:viewhiddencourses', $coursecontext)) {
            unset($courses[$c->id]);
            continue;
        }
        if (!has_capability('enrol/meta:selectaslinked', $coursecontext)) {
            unset($courses[$c->id]);
            continue;
        }
    }
    $numcourses = count($courses);
}

$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('pluginname', 'enrol_meta'));

echo $OUTPUT->header();
?>

    <form id="studentform" method="post" action="addinstance.php">
    <input type="hidden" name="previoussearch" value="<?php echo $previoussearch ?>" />
    <input type="hidden" name="sesskey" value="<?php echo sesskey() ?>" />
    <input type="hidden" name="id" value="<?php echo $id?>" />
    <table summary="" align="center" border="0" cellpadding="5" cellspacing="0">
    <tr>
    <td valign="top">
    <label for="removeselect"><?php echo count($alreadycourses) . " ". $stralreadycourses ?></label>
              <br />
              <select name="removeselect[]" size="20" id="removeselect" multiple="multiple"
                      onFocus="getElementById('studentform').add.disabled=true;
                               getElementById('studentform').remove.disabled=false;
                               getElementById('studentform').addselect.selectedIndex=-1;">
              <?php
                foreach ($alreadycourses as $course) {
                    echo "<option value=\"$course->id\">".course_format_name($course, 60)."</option>\n";
                }
              ?>
              </select></td>
          <td valign="top">
            <p class="arrow_button">
                <input name="add" id="add" type="submit" value="<?php echo '&nbsp;'.$OUTPUT->larrow().' &nbsp; &nbsp; '.get_string('add'); ?>" title="<?php print_string('add'); ?>" />
                <br />
                <input name="remove" id="remove" type="submit" value="<?php echo '&nbsp; '.$OUTPUT->rarrow().' &nbsp; &nbsp; '.get_string('remove'); ?>" title="<?php print_string('remove'); ?>" />
            </p>
          </td>
          <td valign="top">
              <label for="addselect"><?php echo $numcourses . " " . $strpotentialcourses ?></label>
              <br />
              <select name="addselect[]" size="20" id="addselect" multiple="multiple"
                      onFocus="getElementById('studentform').add.disabled=false;
                               getElementById('studentform').remove.disabled=true;
                               getElementById('studentform').removeselect.selectedIndex=-1;">
              <?php

                  if (!empty($searchcourses)) {
                      echo "<optgroup label=\"$strsearchresults (" . count($searchcourses) . ")\">\n";
                      foreach ($searchcourses as $course) {
                          echo "<option value=\"$course->id\">".course_format_name($course, 60)."</option>\n";
                      }
                      echo "</optgroup>\n";
                  }
                  if (!empty($courses)) {
                      if ($numcourses > MAX_COURSES_PER_PAGE) {
                          echo '<optgroup label="'.get_string('toomanytoshow').'"><option></option></optgroup>'."\n"
                              .'<optgroup label="'.get_string('trysearching').'"><option></option></optgroup>'."\n";
                      } else {
                          foreach ($courses as $course) {
                              echo "<option value=\"$course->id\">".course_format_name($course, 60)."</option>\n";
                          }
                      }
                  }
              ?>
             </select>
             <br />
             <label for="searchtext" class="accesshide"><?php p($strsearch) ?></label>
             <input type="text" name="searchtext" id="searchtext" size="30" value="<?php p($searchtext, true) ?>"
                      onFocus ="getElementById('studentform').add.disabled=true;
                                getElementById('studentform').remove.disabled=true;
                                getElementById('studentform').removeselect.selectedIndex=-1;
                                getElementById('studentform').addselect.selectedIndex=-1;"
                      onkeydown = "var keyCode = event.which ? event.which : event.keyCode;
                                   if (keyCode == 13) {
                                        getElementById('studentform').previoussearch.value=1;
                                        getElementById('studentform').submit();
                                   } " />
             <input name="search" id="search" type="submit" value="<?php p($strsearch) ?>" />
             <?php
                  if (!empty($searchcourses)) {
                      echo '<input name="showall" id="showall" type="submit" value="'.$strshowall.'" />'."\n";
                  }
             ?>
           </td>
        </tr>
      </table>
    </form>
<?php
    echo $OUTPUT->footer();
