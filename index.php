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
 * This script lists all the instances of pimenkoquestionnaire in a particular course
 *
 * @package    mod
 * @subpackage pimenkoquestionnaire
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once($CFG->dirroot . '/mod/pimenkoquestionnaire/locallib.php');

$id = required_param('id', PARAM_INT);
$PAGE->set_url('/mod/pimenkoquestionnaire/index.php', ['id' => $id]);
if (!$course = $DB->get_record('course', ['id' => $id])) {
    print_error('incorrectcourseid', 'pimenkoquestionnaire');
}
$coursecontext = context_course::instance($id);
require_login($course->id);
$PAGE->set_pagelayout('incourse');

$event = \mod_pimenkoquestionnaire\event\course_module_instance_list_viewed::create([
        'context' => context_course::instance($course->id)]);
$event->trigger();

// Print the header.
$strpimenkoquestionnaires = get_string("modulenameplural", "pimenkoquestionnaire");
$PAGE->navbar->add($strpimenkoquestionnaires);
$PAGE->set_title("$course->shortname: $strpimenkoquestionnaires");
$PAGE->set_heading(format_string($course->fullname));
echo $OUTPUT->header();

// Get all the appropriate data.
if (!$pimenkoquestionnaires = get_all_instances_in_course("pimenkoquestionnaire", $course)) {
    notice(get_string('thereareno', 'moodle', $strpimenkoquestionnaires), "../../course/view.php?id=$course->id");
    die;
}

// Check if we need the closing date header.
$showclosingheader = false;
foreach ($pimenkoquestionnaires as $pimenkoquestionnaire) {
    if ($pimenkoquestionnaire->closedate != 0) {
        $showclosingheader = true;
    }
    if ($showclosingheader) {
        break;
    }
}

// Configure table for displaying the list of instances.
$headings = [get_string('name')];
$align = ['left'];

if ($showclosingheader) {
    array_push($headings, get_string('pimenkoquestionnairecloses', 'pimenkoquestionnaire'));
    array_push($align, 'left');
}

array_unshift($headings, get_string('sectionname', 'format_' . $course->format));
array_unshift($align, 'left');

$showing = '';

// Current user role == admin or teacher.
if (has_capability('mod/pimenkoquestionnaire:viewsingleresponse', $coursecontext)) {
    array_push($headings, get_string('responses', 'pimenkoquestionnaire'));
    array_push($align, 'center');
    $showing = 'stats';
    array_push($headings, get_string('realm', 'pimenkoquestionnaire'));
    array_push($align, 'left');
    // Current user role == student.
} else if (has_capability('mod/pimenkoquestionnaire:submit', $coursecontext)) {
    array_push($headings, get_string('status'));
    array_push($align, 'left');
    $showing = 'responses';
}

$table = new html_table();
$table->head = $headings;
$table->align = $align;

// Populate the table with the list of instances.
$currentsection = '';
foreach ($pimenkoquestionnaires as $pimenkoquestionnaire) {
    $cmid = $pimenkoquestionnaire->coursemodule;
    $data = [];
    $realm = $DB->get_field('pimenkoquestionnaire_survey', 'realm', ['id' => $pimenkoquestionnaire->sid]);
    // Template surveys should NOT be displayed as an activity to students.
    if (!($realm == 'template' && !has_capability('mod/pimenkoquestionnaire:manage', context_module::instance($cmid)))) {
        // Section number if necessary.
        $strsection = '';
        if ($pimenkoquestionnaire->section != $currentsection) {
            $strsection = get_section_name($course, $pimenkoquestionnaire->section);
            $currentsection = $pimenkoquestionnaire->section;
        }
        $data[] = $strsection;
        // Show normal if the mod is visible.
        $class = '';
        if (!$pimenkoquestionnaire->visible) {
            $class = ' class="dimmed"';
        }
        $data[] = "<a$class href=\"view.php?id=$cmid\">$pimenkoquestionnaire->name</a>";

        // Close date.
        if ($pimenkoquestionnaire->closedate) {
            $data[] = userdate($pimenkoquestionnaire->closedate);
        } else if ($showclosingheader) {
            $data[] = '';
        }

        if ($showing == 'responses') {
            $status = '';
            if ($responses = pimenkoquestionnaire_get_user_responses($pimenkoquestionnaire->id, $USER->id, $complete = false)) {
                foreach ($responses as $response) {
                    if ($response->complete == 'y') {
                        $status .= get_string('submitted', 'pimenkoquestionnaire') . ' ' . userdate($response->submitted) .
                                '<br />';
                    } else {
                        $status .= get_string('attemptstillinprogress', 'pimenkoquestionnaire') . ' ' .
                                userdate($response->submitted) . '<br />';
                    }
                }
            }
            $data[] = $status;
        } else if ($showing == 'stats') {
            $data[] = $DB->count_records('pimenko_response',
                    ['pimenkoquestionnaireid' => $pimenkoquestionnaire->id, 'complete' => 'y']);
            if ($survey = $DB->get_record('pimenkoquestionnaire_survey', ['id' => $pimenkoquestionnaire->sid])) {
                // For a public pimenkoquestionnaire, look for the original public pimenkoquestionnaire that it is based on.
                if ($survey->realm == 'public') {
                    $strpreview = get_string('preview_pimenkoquestionnaire', 'pimenkoquestionnaire');
                    if ($survey->courseid != $course->id) {
                        $publicoriginal = '';
                        $originalcourse = $DB->get_record('course', ['id' => $survey->courseid]);
                        $originalcoursecontext = context_course::instance($survey->courseid);
                        $originalpimenkoquestionnaire = $DB->get_record('pimenkoquestionnaire',
                                ['sid' => $survey->id, 'course' => $survey->courseid]);
                        $cm = get_coursemodule_from_instance("pimenkoquestionnaire", $originalpimenkoquestionnaire->id,
                                $survey->courseid);
                        $context = context_course::instance($survey->courseid, MUST_EXIST);
                        $canvieworiginal = has_capability('mod/pimenkoquestionnaire:preview', $context, $USER->id, true);
                        // If current user can view pimenkoquestionnaires in original course,
                        // provide a link to the original public pimenkoquestionnaire.
                        if ($canvieworiginal) {
                            $publicoriginal = '<br />' . get_string('publicoriginal', 'pimenkoquestionnaire') . '&nbsp;' .
                                    '<a href="' . $CFG->wwwroot . '/mod/pimenkoquestionnaire/preview.php?id=' .
                                    $cm->id . '" title="' . $strpreview . ']">' . $originalpimenkoquestionnaire->name . ' [' .
                                    $originalcourse->fullname . ']</a>';
                        } else {
                            // If current user is not enrolled as teacher in original course,
                            // only display the original public pimenkoquestionnaire's name and course name.
                            $publicoriginal = '<br />' . get_string('publicoriginal', 'pimenkoquestionnaire') . '&nbsp;' .
                                    $originalpimenkoquestionnaire->name . ' [' . $originalcourse->fullname . ']';
                        }
                        $data[] = get_string($realm, 'pimenkoquestionnaire') . ' ' . $publicoriginal;
                    } else {
                        // Original public pimenkoquestionnaire was created in current course.
                        // Find which courses it is used in.
                        $publiccopy = '';
                        $select = 'course != ' . $course->id . ' AND sid = ' . $pimenkoquestionnaire->sid;
                        if ($copies = $DB->get_records_select('pimenkoquestionnaire', $select, null,
                                $sort = 'course ASC', $fields = 'id, course, name')) {
                            foreach ($copies as $copy) {
                                $copycourse = $DB->get_record('course', ['id' => $copy->course]);
                                $select = 'course = ' . $copycourse->id . ' AND sid = ' . $pimenkoquestionnaire->sid;
                                $copypimenkoquestionnaire = $DB->get_record('pimenkoquestionnaire',
                                        ['id' => $copy->id, 'sid' => $survey->id, 'course' => $copycourse->id]);
                                $cm = get_coursemodule_from_instance("pimenkoquestionnaire", $copypimenkoquestionnaire->id,
                                        $copycourse->id);
                                $context = context_course::instance($copycourse->id, MUST_EXIST);
                                $canviewcopy = has_capability('mod/pimenkoquestionnaire:view', $context, $USER->id, true);
                                if ($canviewcopy) {
                                    $publiccopy .= '<br />' . get_string('publiccopy', 'pimenkoquestionnaire') . '&nbsp;:&nbsp;' .
                                            '<a href = "' . $CFG->wwwroot . '/mod/pimenkoquestionnaire/preview.php?id=' .
                                            $cm->id . '" title = "' . $strpreview . '">' .
                                            $copypimenkoquestionnaire->name . ' [' . $copycourse->fullname . ']</a>';
                                } else {
                                    // If current user does not have "view" capability in copy course,
                                    // only display the copied public pimenkoquestionnaire's name and course name.
                                    $publiccopy .= '<br />' . get_string('publiccopy', 'pimenkoquestionnaire') . '&nbsp;:&nbsp;' .
                                            $copypimenkoquestionnaire->name . ' [' . $copycourse->fullname . ']';
                                }
                            }
                        }
                        $data[] = get_string($realm, 'pimenkoquestionnaire') . ' ' . $publiccopy;
                    }
                } else {
                    $data[] = get_string($realm, 'pimenkoquestionnaire');
                }
            } else {
                // If a pimenkoquestionnaire is a copy of a public pimenkoquestionnaire which has been deleted.
                $data[] = get_string('removenotinuse', 'pimenkoquestionnaire');
            }
        }
    }
    $table->data[] = $data;
} // End of loop over pimenkoquestionnaire instances.

echo html_writer::table($table);

// Finish the page.
echo $OUTPUT->footer();