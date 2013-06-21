<?php

  /* This is the global search shortcut block - a single query can be entered, and
  * the user will be redirected to the query page where they can enter more
  *  advanced queries, and view the results of their search. When searching from
  *  this block, the broadest possible selection of documents is searched.
  *
  *
  *  Todo: make strings -> get_string()
  *
  * @package search
  * @subpackage search block
  * @author: Michael Champanis (mchampan), reengineered by Valery Fremaux
  * @date: 2006 06 25
  */

  class block_quickset extends block_base {

    function init() {
      $this->title = get_string('pluginname', 'block_quickset');
      $this->cron = 1;
    } //init

    // only one instance of this block is required
    function instance_allow_multiple() {
      return false;
    } //instance_allow_multiple

    // label and button values can be set in admin
    function has_config() {
      return false;
    } //has_config

    function get_content() {
		global $CFG, $COURSE, $USER, $PAGE, $DB;
		echo '<style>';
		include_once 'styles.css';
		echo '</style>';
        $AVAILABLE = 1;
        $UNAVAILABLE = 0;
	  $this->content = new stdClass;
	  $returnurl = "$CFG->wwwroot/course/view.php?id=$COURSE->id";
	  $numsections = $DB->get_field('course_format_options', 'value', array('courseid' => $COURSE->id, 'name' => 'numsections'));

      $context = get_context_instance(CONTEXT_COURSE, $COURSE->id);
    if (has_capability('moodle/course:update', $context)) {
        if ($COURSE->visible == 1) {
            $students = 'green';
            $studentschecked = ' checked="checked"';
            $studentsunchecked = '';
        } else {
            $students = 'red';
            $studentsunchecked = ' checked="checked"';
            $studentschecked = '';
        }
        if ($COURSE->showgrades == 1) {
            $grades = 'green';
            $gradeschecked = ' checked="checked"';
            $gradesunchecked = '';
        } else {
            $grades = 'red';
            $gradesunchecked = ' checked="checked"';
            $gradeschecked = '';
        }
        $this->content->text = '<form id="quickset" action="' . $CFG->wwwroot . '/blocks/quickset/edit.php" method="post">'
                . '<input type="hidden" value="'.$PAGE->course->id.'" name="courseid" />'
                . '<input type="hidden" value="'.sesskey().'" name="sesskey" />'
                . '<input type="hidden" value="' . $returnurl . '" name="pageurl"/>'
                . '<input type="hidden" value="grader" name="report"/>';
        $this->content->text .= '<div id="context">'
                . '<div class="ynlabel" style="margin-right:0em">Yes | No</div>'

                . '<div class="setleft ' . $students . '">Students see course?</div>'
                . '<div class="setright">'
                	. '<input type="radio" style="margin-top:4px" name="course" value=' . $AVAILABLE . $studentschecked . ' />'
                	. '<input type="radio" style="margin-top:4px" name="course" value=' . $UNAVAILABLE . $studentsunchecked . ' />'
                . '</div>'

                . '<div class="setleft ' . $grades . '">Grades visible?</div>'
                . '<div>'
                	. '<input type="radio" style="margin-top:4px" name="grades" value=' . $AVAILABLE . $gradeschecked . ' />'
                	. '<input type="radio" style="margin-top:4px" name="grades" value=' . $UNAVAILABLE . $gradesunchecked . ' />'
                . '</div>'

                . '<div class="setleft blue toplevel" style="margin-top:4px" >Visible sections </div>'
                . '<div class="setright" style="margin-top:4px">'
                	. '<input type="text" name="number" size="2" value="'.$numsections.'"/>'
                . '</div>'

                . '<br /><br />'

                . '<div style="min-width:100%">'
                	. '<span class="updatesettings">'
	                	. '<input type="submit" name="updatesettings" style="display:inline-block;font-size:.9em;float:left;margin-right:0px;margin-left:0px;padding-left:0px;background-color: #ff9999" value="Update settings">'
                	. '</span>'
	                . '<span class="noaction">'
	                	. '<input type="submit" name="noaction" value="Edit Sections" style="display:inline-block;float:right;font-size:.9em;margin-right:0px;margin-left:0px;padding-right:0px;background-color: #66ffcc">'
	                . '</span>'
	            . '</div>'

	            . '<br /><br />'

                . '<div style="text-align:center"><a href="' . $CFG->wwwroot . '/course/edit.php?id=' . $COURSE->id . '"> More Settings </a></div>'
                . '</div></form>';
        $this->content->text .= '<div class="small" style="text-align:center;color:red">Note: This block invisible to students</div>';

    }
		  //no footer, thanks
		  $this->content->footer = '';
		  return $this->content;
    } //get_content

    function specialisation() {
      //empty!
    } //specialisation


  } //block_course_settings
?>