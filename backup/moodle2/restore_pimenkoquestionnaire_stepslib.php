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
 * @package    mod_pimenkoquestionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define all the restore steps that will be used by the restore_pimenkoquestionnaire_activity_task
 */

/**
 * Structure step to restore one pimenkoquestionnaire activity
 */
class restore_pimenkoquestionnaire_activity_structure_step extends restore_activity_structure_step {

    /**
     * @var array $olddependquestions Contains any question id's with dependencies.
     */
    protected $olddependquestions = [];

    /**
     * @var array $olddependchoices Contains any choice id's for questions with dependencies.
     */
    protected $olddependchoices = [];

    /**
     * @var array $olddependencies Contains the old id's from the dependencies array.
     */
    protected $olddependencies = [];

    protected function define_structure() {

        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('pimenkoquestionnaire', '/activity/pimenkoquestionnaire');
        $paths[] = new restore_path_element('pimenkoquestionnaire_survey', '/activity/pimenkoquestionnaire/surveys/survey');
        $paths[] = new restore_path_element('pimenko_fb_sections',
                '/activity/pimenkoquestionnaire/surveys/survey/fb_sections/fb_section');
        $paths[] = new restore_path_element('pimenko_feedbackections',
                '/activity/pimenkoquestionnaire/surveys/survey/fb_sections/fb_section/feedbacks/feedback');
        $paths[] = new restore_path_element('pimenko_question',
                '/activity/pimenkoquestionnaire/surveys/survey/questions/question');
        $paths[] = new restore_path_element('pimenko_quest_choice',
                '/activity/pimenkoquestionnaire/surveys/survey/questions/question/quest_choices/quest_choice');
        $paths[] = new restore_path_element('pimenko_dependency',
                '/activity/pimenkoquestionnaire/surveys/survey/questions/question/quest_dependencies/quest_dependency');

        if ($userinfo) {
            if ($this->task->get_old_moduleversion() < 2018050102) {
                // Old system.
                $paths[] =
                        new restore_path_element('pimenkoquestionnaire_attempt', '/activity/pimenkoquestionnaire/attempts/attempt');
                $paths[] = new restore_path_element('pimenko_response',
                        '/activity/pimenkoquestionnaire/attempts/attempt/responses/response');
                $paths[] = new restore_path_element('pimenko_response_bool',
                        '/activity/pimenkoquestionnaire/attempts/attempt/responses/response/response_bools/response_bool');
                $paths[] = new restore_path_element('pimenko_response_date',
                        '/activity/pimenkoquestionnaire/attempts/attempt/responses/response/response_dates/response_date');
                $paths[] = new restore_path_element('pimenko_response_multiple',
                        '/activity/pimenkoquestionnaire/attempts/attempt/responses/response/response_multiples/response_multiple');
                $paths[] = new restore_path_element('pimenko_response_other',
                        '/activity/pimenkoquestionnaire/attempts/attempt/responses/response/response_others/response_other');
                $paths[] = new restore_path_element('pimenko_response_rank',
                        '/activity/pimenkoquestionnaire/attempts/attempt/responses/response/response_ranks/response_rank');
                $paths[] = new restore_path_element('pimenko_response_single',
                        '/activity/pimenkoquestionnaire/attempts/attempt/responses/response/response_singles/response_single');
                $paths[] = new restore_path_element('pimenko_response_text',
                        '/activity/pimenkoquestionnaire/attempts/attempt/responses/response/response_texts/response_text');

            } else {
                // New system.
                $paths[] = new restore_path_element('pimenko_response', '/activity/pimenkoquestionnaire/responses/response');
                $paths[] = new restore_path_element('pimenko_response_bool',
                        '/activity/pimenkoquestionnaire/responses/response/response_bools/response_bool');
                $paths[] = new restore_path_element('pimenko_response_date',
                        '/activity/pimenkoquestionnaire/responses/response/response_dates/response_date');
                $paths[] = new restore_path_element('pimenko_response_multiple',
                        '/activity/pimenkoquestionnaire/responses/response/response_multiples/response_multiple');
                $paths[] = new restore_path_element('pimenko_response_other',
                        '/activity/pimenkoquestionnaire/responses/response/response_others/response_other');
                $paths[] = new restore_path_element('pimenko_response_rank',
                        '/activity/pimenkoquestionnaire/responses/response/response_ranks/response_rank');
                $paths[] = new restore_path_element('pimenko_response_single',
                        '/activity/pimenkoquestionnaire/responses/response/response_singles/response_single');
                $paths[] = new restore_path_element('pimenko_response_text',
                        '/activity/pimenkoquestionnaire/responses/response/response_texts/response_text');
            }
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function after_execute() {
        global $DB;

        // Process any question dependencies after all questions and choices have already been processed to ensure we have all of
        // the new id's.

        // First, process any old system question dependencies into the new system.
        foreach ($this->olddependquestions as $newid => $olddependid) {
            $newrec = new stdClass();
            $newrec->questionid = $newid;
            $newrec->surveyid = $this->get_new_parentid('pimenkoquestionnaire_survey');
            $newrec->dependquestionid = $this->get_mappingid('pimenko_question', $olddependid);
            // Only change mapping for RADIO and DROP question types, not for YESNO question.
            $dependqtype = $DB->get_field('pimenko_question', 'type_id', ['id' => $newrec->dependquestionid]);
            if (($dependqtype !== false) && ($dependqtype != 1)) {
                $newrec->dependchoiceid = $this->get_mappingid('pimenko_quest_choice',
                        $this->olddependchoices[$newid]);
            } else {
                $newrec->dependchoiceid = $this->olddependchoices[$newid];
            }
            $newrec->dependlogic = 1; // Set to "answer given", previously the only option.
            $newrec->dependandor = 'and'; // Not used previously.
            $DB->insert_record('pimenko_dependency', $newrec);
        }

        // Next process all new system dependencies.
        foreach ($this->olddependencies as $data) {
            $data->dependquestionid = $this->get_mappingid('pimenko_question', $data->dependquestionid);

            // Only change mapping for RADIO and DROP question types, not for YESNO question.
            $dependqtype = $DB->get_field('pimenko_question', 'type_id', ['id' => $data->dependquestionid]);
            if (($dependqtype !== false) && ($dependqtype != 1)) {
                $data->dependchoiceid = $this->get_mappingid('pimenko_quest_choice', $data->dependchoiceid);
            }
            $DB->insert_record('pimenko_dependency', $data);
        }

        // Add pimenkoquestionnaire related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_pimenkoquestionnaire', 'intro', null);
        $this->add_related_files('mod_pimenkoquestionnaire', 'info', 'pimenkoquestionnaire_survey');
        $this->add_related_files('mod_pimenkoquestionnaire', 'thankbody', 'pimenkoquestionnaire_survey');
        $this->add_related_files('mod_pimenkoquestionnaire', 'feedbacknotes', 'pimenkoquestionnaire_survey');
        $this->add_related_files('mod_pimenkoquestionnaire', 'question', 'pimenko_question');
        $this->add_related_files('mod_pimenkoquestionnaire', 'sectionheading', 'pimenko_fb_sections');
        $this->add_related_files('mod_pimenkoquestionnaire', 'feedback', 'pimenko_feedbackections');
    }

    protected function process_pimenkoquestionnaire( $data ) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        $data->opendate = $this->apply_date_offset($data->opendate);
        $data->closedate = $this->apply_date_offset($data->closedate);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert the pimenkoquestionnaire record.
        $newitemid = $DB->insert_record('pimenkoquestionnaire', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    protected function process_pimenkoquestionnaire_survey( $data ) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->courseid = $this->get_courseid();

        // Check for a 'feedbacksections' value larger than 2, and limit it to 2. As of 3.5.1 this has a different meaning.
        if ($data->feedbacksections > 2) {
            $data->feedbacksections = 2;
        }

        // Insert the pimenkoquestionnaire_survey record.
        $newitemid = $DB->insert_record('pimenkoquestionnaire_survey', $data);
        $this->set_mapping('pimenkoquestionnaire_survey', $oldid, $newitemid, true);

        // Update the pimenkoquestionnaire record we just created with the new survey id.
        $DB->set_field('pimenkoquestionnaire', 'sid', $newitemid, ['id' => $this->get_new_parentid('pimenkoquestionnaire')]);
    }

    protected function process_pimenko_question( $data ) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->surveyid = $this->get_new_parentid('pimenkoquestionnaire_survey');

        // Insert the pimenko_question record.
        $newitemid = $DB->insert_record('pimenko_question', $data);
        $this->set_mapping('pimenko_question', $oldid, $newitemid, true);

        if (isset($data->dependquestion) && ($data->dependquestion > 0)) {
            // Questions using the old dependency system will need to be processed and restored using the new system.
            // See CONTRIB-6787.
            $this->olddependquestions[$newitemid] = $data->dependquestion;
            $this->olddependchoices[$newitemid] = $data->dependchoice;
        }
    }

    /**
     * $qid is unused, but is needed in order to get the $key elements of the array. Suppress PHPMD warning.
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    protected function process_pimenko_fb_sections( $data ) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->surveyid = $this->get_new_parentid('pimenkoquestionnaire_survey');

        // If this pimenkoquestionnaire has separate sections feedbacks.
        if (isset($data->scorecalculation)) {
            $scorecalculation = unserialize($data->scorecalculation);
            $newscorecalculation = [];
            foreach ($scorecalculation as $qid => $val) {
                $newqid = $this->get_mappingid('pimenko_question', $qid);
                $newscorecalculation[$newqid] = $val;
            }
            $data->scorecalculation = serialize($newscorecalculation);
        }

        // Insert the pimenko_fb_sections record.
        $newitemid = $DB->insert_record('pimenko_fb_sections', $data);
        $this->set_mapping('pimenko_fb_sections', $oldid, $newitemid, true);
    }

    protected function process_pimenko_feedbackections( $data ) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->sectionid = $this->get_new_parentid('pimenko_fb_sections');

        // Insert the pimenko_feedbackections record.
        $newitemid = $DB->insert_record('pimenko_feedbackections', $data);
        $this->set_mapping('pimenko_feedbackections', $oldid, $newitemid, true);
    }

    protected function process_pimenko_quest_choice( $data ) {
        global $CFG, $DB;

        $data = (object) $data;

        // Replace the = separator with :: separator in quest_choice content.
        // This fixes radio button options using old "value"="display" formats.
        require_once($CFG->dirroot . '/mod/pimenkoquestionnaire/locallib.php');

        // Some old systems had '' instead of NULL. Change it to NULL.
        if ($data->value === '') {
            $data->value = null;
        }

        if (($data->value == null || $data->value == 'NULL') && !preg_match("/^([0-9]{1,3}=.*|!other=.*)$/", $data->content)) {
            $content = pimenkoquestionnaire_choice_values($data->content);
            if (strpos($content->text, '=')) {
                $data->content = str_replace('=', '::', $content->text);
            }
        }

        $oldid = $data->id;
        $data->question_id = $this->get_new_parentid('pimenko_question');

        // Insert the pimenko_quest_choice record.
        $newitemid = $DB->insert_record('pimenko_quest_choice', $data);
        $this->set_mapping('pimenko_quest_choice', $oldid, $newitemid);
    }

    protected function process_pimenko_dependency( $data ) {
        $data = (object) $data;

        $data->questionid = $this->get_new_parentid('pimenko_question');
        $data->surveyid = $this->get_new_parentid('pimenkoquestionnaire_survey');

        if (isset($data)) {
            $this->olddependencies[] = $data;
        }
    }

    protected function process_pimenkoquestionnaire_attempt( $data ) {
        // New structure will be completed in process_pimenko_response. Nothing to do here any more.
        return true;
    }

    protected function process_pimenko_response( $data ) {
        global $DB;

        $data = (object) $data;

        // Older versions of pimenkoquestionnaire used 'username' instead of 'userid'. If 'username' exists, change it to 'userid'.
        if (isset($data->username) && !isset($data->userid)) {
            $data->userid = $data->username;
        }

        $oldid = $data->id;
        $data->pimenkoquestionnaireid = $this->get_new_parentid('pimenkoquestionnaire');
        $data->userid = $this->get_mappingid('user', $data->userid);

        // Insert the pimenko_response record.
        $newitemid = $DB->insert_record('pimenko_response', $data);
        $this->set_mapping('pimenko_response', $oldid, $newitemid);
    }

    protected function process_pimenko_response_bool( $data ) {
        global $DB;

        $data = (object) $data;
        $data->response_id = $this->get_new_parentid('pimenko_response');
        $data->question_id = $this->get_mappingid('pimenko_question', $data->question_id);

        // Insert the pimenko_response_bool record.
        $DB->insert_record('pimenko_response_bool', $data);
    }

    protected function process_pimenko_response_date( $data ) {
        global $DB;

        $data = (object) $data;
        $data->response_id = $this->get_new_parentid('pimenko_response');
        $data->question_id = $this->get_mappingid('pimenko_question', $data->question_id);

        // Insert the pimenko_response_date record.
        $DB->insert_record('pimenko_response_date', $data);
    }

    protected function process_pimenko_response_multiple( $data ) {
        global $DB;

        $data = (object) $data;
        $data->response_id = $this->get_new_parentid('pimenko_response');
        $data->question_id = $this->get_mappingid('pimenko_question', $data->question_id);
        $data->choice_id = $this->get_mappingid('pimenko_quest_choice', $data->choice_id);

        // Insert the pimenko_resp_multiple record.
        $DB->insert_record('pimenko_resp_multiple', $data);
    }

    protected function process_pimenko_response_other( $data ) {
        global $DB;

        $data = (object) $data;
        $data->response_id = $this->get_new_parentid('pimenko_response');
        $data->question_id = $this->get_mappingid('pimenko_question', $data->question_id);
        $data->choice_id = $this->get_mappingid('pimenko_quest_choice', $data->choice_id);

        // Insert the pimenko_response_other record.
        $DB->insert_record('pimenko_response_other', $data);
    }

    protected function process_pimenko_response_rank( $data ) {
        global $DB;

        $data = (object) $data;

        // Older versions of pimenkoquestionnaire used 'rank' instead of 'rankvalue'. If 'rank' exists, change it to 'rankvalue'.
        if (isset($data->rank) && !isset($data->rankvalue)) {
            $data->rankvalue = $data->rank;
        }

        $data->response_id = $this->get_new_parentid('pimenko_response');
        $data->question_id = $this->get_mappingid('pimenko_question', $data->question_id);
        $data->choice_id = $this->get_mappingid('pimenko_quest_choice', $data->choice_id);

        // Insert the pimenko_response_rank record.
        $DB->insert_record('pimenko_response_rank', $data);
    }

    protected function process_pimenko_response_single( $data ) {
        global $DB;

        $data = (object) $data;
        $data->response_id = $this->get_new_parentid('pimenko_response');
        $data->question_id = $this->get_mappingid('pimenko_question', $data->question_id);
        $data->choice_id = $this->get_mappingid('pimenko_quest_choice', $data->choice_id);

        // Insert the pimenko_resp_single record.
        $DB->insert_record('pimenko_resp_single', $data);
    }

    protected function process_pimenko_response_text( $data ) {
        global $DB;

        $data = (object) $data;
        $data->response_id = $this->get_new_parentid('pimenko_response');
        $data->question_id = $this->get_mappingid('pimenko_question', $data->question_id);

        // Insert the pimenko_response_text record.
        $DB->insert_record('pimenko_response_text', $data);
    }
}