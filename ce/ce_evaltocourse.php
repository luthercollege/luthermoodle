<?php
//	global $CFG; // if run from inside moodle environment as by moodlecron
	require_once('../config.php');  // if run from outside as by cron
	// number of days ahead of end of class when course evaluations must be in course
//	$ce_leadtime = isset($CFG->ce_leadtime) ? $CFG->ce_leadtime : 14;
	$ce_leadtime = isset($CFG->ce_leadtime) ? $CFG->ce_leadtime : 100;
	$lead_seconds = $ce_leadtime * 24 * 60 * 60;
	$cur_course = 0;
	$curtime = time();

	// get list of courses for current term
	// that haven't already been populated (installdate > 0)
	// that are due to populated with course evaluations curtime + leadtime is after enddate
	//
	$currentterm = '2013JT';
	$sql = "SELECT * FROM mdl_ce_course WHERE term = '$currentterm'
			AND installdate = 0
			AND $curtime + $lead_seconds > enddate
			ORDER BY department";
	$ce_recs = get_records_sql($sql);
	foreach($ce_recs as $rec) {
		// set up record shells to be tailored to this course
		$this_ce = ce_rec($rec->teacherusername, $currentterm);

		// always creates a new all colleges eval if this is the first time this
		// session has come to this course
		// this means that refreshing courses (repairing) will leave multiple all college evals out there, one for the old
		// evals and one for any new ones updated
		if ($rec->courseid <> $cur_course) {
			$cur_course = $rec->courseid;

			// set up record shells to be tailored to this course
			$this_all = all_rec();

			// determine last visible section of course
			$this_all['cs']->course = $rec->courseid;
			$sql = "SELECT MAX(section) FROM mdl_course_sections
							WHERE course = $rec->courseid";
			$maxsection = get_field_sql($sql);

			// determine zero section id of course
			$sql = "SELECT * FROM mdl_course_sections
							WHERE course = $rec->courseid
							AND section = 0";
			$section = get_record_sql($sql);
			$zerosection = $section->id;

			// determine what content is in the zero section
			$tempsequence = $section->sequence;
			$tempsummary = $section->summary;

			// add five invisible course_sections records to end of course -- make sure its at least section 15
			$targetsection = $maxsection < 10 ? 15 - $maxsection : $maxsection + 5;
			for ($i=$maxsection + 1; $i<=$targetsection; $i++) {
				$this_all['cs']->section = $i;
				// TODO: test out this new syntax this should insert a new section
				$return = get_course_section($i, $rec->courseid);
				$cs_id = $return->id; // used later
//				$cs_id = insert_record('course_sections', $this_all['cs']);
			}
			$this_all['cs']->id = $cs_id;

			// write out questionnaire record for All College
			$this_all['q']->course = $rec->courseid;
			$all_q_id = insert_record('questionnaire', $this_all['q']);

			// add course_modules record for All College questionnaire using questionnaire.id as instance
			$this_all['cm']->course = $rec->courseid;
			$this_all['cm']->section = $cs_id;
			$this_all['cm']->instance = $all_q_id;
			// TODO: test out this new syntax
			$all_cm_id = add_course_module($this_all['cm']);
//			$all_cm_id = insert_record('course_modules', $this_all['cm']);

			// add course_modules.id to invisible course_sections record
			$this_all['cs']->sequence = $all_cm_id;
			$success = update_record('course_sections',$this_all['cs']);
			if (!$success) {
				echo 'FAILURE IN CREATING ALL COLLEGES course section insert for ' . $rec->courseid . '-' . $rec->fullname . '<br />';
			} else {
				echo 'Successfully created all colleges course section insert for ' . $rec->courseid . '-' . $rec->fullname . '<br />';
			}
			unset($success);
		}

		// determine the name for the course evaluation based on course name and instructor name
		$this_ce['q']->name = "Course Evaluation for $rec->fullname - $rec->teacherfullname";
		$this_ce['sur']->name = $this_ce['q']->name;

		// write out survey record for course eval with course_modules.id in thanks_page
		$tempthanks = split("=",$this_ce['sur']->thanks_page); // strip the cm_id from the end of the thanks page referral
		$this_ce['sur']->thanks_page = $tempthanks[0] . '=' . $all_cm_id;
		$this_ce['sur']->owner = $rec->courseid;
		$ce_sur_id = insert_record('questionnaire_survey',$this_ce['sur']);

		// write out questionnaire record for course eval
		$this_ce['q']->sid = $ce_sur_id;
		$this_ce['q']->course = $rec->courseid;
		$ce_q_id = insert_record('questionnaire',$this_ce['q']);
		$rec->qid = $ce_q_id; // store the questionnaire id so it can be found easily when reporting even if the name's been changed

		// write out questionnaire_question records for course eval using survey id
		foreach ($this_ce['ques'] as $question) {
			$question->survey_id = $ce_sur_id;
			$question_id = insert_record('questionnaire_question',$question);
			foreach ($question->choices as $choice) {
				$choice->question_id = $question_id;
				insert_record('questionnaire_quest_choice', $choice);
			}
		}

		// add course_modules record for course eval questionnaire using questionnaire.id as instance
		if($grouping = get_record('groupings','courseid', $rec->courseid,'name', $rec->teacherfullname)) {
			$this_ce['cm']->groupingid = $grouping->id;
			$this_ce['cm']->groupmembersonly = 1;
		}
		$this_ce['cm']->course = $rec->courseid;
		$this_ce['cm']->section = $zerosection;
		$this_ce['cm']->instance = $ce_q_id;
		// TODO: test out this new syntax
		$ce_cm_id = add_course_module($this_ce['cm']);
//		$ce_cm_id = insert_record('course_modules', $this_ce['cm']);


		// add course_modules.id to 0 course_sections record
//		$tempsequence = $tempsequence === null ? $ce_cm_id : $ce_cm_id . ',' . $tempsequence;
		$this_ce['cs'] = get_record('course_sections', 'section', 0, 'course', $rec->courseid);
		$this_ce['cs']->sequence = $ce_cm_id . ',' . $this_ce['cs']->sequence;
		$this_ce['cs']->summary = addslashes($this_ce['cs']->summary);

//		$this_ce['cs']->id = $zerosection;
//		$this_ce['cs']->course = $rec->courseid;
//		$this_ce['cs']->section = 0;
//		$this_ce['cs']->summary = $tempsummary;
		// email out any errors here since we've been getting some
		$success = update_record('course_sections',$this_ce['cs']);
		if (!$success) {
			echo 'FAILURE IN CREATING COURSE EVALUATION course section insert for ' . $rec->courseid . '-' . $rec->fullname . '<br />';
		} else {
			echo 'Successfully created course evaluation course section insert for ' . $rec->courseid . '-' . $rec->fullname . '<br />';
		}
		// mark ce_course record as completed
		$rec->installdate = $curtime;
		update_record('ce_course',$rec);
	}

function all_rec() {
	global $CFG;
	require_once("$CFG->dirroot/ce/ce_constants.php");
	$curtime = time();

	// get records for ALL_COLLEGE
	$allcoll['q'] = get_record('questionnaire',id,ALL_COLLEGE_Q);
	$all['q']->timemodified = $curtime;
	$cm_select = ' course = ' . CE_SITE_ID . ' AND module = ' . QUESTIONNAIRE_MOD . ' AND instance = ' . ALL_COLLEGE_Q;
	$allcoll['cm'] = get_record_select('course_modules',$cm_select);
	$allcoll['cm']->module = QUESTIONNAIRE_MOD;
	$allcoll['cm']->added = $curtime;
	$cs_select = ' course = ' . CE_SITE_ID . ' AND section = 10 ';
	$allcoll['cs'] = get_record_select('course_sections',$cs_select);
	$allcoll['cs']->visible = 0; // make all new course section invisible
	$allcoll['cs']->sequence = NULL; // make all new course sequences null
	$allcoll['cs']->summary = NULL; // make all new course summaries null
	return $allcoll;
}


function ce_rec($teacher, $term) {
	global $CFG;
	require_once("$CFG->dirroot/ce/ce_constants.php");
	$curtime = time();

	// find out if teacher needs special handling for ATP
	$evaltype = get_field('ce_atp','evaltype', 'term', $term, 'username', $teacher);

	// get records for course eval
	switch ($evaltype) {
		case 'Third Year Review':
			$quest = THIRD_EVAL_Q;
			$surv = THIRD_EVAL_SUR;
			break;
		case 'Tenure/ Promotion':
			$quest = TENURE_EVAL_Q;
			$surv = TENURE_EVAL_SUR;
			break;
		case 'Promotion':
			$quest = PROMOTION_EVAL_Q;
			$surv = PROMOTION_EVAL_SUR;
			break;
		default:
			$quest = COURSE_EVAL_Q;
			$surv = COURSE_EVAL_SUR;
			break;
	}
	$ce['q'] = get_record('questionnaire',id,$quest);
	$ce['q']->timemodified = $curtime;
	$ce['sur'] = get_record('questionnaire_survey',id,$surv);
	$ques_select = ' survey_id = ' . $surv . ' AND deleted = "n" ';
	$ce['ques'] = get_records_select('questionnaire_question',$ques_select);
	foreach($ce['ques'] as $question) {
		$question->choices = get_records('questionnaire_quest_choice','question_id', $question->id);
		sort($question->choices);
	}
	$cm_select = ' course = ' . CE_SITE_ID . ' AND module = ' . QUESTIONNAIRE_MOD . ' AND instance = ' . $quest;
	$ce['cm'] = get_record_select('course_modules',$cm_select);
	$ce['cm']->added = $curtime;
	$ce['cm']->visible = 0;
	$cs_select = ' course = ' . CE_SITE_ID . ' AND section = 0 ';
	$ce['cs'] = get_record_select('course_sections',$cs_select);
	$ce['cs']->visible = 0; // make all new course section invisible
	$ce['cs']->sequence = NULL; // make all new course sequences null
	$ce['cs']->summary = NULL; // make all new course summaries null

	// unset all the ids for all the records gotten so they can be re-used for insertion
	foreach ($allcoll as $coll) {
		unset($coll->id);
	}
	foreach ($ce as $rec) {
		unset($rec->id);
	}
	foreach ($ce['ques'] as $rec) {
		foreach($rec->choices as $choice) {
			unset($choice->id);
		}
		unset($rec->id);
	}
	return $ce;
}
?>