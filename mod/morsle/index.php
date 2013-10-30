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
 * List of morsles in course
 *
 * @package    mod
 * @subpackage morsle
 * @copyright  2012 Bob Puffer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT); // course id

$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);

require_course_login($course, true);
$PAGE->set_pagelayout('incourse');

add_to_log($course->id, 'morsle', 'view all', "index.php?id=$course->id", '');

$strmorsle       = get_string('modulename', 'morsle');
$strmorsles      = get_string('modulenameplural', 'morsle');
$strsectionname  = get_string('sectionname', 'format_'.$course->format);
$strname         = get_string('name');
$strintro        = get_string('moduleintro');
$strlastmodified = get_string('lastmodified');

$PAGE->set_url('/mod/morsle/index.php', array('id' => $course->id));
$PAGE->set_title($course->shortname.': '.$strmorsles);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strmorsles);
echo $OUTPUT->header();

if (!$morsles = get_all_instances_in_course('morsle', $course)) {
    notice(get_string('thereareno', 'moodle', $strmorsles), "$CFG->wwwroot/course/view.php?id=$course->id");
    exit;
}

$usesections = course_format_uses_sections($course->format);
if ($usesections) {
    $sections = get_all_sections($course->id);
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $table->head  = array ($strsectionname, $strname, $strintro);
    $table->align = array ('center', 'left', 'left');
} else {
    $table->head  = array ($strlastmodified, $strname, $strintro);
    $table->align = array ('left', 'left', 'left');
}

$modinfo = get_fast_modinfo($course);
$currentsection = '';
foreach ($morsles as $morsle) {
    $cm = $modinfo->cms[$morsle->coursemodule];
    if ($usesections) {
        $printsection = '';
        if ($morsle->section !== $currentsection) {
            if ($morsle->section) {
                $printsection = get_section_name($course, $sections[$morsle->section]);
            }
            if ($currentsection !== '') {
                $table->data[] = 'hr';
            }
            $currentsection = $morsle->section;
        }
    } else {
        $printsection = '<span class="smallinfo">'.userdate($morsle->timemodified)."</span>";
    }

    $extra = empty($cm->extra) ? '' : $cm->extra;
    $icon = '';
    if (!empty($cm->icon)) {
        // each morsle has an icon in 2.0
        $icon = '<img src="'.$OUTPUT->pix_url($cm->icon).'" class="activityicon" alt="'.get_string('modulename', $cm->modname).'" /> ';
    }

    $class = $morsle->visible ? '' : 'class="dimmed"'; // hidden modules are dimmed
    $table->data[] = array (
        $printsection,
        "<a $class $extra href=\"view.php?id=$cm->id\">".$icon.format_string($morsle->name)."</a>",
        format_module_intro('morsle', $morsle, $cm->id));
}

echo html_writer::table($table);

echo $OUTPUT->footer();
