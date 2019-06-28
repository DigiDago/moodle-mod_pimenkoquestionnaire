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

// This page displays a non-completable instance of pimenkoquestionnaire.

require_once("../../config.php");
require_once($CFG->dirroot . '/mod/pimenkoquestionnaire/pimenkoquestionnaire.class.php');

$id = optional_param('id', 0, PARAM_INT);
$sid = optional_param('sid', 0, PARAM_INT);
$popup = optional_param('popup', 0, PARAM_INT);
$qid = optional_param('qid', 0, PARAM_INT);
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.

if ($id) {
    if (!$cm = get_coursemodule_from_id('pimenkoquestionnaire', $id)) {
        print_error('invalidcoursemodule');
    }

    if (!$course = $DB->get_record("course", ["id" => $cm->course])) {
        print_error('coursemisconf');
    }

    if (!$pimenkoquestionnaire = $DB->get_record("pimenkoquestionnaire", ["id" => $cm->instance])) {
        print_error('invalidcoursemodule');
    }
} else {
    if (!$survey = $DB->get_record("pimenkoquestionnaire_survey", ["id" => $sid])) {
        print_error('surveynotexists', 'pimenkoquestionnaire');
    }
    if (!$course = $DB->get_record("course", ["id" => $survey->courseid])) {
        print_error('coursemisconf');
    }
    // Dummy pimenkoquestionnaire object.
    $pimenkoquestionnaire = new stdClass();
    $pimenkoquestionnaire->id = 0;
    $pimenkoquestionnaire->course = $course->id;
    $pimenkoquestionnaire->name = $survey->title;
    $pimenkoquestionnaire->sid = $sid;
    $pimenkoquestionnaire->resume = 0;
    // Dummy cm object.
    if (!empty($qid)) {
        $cm = get_coursemodule_from_instance('pimenkoquestionnaire', $qid, $course->id);
    } else {
        $cm = false;
    }
}

// Check login and get context.
// Do not require login if this pimenkoquestionnaire is viewed from the Add pimenkoquestionnaire page
// to enable teachers to view template or public pimenkoquestionnaires located in a course where they are not enroled.
if (!$popup) {
    require_login($course->id, false, $cm);
}
$context = $cm ? context_module::instance($cm->id) : false;

$url = new moodle_url('/mod/pimenkoquestionnaire/preview.php');
if ($id !== 0) {
    $url->param('id', $id);
}
if ($sid) {
    $url->param('sid', $sid);
}
$PAGE->set_url($url);

$PAGE->set_context($context);
$PAGE->set_cm($cm);   // CONTRIB-5872 - I don't know why this is needed.

$pimenkoquestionnaire = new pimenkoquestionnaire($qid, $pimenkoquestionnaire, $course, $cm);

// Add renderer and page objects to the pimenkoquestionnaire object for display use.
$pimenkoquestionnaire->add_renderer($PAGE->get_renderer('mod_pimenkoquestionnaire'));
$pimenkoquestionnaire->add_page(new \mod_pimenkoquestionnaire\output\previewpage());

$canpreview = (!isset($pimenkoquestionnaire->capabilities) &&
                has_capability('mod/pimenkoquestionnaire:preview', context_course::instance($course->id))) ||
        (isset($pimenkoquestionnaire->capabilities) && $pimenkoquestionnaire->capabilities->preview);
if (!$canpreview && !$popup) {
    // Should never happen, unless called directly by a snoop...
    print_error('nopermissions', 'pimenkoquestionnaire', $CFG->wwwroot . '/mod/pimenkoquestionnaire/view.php?id=' . $cm->id);
}

if (!isset($SESSION->pimenkoquestionnaire)) {
    $SESSION->pimenkoquestionnaire = new stdClass();
}
$SESSION->pimenkoquestionnaire->current_tab = new stdClass();
$SESSION->pimenkoquestionnaire->current_tab = 'preview';

$qp = get_string('preview_pimenkoquestionnaire', 'pimenkoquestionnaire');
$pq = get_string('previewing', 'pimenkoquestionnaire');

// Print the page header.
if ($popup) {
    $PAGE->set_pagelayout('popup');
}
$PAGE->set_title(format_string($qp));
if (!$popup) {
    $PAGE->set_heading(format_string($course->fullname));
}

// Include the needed js.

$PAGE->requires->js('/mod/pimenkoquestionnaire/module.js');
// Print the tabs.

echo $pimenkoquestionnaire->renderer->header();
if (!$popup) {
    require('tabs.php');
}
$pimenkoquestionnaire->page->add_to_page('heading', clean_text($pq));

if ($pimenkoquestionnaire->capabilities->printblank) {
    // Open print friendly as popup window.

    $linkname = '&nbsp;' . get_string('printblank', 'pimenkoquestionnaire');
    $title = get_string('printblanktooltip', 'pimenkoquestionnaire');
    $url = '/mod/pimenkoquestionnaire/print.php?qid=' . $pimenkoquestionnaire->id . '&amp;rid=0&amp;' . 'courseid=' .
            $pimenkoquestionnaire->course->id . '&amp;sec=1';
    $options = ['menubar' => true, 'location' => false, 'scrollbars' => true, 'resizable' => true,
            'height' => 600, 'width' => 800, 'title' => $title];
    $name = 'popup';
    $link = new moodle_url($url);
    $action = new popup_action('click', $link, $name, $options);
    $class = "floatprinticon";
    $pimenkoquestionnaire->page->add_to_page('printblank',
            $pimenkoquestionnaire->renderer->action_link($link, $linkname, $action, ['class' => $class, 'title' => $title],
                    new pix_icon('t/print', $title)));
}
$pimenkoquestionnaire->survey_print_render('', 'preview', $course->id, $rid = 0, $popup);
if ($popup) {
    $pimenkoquestionnaire->page->add_to_page('closebutton', $pimenkoquestionnaire->renderer->close_window_button());
}
echo $pimenkoquestionnaire->renderer->render($pimenkoquestionnaire->page);
echo $pimenkoquestionnaire->renderer->footer($course);

// Log this pimenkoquestionnaire preview.
$context = context_module::instance($pimenkoquestionnaire->cm->id);
$anonymous = $pimenkoquestionnaire->respondenttype == 'anonymous';

$event = \mod_pimenkoquestionnaire\event\pimenkoquestionnaire_previewed::create([
        'objectid' => $pimenkoquestionnaire->id,
        'anonymous' => $anonymous,
        'context' => $context
]);
$event->trigger();
