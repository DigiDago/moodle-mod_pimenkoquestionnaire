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
 * Setting your observers
 *
 * @package     mod_pimenkoquestionnaire
 * @category    mod
 * @copyright   DigiDago 2019
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class mod_pimenkoquestionnaire_observer {
    public static function role_assigned( \core\event\role_assigned $event ) {
        self::updateteacherquestionarylist($event);
    }

    public static function updateteacherquestionarylist( $event ) {
        global $DB;
        $role = $DB->get_record($event->objecttable, ['id' => $event->objectid]);
        $user = $DB->get_record('user', ['id' => $event->relateduserid]);

        if ($role->shortname == 'editingteacher' || $role->shortname == 'responsablebloccontact') {
            $questionnaires = $DB->get_records('pimenkoquestionnaire', ['course' => $event->courseid]);
            foreach ($questionnaires as $questionnaire) {
                $questions = $DB->get_records("pimenko_question", ['surveyid' => $questionnaire->sid, 'type_id' => 11]);
                foreach ($questions as $question) {
                    $record = new \stdClass();
                    $record->question_id = $question->id;
                    $record->content = $user->firstname . ' ' . $user->lastname;
                    $record->value = $record->content;
                    $sql = "SELECT * FROM {pimenko_quest_choice}
                            WHERE question_id = " . $record->question_id . "
                            AND content = '" . str_replace("'", "''", $record->content) . "'";
                    $existing = $DB->get_record_sql($sql);
                    if (!$existing) {
                        $DB->insert_record('pimenko_quest_choice', $record);
                    }
                    $sql = "SELECT * FROM {pimenko_quest_choice}
                            WHERE question_id = " . $record->question_id . "
                            AND content = '" . get_string('noteacher', 'pimenkoquestionnaire') . "'";
                    $record = $DB->get_record_sql($sql);
                    if ($record) {
                        $sql = "DELETE FROM {pimenko_quest_choice} WHERE question_id = " . $record->question_id . "
                            AND content = '" . get_string('noteacher', 'pimenkoquestionnaire') . "'";
                        $DB->execute($sql);
                    }
                }
            }
        }
    }
}
