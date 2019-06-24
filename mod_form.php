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
 * print the form to add or edit a pimenkoquestionnaire-instance
 *
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package pimenkoquestionnaire
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/pimenkoquestionnaire/pimenkoquestionnaire.class.php');
require_once($CFG->dirroot.'/mod/pimenkoquestionnaire/locallib.php');

class mod_pimenkoquestionnaire_mod_form extends moodleform_mod {

    protected function definition() {
        global $COURSE;
        global $pimenkoquestionnairetypes, $pimenkoquestionnairerespondents, $pimenkoquestionnaireresponseviewers, $autonumbering;

        $pimenkoquestionnaire = new pimenkoquestionnaire($this->_instance, null, $COURSE, $this->_cm);

        $mform    =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'pimenkoquestionnaire'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements(get_string('description'));

        $mform->addElement('header', 'timinghdr', get_string('timing', 'form'));

        $enableopengroup = array();
        $enableopengroup[] =& $mform->createElement('checkbox', 'useopendate', get_string('opendate', 'pimenkoquestionnaire'));
        $enableopengroup[] =& $mform->createElement('date_time_selector', 'opendate', '');
        $mform->addGroup($enableopengroup, 'enableopengroup', get_string('opendate', 'pimenkoquestionnaire'), ' ', false);
        $mform->addHelpButton('enableopengroup', 'opendate', 'pimenkoquestionnaire');
        $mform->disabledIf('enableopengroup', 'useopendate', 'notchecked');

        $enableclosegroup = array();
        $enableclosegroup[] =& $mform->createElement('checkbox', 'useclosedate', get_string('closedate', 'pimenkoquestionnaire'));
        $enableclosegroup[] =& $mform->createElement('date_time_selector', 'closedate', '');
        $mform->addGroup($enableclosegroup, 'enableclosegroup', get_string('closedate', 'pimenkoquestionnaire'), ' ', false);
        $mform->addHelpButton('enableclosegroup', 'closedate', 'pimenkoquestionnaire');
        $mform->disabledIf('enableclosegroup', 'useclosedate', 'notchecked');

        $mform->addElement('header', 'pimenkoquestionnairehdr', get_string('responseoptions', 'pimenkoquestionnaire'));

        $mform->addElement('select', 'qtype', get_string('qtype', 'pimenkoquestionnaire'), $pimenkoquestionnairetypes);
        $mform->addHelpButton('qtype', 'qtype', 'pimenkoquestionnaire');

        $mform->addElement('hidden', 'cannotchangerespondenttype');
        $mform->setType('cannotchangerespondenttype', PARAM_INT);
        $mform->addElement('select', 'respondenttype', get_string('respondenttype', 'pimenkoquestionnaire'), $pimenkoquestionnairerespondents);
        $mform->addHelpButton('respondenttype', 'respondenttype', 'pimenkoquestionnaire');
        $mform->disabledIf('respondenttype', 'cannotchangerespondenttype', 'eq', 1);

        $mform->addElement('select', 'resp_view', get_string('responseview', 'pimenkoquestionnaire'), $pimenkoquestionnaireresponseviewers);
        $mform->addHelpButton('resp_view', 'responseview', 'pimenkoquestionnaire');

        $notificationoptions = array(0 => get_string('no'), 1 => get_string('notificationsimple', 'pimenkoquestionnaire'),
            2 => get_string('notificationfull', 'pimenkoquestionnaire'));
        $mform->addElement('select', 'notifications', get_string('notifications', 'pimenkoquestionnaire'), $notificationoptions);
        $mform->addHelpButton('notifications', 'notifications', 'pimenkoquestionnaire');

        $options = array('0' => get_string('no'), '1' => get_string('yes'));
        $mform->addElement('select', 'resume', get_string('resume', 'pimenkoquestionnaire'), $options);
        $mform->addHelpButton('resume', 'resume', 'pimenkoquestionnaire');

        $options = array('0' => get_string('no'), '1' => get_string('yes'));
        $mform->addElement('select', 'navigate', get_string('navigate', 'pimenkoquestionnaire'), $options);
        $mform->addHelpButton('navigate', 'navigate', 'pimenkoquestionnaire');

        $mform->addElement('select', 'autonum', get_string('autonumbering', 'pimenkoquestionnaire'), $autonumbering);
        $mform->addHelpButton('autonum', 'autonumbering', 'pimenkoquestionnaire');
        // Default = autonumber both questions and pages.
        $mform->setDefault('autonum', 3);

        // Removed potential scales from list of grades. CONTRIB-3167.
        $grades[0] = get_string('nograde');
        for ($i = 100; $i >= 1; $i--) {
            $grades[$i] = $i;
        }
        $mform->addElement('select', 'grade', get_string('grade', 'pimenkoquestionnaire'), $grades);

        if (empty($pimenkoquestionnaire->sid)) {
            if (!isset($pimenkoquestionnaire->id)) {
                $pimenkoquestionnaire->id = 0;
            }

            $mform->addElement('header', 'contenthdr', get_string('contentoptions', 'pimenkoquestionnaire'));
            $mform->addHelpButton('contenthdr', 'createcontent', 'pimenkoquestionnaire');

            $mform->addElement('radio', 'create', get_string('createnew', 'pimenkoquestionnaire'), '', 'new-0');

            // Retrieve existing private pimenkoquestionnaires from current course.
            $surveys = pimenkoquestionnaire_get_survey_select($COURSE->id, 'private');
            if (!empty($surveys)) {
                $prelabel = get_string('useprivate', 'pimenkoquestionnaire');
                foreach ($surveys as $value => $label) {
                    $mform->addElement('radio', 'create', $prelabel, $label, $value);
                    $prelabel = '';
                }
            }
            // Retrieve existing template pimenkoquestionnaires from this site.
            $surveys = pimenkoquestionnaire_get_survey_select($COURSE->id, 'template');
            if (!empty($surveys)) {
                $prelabel = get_string('usetemplate', 'pimenkoquestionnaire');
                foreach ($surveys as $value => $label) {
                    $mform->addElement('radio', 'create', $prelabel, $label, $value);
                    $prelabel = '';
                }
            } else {
                $mform->addElement('static', 'usetemplate', get_string('usetemplate', 'pimenkoquestionnaire'),
                                '('.get_string('notemplatesurveys', 'pimenkoquestionnaire').')');
            }

            // Retrieve existing public pimenkoquestionnaires from this site.
            $surveys = pimenkoquestionnaire_get_survey_select($COURSE->id, 'public');
            if (!empty($surveys)) {
                $prelabel = get_string('usepublic', 'pimenkoquestionnaire');
                foreach ($surveys as $value => $label) {
                    $mform->addElement('radio', 'create', $prelabel, $label, $value);
                    $prelabel = '';
                }
            } else {
                $mform->addElement('static', 'usepublic', get_string('usepublic', 'pimenkoquestionnaire'),
                                   '('.get_string('nopublicsurveys', 'pimenkoquestionnaire').')');
            }

            $mform->setDefault('create', 'new-0');
        }

        $this->standard_coursemodule_elements();

        // Buttons.
        $this->add_action_buttons();
    }

    public function data_preprocessing(&$defaultvalues) {
        global $DB;
        if (empty($defaultvalues['opendate'])) {
            $defaultvalues['useopendate'] = 0;
        } else {
            $defaultvalues['useopendate'] = 1;
        }
        if (empty($defaultvalues['closedate'])) {
            $defaultvalues['useclosedate'] = 0;
        } else {
            $defaultvalues['useclosedate'] = 1;
        }
        // Prevent pimenkoquestionnaire set to "anonymous" to be reverted to "full name".
        $defaultvalues['cannotchangerespondenttype'] = 0;
        if (!empty($defaultvalues['respondenttype']) && $defaultvalues['respondenttype'] == "anonymous") {
            // If this pimenkoquestionnaire has responses.
            $numresp = $DB->count_records('pimenko_response',
                            array('pimenkoquestionnaireid' => $defaultvalues['instance'], 'complete' => 'y'));
            if ($numresp) {
                $defaultvalues['cannotchangerespondenttype'] = 1;
            }
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }

    public function add_completion_rules() {
        $mform =& $this->_form;
        $mform->addElement('checkbox', 'completionsubmit', '', get_string('completionsubmit', 'pimenkoquestionnaire'));
        return array('completionsubmit');
    }

    public function completion_rule_enabled($data) {
        return !empty($data['completionsubmit']);
    }

}