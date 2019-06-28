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

require_once("../../config.php");
require_once($CFG->dirroot . '/mod/pimenkoquestionnaire/pimenkoquestionnaire.class.php');

$id = required_param('id', PARAM_INT);    // Course module ID.
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.
$cancel = optional_param('cancel', '', PARAM_ALPHA);
$submitbutton2 = optional_param('submitbutton2', '', PARAM_ALPHA);

if (!$cm = get_coursemodule_from_id('pimenkoquestionnaire', $id)) {
    print_error('invalidcoursemodule');
}

if (!$course = $DB->get_record("course", ["id" => $cm->course])) {
    print_error('coursemisconf');
}

if (!$pimenkoquestionnaire = $DB->get_record("pimenkoquestionnaire", ["id" => $cm->instance])) {
    print_error('invalidcoursemodule');
}

// Needed here for forced language courses.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url($CFG->wwwroot . '/mod/pimenkoquestionnaire/qsettings.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
if (!isset($SESSION->pimenkoquestionnaire)) {
    $SESSION->pimenkoquestionnaire = new stdClass();
}
$pimenkoquestionnaire = new pimenkoquestionnaire(0, $pimenkoquestionnaire, $course, $cm);

// Add renderer and page objects to the pimenkoquestionnaire object for display use.
$pimenkoquestionnaire->add_renderer($PAGE->get_renderer('mod_pimenkoquestionnaire'));
$pimenkoquestionnaire->add_page(new \mod_pimenkoquestionnaire\output\qsettingspage());

$SESSION->pimenkoquestionnaire->current_tab = 'settings';

if (!$pimenkoquestionnaire->capabilities->manage) {
    print_error('nopermissions', 'error', '', 'mod:pimenkoquestionnaire:manage');
}

$settingsform = new \mod_pimenkoquestionnaire\settings_form('qsettings.php');
$sdata = clone($pimenkoquestionnaire->survey);
$sdata->sid = $pimenkoquestionnaire->survey->id;
$sdata->id = $cm->id;

$draftideditor = file_get_submitted_draft_itemid('info');
$currentinfo = file_prepare_draft_area($draftideditor, $context->id, 'mod_pimenkoquestionnaire', 'info',
        $sdata->sid, ['subdirs' => true], $pimenkoquestionnaire->survey->info);
$sdata->info = ['text' => $currentinfo, 'format' => FORMAT_HTML, 'itemid' => $draftideditor];

$draftideditor = file_get_submitted_draft_itemid('thankbody');
$currentinfo = file_prepare_draft_area($draftideditor, $context->id, 'mod_pimenkoquestionnaire', 'thankbody',
        $sdata->sid, ['subdirs' => true], $pimenkoquestionnaire->survey->thank_body);
$sdata->thank_body = ['text' => $currentinfo, 'format' => FORMAT_HTML, 'itemid' => $draftideditor];

$settingsform->set_data($sdata);

if ($settingsform->is_cancelled()) {
    redirect($CFG->wwwroot . '/mod/pimenkoquestionnaire/view.php?id=' . $pimenkoquestionnaire->cm->id, '');
}

if ($settings = $settingsform->get_data()) {
    $sdata = new stdClass();
    $sdata->id = $settings->sid;
    $sdata->name = $settings->name;
    $sdata->realm = $settings->realm;
    $sdata->title = $settings->title;
    $sdata->subtitle = $settings->subtitle;

    $sdata->infoitemid = $settings->info['itemid'];
    $sdata->infoformat = $settings->info['format'];
    $sdata->info = $settings->info['text'];
    $sdata->info = file_save_draft_area_files($sdata->infoitemid, $context->id, 'mod_pimenkoquestionnaire', 'info',
            $sdata->id, ['subdirs' => true], $sdata->info);

    $sdata->theme = ''; // Deprecated theme field.
    $sdata->thanks_page = $settings->thanks_page;
    $sdata->thank_head = $settings->thank_head;

    $sdata->thankitemid = $settings->thank_body['itemid'];
    $sdata->thankformat = $settings->thank_body['format'];
    $sdata->thank_body = $settings->thank_body['text'];
    $sdata->thank_body = file_save_draft_area_files($sdata->thankitemid, $context->id, 'mod_pimenkoquestionnaire', 'thankbody',
            $sdata->id, ['subdirs' => true], $sdata->thank_body);
    $sdata->email = $settings->email;

    $sdata->courseid = $settings->courseid;
    if (!($sid = $pimenkoquestionnaire->survey_update($sdata))) {
        print_error('couldnotcreatenewsurvey', 'pimenkoquestionnaire');
    } else {
        if ($submitbutton2) {
            $redirecturl = course_get_url($cm->course);
        } else {
            $redirecturl = $CFG->wwwroot . '/mod/pimenkoquestionnaire/view.php?id=' . $pimenkoquestionnaire->cm->id;
        }

        // Save current advanced settings only.
        if (isset($settings->submitbutton) || isset($settings->submitbutton2)) {
            redirect($redirecturl, get_string('settingssaved', 'pimenkoquestionnaire'));
        }
    }
}

// Print the page header.
$PAGE->set_title(get_string('editingpimenkoquestionnaire', 'pimenkoquestionnaire'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('editingpimenkoquestionnaire', 'pimenkoquestionnaire'));
echo $pimenkoquestionnaire->renderer->header();
require('tabs.php');
$pimenkoquestionnaire->page->add_to_page('formarea', $settingsform->render());
echo $pimenkoquestionnaire->renderer->render($pimenkoquestionnaire->page);
echo $pimenkoquestionnaire->renderer->footer($course);
