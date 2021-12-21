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

$instance = optional_param('instance', false, PARAM_INT);   // Questionnaire ID.
$action = optional_param('action', 'vall', PARAM_ALPHA);
$sid = optional_param('sid', null, PARAM_INT);              // Survey id.
$rid = optional_param('rid', false, PARAM_INT);
$type = optional_param('type', '', PARAM_ALPHA);
$byresponse = optional_param('byresponse', false, PARAM_INT);
$individualresponse = optional_param('individualresponse', false, PARAM_INT);
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.
$user = optional_param('user', '', PARAM_INT);
$userid = $USER->id;
switch ($action) {
    case 'vallasort':
        $sort = 'ascending';
        break;
    case 'vallarsort':
        $sort = 'descending';
        break;
    default:
        $sort = 'default';
}

if ($instance === false) {
    if (!empty($SESSION->instance)) {
        $instance = $SESSION->instance;
    } else {
        print_error('requiredparameter', 'pimenkoquestionnaire');
    }
}
$SESSION->instance = $instance;
$usergraph = get_config('pimenkoquestionnaire', 'usergraph');

if (!$pimenkoquestionnaire = $DB->get_record("pimenkoquestionnaire", ["id" => $instance])) {
    print_error('incorrectpimenkoquestionnaire', 'pimenkoquestionnaire');
}
if (!$course = $DB->get_record("course", ["id" => $pimenkoquestionnaire->course])) {
    print_error('coursemisconf');
}
if (!$cm = get_coursemodule_from_instance("pimenkoquestionnaire", $pimenkoquestionnaire->id, $course->id)) {
    print_error('invalidcoursemodule');
}

require_course_login($course, true, $cm);

$pimenkoquestionnaire = new pimenkoquestionnaire(0, $pimenkoquestionnaire, $course, $cm);

// Add renderer and page objects to the pimenkoquestionnaire object for display use.
$pimenkoquestionnaire->add_renderer($PAGE->get_renderer('mod_pimenkoquestionnaire'));
$pimenkoquestionnaire->add_page(new \mod_pimenkoquestionnaire\output\reportpage());

// If you can't view the pimenkoquestionnaire, or can't view a specified response, error out.
$context = context_module::instance($cm->id);
if (!has_capability('mod/pimenkoquestionnaire:readallresponseanytime', $context) &&
        !($pimenkoquestionnaire->capabilities->view && $pimenkoquestionnaire->can_view_response($rid))) {
    // Should never happen, unless called directly by a snoop...
    print_error('nopermissions', 'moodle', $CFG->wwwroot . '/mod/pimenkoquestionnaire/view.php?id=' . $cm->id);
}

$pimenkoquestionnaire->canviewallgroups = has_capability('moodle/site:accessallgroups', $context);
$sid = $pimenkoquestionnaire->survey->id;

$url = new moodle_url($CFG->wwwroot . '/mod/pimenkoquestionnaire/report.php');
if ($instance) {
    $url->param('instance', $instance);
}

$url->param('action', $action);

if ($type) {
    $url->param('type', $type);
}
if ($byresponse || $individualresponse) {
    $url->param('byresponse', 1);
}
if ($user) {
    $url->param('user', $user);
}
if ($action == 'dresp') {
    $url->param('action', 'dresp');
    $url->param('byresponse', 1);
    $url->param('rid', $rid);
    $url->param('individualresponse', 1);
}
if ($currentgroupid !== null) {
    $url->param('group', $currentgroupid);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
// Other Solution.
//$PAGE->requires->js(new moodle_url('/mod/pimenkoquestionnaire/javascript/html2canvas.js'), true);
//$PAGE->requires->js(new moodle_url('/mod/pimenkoquestionnaire/javascript/jsPDF.js'), true);
$PAGE->requires->js_call_amd('mod_pimenkoquestionnaire/pimenkoquestionnaire', 'init');
// Tab setup.
if (!isset($SESSION->pimenkoquestionnaire)) {
    $SESSION->pimenkoquestionnaire = new stdClass();
}
$SESSION->pimenkoquestionnaire->current_tab = 'allreport';

// Get all responses for further use in viewbyresp and deleteall etc.
// All participants.
$respsallparticipants = $pimenkoquestionnaire->get_responses();
$SESSION->pimenkoquestionnaire->numrespsallparticipants = count($respsallparticipants);
$SESSION->pimenkoquestionnaire->numselectedresps = $SESSION->pimenkoquestionnaire->numrespsallparticipants;

// Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
$groupmode = groups_get_activity_groupmode($cm, $course);
$pimenkoquestionnairegroups = '';
$groupscount = 0;
$SESSION->pimenkoquestionnaire->respscount = 0;
$SESSION->pimenkoquestionnaire_surveyid = $sid;

if ($groupmode > 0) {
    if ($groupmode == 1) {
        $pimenkoquestionnairegroups = groups_get_all_groups($course->id, $userid);
    }
    if ($groupmode == 2 || $pimenkoquestionnaire->canviewallgroups) {
        $pimenkoquestionnairegroups = groups_get_all_groups($course->id);
    }

    if (!empty($pimenkoquestionnairegroups)) {
        $groupscount = count($pimenkoquestionnairegroups);
        foreach ($pimenkoquestionnairegroups as $key) {
            $firstgroupid = $key->id;
            break;
        }
        if ($groupscount === 0 && $groupmode == 1) {
            $currentgroupid = 0;
        }
        if ($groupmode == 1 && !$pimenkoquestionnaire->canviewallgroups && $currentgroupid == 0) {
            $currentgroupid = $firstgroupid;
        }
    } else {
        // Groupmode = separate groups but user is not member of any group
        // and does not have moodle/site:accessallgroups capability -> refuse view responses.
        if (!$pimenkoquestionnaire->canviewallgroups) {
            $currentgroupid = 0;
        }
    }

    if ($currentgroupid > 0) {
        $groupname = get_string('group') . ' <strong>' . groups_get_group_name($currentgroupid) . '</strong>';
    } else {
        $groupname = '<strong>' . get_string('allparticipants') . '</strong>';
    }
}
if ($usergraph) {
    $charttype = $pimenkoquestionnaire->survey->chart_type;
    if ($charttype) {
        $PAGE->requires->js('/mod/pimenkoquestionnaire/javascript/RGraph/RGraph.common.core.js');

        switch ($charttype) {
            case 'bipolar':
                $PAGE->requires->js('/mod/pimenkoquestionnaire/javascript/RGraph/RGraph.bipolar.js');
                break;
            case 'hbar':
                $PAGE->requires->js('/mod/pimenkoquestionnaire/javascript/RGraph/RGraph.hbar.js');
                break;
            case 'radar':
                $PAGE->requires->js('/mod/pimenkoquestionnaire/javascript/RGraph/RGraph.radar.js');
                break;
            case 'rose':
                $PAGE->requires->js('/mod/pimenkoquestionnaire/javascript/RGraph/RGraph.rose.js');
                break;
            case 'vprogress':
                $PAGE->requires->js('/mod/pimenkoquestionnaire/javascript/RGraph/RGraph.vprogress.js');
                break;
        }
    }
}

switch ($action) {

    case 'dresp':  // Delete individual response? Ask for confirmation.

        require_capability('mod/pimenkoquestionnaire:deleteresponses', $context);

        if (empty($pimenkoquestionnaire->survey)) {
            $id = $pimenkoquestionnaire->survey;
            notify("pimenkoquestionnaire->survey = /$id/");
            print_error('surveynotexists', 'pimenkoquestionnaire');
        } else if ($pimenkoquestionnaire->survey->courseid != $course->id) {
            print_error('surveyowner', 'pimenkoquestionnaire');
        } else if (!$rid || !is_numeric($rid)) {
            print_error('invalidresponse', 'pimenkoquestionnaire');
        } else if (!($resp = $DB->get_record('pimenko_response', ['id' => $rid]))) {
            print_error('invalidresponserecord', 'pimenkoquestionnaire');
        }

        $ruser = false;
        if (!empty($resp->userid)) {
            if ($user = $DB->get_record('user', ['id' => $resp->userid])) {
                $ruser = fullname($user);
            } else {
                $ruser = '- ' . get_string('unknown', 'pimenkoquestionnaire') . ' -';
            }
        } else {
            $ruser = $resp->userid;
        }

        // Print the page header.
        $PAGE->set_title(get_string('deletingresp', 'pimenkoquestionnaire'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $pimenkoquestionnaire->renderer->header();

        // Print the tabs.
        $SESSION->pimenkoquestionnaire->current_tab = 'deleteresp';
        include('tabs.php');

        $timesubmitted = '<br />' . get_string('submitted', 'pimenkoquestionnaire') . '&nbsp;' . userdate($resp->submitted);
        if ($pimenkoquestionnaire->respondenttype == 'anonymous') {
            $ruser = '- ' . get_string('anonymous', 'pimenkoquestionnaire') . ' -';
            $timesubmitted = '';
        }

        // Print the confirmation.
        $msg = '<div class="warning centerpara">';
        $msg .= get_string('confirmdelresp', 'pimenkoquestionnaire', $ruser . $timesubmitted);
        $msg .= '</div>';
        $urlyes = new moodle_url('report.php', ['action' => 'dvresp',
                'rid' => $rid, 'individualresponse' => 1, 'instance' => $instance, 'group' => $currentgroupid]);
        $urlno = new moodle_url('report.php', ['action' => 'vresp', 'instance' => $instance,
                'rid' => $rid, 'individualresponse' => 1, 'group' => $currentgroupid]);
        $buttonyes = new single_button($urlyes, get_string('delete'), 'post');
        $buttonno = new single_button($urlno, get_string('cancel'), 'get');
        $pimenkoquestionnaire->page->add_to_page('notifications',
                $pimenkoquestionnaire->renderer->confirm($msg, $buttonyes, $buttonno));
        echo $pimenkoquestionnaire->renderer->render($pimenkoquestionnaire->page);
        // Finish the page.
        echo $pimenkoquestionnaire->renderer->footer($course);
        break;

    case 'delallresp': // Delete all responses? Ask for confirmation.
        require_capability('mod/pimenkoquestionnaire:deleteresponses', $context);

        if (!empty($respsallparticipants)) {

            // Print the page header.
            $PAGE->set_title(get_string('deletingresp', 'pimenkoquestionnaire'));
            $PAGE->set_heading(format_string($course->fullname));
            echo $pimenkoquestionnaire->renderer->header();

            // Print the tabs.
            $SESSION->pimenkoquestionnaire->current_tab = 'deleteall';
            include('tabs.php');

            // Print the confirmation.
            $msg = '<div class="warning centerpara">';
            if ($groupmode == 0) {   // No groups or visible groups.
                $msg .= get_string('confirmdelallresp', 'pimenkoquestionnaire');
            } else {                 // Separate groups.
                $msg .= get_string('confirmdelgroupresp', 'pimenkoquestionnaire', $groupname);
            }
            $msg .= '</div>';

            $urlyes = new moodle_url('report.php', ['action' => 'dvallresp', 'sid' => $sid,
                    'instance' => $instance, 'group' => $currentgroupid]);
            $urlno = new moodle_url('report.php', ['instance' => $instance, 'group' => $currentgroupid]);
            $buttonyes = new single_button($urlyes, get_string('delete'), 'post');
            $buttonno = new single_button($urlno, get_string('cancel'), 'get');

            $pimenkoquestionnaire->page->add_to_page('notifications',
                    $pimenkoquestionnaire->renderer->confirm($msg, $buttonyes, $buttonno));
            echo $pimenkoquestionnaire->renderer->render($pimenkoquestionnaire->page);
            // Finish the page.
            echo $pimenkoquestionnaire->renderer->footer($course);
        }
        break;

    case 'dvresp': // Delete single response. Do it!

        require_capability('mod/pimenkoquestionnaire:deleteresponses', $context);

        if (empty($pimenkoquestionnaire->survey)) {
            print_error('surveynotexists', 'pimenkoquestionnaire');
        } else if ($pimenkoquestionnaire->survey->courseid != $course->id) {
            print_error('surveyowner', 'pimenkoquestionnaire');
        } else if (!$rid || !is_numeric($rid)) {
            print_error('invalidresponse', 'pimenkoquestionnaire');
        } else if (!($response = $DB->get_record('pimenko_response', ['id' => $rid]))) {
            print_error('invalidresponserecord', 'pimenkoquestionnaire');
        }

        if (pimenkoquestionnaire_delete_response($response, $pimenkoquestionnaire)) {
            if (!$DB->count_records('pimenko_response',
                    ['pimenkoquestionnaireid' => $pimenkoquestionnaire->id, 'complete' => 'y'])) {
                $redirection = $CFG->wwwroot . '/mod/pimenkoquestionnaire/view.php?id=' . $cm->id;
            } else {
                $redirection = $CFG->wwwroot . '/mod/pimenkoquestionnaire/report.php?action=vresp&amp;instance=' .
                        $instance . '&amp;byresponse=1';
            }

            // Log this pimenkoquestionnaire delete single response action.
            $params = ['objectid' => $pimenkoquestionnaire->survey->id,
                    'context' => $pimenkoquestionnaire->context,
                    'courseid' => $pimenkoquestionnaire->course->id,
                    'relateduserid' => $response->userid];
            $event = \mod_pimenkoquestionnaire\event\response_deleted::create($params);
            $event->trigger();

            redirect($redirection);
        } else {
            if ($pimenkoquestionnaire->respondenttype == 'anonymous') {
                $ruser = '- ' . get_string('anonymous', 'pimenkoquestionnaire') . ' -';
            } else if (!empty($response->userid)) {
                if ($user = $DB->get_record('user', ['id' => $response->userid])) {
                    $ruser = fullname($user);
                } else {
                    $ruser = '- ' . get_string('unknown', 'pimenkoquestionnaire') . ' -';
                }
            } else {
                $ruser = $response->userid;
            }
            error(get_string('couldnotdelresp', 'pimenkoquestionnaire') . $rid . get_string('by', 'pimenkoquestionnaire') . $ruser .
                    '?',
                    $CFG->wwwroot . '/mod/pimenkoquestionnaire/report.php?action=vresp&amp;sid=' . $sid . '&amp;&amp;instance=' .
                    $instance . 'byresponse=1');
        }
        break;

    case 'dvallresp': // Delete all responses in pimenkoquestionnaire (or group). Do it!

        require_capability('mod/pimenkoquestionnaire:deleteresponses', $context);

        if (empty($pimenkoquestionnaire->survey)) {
            print_error('surveynotexists', 'pimenkoquestionnaire');
        } else if ($pimenkoquestionnaire->survey->courseid != $course->id) {
            print_error('surveyowner', 'pimenkoquestionnaire');
        }

        // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
        if ($groupmode > 0) {
            switch ($currentgroupid) {
                case 0:     // All participants.
                    $resps = $respsallparticipants;
                    break;
                default:     // Members of a specific group.
                    if (!($resps = $pimenkoquestionnaire->get_responses(false, $currentgroupid))) {
                        $resps = [];
                    }
            }
            if (empty($resps)) {
                $noresponses = true;
            } else {
                if ($rid === false) {
                    $resp = current($resps);
                    $rid = $resp->id;
                } else {
                    $resp = $DB->get_record('pimenko_response', ['id' => $rid]);
                }
                if (!empty($resp->userid)) {
                    if ($user = $DB->get_record('user', ['id' => $resp->userid])) {
                        $ruser = fullname($user);
                    } else {
                        $ruser = '- ' . get_string('unknown', 'pimenkoquestionnaire') . ' -';
                    }
                } else {
                    $ruser = $resp->userid;
                }
            }
        } else {
            $resps = $respsallparticipants;
        }

        if (!empty($resps)) {
            foreach ($resps as $response) {
                pimenkoquestionnaire_delete_response($response, $pimenkoquestionnaire);
            }
            if (!$pimenkoquestionnaire->count_submissions()) {
                $redirection = $CFG->wwwroot . '/mod/pimenkoquestionnaire/view.php?id=' . $cm->id;
            } else {
                $redirection =
                        $CFG->wwwroot . '/mod/pimenkoquestionnaire/report.php?action=vall&amp;sid=' . $sid . '&amp;instance=' .
                        $instance;
            }

            // Log this pimenkoquestionnaire delete all responses action.
            $context = context_module::instance($pimenkoquestionnaire->cm->id);
            $anonymous = $pimenkoquestionnaire->respondenttype == 'anonymous';

            $event = \mod_pimenkoquestionnaire\event\all_responses_deleted::create([
                    'objectid' => $pimenkoquestionnaire->id,
                    'anonymous' => $anonymous,
                    'context' => $context
            ]);
            $event->trigger();

            redirect($redirection);
        } else {
            error(get_string('couldnotdelresp', 'pimenkoquestionnaire'),
                    $CFG->wwwroot . '/mod/pimenkoquestionnaire/report.php?action=vall&amp;sid=' . $sid . '&amp;instance=' .
                    $instance);
        }
        break;

    case 'dwnpg': // Download page options.

        require_capability('mod/pimenkoquestionnaire:downloadresponses', $context);

        $PAGE->set_title(get_string('pimenkoquestionnairereport', 'pimenkoquestionnaire'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $pimenkoquestionnaire->renderer->header();

        // Print the tabs.
        // Tab setup.
        if (empty($user)) {
            $SESSION->pimenkoquestionnaire->current_tab = 'downloadcsv';
        } else {
            $SESSION->pimenkoquestionnaire->current_tab = 'mydownloadcsv';
        }

        include('tabs.php');

        $groupname = '';
        if ($groupmode > 0) {
            switch ($currentgroupid) {
                case 0:     // All participants.
                    $groupname = get_string('allparticipants');
                    break;
                default:     // Members of a specific group.
                    $groupname = get_string('membersofselectedgroup', 'group') . ' ' . get_string('group') . ' ' .
                            $pimenkoquestionnairegroups[$currentgroupid]->name;
            }
        }
        $output = '';
        $output .= "<br /><br />\n";
        $output .= $pimenkoquestionnaire->renderer->help_icon('downloadtextformat', 'pimenkoquestionnaire');
        $output .= '&nbsp;' . (get_string('downloadtextformat', 'pimenkoquestionnaire')) . ':&nbsp;' .
                get_string('responses', 'pimenkoquestionnaire') . '&nbsp;' . $groupname;
        $output .= $pimenkoquestionnaire->renderer->heading(get_string('textdownloadoptions', 'pimenkoquestionnaire'));
        $output .= $pimenkoquestionnaire->renderer->box_start();
        $output .= "<form action=\"{$CFG->wwwroot}/mod/pimenkoquestionnaire/report.php\" method=\"GET\">\n";
        $output .= "<input type=\"hidden\" name=\"instance\" value=\"$instance\" />\n";
        $output .= "<input type=\"hidden\" name=\"user\" value=\"$user\" />\n";
        $output .= "<input type=\"hidden\" name=\"sid\" value=\"$sid\" />\n";
        $output .= "<input type=\"hidden\" name=\"action\" value=\"dcsv\" />\n";
        $output .= "<input type=\"hidden\" name=\"group\" value=\"$currentgroupid\" />\n";
        $output .= html_writer::checkbox('choicecodes', 1, true, get_string('includechoicecodes', 'pimenkoquestionnaire'));
        $output .= "<br />\n";
        $output .= html_writer::checkbox('choicetext', 1, true, get_string('includechoicetext', 'pimenkoquestionnaire'));
        $output .= "<br />\n";
        $output .= html_writer::checkbox('complete', 1, false, get_string('includeincomplete', 'pimenkoquestionnaire'));
        $output .= "<br />\n";
        $output .= "<br />\n";
        $output .= "<input type=\"submit\" name=\"submit\" value=\"" . get_string('download', 'pimenkoquestionnaire') . "\" />\n";
        $output .= "</form>\n";
        $output .= $pimenkoquestionnaire->renderer->box_end();

        $pimenkoquestionnaire->page->add_to_page('respondentinfo', $output);
        echo $pimenkoquestionnaire->renderer->render($pimenkoquestionnaire->page);

        echo $pimenkoquestionnaire->renderer->footer('none');

        // Log saved as text action.
        $params = ['objectid' => $pimenkoquestionnaire->id,
                'context' => $pimenkoquestionnaire->context,
                'courseid' => $course->id,
                'other' => ['action' => $action, 'instance' => $instance, 'currentgroupid' => $currentgroupid]
        ];
        $event = \mod_pimenkoquestionnaire\event\all_responses_saved_as_text::create($params);
        $event->trigger();

        exit();
        break;

    case 'dcsv': // Download responses data as text (cvs) format.
        require_capability('mod/pimenkoquestionnaire:downloadresponses', $context);
        require_once($CFG->libdir . '/dataformatlib.php');

        // Use the pimenkoquestionnaire name as the file name. Clean it and change any non-filename characters to '_'.
        $name = format_string($pimenkoquestionnaire->name);
        $name = preg_replace('/[\x00-\x1F\x7F-\xFF]/', "", trim($name));
        $name = trim($name);

        $choicecodes = optional_param('choicecodes', '0', PARAM_INT);
        $choicetext = optional_param('choicetext', '0', PARAM_INT);
        $showincompletes = optional_param('complete', '0', PARAM_INT);
        $output = $pimenkoquestionnaire->generate_csv('', $user, $choicecodes, $choicetext, $currentgroupid, $showincompletes);

        // Use Moodle's core download function for outputting csv.
        $rowheaders = array_shift($output);
        download_as_dataformat($name, 'csv', $rowheaders, $output);
        exit();
        break;

    case 'vall':         // View all responses.
    case 'vallasort':    // View all responses sorted in ascending order.
    case 'vallarsort':   // View all responses sorted in descending order.

        $PAGE->set_title(get_string('pimenkoquestionnairereport', 'pimenkoquestionnaire'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $pimenkoquestionnaire->renderer->header();
        if (!$pimenkoquestionnaire->capabilities->readallresponses &&
                !$pimenkoquestionnaire->capabilities->readallresponseanytime) {
            // Should never happen, unless called directly by a snoop.
            print_error('nopermissions', '', '', get_string('viewallresponses', 'pimenkoquestionnaire'));
            // Finish the page.
            echo $pimenkoquestionnaire->renderer->footer($course);
            break;
        }

        // Print the tabs.
        switch ($action) {
            case 'vallasort':
                $SESSION->pimenkoquestionnaire->current_tab = 'vallasort';
                break;
            case 'vallarsort':
                $SESSION->pimenkoquestionnaire->current_tab = 'vallarsort';
                break;
            default:
                $SESSION->pimenkoquestionnaire->current_tab = 'valldefault';
        }
        include('tabs.php');

        $respinfo = '';
        $resps = [];
        // Enable choose_group if there are pimenkoquestionnaire groups and groupmode is not set to "no groups"
        // and if there are more goups than 1 (or if user can view all groups).
        if (is_array($pimenkoquestionnairegroups) && $groupmode > 0) {
            $groupselect = groups_print_activity_menu($cm, $url->out(), true);
            // Count number of responses in each group.
            foreach ($pimenkoquestionnairegroups as $group) {
                $respscount = $pimenkoquestionnaire->count_submissions(false, $group->id);
                $thisgroupname = groups_get_group_name($group->id);
                $escapedgroupname = preg_quote($thisgroupname, '/');
                if (!empty ($respscount)) {
                    // Add number of responses to name of group in the groups select list.
                    $groupselect = preg_replace('/\<option value="' . $group->id . '">' . $escapedgroupname . '<\/option>/',
                            '<option value="' . $group->id . '">' . $thisgroupname . ' (' . $respscount . ')</option>',
                            $groupselect);
                } else {
                    // Remove groups with no responses from the groups select list.
                    $groupselect = preg_replace('/\<option value="' . $group->id . '">' . $escapedgroupname .
                            '<\/option>/', '', $groupselect);
                }
            }
            $respinfo .= isset($groupselect) ? ($groupselect . ' ') : '';
            $currentgroupid = groups_get_activity_group($cm);
        }
        if ($currentgroupid > 0) {
            $groupname = get_string('group') . ': <strong>' . groups_get_group_name($currentgroupid) . '</strong>';
        } else {
            $groupname = '<strong>' . get_string('allparticipants') . '</strong>';
        }

        // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
        if ($groupmode > 0) {
            switch ($currentgroupid) {
                case 0:     // All participants.
                    $resps = $respsallparticipants;
                    break;
                default:     // Members of a specific group.
                    if (!($resps = $pimenkoquestionnaire->get_responses(false, $currentgroupid))) {
                        $resps = '';
                    }
            }
            if (empty($resps)) {
                $noresponses = true;
            }
        } else {
            $resps = $respsallparticipants;
        }
        if (!empty($resps)) {
            // NOTE: response_analysis uses $resps to get the id's of the responses only.
            // Need to figure out what this function does.
            $feedbackmessages = $pimenkoquestionnaire->response_analysis(0, $resps, false, false, true, $currentgroupid);

            if ($feedbackmessages) {
                $msgout = '';
                foreach ($feedbackmessages as $msg) {
                    $msgout .= $msg;
                }
                $pimenkoquestionnaire->page->add_to_page('feedbackmessages', $msgout);
            }
        }

        $params = ['objectid' => $pimenkoquestionnaire->id,
                'context' => $context,
                'courseid' => $course->id,
                'other' => ['action' => $action, 'instance' => $instance, 'groupid' => $currentgroupid]
        ];
        $event = \mod_pimenkoquestionnaire\event\all_responses_viewed::create($params);
        $event->trigger();

        $respinfo .= get_string('viewallresponses', 'pimenkoquestionnaire') . '. ' . $groupname . '. ';
        $strsort = get_string('order_' . $sort, 'pimenkoquestionnaire');
        $respinfo .= $strsort;
        $respinfo .= $pimenkoquestionnaire->renderer->help_icon('orderresponses', 'pimenkoquestionnaire');
        $pimenkoquestionnaire->page->add_to_page('respondentinfo', $respinfo);

        $ret = $pimenkoquestionnaire->survey_results(1, 1, '', '', '', false, $currentgroupid, $sort);

        echo $pimenkoquestionnaire->renderer->render($pimenkoquestionnaire->page);

        // Finish the page.
        echo $pimenkoquestionnaire->renderer->footer($course);
        break;

    case 'vresp': // View by response.

    default:
        if (empty($pimenkoquestionnaire->survey)) {
            print_error('surveynotexists', 'pimenkoquestionnaire');
        } else if ($pimenkoquestionnaire->survey->courseid != $course->id) {
            print_error('surveyowner', 'pimenkoquestionnaire');
        }
        $ruser = false;
        $noresponses = false;
        if ($usergraph) {
            $charttype = $pimenkoquestionnaire->survey->chart_type;
            if ($charttype) {
                $PAGE->requires->js('/mod/pimenkoquestionnaire/javascript/RGraph/RGraph.common.core.js');

                switch ($charttype) {
                    case 'bipolar':
                        $PAGE->requires->js('/mod/pimenkoquestionnaire/javascript/RGraph/RGraph.bipolar.js');
                        break;
                    case 'hbar':
                        $PAGE->requires->js('/mod/pimenkoquestionnaire/javascript/RGraph/RGraph.hbar.js');
                        break;
                    case 'radar':
                        $PAGE->requires->js('/mod/pimenkoquestionnaire/javascript/RGraph/RGraph.radar.js');
                        break;
                    case 'rose':
                        $PAGE->requires->js('/mod/pimenkoquestionnaire/javascript/RGraph/RGraph.rose.js');
                        break;
                    case 'vprogress':
                        $PAGE->requires->js('/mod/pimenkoquestionnaire/javascript/RGraph/RGraph.vprogress.js');
                        break;
                }
            }
        }

        if ($byresponse || $rid) {
            // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
            if ($groupmode > 0) {
                switch ($currentgroupid) {
                    case 0:     // All participants.
                        $resps = $respsallparticipants;
                        break;
                    default:     // Members of a specific group.
                        $resps = $pimenkoquestionnaire->get_responses(false, $currentgroupid);
                }
                if (empty($resps)) {
                    $noresponses = true;
                } else {
                    if ($rid === false) {
                        $resp = current($resps);
                        $rid = $resp->id;
                    } else {
                        $resp = $DB->get_record('pimenko_response', ['id' => $rid]);
                    }
                    if (!empty($resp->userid)) {
                        if ($user = $DB->get_record('user', ['id' => $resp->userid])) {
                            $ruser = fullname($user);
                        } else {
                            $ruser = '- ' . get_string('unknown', 'pimenkoquestionnaire') . ' -';
                        }
                    } else {
                        $ruser = $resp->userid;
                    }
                }
            } else {
                $resps = $respsallparticipants;
            }
        }
        $rids = array_keys($resps);
        if (!$rid && !$noresponses) {
            $rid = $rids[0];
        }

        // Print the page header.
        $PAGE->set_title(get_string('pimenkoquestionnairereport', 'pimenkoquestionnaire'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $pimenkoquestionnaire->renderer->header();

        // Print the tabs.
        if ($byresponse) {
            $SESSION->pimenkoquestionnaire->current_tab = 'vrespsummary';
        }
        if ($individualresponse) {
            $SESSION->pimenkoquestionnaire->current_tab = 'individualresp';
        }
        include('tabs.php');

        // Print the main part of the page.
        // TODO provide option to select how many columns and/or responses per page.

        if ($noresponses) {
            $pimenkoquestionnaire->page->add_to_page('respondentinfo',
                    get_string('group') . ' <strong>' . groups_get_group_name($currentgroupid) . '</strong>: ' .
                    get_string('noresponses', 'pimenkoquestionnaire'));
        } else {
            $groupname = get_string('group') . ': <strong>' . groups_get_group_name($currentgroupid) . '</strong>';
            if ($currentgroupid == 0) {
                $groupname = get_string('allparticipants');
            }
            if ($byresponse) {
                $respinfo = '';
                $respinfo .= $pimenkoquestionnaire->renderer->box_start();
                $respinfo .= $pimenkoquestionnaire->renderer->help_icon('viewindividualresponse', 'pimenkoquestionnaire') .
                        '&nbsp;';
                $respinfo .= get_string('viewindividualresponse', 'pimenkoquestionnaire') . ' <strong> : ' . $groupname .
                        '</strong>';
                $respinfo .= $pimenkoquestionnaire->renderer->box_end();
                $pimenkoquestionnaire->page->add_to_page('respondentinfo', $respinfo);
            }
            $pimenkoquestionnaire->survey_results_navbar_alpha($rid, $currentgroupid, $cm, $byresponse);
            if (!$byresponse) { // Show respondents individual responses.
                $pimenkoquestionnaire->view_response($rid, '', false, $resps, true, true, false, $currentgroupid);
            }
        }

        echo $pimenkoquestionnaire->renderer->render($pimenkoquestionnaire->page);

        // Finish the page.
        echo $pimenkoquestionnaire->renderer->footer($course);
        break;
}
