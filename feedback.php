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

// This page prints a particular instance of pimenkoquestionnaire.

/**
 * Manage feedback settings.
 *
 * @package mod_pimenkoquestionnaire
 * @copyright  2016 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author Joseph Rezeau
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/pimenkoquestionnaire/pimenkoquestionnaire.class.php');

$id = required_param('id', PARAM_INT);    // Course module ID.
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.
$action = optional_param('action', '', PARAM_ALPHA);

if (! $cm = get_coursemodule_from_id('pimenkoquestionnaire', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", ["id" => $cm->course])) {
    print_error('coursemisconf');
}

if (! $pimenkoquestionnaire = $DB->get_record("pimenkoquestionnaire", ["id" => $cm->instance])) {
    print_error('invalidcoursemodule');
}

// Needed here for forced language courses.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

$PAGE->set_url(new moodle_url($CFG->wwwroot.'/mod/pimenkoquestionnaire/feedback.php', ['id' => $id]));
$PAGE->set_context($context);
if (!isset($SESSION->pimenkoquestionnaire)) {
    $SESSION->pimenkoquestionnaire = new stdClass();
}
$pimenkoquestionnaire = new pimenkoquestionnaire(0, $pimenkoquestionnaire, $course, $cm);

// Add renderer and page objects to the pimenkoquestionnaire object for display use.
$pimenkoquestionnaire->add_renderer($PAGE->get_renderer('mod_pimenkoquestionnaire'));
$pimenkoquestionnaire->add_page(new \mod_pimenkoquestionnaire\output\feedbackpage());

$SESSION->pimenkoquestionnaire->current_tab = 'feedback';

if (!$pimenkoquestionnaire->capabilities->editquestions) {
    print_error('nopermissions', 'error', '', 'mod:pimenkoquestionnaire:editquestions');
}

$feedbackform = new \mod_pimenkoquestionnaire\feedback_form('feedback.php');
$sdata = clone($pimenkoquestionnaire->survey);
$sdata->sid = $pimenkoquestionnaire->survey->id;
$sdata->id = $cm->id;

$draftideditor = file_get_submitted_draft_itemid('feedbacknotes');
$currentinfo = file_prepare_draft_area($draftideditor, $context->id, 'mod_pimenkoquestionnaire', 'feedbacknotes',
    $sdata->sid, ['subdirs' => true], $pimenkoquestionnaire->survey->feedbacknotes);
$sdata->feedbacknotes = ['text' => $currentinfo, 'format' => FORMAT_HTML, 'itemid' => $draftideditor];

$feedbackform->set_data($sdata);

if ($feedbackform->is_cancelled()) {
    redirect(new moodle_url('/mod/pimenkoquestionnaire/view.php', ['id' => $pimenkoquestionnaire->cm->id]));
}
// Confirm that feedback can be used for this pimenkoquestionnaire...
// Get all questions that are valid feedback questions.
$validquestions = false;
foreach ($pimenkoquestionnaire->questions as $question) {
    if ($question->valid_feedback()) {
        $validquestions = true;
        break;
    }
}

if ($settings = $feedbackform->get_data()) {
    if (isset($settings->feedbacksettingsbutton1) || isset($settings->buttongroup)) {
        if (isset ($settings->feedbackscores)) {
            $sdata->feedbackscores = $settings->feedbackscores;
        } else {
            $sdata->feedbackscores = 0;
        }

        if (isset ($settings->feedbacknotes)) {
            $sdata->fbnotesitemid = $settings->feedbacknotes['itemid'];
            $sdata->fbnotesformat = $settings->feedbacknotes['format'];
            $sdata->feedbacknotes = $settings->feedbacknotes['text'];
            $sdata->feedbacknotes = file_save_draft_area_files($sdata->fbnotesitemid, $context->id, 'mod_pimenkoquestionnaire',
                'feedbacknotes', $sdata->id, ['subdirs' => true], $sdata->feedbacknotes);
        } else {
            $sdata->feedbacknotes = '';
        }

        if ($settings->feedbacksections > 0) {
            $sdata->feedbacksections = $settings->feedbacksections;
            $usergraph = get_config('pimenkoquestionnaire', 'usergraph');
            if ($usergraph) {
                if ($settings->feedbacksections == 1) {
                    $sdata->chart_type = $settings->chart_type_global;
                } else if ($settings->feedbacksections == 2) {
                    $sdata->chart_type = $settings->chart_type_two_sections;
                } else if ($settings->feedbacksections > 2) {
                    $sdata->chart_type = $settings->chart_type_sections;
                }
            }
        } else {
            $sdata->feedbacksections = 0;
        }
        $sdata->courseid = $settings->courseid;
        if (!($sid = $pimenkoquestionnaire->survey_update($sdata))) {
            print_error('couldnotcreatenewsurvey', 'pimenkoquestionnaire');
        }
    }

    // Handle the edit feedback sections action.
    if (isset($settings->buttongroup['feedbackeditbutton'])) {
        // Create a single section for Global Feedback if not existent.
        if (!($firstsection = $DB->get_field('pimenko_fb_sections', 'MIN(section)', ['surveyid' => $pimenkoquestionnaire->sid]))) {
            $firstsection = 0;
        }
        if (($sdata->feedbacksections > 0) && ($firstsection == 0)) {
            if ($sdata->feedbacksections == 1) {
                $sectionlabel = get_string('feedbackglobal', 'pimenkoquestionnaire');
            } else {
                $sectionlabel = get_string('feedbackdefaultlabel', 'pimenkoquestionnaire');
            }
            $feedbacksection = mod_pimenkoquestionnaire\feedback\section::new_section($pimenkoquestionnaire->sid, $sectionlabel);
        }
        redirect(new moodle_url('/mod/pimenkoquestionnaire/fbsections.php', ['id' => $cm->id, 'section' => $firstsection]));
    }
}

// Print the page header.
$PAGE->set_title(get_string('editingfeedback', 'pimenkoquestionnaire'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('editingfeedback', 'pimenkoquestionnaire'));
echo $pimenkoquestionnaire->renderer->header();
require('tabs.php');
if (!$validquestions) {
    $pimenkoquestionnaire->page->add_to_page('formarea', get_string('feedbackoptions_help', 'pimenkoquestionnaire'));
} else {
    $pimenkoquestionnaire->page->add_to_page('formarea', $feedbackform->render());
}
echo $pimenkoquestionnaire->renderer->render($pimenkoquestionnaire->page);
echo $pimenkoquestionnaire->renderer->footer($course);
