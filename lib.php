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

// Library of functions and constants for module pimenkoquestionnaire.

/**
 * @package mod_pimenkoquestionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

define('QUESTIONNAIRE_RESETFORM_RESET', 'pimenkoquestionnaire_reset_data_');
define('QUESTIONNAIRE_RESETFORM_DROP', 'pimenkoquestionnaire_drop_pimenko_');

function pimenkoquestionnaire_supports($feature) {
    switch($feature) {
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;

        default:
            return null;
    }
}

/**
 * @return array all other caps used in module
 */
function pimenkoquestionnaire_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

function pimenkoquestionnaire_add_instance($pimenkoquestionnaire) {
    // Given an object containing all the necessary data,
    // (defined by the form in mod.html) this function
    // will create a new instance and return the id number
    // of the new instance.
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/pimenkoquestionnaire/pimenkoquestionnaire.class.php');
    require_once($CFG->dirroot.'/mod/pimenkoquestionnaire/locallib.php');

    // Check the realm and set it to the survey if it's set.

    if (empty($pimenkoquestionnaire->sid)) {
        // Create a new survey.
        $course = get_course($pimenkoquestionnaire->course);
        $cm = new stdClass();
        $qobject = new pimenkoquestionnaire(0, $pimenkoquestionnaire, $course, $cm);

        if ($pimenkoquestionnaire->create == 'new-0') {
            $sdata = new stdClass();
            $sdata->name = $pimenkoquestionnaire->name;
            $sdata->realm = 'private';
            $sdata->title = $pimenkoquestionnaire->name;
            $sdata->subtitle = '';
            $sdata->info = '';
            $sdata->theme = ''; // Theme is deprecated.
            $sdata->thanks_page = '';
            $sdata->thank_head = '';
            $sdata->thank_body = '';
            $sdata->email = '';
            $sdata->feedbacknotes = '';
            $sdata->courseid = $course->id;
            if (!($sid = $qobject->survey_update($sdata))) {
                print_error('couldnotcreatenewsurvey', 'pimenkoquestionnaire');
            }
        } else {
            $copyid = explode('-', $pimenkoquestionnaire->create);
            $copyrealm = $copyid[0];
            $copyid = $copyid[1];
            if (empty($qobject->survey)) {
                $qobject->add_survey($copyid);
                $qobject->add_questions($copyid);
            }
            // New pimenkoquestionnaires created as "use public" should not create a new survey instance.
            if ($copyrealm == 'public') {
                $sid = $copyid;
            } else {
                $sid = $qobject->sid = $qobject->survey_copy($course->id);
                // All new pimenkoquestionnaires should be created as "private".
                // Even if they are *copies* of public or template pimenkoquestionnaires.
                $DB->set_field('pimenkoquestionnaire_survey', 'realm', 'private', array('id' => $sid));
            }
            // If the survey has dependency data, need to set the pimenkoquestionnaire to allow dependencies.
            if ($DB->count_records('pimenko_dependency', ['surveyid' => $sid]) > 0) {
                $pimenkoquestionnaire->navigate = 1;
            }
        }
        $pimenkoquestionnaire->sid = $sid;
    }

    $pimenkoquestionnaire->timemodified = time();

    // May have to add extra stuff in here.
    if (empty($pimenkoquestionnaire->useopendate)) {
        $pimenkoquestionnaire->opendate = 0;
    }
    if (empty($pimenkoquestionnaire->useclosedate)) {
        $pimenkoquestionnaire->closedate = 0;
    }

    if ($pimenkoquestionnaire->resume == '1') {
        $pimenkoquestionnaire->resume = 1;
    } else {
        $pimenkoquestionnaire->resume = 0;
    }

    if (!$pimenkoquestionnaire->id = $DB->insert_record("pimenkoquestionnaire", $pimenkoquestionnaire)) {
        return false;
    }

    pimenkoquestionnaire_set_events($pimenkoquestionnaire);

    $completiontimeexpected = !empty($pimenkoquestionnaire->completionexpected) ? $pimenkoquestionnaire->completionexpected : null;
    \core_completion\api::update_completion_date_event($pimenkoquestionnaire->coursemodule, 'pimenkoquestionnaire',
        $pimenkoquestionnaire->id, $completiontimeexpected);

    return $pimenkoquestionnaire->id;
}

// Given an object containing all the necessary data,
// (defined by the form in mod.html) this function
// will update an existing instance with new data.
function pimenkoquestionnaire_update_instance($pimenkoquestionnaire) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/pimenkoquestionnaire/locallib.php');

    // Check the realm and set it to the survey if its set.
    if (!empty($pimenkoquestionnaire->sid) && !empty($pimenkoquestionnaire->realm)) {
        $DB->set_field('pimenkoquestionnaire_survey', 'realm', $pimenkoquestionnaire->realm, array('id' => $pimenkoquestionnaire->sid));
    }

    $pimenkoquestionnaire->timemodified = time();
    $pimenkoquestionnaire->id = $pimenkoquestionnaire->instance;

    // May have to add extra stuff in here.
    if (empty($pimenkoquestionnaire->useopendate)) {
        $pimenkoquestionnaire->opendate = 0;
    }
    if (empty($pimenkoquestionnaire->useclosedate)) {
        $pimenkoquestionnaire->closedate = 0;
    }

    if ($pimenkoquestionnaire->resume == '1') {
        $pimenkoquestionnaire->resume = 1;
    } else {
        $pimenkoquestionnaire->resume = 0;
    }

    // Get existing grade item.
    pimenkoquestionnaire_grade_item_update($pimenkoquestionnaire);

    pimenkoquestionnaire_set_events($pimenkoquestionnaire);

    $completiontimeexpected = !empty($pimenkoquestionnaire->completionexpected) ? $pimenkoquestionnaire->completionexpected : null;
    \core_completion\api::update_completion_date_event($pimenkoquestionnaire->coursemodule, 'pimenkoquestionnaire',
        $pimenkoquestionnaire->id, $completiontimeexpected);

    return $DB->update_record("pimenkoquestionnaire", $pimenkoquestionnaire);
}

// Given an ID of an instance of this module,
// this function will permanently delete the instance
// and any data that depends on it.
function pimenkoquestionnaire_delete_instance($id) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/pimenkoquestionnaire/locallib.php');

    if (! $pimenkoquestionnaire = $DB->get_record('pimenkoquestionnaire', array('id' => $id))) {
        return false;
    }

    $result = true;

    if ($events = $DB->get_records('event', array("modulename" => 'pimenkoquestionnaire', "instance" => $pimenkoquestionnaire->id))) {
        foreach ($events as $event) {
            $event = calendar_event::load($event);
            $event->delete();
        }
    }

    if (! $DB->delete_records('pimenkoquestionnaire', array('id' => $pimenkoquestionnaire->id))) {
        $result = false;
    }

    if ($survey = $DB->get_record('pimenkoquestionnaire_survey', array('id' => $pimenkoquestionnaire->sid))) {
        // If this survey is owned by this course, delete all of the survey records and responses.
        if ($survey->courseid == $pimenkoquestionnaire->course) {
            $result = $result && pimenkoquestionnaire_delete_survey($pimenkoquestionnaire->sid, $pimenkoquestionnaire->id);
        }
    }

    return $result;
}

// Return a small object with summary information about what a
// user has done with a given particular instance of this module
// Used for user activity reports.
// $return->time = the time they did it
// $return->info = a short text description.
/**
 * $course and $mod are unused, but API requires them. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function pimenkoquestionnaire_user_outline($course, $user, $mod, $pimenkoquestionnaire) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/pimenkoquestionnaire/locallib.php');

    $result = new stdClass();
    if ($responses = pimenkoquestionnaire_get_user_responses($pimenkoquestionnaire->id, $user->id, true)) {
        $n = count($responses);
        if ($n == 1) {
            $result->info = $n.' '.get_string("response", "pimenkoquestionnaire");
        } else {
            $result->info = $n.' '.get_string("responses", "pimenkoquestionnaire");
        }
        $lastresponse = array_pop($responses);
        $result->time = $lastresponse->submitted;
    } else {
        $result->info = get_string("noresponses", "pimenkoquestionnaire");
    }
    return $result;
}

// Print a detailed representation of what a  user has done with
// a given particular instance of this module, for user activity reports.
/**
 * $course and $mod are unused, but API requires them. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function pimenkoquestionnaire_user_complete($course, $user, $mod, $pimenkoquestionnaire) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/pimenkoquestionnaire/locallib.php');

    if ($responses = pimenkoquestionnaire_get_user_responses($pimenkoquestionnaire->id, $user->id, false)) {
        foreach ($responses as $response) {
            if ($response->complete == 'y') {
                echo get_string('submitted', 'pimenkoquestionnaire').' '.userdate($response->submitted).'<br />';
            } else {
                echo get_string('attemptstillinprogress', 'pimenkoquestionnaire').' '.userdate($response->submitted).'<br />';
            }
        }
    } else {
        print_string('noresponses', 'pimenkoquestionnaire');
    }

    return true;
}

// Given a course and a time, this module should find recent activity
// that has occurred in pimenkoquestionnaire activities and print it out.
// Return true if there was output, or false is there was none.
/**
 * $course, $isteacher and $timestart are unused, but API requires them. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function pimenkoquestionnaire_print_recent_activity($course, $isteacher, $timestart) {
    return false;  // True if anything was printed, otherwise false.
}

// Must return an array of grades for a given instance of this module,
// indexed by user.  It also returns a maximum allowed grade.
/**
 * $pimenkoquestionnaireid is unused, but API requires it. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function pimenkoquestionnaire_grades($pimenkoquestionnaireid) {
    return null;
}

/**
 * Return grade for given user or all users.
 *
 * @param int $pimenkoquestionnaireid id of assignment
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function pimenkoquestionnaire_get_user_grades($pimenkoquestionnaire, $userid=0) {
    global $DB;
    $params = array();
    $usersql = '';
    if (!empty($userid)) {
        $usersql = "AND u.id = ?";
        $params[] = $userid;
    }

    $sql = "SELECT r.id, u.id AS userid, r.grade AS rawgrade, r.submitted AS dategraded, r.submitted AS datesubmitted
            FROM {user} u, {pimenko_response} r
            WHERE u.id = r.userid AND r.pimenkoquestionnaireid = $pimenkoquestionnaire->id AND r.complete = 'y' $usersql";
    return $DB->get_records_sql($sql, $params);
}

/**
 * Update grades by firing grade_updated event
 *
 * @param object $assignment null means all assignments
 * @param int $userid specific user only, 0 mean all
 *
 * $nullifnone is unused, but API requires it. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function pimenkoquestionnaire_update_grades($pimenkoquestionnaire=null, $userid=0, $nullifnone=true) {
    global $CFG, $DB;

    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir.'/gradelib.php');
    }

    if ($pimenkoquestionnaire != null) {
        if ($graderecs = pimenkoquestionnaire_get_user_grades($pimenkoquestionnaire, $userid)) {
            $grades = array();
            foreach ($graderecs as $v) {
                if (!isset($grades[$v->userid])) {
                    $grades[$v->userid] = new stdClass();
                    if ($v->rawgrade == -1) {
                        $grades[$v->userid]->rawgrade = null;
                    } else {
                        $grades[$v->userid]->rawgrade = $v->rawgrade;
                    }
                    $grades[$v->userid]->userid = $v->userid;
                } else if (isset($grades[$v->userid]) && ($v->rawgrade > $grades[$v->userid]->rawgrade)) {
                    $grades[$v->userid]->rawgrade = $v->rawgrade;
                }
            }
            pimenkoquestionnaire_grade_item_update($pimenkoquestionnaire, $grades);
        } else {
            pimenkoquestionnaire_grade_item_update($pimenkoquestionnaire);
        }

    } else {
        $sql = "SELECT q.*, cm.idnumber as cmidnumber, q.course as courseid
                  FROM {pimenkoquestionnaire} q, {course_modules} cm, {modules} m
                 WHERE m.name='pimenkoquestionnaire' AND m.id=cm.module AND cm.instance=q.id";
        if ($rs = $DB->get_recordset_sql($sql)) {
            foreach ($rs as $pimenkoquestionnaire) {
                if ($pimenkoquestionnaire->grade != 0) {
                    pimenkoquestionnaire_update_grades($pimenkoquestionnaire);
                } else {
                    pimenkoquestionnaire_grade_item_update($pimenkoquestionnaire);
                }
            }
            $rs->close();
        }
    }
}

/**
 * Create grade item for given pimenkoquestionnaire
 *
 * @param object $pimenkoquestionnaire object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function pimenkoquestionnaire_grade_item_update($pimenkoquestionnaire, $grades = null) {
    global $CFG;
    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (!isset($pimenkoquestionnaire->courseid)) {
        $pimenkoquestionnaire->courseid = $pimenkoquestionnaire->course;
    }

    if ($pimenkoquestionnaire->cmidnumber != '') {
        $params = array('itemname' => $pimenkoquestionnaire->name, 'idnumber' => $pimenkoquestionnaire->cmidnumber);
    } else {
        $params = array('itemname' => $pimenkoquestionnaire->name);
    }

    if ($pimenkoquestionnaire->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $pimenkoquestionnaire->grade;
        $params['grademin']  = 0;

    } else if ($pimenkoquestionnaire->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$pimenkoquestionnaire->grade;

    } else if ($pimenkoquestionnaire->grade == 0) { // No Grade..be sure to delete the grade item if it exists.
        $grades = null;
        $params = array('deleted' => 1);

    } else {
        $params = null; // Allow text comments only.
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/pimenkoquestionnaire', $pimenkoquestionnaire->courseid, 'mod', 'pimenkoquestionnaire',
                    $pimenkoquestionnaire->id, 0, $grades, $params);
}

/**
 * This function returns if a scale is being used by one pimenkoquestionnaire
 * it it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 * @param $pimenkoquestionnaireid int
 * @param $scaleid int
 * @return boolean True if the scale is used by any pimenkoquestionnaire
 *
 * Function parameters are unused, but API requires them. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function pimenkoquestionnaire_scale_used ($pimenkoquestionnaireid, $scaleid) {
    return false;
}

/**
 * Checks if scale is being used by any instance of pimenkoquestionnaire
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any pimenkoquestionnaire
 *
 * Function parameters are unused, but API requires them. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function pimenkoquestionnaire_scale_used_anywhere($scaleid) {
    return false;
}

/**
 * Serves the pimenkoquestionnaire attachments. Implements needed access control ;-)
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - justsend the file
 *
 * $forcedownload is unused, but API requires it. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function pimenkoquestionnaire_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    $fileareas = ['intro', 'info', 'thankbody', 'question', 'feedbacknotes', 'sectionheading', 'feedback'];
    if (!in_array($filearea, $fileareas)) {
        return false;
    }

    $componentid = (int)array_shift($args);

    if ($filearea == 'question') {
        if (!$DB->record_exists('pimenko_question', ['id' => $componentid])) {
            return false;
        }
    } else if ($filearea == 'sectionheading') {
        if (!$DB->record_exists('pimenko_fb_sections', ['id' => $componentid])) {
            return false;
        }
    } else if ($filearea == 'feedback') {
        if (!$DB->record_exists('pimenko_feedbackections', ['id' => $componentid])) {
            return false;
        }
    } else {
        if (!$DB->record_exists('pimenkoquestionnaire_survey', ['id' => $componentid])) {
            return false;
        }
    }

    if (!$DB->record_exists('pimenkoquestionnaire', ['id' => $cm->instance])) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_pimenkoquestionnaire/$filearea/$componentid/$relativepath";
    if (!($file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
        return false;
    }

    // Finally send the file.
    send_stored_file($file, 0, 0, true); // Download MUST be forced - security!
}
/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $pimenkoquestionnairenode The node to add module settings to
 *
 * $settings is unused, but API requires it. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function pimenkoquestionnaire_extend_settings_navigation(settings_navigation $settings,
        navigation_node $pimenkoquestionnairenode) {

    global $PAGE, $DB, $USER, $CFG;
    $individualresponse = optional_param('individualresponse', false, PARAM_INT);
    $rid = optional_param('rid', false, PARAM_INT); // Response id.
    $currentgroupid = optional_param('group', 0, PARAM_INT); // Group id.

    require_once($CFG->dirroot.'/mod/pimenkoquestionnaire/pimenkoquestionnaire.class.php');

    $context = $PAGE->cm->context;
    $cmid = $PAGE->cm->id;
    $cm = $PAGE->cm;
    $course = $PAGE->course;

    if (! $pimenkoquestionnaire = $DB->get_record("pimenkoquestionnaire", array("id" => $cm->instance))) {
        print_error('invalidcoursemodule');
    }

    $courseid = $course->id;
    $pimenkoquestionnaire = new pimenkoquestionnaire(0, $pimenkoquestionnaire, $course, $cm);

    if ($owner = $DB->get_field('pimenkoquestionnaire_survey', 'courseid', ['id' => $pimenkoquestionnaire->sid])) {
        $owner = (trim($owner) == trim($courseid));
    } else {
        $owner = true;
    }

    // On view page, currentgroupid is not yet sent as an optional_param, so get it.
    $groupmode = groups_get_activity_groupmode($cm, $course);
    if ($groupmode > 0 && $currentgroupid == 0) {
        $currentgroupid = groups_get_activity_group($pimenkoquestionnaire->cm);
        if (!groups_is_member($currentgroupid, $USER->id)) {
            $currentgroupid = 0;
        }
    }

    // We want to add these new nodes after the Edit settings node, and before the
    // Locally assigned roles node. Of course, both of those are controlled by capabilities.
    $keys = $pimenkoquestionnairenode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if (($i === false) && array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (has_capability('mod/pimenkoquestionnaire:manage', $context) && $owner) {
        $url = '/mod/pimenkoquestionnaire/qsettings.php';
        $node = navigation_node::create(get_string('advancedsettings'),
            new moodle_url($url, array('id' => $cmid)),
            navigation_node::TYPE_SETTING, null, 'advancedsettings',
            new pix_icon('t/edit', ''));
        $pimenkoquestionnairenode->add_node($node, $beforekey);
    }

    if (has_capability('mod/pimenkoquestionnaire:editquestions', $context) && $owner) {
        $url = '/mod/pimenkoquestionnaire/questions.php';
        $node = navigation_node::create(get_string('questions', 'pimenkoquestionnaire'),
            new moodle_url($url, array('id' => $cmid)),
            navigation_node::TYPE_SETTING, null, 'questions',
            new pix_icon('t/edit', ''));
        $pimenkoquestionnairenode->add_node($node, $beforekey);
    }

    if (has_capability('mod/pimenkoquestionnaire:editquestions', $context) && $owner) {
        $url = '/mod/pimenkoquestionnaire/feedback.php';
        $node = navigation_node::create(get_string('feedback', 'pimenkoquestionnaire'),
            new moodle_url($url, array('id' => $cmid)),
            navigation_node::TYPE_SETTING, null, 'feedback',
            new pix_icon('t/edit', ''));
        $pimenkoquestionnairenode->add_node($node, $beforekey);
    }

    if (has_capability('mod/pimenkoquestionnaire:preview', $context)) {
        $url = '/mod/pimenkoquestionnaire/preview.php';
        $node = navigation_node::create(get_string('preview_label', 'pimenkoquestionnaire'),
            new moodle_url($url, array('id' => $cmid)),
            navigation_node::TYPE_SETTING, null, 'preview',
            new pix_icon('t/preview', ''));
        $pimenkoquestionnairenode->add_node($node, $beforekey);
    }

    if ($pimenkoquestionnaire->user_can_take($USER->id)) {
        $url = '/mod/pimenkoquestionnaire/complete.php';
        if ($pimenkoquestionnaire->user_has_saved_response($USER->id)) {
            $args = ['id' => $cmid, 'resume' => 1];
            $text = get_string('resumesurvey', 'pimenkoquestionnaire');
        } else {
            $args = ['id' => $cmid];
            $text = get_string('answerquestions', 'pimenkoquestionnaire');
        }
        $node = navigation_node::create($text, new moodle_url($url, $args),
            navigation_node::TYPE_SETTING, null, '', new pix_icon('i/info', 'answerquestions'));
        $pimenkoquestionnairenode->add_node($node, $beforekey);
    }
    $usernumresp = $pimenkoquestionnaire->count_submissions($USER->id);

    if ($pimenkoquestionnaire->capabilities->readownresponses && ($usernumresp > 0)) {
        $url = '/mod/pimenkoquestionnaire/myreport.php';

        if ($usernumresp > 1) {
            $urlargs = array('instance' => $pimenkoquestionnaire->id, 'userid' => $USER->id,
                'byresponse' => 0, 'action' => 'summary', 'group' => $currentgroupid);
            $node = navigation_node::create(get_string('yourresponses', 'pimenkoquestionnaire'),
                new moodle_url($url, $urlargs), navigation_node::TYPE_SETTING, null, 'yourresponses');
            $myreportnode = $pimenkoquestionnairenode->add_node($node, $beforekey);

            $urlargs = array('instance' => $pimenkoquestionnaire->id, 'userid' => $USER->id,
                'byresponse' => 0, 'action' => 'summary', 'group' => $currentgroupid);
            $myreportnode->add(get_string('summary', 'pimenkoquestionnaire'), new moodle_url($url, $urlargs));

            $urlargs = array('instance' => $pimenkoquestionnaire->id, 'userid' => $USER->id,
                'byresponse' => 1, 'action' => 'vresp', 'group' => $currentgroupid);
            $byresponsenode = $myreportnode->add(get_string('viewindividualresponse', 'pimenkoquestionnaire'),
                new moodle_url($url, $urlargs));

            $urlargs = array('instance' => $pimenkoquestionnaire->id, 'userid' => $USER->id,
                'byresponse' => 0, 'action' => 'vall', 'group' => $currentgroupid);
            $myreportnode->add(get_string('myresponses', 'pimenkoquestionnaire'), new moodle_url($url, $urlargs));
            if ($pimenkoquestionnaire->capabilities->downloadresponses) {
                $urlargs = array('instance' => $pimenkoquestionnaire->id, 'user' => $USER->id,
                    'action' => 'dwnpg', 'group' => $currentgroupid);
                $myreportnode->add(get_string('downloadtextformat', 'pimenkoquestionnaire'),
                    new moodle_url('/mod/pimenkoquestionnaire/report.php', $urlargs));
            }
        } else {
            $urlargs = array('instance' => $pimenkoquestionnaire->id, 'userid' => $USER->id,
                'byresponse' => 1, 'action' => 'vresp', 'group' => $currentgroupid);
            $node = navigation_node::create(get_string('yourresponse', 'pimenkoquestionnaire'),
                new moodle_url($url, $urlargs), navigation_node::TYPE_SETTING, null, 'yourresponse');
            $myreportnode = $pimenkoquestionnairenode->add_node($node, $beforekey);
        }
    }

    // If pimenkoquestionnaire is set to separate groups, prevent user who is not member of any group
    // and is not a non-editing teacher to view All responses.
    if ($pimenkoquestionnaire->can_view_all_responses($usernumresp)) {

        $url = '/mod/pimenkoquestionnaire/report.php';
        $node = navigation_node::create(get_string('viewallresponses', 'pimenkoquestionnaire'),
            new moodle_url($url, array('instance' => $pimenkoquestionnaire->id, 'action' => 'vall')),
            navigation_node::TYPE_SETTING, null, 'vall');
        $reportnode = $pimenkoquestionnairenode->add_node($node, $beforekey);

        if ($pimenkoquestionnaire->capabilities->viewsingleresponse) {
            $summarynode = $reportnode->add(get_string('summary', 'pimenkoquestionnaire'),
                new moodle_url('/mod/pimenkoquestionnaire/report.php',
                    array('instance' => $pimenkoquestionnaire->id, 'action' => 'vall')));
        } else {
            $summarynode = $reportnode;
        }
        $summarynode->add(get_string('order_default', 'pimenkoquestionnaire'),
            new moodle_url('/mod/pimenkoquestionnaire/report.php',
                array('instance' => $pimenkoquestionnaire->id, 'action' => 'vall', 'group' => $currentgroupid)));
        $summarynode->add(get_string('order_ascending', 'pimenkoquestionnaire'),
            new moodle_url('/mod/pimenkoquestionnaire/report.php',
                array('instance' => $pimenkoquestionnaire->id, 'action' => 'vallasort', 'group' => $currentgroupid)));
        $summarynode->add(get_string('order_descending', 'pimenkoquestionnaire'),
            new moodle_url('/mod/pimenkoquestionnaire/report.php',
                array('instance' => $pimenkoquestionnaire->id, 'action' => 'vallarsort', 'group' => $currentgroupid)));

        if ($pimenkoquestionnaire->capabilities->deleteresponses) {
            $summarynode->add(get_string('deleteallresponses', 'pimenkoquestionnaire'),
                new moodle_url('/mod/pimenkoquestionnaire/report.php',
                    array('instance' => $pimenkoquestionnaire->id, 'action' => 'delallresp', 'group' => $currentgroupid)));
        }

        if ($pimenkoquestionnaire->capabilities->downloadresponses) {
            $summarynode->add(get_string('downloadtextformat', 'pimenkoquestionnaire'),
                new moodle_url('/mod/pimenkoquestionnaire/report.php',
                    array('instance' => $pimenkoquestionnaire->id, 'action' => 'dwnpg', 'group' => $currentgroupid)));
        }
        if ($pimenkoquestionnaire->capabilities->viewsingleresponse) {
            $byresponsenode = $reportnode->add(get_string('viewbyresponse', 'pimenkoquestionnaire'),
                new moodle_url('/mod/pimenkoquestionnaire/report.php',
                    array('instance' => $pimenkoquestionnaire->id, 'action' => 'vresp', 'byresponse' => 1, 'group' => $currentgroupid)));

            $byresponsenode->add(get_string('view', 'pimenkoquestionnaire'),
                new moodle_url('/mod/pimenkoquestionnaire/report.php',
                    array('instance' => $pimenkoquestionnaire->id, 'action' => 'vresp', 'byresponse' => 1, 'group' => $currentgroupid)));

            if ($individualresponse) {
                $byresponsenode->add(get_string('deleteresp', 'pimenkoquestionnaire'),
                    new moodle_url('/mod/pimenkoquestionnaire/report.php',
                        array('instance' => $pimenkoquestionnaire->id, 'action' => 'dresp', 'byresponse' => 1,
                            'rid' => $rid, 'group' => $currentgroupid, 'individualresponse' => 1)));
            }
        }
    }

    $canviewgroups = true;
    $groupmode = groups_get_activity_groupmode($cm, $course);
    if ($groupmode == 1) {
        $canviewgroups = groups_has_membership($cm, $USER->id);
    }
    $canviewallgroups = has_capability('moodle/site:accessallgroups', $context);
    if ($pimenkoquestionnaire->capabilities->viewsingleresponse && ($canviewallgroups || $canviewgroups)) {
        $url = '/mod/pimenkoquestionnaire/show_nonrespondents.php';
        $node = navigation_node::create(get_string('show_nonrespondents', 'pimenkoquestionnaire'),
            new moodle_url($url, array('id' => $cmid)),
            navigation_node::TYPE_SETTING, null, 'nonrespondents');
        $pimenkoquestionnairenode->add_node($node, $beforekey);

    }
}

// Any other pimenkoquestionnaire functions go here.  Each of them must have a name that
// starts with pimenkoquestionnaire_.

function pimenkoquestionnaire_get_view_actions() {
    return array('view', 'view all');
}

function pimenkoquestionnaire_get_post_actions() {
    return array('submit', 'update');
}

function pimenkoquestionnaire_get_recent_mod_activity(&$activities, &$index, $timestart,
                $courseid, $cmid, $userid = 0, $groupid = 0) {

    global $CFG, $COURSE, $USER, $DB;
    require_once($CFG->dirroot . '/mod/pimenkoquestionnaire/locallib.php');
    require_once($CFG->dirroot.'/mod/pimenkoquestionnaire/pimenkoquestionnaire.class.php');

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', ['id' => $courseid]);
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];
    $pimenkoquestionnaire = $DB->get_record('pimenkoquestionnaire', ['id' => $cm->instance]);
    $pimenkoquestionnaire = new pimenkoquestionnaire(0, $pimenkoquestionnaire, $course, $cm);

    $context = context_module::instance($cm->id);
    $grader = has_capability('mod/pimenkoquestionnaire:viewsingleresponse', $context);

    // If this is a copy of a public pimenkoquestionnaire whose original is located in another course,
    // current user (teacher) cannot view responses.
    if ($grader) {
        // For a public pimenkoquestionnaire, look for the original public pimenkoquestionnaire that it is based on.
        if (!$pimenkoquestionnaire->survey_is_public_master()) {
            // For a public pimenkoquestionnaire, look for the original public pimenkoquestionnaire that it is based on.
            $originalpimenkoquestionnaire = $DB->get_record('pimenkoquestionnaire',
                ['sid' => $pimenkoquestionnaire->survey->id, 'course' => $pimenkoquestionnaire->survey->courseid]);
            $cmoriginal = get_coursemodule_from_instance("pimenkoquestionnaire", $originalpimenkoquestionnaire->id,
                $pimenkoquestionnaire->survey->courseid);
            $contextoriginal = context_course::instance($pimenkoquestionnaire->survey->courseid, MUST_EXIST);
            if (!has_capability('mod/pimenkoquestionnaire:viewsingleresponse', $contextoriginal)) {
                $tmpactivity = new stdClass();
                $tmpactivity->type = 'pimenkoquestionnaire';
                $tmpactivity->cmid = $cm->id;
                $tmpactivity->cannotview = true;
                $tmpactivity->anonymous = false;
                $activities[$index++] = $tmpactivity;
                return $activities;
            }
        }
    }

    if ($userid) {
        $userselect = "AND u.id = :userid";
        $params['userid'] = $userid;
    } else {
        $userselect = '';
    }

    if ($groupid) {
        $groupselect = 'AND gm.groupid = :groupid';
        $groupjoin   = 'JOIN {groups_members} gm ON  gm.userid=u.id';
        $params['groupid'] = $groupid;
    } else {
        $groupselect = '';
        $groupjoin   = '';
    }

    $params['timestart'] = $timestart;
    $params['pimenkoquestionnaireid'] = $pimenkoquestionnaire->id;

    $ufields = user_picture::fields('u', null, 'useridagain');
    if (!$attempts = $DB->get_records_sql("
                    SELECT qr.*,
                    {$ufields}
                    FROM {pimenko_response} qr
                    JOIN {user} u ON u.id = qr.userid
                    $groupjoin
                    WHERE qr.submitted > :timestart
                    AND qr.pimenkoquestionnaireid = :pimenkoquestionnaireid
                    $userselect
                    $groupselect
                    ORDER BY qr.submitted ASC", $params)) {
        return;
    }

    $accessallgroups = has_capability('moodle/site:accessallgroups', $context);
    $viewfullnames   = has_capability('moodle/site:viewfullnames', $context);
    $groupmode       = groups_get_activity_groupmode($cm, $course);

    $usersgroups = null;
    $aname = format_string($cm->name, true);
    $userattempts = array();
    foreach ($attempts as $attempt) {
        if ($pimenkoquestionnaire->respondenttype != 'anonymous') {
            if (!isset($userattempts[$attempt->lastname])) {
                $userattempts[$attempt->lastname] = 1;
            } else {
                $userattempts[$attempt->lastname]++;
            }
        }
        if ($attempt->userid != $USER->id) {
            if (!$grader) {
                // View complete individual responses permission required.
                continue;
            }

            if (($groupmode == SEPARATEGROUPS) && !$accessallgroups) {
                if ($usersgroups === null) {
                    $usersgroups = groups_get_all_groups($course->id,
                    $attempt->userid, $cm->groupingid);
                    if (is_array($usersgroups)) {
                        $usersgroups = array_keys($usersgroups);
                    } else {
                         $usersgroups = array();
                    }
                }
                if (!array_intersect($usersgroups, $modinfo->groups[$cm->id])) {
                    continue;
                }
            }
        }

        $tmpactivity = new stdClass();

        $tmpactivity->type       = 'pimenkoquestionnaire';
        $tmpactivity->cmid       = $cm->id;
        $tmpactivity->cminstance = $cm->instance;
        // Current user is admin - or teacher enrolled in original public course.
        if (isset($cmoriginal)) {
            $tmpactivity->cminstance = $cmoriginal->instance;
        }
        $tmpactivity->cannotview = false;
        $tmpactivity->anonymous  = false;
        $tmpactivity->name       = $aname;
        $tmpactivity->sectionnum = $cm->sectionnum;
        $tmpactivity->timestamp  = $attempt->submitted;
        $tmpactivity->groupid    = $groupid;
        if (isset($userattempts[$attempt->lastname])) {
            $tmpactivity->nbattempts = $userattempts[$attempt->lastname];
        }

        $tmpactivity->content = new stdClass();
        $tmpactivity->content->attemptid = $attempt->id;

        $userfields = explode(',', user_picture::fields());
        $tmpactivity->user = new stdClass();
        foreach ($userfields as $userfield) {
            if ($userfield == 'id') {
                $tmpactivity->user->{$userfield} = $attempt->userid;
            } else {
                if (!empty($attempt->{$userfield})) {
                    $tmpactivity->user->{$userfield} = $attempt->{$userfield};
                } else {
                    $tmpactivity->user->{$userfield} = null;
                }
            }
        }
        if ($pimenkoquestionnaire->respondenttype != 'anonymous') {
            $tmpactivity->user->fullname  = fullname($attempt, $viewfullnames);
        } else {
            $tmpactivity->user = '';
            unset ($tmpactivity->user);
            $tmpactivity->anonymous = true;
        }
        $activities[$index++] = $tmpactivity;
    }
}

/**
 * Prints all users who have completed a specified pimenkoquestionnaire since a given time
 *
 * @global object
 * @param object $activity
 * @param int $courseid
 * @param string $detail not used but needed for compability
 * @param array $modnames
 * @return void Output is echo'd
 *
 * $details and $modenames are unused, but API requires them. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function pimenkoquestionnaire_print_recent_mod_activity($activity, $courseid, $detail, $modnames) {
    global $OUTPUT;

    // If the pimenkoquestionnaire is "anonymous", then $activity->user won't have been set, so do not display respondent info.
    if ($activity->anonymous) {
        $stranonymous = ' ('.get_string('anonymous', 'pimenkoquestionnaire').')';
        $activity->nbattempts = '';
    } else {
        $stranonymous = '';
    }
    // Current user cannot view responses to public pimenkoquestionnaire.
    if ($activity->cannotview) {
        $strcannotview = get_string('cannotviewpublicresponses', 'pimenkoquestionnaire');
    }
    echo html_writer::start_tag('div');
    echo html_writer::start_tag('span', array('class' => 'clearfix',
                    'style' => 'margin-top:0px; background-color: white; display: inline-block;'));

    if (!$activity->anonymous && !$activity->cannotview) {
        echo html_writer::tag('div', $OUTPUT->user_picture($activity->user, array('courseid' => $courseid)),
                        array('style' => 'float: left; padding-right: 10px;'));
    }
    if (!$activity->cannotview) {
        echo html_writer::start_tag('div');
        echo html_writer::start_tag('div');

        $urlparams = array('action' => 'vresp', 'instance' => $activity->cminstance,
                        'group' => $activity->groupid, 'rid' => $activity->content->attemptid, 'individualresponse' => 1);

        $context = context_module::instance($activity->cmid);
        if (has_capability('mod/pimenkoquestionnaire:viewsingleresponse', $context)) {
            $report = 'report.php';
        } else {
            $report = 'myreport.php';
        }
        echo html_writer::tag('a', get_string('response', 'pimenkoquestionnaire').' '.$activity->nbattempts.$stranonymous,
                        array('href' => new moodle_url('/mod/pimenkoquestionnaire/'.$report, $urlparams)));
        echo html_writer::end_tag('div');
    } else {
        echo html_writer::start_tag('div');
        echo html_writer::start_tag('div');
        echo html_writer::tag('div', $strcannotview);
        echo html_writer::end_tag('div');
    }
    if (!$activity->anonymous  && !$activity->cannotview) {
        $url = new moodle_url('/user/view.php', array('course' => $courseid, 'id' => $activity->user->id));
        $name = $activity->user->fullname;
        $link = html_writer::link($url, $name);
        echo html_writer::start_tag('div', array('class' => 'user'));
        echo $link .' - '. userdate($activity->timestamp);
        echo html_writer::end_tag('div');
    }

    echo html_writer::end_tag('div');
    echo html_writer::end_tag('span');
    echo html_writer::end_tag('div');

    return;
}

/**
 * Prints pimenkoquestionnaire summaries on 'My home' page
 *
 * Prints pimenkoquestionnaire name, due date and attempt information on
 * pimenkoquestionnaires that have a deadline that has not already passed
 * and it is available for taking.
 *
 * @global object
 * @global stdClass
 * @global object
 * @uses CONTEXT_MODULE
 * @param array $courses An array of course objects to get pimenkoquestionnaire instances from
 * @param array $htmlarray Store overview output array( course ID => 'pimenkoquestionnaire' => HTML output )
 * @return void
 */
function pimenkoquestionnaire_print_overview($courses, &$htmlarray) {
    global $USER, $CFG, $DB, $OUTPUT;

    require_once($CFG->dirroot . '/mod/pimenkoquestionnaire/locallib.php');

    if (!$pimenkoquestionnaires = get_all_instances_in_courses('pimenkoquestionnaire', $courses)) {
        return;
    }

    // Get Necessary Strings.
    $strpimenkoquestionnaire       = get_string('modulename', 'pimenkoquestionnaire');
    $strnotattempted = get_string('noattempts', 'pimenkoquestionnaire');
    $strattempted    = get_string('attempted', 'pimenkoquestionnaire');
    $strsavedbutnotsubmitted = get_string('savedbutnotsubmitted', 'pimenkoquestionnaire');

    $now = time();
    foreach ($pimenkoquestionnaires as $pimenkoquestionnaire) {

        // The pimenkoquestionnaire has a deadline.
        if (($pimenkoquestionnaire->closedate != 0)
                        // And it is before the deadline has been met.
                        && ($pimenkoquestionnaire->closedate >= $now)
                        // And the pimenkoquestionnaire is available.
                        && (($pimenkoquestionnaire->opendate == 0) || ($pimenkoquestionnaire->opendate <= $now))) {
            if (!$pimenkoquestionnaire->visible) {
                $class = ' class="dimmed"';
            } else {
                $class = '';
            }
            $str = $OUTPUT->box("$strpimenkoquestionnaire:
                            <a$class href=\"$CFG->wwwroot/mod/pimenkoquestionnaire/view.php?id=$pimenkoquestionnaire->coursemodule\">".
                            format_string($pimenkoquestionnaire->name).'</a>', 'name');

            // Deadline.
            $str .= $OUTPUT->box(get_string('closeson', 'pimenkoquestionnaire', userdate($pimenkoquestionnaire->closedate)), 'info');
            $attempts = $DB->get_records('pimenko_response',
                ['pimenkoquestionnaireid' => $pimenkoquestionnaire->id, 'userid' => $USER->id, 'complete' => 'y']);
            $nbattempts = count($attempts);

            // Do not display a pimenkoquestionnaire as due if it can only be sumbitted once and it has already been submitted!
            if ($nbattempts != 0 && $pimenkoquestionnaire->qtype == QUESTIONNAIREONCE) {
                continue;
            }

            // Attempt information.
            if (has_capability('mod/pimenkoquestionnaire:manage', context_module::instance($pimenkoquestionnaire->coursemodule))) {
                // Number of user attempts.
                $attempts = $DB->count_records('pimenko_response',
                    ['pimenkoquestionnaireid' => $pimenkoquestionnaire->id, 'complete' => 'y']);
                $str .= $OUTPUT->box(get_string('numattemptsmade', 'pimenkoquestionnaire', $attempts), 'info');
            } else {
                if ($responses = pimenkoquestionnaire_get_user_responses($pimenkoquestionnaire->id, $USER->id, false)) {
                    foreach ($responses as $response) {
                        if ($response->complete == 'y') {
                            $str .= $OUTPUT->box($strattempted, 'info');
                            break;
                        } else {
                            $str .= $OUTPUT->box($strsavedbutnotsubmitted, 'info');
                        }
                    }
                } else {
                    $str .= $OUTPUT->box($strnotattempted, 'info');
                }
            }
            $str = $OUTPUT->box($str, 'pimenkoquestionnaire overview');

            if (empty($htmlarray[$pimenkoquestionnaire->course]['pimenkoquestionnaire'])) {
                $htmlarray[$pimenkoquestionnaire->course]['pimenkoquestionnaire'] = $str;
            } else {
                $htmlarray[$pimenkoquestionnaire->course]['pimenkoquestionnaire'] .= $str;
            }
        }
    }
}


/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the pimenkoquestionnaire.
 *
 * @param $mform the course reset form that is being built.
 */
function pimenkoquestionnaire_reset_course_form_definition($mform) {
    $mform->addElement('header', 'pimenkoquestionnaireheader', get_string('modulenameplural', 'pimenkoquestionnaire'));
    $mform->addElement('advcheckbox', 'reset_pimenkoquestionnaire',
                    get_string('removeallpimenkoquestionnaireattempts', 'pimenkoquestionnaire'));
}

/**
 * Course reset form defaults.
 * @return array the defaults.
 *
 * Function parameters are unused, but API requires them. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function pimenkoquestionnaire_reset_course_form_defaults($course) {
    return array('reset_pimenkoquestionnaire' => 1);
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * pimenkoquestionnaire responses for course $data->courseid.
 *
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function pimenkoquestionnaire_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/questionlib.php');
    require_once($CFG->dirroot.'/mod/pimenkoquestionnaire/locallib.php');

    $componentstr = get_string('modulenameplural', 'pimenkoquestionnaire');
    $status = array();

    if (!empty($data->reset_pimenkoquestionnaire)) {
        $surveys = pimenkoquestionnaire_get_survey_list($data->courseid, '');

        // Delete responses.
        foreach ($surveys as $survey) {
            // Get all responses for this pimenkoquestionnaire.
            $sql = "SELECT qr.id, qr.pimenkoquestionnaireid, qr.submitted, qr.userid, q.sid
                 FROM {pimenkoquestionnaire} q
                 INNER JOIN {pimenko_response} qr ON q.id = qr.pimenkoquestionnaireid
                 WHERE q.sid = ?
                 ORDER BY qr.id";
            $resps = $DB->get_records_sql($sql, [$survey->id]);
            if (!empty($resps)) {
                $pimenkoquestionnaire = $DB->get_record("pimenkoquestionnaire", ["sid" => $survey->id, "course" => $survey->courseid]);
                $pimenkoquestionnaire->course = $DB->get_record("course", array("id" => $pimenkoquestionnaire->course));
                foreach ($resps as $response) {
                    pimenkoquestionnaire_delete_response($response, $pimenkoquestionnaire);
                }
            }
            // Remove this pimenkoquestionnaire's grades (and feedback) from gradebook (if any).
            $select = "itemmodule = 'pimenkoquestionnaire' AND iteminstance = ".$survey->qid;
            $fields = 'id';
            if ($itemid = $DB->get_record_select('grade_items', $select, null, $fields)) {
                $itemid = $itemid->id;
                $DB->delete_records_select('grade_grades', 'itemid = '.$itemid);

            }
        }
        $status[] = array(
                        'component' => $componentstr,
                        'item' => get_string('deletedallresp', 'pimenkoquestionnaire'),
                        'error' => false);

        $status[] = array(
                        'component' => $componentstr,
                        'item' => get_string('gradesdeleted', 'pimenkoquestionnaire'),
                        'error' => false);
    }
    return $status;
}

/**
 * Obtains the automatic completion state for this pimenkoquestionnaire based on the condition
 * in pimenkoquestionnaire settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 *
 * $course is unused, but API requires it. Suppress PHPMD warning.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function pimenkoquestionnaire_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    // Get pimenkoquestionnaire details.
    $pimenkoquestionnaire = $DB->get_record('pimenkoquestionnaire', array('id' => $cm->instance), '*', MUST_EXIST);

    // If completion option is enabled, evaluate it and return true/false.
    if ($pimenkoquestionnaire->completionsubmit) {
        $params = ['userid' => $userid, 'pimenkoquestionnaireid' => $pimenkoquestionnaire->id, 'complete' => 'y'];
        return $DB->record_exists('pimenko_response', $params);
    } else {
        // Completion option is not enabled so just return $type.
        return $type;
    }
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_pimenkoquestionnaire_core_calendar_provide_event_action(calendar_event $event,
                                                            \core_calendar\action_factory $factory) {
    $cm = get_fast_modinfo($event->courseid)->instances['pimenkoquestionnaire'][$event->instance];

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
            get_string('view'),
            new \moodle_url('/mod/pimenkoquestionnaire/view.php', ['id' => $cm->id]),
            1,
            true
    );
}

