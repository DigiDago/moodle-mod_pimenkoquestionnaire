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
require_once($CFG->dirroot . '/mod/pimenkoquestionnaire/locallib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/mod/pimenkoquestionnaire/pimenkoquestionnaire.class.php');

if (!isset($SESSION->pimenkoquestionnaire)) {
    $SESSION->pimenkoquestionnaire = new stdClass();
}
$SESSION->pimenkoquestionnaire->current_tab = 'view';

$id = optional_param('id', null, PARAM_INT);    // Course Module ID.
$a = optional_param('a', null, PARAM_INT);      // Or pimenkoquestionnaire ID.

$sid = optional_param('sid', null, PARAM_INT);  // Survey id.

list($cm, $course, $pimenkoquestionnaire) = pimenkoquestionnaire_get_standard_page_items($id, $a);

// Check login and get context.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url($CFG->wwwroot . '/mod/pimenkoquestionnaire/view.php');
if (isset($id)) {
    $url->param('id', $id);
} else {
    $url->param('a', $a);
}
if (isset($sid)) {
    $url->param('sid', $sid);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$pimenkoquestionnaire = new pimenkoquestionnaire(0, $pimenkoquestionnaire, $course, $cm);
// Add renderer and page objects to the pimenkoquestionnaire object for display use.
$pimenkoquestionnaire->add_renderer($PAGE->get_renderer('mod_pimenkoquestionnaire'));
$pimenkoquestionnaire->add_page(new \mod_pimenkoquestionnaire\output\viewpage());

$PAGE->set_title(format_string($pimenkoquestionnaire->name));
$PAGE->set_heading(format_string($course->fullname));

echo $pimenkoquestionnaire->renderer->header();
$pimenkoquestionnaire->page->add_to_page('pimenkoquestionnairename', format_string($pimenkoquestionnaire->name));

// Print the main part of the page.
if ($pimenkoquestionnaire->intro) {
    $pimenkoquestionnaire->page->add_to_page('intro', format_module_intro('pimenkoquestionnaire', $pimenkoquestionnaire, $cm->id));
}

$cm = $pimenkoquestionnaire->cm;
$currentgroupid = groups_get_activity_group($cm);
if (!groups_is_member($currentgroupid, $USER->id)) {
    $currentgroupid = 0;
}

if (!$pimenkoquestionnaire->is_active()) {
    if ($pimenkoquestionnaire->capabilities->manage) {
        $msg = 'removenotinuse';
    } else {
        $msg = 'notavail';
    }
    $pimenkoquestionnaire->page->add_to_page('message', get_string($msg, 'pimenkoquestionnaire'));

} else if ($pimenkoquestionnaire->survey->realm == 'template') {
    // If this is a template survey, notify and exit.
    $pimenkoquestionnaire->page->add_to_page('message', get_string('templatenotviewable', 'pimenkoquestionnaire'));
    echo $pimenkoquestionnaire->renderer->render($pimenkoquestionnaire->page);
    echo $pimenkoquestionnaire->renderer->footer($pimenkoquestionnaire->course);
    exit();

} else if (!$pimenkoquestionnaire->is_open()) {
    $pimenkoquestionnaire->page->add_to_page('message',
            get_string('notopen', 'pimenkoquestionnaire', userdate($pimenkoquestionnaire->opendate)));

} else if ($pimenkoquestionnaire->is_closed()) {
    $pimenkoquestionnaire->page->add_to_page('message',
            get_string('closed', 'pimenkoquestionnaire', userdate($pimenkoquestionnaire->closedate)));

} else if (!$pimenkoquestionnaire->user_is_eligible($USER->id)) {
    if ($pimenkoquestionnaire->questions) {
        $pimenkoquestionnaire->page->add_to_page('message', get_string('noteligible', 'pimenkoquestionnaire'));
    }

} else if (!$pimenkoquestionnaire->user_can_take($USER->id)) {
    switch ($pimenkoquestionnaire->qtype) {
        case QUESTIONNAIREDAILY:
            $msgstring = ' ' . get_string('today', 'pimenkoquestionnaire');
            break;
        case QUESTIONNAIREWEEKLY:
            $msgstring = ' ' . get_string('thisweek', 'pimenkoquestionnaire');
            break;
        case QUESTIONNAIREMONTHLY:
            $msgstring = ' ' . get_string('thismonth', 'pimenkoquestionnaire');
            break;
        default:
            $msgstring = '';
            break;
    }
    $pimenkoquestionnaire->page->add_to_page('message', get_string("alreadyfilled", "pimenkoquestionnaire", $msgstring));

} else if ($pimenkoquestionnaire->user_can_take($USER->id)) {
    if ($pimenkoquestionnaire->questions) { // Sanity check.
        if (!$pimenkoquestionnaire->user_has_saved_response($USER->id)) {
            $pimenkoquestionnaire->page->add_to_page('complete',
                    '<a href="' . $CFG->wwwroot . htmlspecialchars('/mod/pimenkoquestionnaire/complete.php?' .
                            'id=' . $pimenkoquestionnaire->cm->id) . '">' . get_string('answerquestions', 'pimenkoquestionnaire') .
                    '</a>');
        } else {
            $resumesurvey = get_string('resumesurvey', 'pimenkoquestionnaire');
            $pimenkoquestionnaire->page->add_to_page('complete',
                    '<a href="' . $CFG->wwwroot . htmlspecialchars('/mod/pimenkoquestionnaire/complete.php?' .
                            'id=' . $pimenkoquestionnaire->cm->id . '&resume=1') . '" title="' . $resumesurvey . '">' .
                    $resumesurvey . '</a>');
        }
    } else {
        $pimenkoquestionnaire->page->add_to_page('message', get_string('noneinuse', 'pimenkoquestionnaire'));
    }
}

if ($pimenkoquestionnaire->capabilities->editquestions && !$pimenkoquestionnaire->questions && $pimenkoquestionnaire->is_active()) {
    $pimenkoquestionnaire->page->add_to_page('complete',
            '<a href="' . $CFG->wwwroot . htmlspecialchars('/mod/pimenkoquestionnaire/questions.php?' .
                    'id=' . $pimenkoquestionnaire->cm->id) . '">' . '<strong>' .
            get_string('addquestions', 'pimenkoquestionnaire') . '</strong></a>');
}

if (isguestuser()) {
    $guestno = html_writer::tag('p', get_string('noteligible', 'pimenkoquestionnaire'));
    $liketologin = html_writer::tag('p', get_string('liketologin'));
    $pimenkoquestionnaire->page->add_to_page('guestuser',
            $pimenkoquestionnaire->renderer->confirm($guestno . "\n\n" . $liketologin . "\n", get_login_url(),
                    get_local_referer(false)));
}

// Log this course module view.
// Needed for the event logging.
$context = context_module::instance($pimenkoquestionnaire->cm->id);
$anonymous = $pimenkoquestionnaire->respondenttype == 'anonymous';

$event = \mod_pimenkoquestionnaire\event\course_module_viewed::create([
        'objectid' => $pimenkoquestionnaire->id,
        'anonymous' => $anonymous,
        'context' => $context
]);
$event->trigger();

$usernumresp = $pimenkoquestionnaire->count_submissions($USER->id);

if ($pimenkoquestionnaire->capabilities->readownresponses && ($usernumresp > 0)) {
    $argstr = 'instance=' . $pimenkoquestionnaire->id . '&user=' . $USER->id;
    if ($usernumresp > 1) {
        $titletext = get_string('viewyourresponses', 'pimenkoquestionnaire', $usernumresp);
    } else {
        $titletext = get_string('yourresponse', 'pimenkoquestionnaire');
        $argstr .= '&byresponse=1&action=vresp';
    }
    $pimenkoquestionnaire->page->add_to_page('yourresponse',
            '<a href="' . $CFG->wwwroot . htmlspecialchars('/mod/pimenkoquestionnaire/myreport.php?' . $argstr) . '">' .
            $titletext . '</a>');
}

if ($pimenkoquestionnaire->can_view_all_responses($usernumresp)) {
    $argstr = 'instance=' . $pimenkoquestionnaire->id . '&group=' . $currentgroupid;
    $pimenkoquestionnaire->page->add_to_page('allresponses',
            '<a href="' . $CFG->wwwroot . htmlspecialchars('/mod/pimenkoquestionnaire/report.php?' . $argstr) . '">' .
            get_string('viewallresponses', 'pimenkoquestionnaire') . '</a>');
}

echo $pimenkoquestionnaire->renderer->render($pimenkoquestionnaire->page);
echo $pimenkoquestionnaire->renderer->footer();
