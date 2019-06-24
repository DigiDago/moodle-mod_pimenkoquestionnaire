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

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/pimenkoquestionnaire/pimenkoquestionnaire.class.php');
require_once($CFG->dirroot.'/mod/pimenkoquestionnaire/classes/question/base.php'); // Needed for question type constants.

$id     = required_param('id', PARAM_INT);                 // Course module ID
$action = optional_param('action', 'main', PARAM_ALPHA);   // Screen.
$qid    = optional_param('qid', 0, PARAM_INT);             // Question id.
$moveq  = optional_param('moveq', 0, PARAM_INT);           // Question id to move.
$delq   = optional_param('delq', 0, PARAM_INT);             // Question id to delete
$qtype  = optional_param('type_id', 0, PARAM_INT);         // Question type.
$currentgroupid = optional_param('group', 0, PARAM_INT); // Group id.

if (! $cm = get_coursemodule_from_id('pimenkoquestionnaire', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error('coursemisconf');
}

if (! $pimenkoquestionnaire = $DB->get_record("pimenkoquestionnaire", array("id" => $cm->instance))) {
    print_error('invalidcoursemodule');
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url($CFG->wwwroot.'/mod/pimenkoquestionnaire/questions.php');
$url->param('id', $id);
if ($qid) {
    $url->param('qid', $qid);
}

$PAGE->set_url($url);
$PAGE->set_context($context);

$pimenkoquestionnaire = new pimenkoquestionnaire(0, $pimenkoquestionnaire, $course, $cm);

// Add renderer and page objects to the pimenkoquestionnaire object for display use.
$pimenkoquestionnaire->add_renderer($PAGE->get_renderer('mod_pimenkoquestionnaire'));
$pimenkoquestionnaire->add_page(new \mod_pimenkoquestionnaire\output\questionspage());

if (!$pimenkoquestionnaire->capabilities->editquestions) {
    print_error('nopermissions', 'error', '', 'mod:pimenkoquestionnaire:edit');
}

$pimenkoquestionnairehasdependencies = $pimenkoquestionnaire->has_dependencies();
$haschildren = [];
if (!isset($SESSION->pimenkoquestionnaire)) {
    $SESSION->pimenkoquestionnaire = new stdClass();
}
$SESSION->pimenkoquestionnaire->current_tab = 'questions';
$reload = false;
$sid = $pimenkoquestionnaire->survey->id;
// Process form data.

// Delete question button has been pressed in questions_form AND deletion has been confirmed on the confirmation page.
if ($delq) {
    $qid = $delq;
    $sid = $pimenkoquestionnaire->survey->id;
    $pimenkoquestionnaireid = $pimenkoquestionnaire->id;

    // Need to reload questions before setting deleted question to 'y'.
    $questions = $DB->get_records('pimenko_question', ['surveyid' => $sid, 'deleted' => 'n'], 'id');
    $DB->set_field('pimenko_question', 'deleted', 'y', ['id' => $qid, 'surveyid' => $sid]);

    // Delete all dependency records for this question.
    pimenkoquestionnaire_delete_dependencies($qid);

    // Just in case the page is refreshed (F5) after a question has been deleted.
    if (isset($questions[$qid])) {
        $select = 'surveyid = '.$sid.' AND deleted = \'n\' AND position > '.
                        $questions[$qid]->position;
    } else {
        redirect($CFG->wwwroot.'/mod/pimenkoquestionnaire/questions.php?id='.$pimenkoquestionnaire->cm->id);
    }

    if ($records = $DB->get_records_select('pimenko_question', $select, null, 'position ASC')) {
        foreach ($records as $record) {
            $DB->set_field('pimenko_question', 'position', $record->position - 1, array('id' => $record->id));
        }
    }
    // Delete section breaks without asking for confirmation.
    // No need to delete responses to those "question types" which are not real questions.
    if (!$pimenkoquestionnaire->questions[$qid]->supports_responses()) {
        $reload = true;
    } else {
        // Delete responses to that deleted question.
        pimenkoquestionnaire_delete_responses($qid);

        // If no questions left in this pimenkoquestionnaire, remove all responses.
        if ($DB->count_records('pimenko_question', ['surveyid' => $sid, 'deleted' => 'n']) == 0) {
            $DB->delete_records('pimenko_response', ['pimenkoquestionnaireid' => $qid]);
        }
    }

    // Log question deleted event.
    $context = context_module::instance($pimenkoquestionnaire->cm->id);
    $questiontype = \mod_pimenkoquestionnaire\question\base::qtypename($pimenkoquestionnaire->questions[$qid]->type_id);
    $params = array(
                    'context' => $context,
                    'courseid' => $pimenkoquestionnaire->course->id,
                    'other' => array('questiontype' => $questiontype)
    );
    $event = \mod_pimenkoquestionnaire\event\question_deleted::create($params);
    $event->trigger();

    if ($pimenkoquestionnairehasdependencies) {
        $SESSION->pimenkoquestionnaire->validateresults = pimenkoquestionnaire_check_page_breaks($pimenkoquestionnaire);
    }
    $reload = true;
}

if ($action == 'main') {
    $questionsform = new \mod_pimenkoquestionnaire\questions_form('questions.php', $moveq);
    $sdata = clone($pimenkoquestionnaire->survey);
    $sdata->sid = $pimenkoquestionnaire->survey->id;
    $sdata->id = $cm->id;
    if (!empty($pimenkoquestionnaire->questions)) {
        $pos = 1;
        foreach ($pimenkoquestionnaire->questions as $qidx => $question) {
            $sdata->{'pos_'.$qidx} = $pos;
            $pos++;
        }
    }
    $questionsform->set_data($sdata);
    if ($questionsform->is_cancelled()) {
        // Switch to main screen.
        $action = 'main';
        redirect($CFG->wwwroot.'/mod/pimenkoquestionnaire/questions.php?id='.$pimenkoquestionnaire->cm->id);
        $reload = true;
    }
    if ($qformdata = $questionsform->get_data()) {
        // Quickforms doesn't return values for 'image' input types using 'exportValue', so we need to grab
        // it from the raw submitted data.
        $exformdata = data_submitted();

        if (isset($exformdata->movebutton)) {
            $qformdata->movebutton = $exformdata->movebutton;
        } else if (isset($exformdata->moveherebutton)) {
            $qformdata->moveherebutton = $exformdata->moveherebutton;
        } else if (isset($exformdata->editbutton)) {
            $qformdata->editbutton = $exformdata->editbutton;
        } else if (isset($exformdata->removebutton)) {
            $qformdata->removebutton = $exformdata->removebutton;
        } else if (isset($exformdata->requiredbutton)) {
            $qformdata->requiredbutton = $exformdata->requiredbutton;
        }

        // Insert a section break.
        if (isset($qformdata->removebutton)) {
            // Need to use the key, since IE returns the image position as the value rather than the specified
            // value in the <input> tag.
            $qid = key($qformdata->removebutton);
            $qtype = $pimenkoquestionnaire->questions[$qid]->type_id;

            // Delete section breaks without asking for confirmation.
            if ($qtype == QUESPAGEBREAK) {
                redirect($CFG->wwwroot.'/mod/pimenkoquestionnaire/questions.php?id='.$pimenkoquestionnaire->cm->id.'&amp;delq='.$qid);
            }
            if ($pimenkoquestionnairehasdependencies) {
                // Important: due to possibly multiple parents per question
                // just remove the dependency and inform the user about it.
                $haschildren = $pimenkoquestionnaire->get_all_dependants($qid);
            }
            if (count($haschildren) != 0) {
                $action = "confirmdelquestionparent";
            } else {
                $action = "confirmdelquestion";
            }

        } else if (isset($qformdata->editbutton)) {
            // Switch to edit question screen.
            $action = 'question';
            // Need to use the key, since IE returns the image position as the value rather than the specified
            // value in the <input> tag.
            $qid = key($qformdata->editbutton);
            $reload = true;

        } else if (isset($qformdata->requiredbutton)) {
            // Need to use the key, since IE returns the image position as the value rather than the specified
            // value in the <input> tag.

            $qid = key($qformdata->requiredbutton);
            if ($pimenkoquestionnaire->questions[$qid]->required()) {
                $pimenkoquestionnaire->questions[$qid]->set_required(false);

            } else {
                $pimenkoquestionnaire->questions[$qid]->set_required(true);
            }

            $reload = true;

        } else if (isset($qformdata->addqbutton)) {
            if ($qformdata->type_id == QUESPAGEBREAK) { // Adding section break is handled right away....
                $questionrec = new stdClass();
                $questionrec->surveyid = $qformdata->sid;
                $questionrec->type_id = QUESPAGEBREAK;
                $questionrec->content = 'break';
                $question = \mod_pimenkoquestionnaire\question\base::question_builder(QUESPAGEBREAK);
                $question->add($questionrec);
                $reload = true;
            } else {
                // Switch to edit question screen.
                $action = 'question';
                $qtype = $qformdata->type_id;
                $qid = 0;
                $reload = true;
            }

        } else if (isset($qformdata->movebutton)) {
            // Nothing I do will seem to reload the form with new data, except for moving away from the page, so...
            redirect($CFG->wwwroot.'/mod/pimenkoquestionnaire/questions.php?id='.$pimenkoquestionnaire->cm->id.
                     '&moveq='.key($qformdata->movebutton));
            $reload = true;



        } else if (isset($qformdata->moveherebutton)) {
            // Need to use the key, since IE returns the image position as the value rather than the specified
            // value in the <input> tag.

            // No need to move question if new position = old position!
            $qpos = key($qformdata->moveherebutton);
            if ($qformdata->moveq != $qpos) {
                $pimenkoquestionnaire->move_question($qformdata->moveq, $qpos);
            }
            if ($pimenkoquestionnairehasdependencies) {
                $SESSION->pimenkoquestionnaire->validateresults = pimenkoquestionnaire_check_page_breaks($pimenkoquestionnaire);
            }
            // Nothing I do will seem to reload the form with new data, except for moving away from the page, so...
            redirect($CFG->wwwroot.'/mod/pimenkoquestionnaire/questions.php?id='.$pimenkoquestionnaire->cm->id);
            $reload = true;

        } else if (isset($qformdata->validate)) {
            // Validates page breaks for depend questions.
            $SESSION->pimenkoquestionnaire->validateresults = pimenkoquestionnaire_check_page_breaks($pimenkoquestionnaire);
            $reload = true;
        }
    }

} else if ($action == 'question') {
    $question = pimenkoquestionnaire_prep_for_questionform($pimenkoquestionnaire, $qid, $qtype);
    $questionsform = new \mod_pimenkoquestionnaire\edit_question_form('questions.php');
    $questionsform->set_data($question);
    if ($questionsform->is_cancelled()) {
        // Switch to main screen.
        $action = 'main';
        $reload = true;

    } else if ($qformdata = $questionsform->get_data()) {
        // Saving question data.
        if (isset($qformdata->makecopy)) {
            $qformdata->qid = 0;
        }

        $question->form_update($qformdata, $pimenkoquestionnaire);

        // Make these field values 'sticky' for further new questions.
        if (!isset($qformdata->required)) {
            $qformdata->required = 'n';
        }

        pimenkoquestionnaire_check_page_breaks($pimenkoquestionnaire);
        $SESSION->pimenkoquestionnaire->required = $qformdata->required;
        $SESSION->pimenkoquestionnaire->type_id = $qformdata->type_id;
        // Switch to main screen.
        $action = 'main';
        $reload = true;
    }

    // Log question created event.
    if (isset($qformdata)) {
        $context = context_module::instance($pimenkoquestionnaire->cm->id);
        $questiontype = \mod_pimenkoquestionnaire\question\base::qtypename($qformdata->type_id);
        $params = array(
                        'context' => $context,
                        'courseid' => $pimenkoquestionnaire->course->id,
                        'other' => array('questiontype' => $questiontype)
        );
        $event = \mod_pimenkoquestionnaire\event\question_created::create($params);
        $event->trigger();
    }

    $questionsform->set_data($question);
}

// Reload the form data if called for...
if ($reload) {
    unset($questionsform);
    $pimenkoquestionnaire = new pimenkoquestionnaire($pimenkoquestionnaire->id, null, $course, $cm);
    // Add renderer and page objects to the pimenkoquestionnaire object for display use.
    $pimenkoquestionnaire->add_renderer($PAGE->get_renderer('mod_pimenkoquestionnaire'));
    $pimenkoquestionnaire->add_page(new \mod_pimenkoquestionnaire\output\questionspage());
    if ($action == 'main') {
        $questionsform = new \mod_pimenkoquestionnaire\questions_form('questions.php', $moveq);
        $sdata = clone($pimenkoquestionnaire->survey);
        $sdata->sid = $pimenkoquestionnaire->survey->id;
        $sdata->id = $cm->id;
        if (!empty($pimenkoquestionnaire->questions)) {
            $pos = 1;
            foreach ($pimenkoquestionnaire->questions as $qidx => $question) {
                $sdata->{'pos_'.$qidx} = $pos;
                $pos++;
            }
        }
        $questionsform->set_data($sdata);
    } else if ($action == 'question') {
        $question = pimenkoquestionnaire_prep_for_questionform($pimenkoquestionnaire, $qid, $qtype);
        $questionsform = new \mod_pimenkoquestionnaire\edit_question_form('questions.php');
        $questionsform->set_data($question);
    }
}

// Print the page header.
if ($action == 'question') {
    if (isset($question->qid)) {
        $streditquestion = get_string('editquestion', 'pimenkoquestionnaire', pimenkoquestionnaire_get_type($question->type_id));
    } else {
        $streditquestion = get_string('addnewquestion', 'pimenkoquestionnaire', pimenkoquestionnaire_get_type($question->type_id));
    }
} else {
    $streditquestion = get_string('managequestions', 'pimenkoquestionnaire');
}

$PAGE->set_title($streditquestion);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add($streditquestion);
echo $pimenkoquestionnaire->renderer->header();
require('tabs.php');

if ($action == "confirmdelquestion" || $action == "confirmdelquestionparent") {

    $qid = key($qformdata->removebutton);
    $question = $pimenkoquestionnaire->questions[$qid];
    $qtype = $question->type_id;

    // Count responses already saved for that question.
    $countresps = 0;
    if ($qtype != QUESSECTIONTEXT) {
        $responsetable = $DB->get_field('pimenko_question_type', 'response_table', array('typeid' => $qtype));
        if (!empty($responsetable)) {
            $countresps = $DB->count_records('pimenko_'.$responsetable, array('question_id' => $qid));
        }
    }

    // Needed to print potential media in question text.

    // If question text is "empty", i.e. 2 non-breaking spaces were inserted, do not display any question text.

    if ($question->content == '<p>  </p>') {
        $question->content = '';
    }

    $qname = '';
    if ($question->name) {
        $qname = ' ('.$question->name.')';
    }

    $num = get_string('position', 'pimenkoquestionnaire');
    $pos = $question->position.$qname;

    $msg = '<div class="warning centerpara"><p>'.get_string('confirmdelquestion', 'pimenkoquestionnaire', $pos).'</p>';
    if ($countresps !== 0) {
        $msg .= '<p>'.get_string('confirmdelquestionresps', 'pimenkoquestionnaire', $countresps).'</p>';
    }
    $msg .= '</div>';
    $msg .= '<div class = "qn-container">'.$num.' '.$pos.'<div class="qn-question">'.$question->content.'</div></div>';
    $args = "id={$pimenkoquestionnaire->cm->id}";
    $urlno = new moodle_url("/mod/pimenkoquestionnaire/questions.php?{$args}");
    $args .= "&delq={$qid}";
    $urlyes = new moodle_url("/mod/pimenkoquestionnaire/questions.php?{$args}");
    $buttonyes = new single_button($urlyes, get_string('yes'));
    $buttonno = new single_button($urlno, get_string('no'));
    if ($action == "confirmdelquestionparent") {
        $strnum = get_string('position', 'pimenkoquestionnaire');
        $qid = key($qformdata->removebutton);
        // Show the dependencies and inform about the dependencies to be removed.
        // Split dependencies in direct and indirect ones to separate for the confirm-dialogue. Only direct ones will be deleted.
        // List direct dependencies.
        $msg .= $pimenkoquestionnaire->renderer->dependency_warnings($haschildren->directs, 'directwarnings', $strnum);
        // List indirect dependencies.
        $msg .= $pimenkoquestionnaire->renderer->dependency_warnings($haschildren->indirects, 'indirectwarnings', $strnum);
    }
    $pimenkoquestionnaire->page->add_to_page('formarea', $pimenkoquestionnaire->renderer->confirm($msg, $buttonyes, $buttonno));

} else {
    $pimenkoquestionnaire->page->add_to_page('formarea', $questionsform->render());
}
echo $pimenkoquestionnaire->renderer->render($pimenkoquestionnaire->page);
echo $pimenkoquestionnaire->renderer->footer();