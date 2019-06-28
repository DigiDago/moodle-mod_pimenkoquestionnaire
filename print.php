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
require_once($CFG->dirroot . '/mod/pimenkoquestionnaire/pimenkoquestionnaire.class.php');

$qid = required_param('qid', PARAM_INT);
$rid = required_param('rid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$sec = required_param('sec', PARAM_INT);
$null = null;
$referer = $CFG->wwwroot . '/mod/pimenkoquestionnaire/report.php';

if (!$pimenkoquestionnaire = $DB->get_record("pimenkoquestionnaire", ["id" => $qid])) {
    print_error('invalidcoursemodule');
}
if (!$course = $DB->get_record("course", ["id" => $pimenkoquestionnaire->course])) {
    print_error('coursemisconf');
}
if (!$cm = get_coursemodule_from_instance("pimenkoquestionnaire", $pimenkoquestionnaire->id, $course->id)) {
    print_error('invalidcoursemodule');
}

// Check login and get context.
require_login($courseid);

$pimenkoquestionnaire = new pimenkoquestionnaire(0, $pimenkoquestionnaire, $course, $cm);

// Add renderer and page objects to the pimenkoquestionnaire object for display use.
$pimenkoquestionnaire->add_renderer($PAGE->get_renderer('mod_pimenkoquestionnaire'));
if (!empty($rid)) {
    $pimenkoquestionnaire->add_page(new \mod_pimenkoquestionnaire\output\reportpage());
} else {
    $pimenkoquestionnaire->add_page(new \mod_pimenkoquestionnaire\output\previewpage());
}

// If you can't view the pimenkoquestionnaire, or can't view a specified response, error out.
if (!($pimenkoquestionnaire->capabilities->view && (($rid == 0) || $pimenkoquestionnaire->can_view_response($rid)))) {
    // Should never happen, unless called directly by a snoop...
    print_error('nopermissions', 'moodle', $CFG->wwwroot . '/mod/pimenkoquestionnaire/view.php?id=' . $cm->id);
}
$blankpimenkoquestionnaire = true;
if ($rid != 0) {
    $blankpimenkoquestionnaire = false;
}
$url = new moodle_url($CFG->wwwroot . '/mod/pimenkoquestionnaire/print.php');
$url->param('qid', $qid);
$url->param('rid', $rid);
$url->param('courseid', $courseid);
$url->param('sec', $sec);
$PAGE->set_url($url);
$PAGE->set_title($pimenkoquestionnaire->survey->title);
$PAGE->set_pagelayout('popup');
echo $pimenkoquestionnaire->renderer->header();
$pimenkoquestionnaire->page->add_to_page('closebutton', $pimenkoquestionnaire->renderer->close_window_button());
$pimenkoquestionnaire->survey_print_render('', 'print', $courseid, $rid, $blankpimenkoquestionnaire);
echo $pimenkoquestionnaire->renderer->render($pimenkoquestionnaire->page);
echo $pimenkoquestionnaire->renderer->footer();
