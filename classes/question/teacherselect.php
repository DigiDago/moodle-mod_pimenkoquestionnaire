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
 * This file contains the parent class for teacherselect question types.
 *
 * @author  Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questiontypes
 */

namespace mod_pimenkoquestionnaire\question;

use context_course;

defined('MOODLE_INTERNAL') || die();

class teacherselect extends base {

    protected function responseclass() {
        return '\\mod_pimenkoquestionnaire\\response\\multiple';
    }

    public function helpname() {
        return 'teacherselect';
    }

    /**
     * Return true if this question has been marked as required.
     *
     * @return boolean
     */
    public function required() {
        return true;
    }

    /**
     * Return true if the question has choices.
     */
    public function has_choices() {
        return true;
    }

    protected function form_choices( \MoodleQuickForm $mform, array $choices, $helpname = '' ) {
        global $DB, $COURSE;
        $numchoices = count($choices);
        $allchoices = '';

        if (!$choices) {
            $roleeditingteacher = $DB->get_record('role', ['shortname' => 'editingteacher']);
            $roleresponsable = $DB->get_record('role', ['shortname' => 'responsablebloccontact']);

            $context = context_course::instance($COURSE->id);
            if($roleeditingteacher){
                $editingteacher = get_role_users($roleeditingteacher->id, $context);
            }
            if($roleresponsable){
                $responsable = get_role_users($roleresponsable->id, $context);
            }

            $teachers = [];

            if(!empty($editingteacher) && !empty($responsable)) {
                $teachers = array_merge($editingteacher,$responsable);
            } elseif (!empty($editingteacher)) {
                $teachers = $editingteacher;
            } elseif (!empty($responsable)) {
                $teachers = $responsable;
            }

            if (isset($teachers)) {
                foreach ($teachers as $teacher) {
                    $choice = new \stdClass();
                    $choice->content = $teacher->firstname . ' ' . $teacher->lastname;
                    $choice->value = $choice->content;
                    if(!in_array($choice->value,$choices)){
                        $choices[$choice->value] = $choice;
                    }
                }
            }
        }

        $choices = array_values($choices);

        foreach ($choices as $choice) {
            if (!empty($allchoices)) {
                $allchoices .= "\n";
            }
            $allchoices .= $choice->content;
        }
        if (empty($helpname)) {
            $helpname = $this->helpname();
        }

        $mform->addElement('html', '<div class="qoptcontainer">');
        $options = ['wrap' => 'virtual', 'class' => 'qopts'];
        $mform->addElement('textarea', 'allchoices', get_string('possibleanswers', 'pimenkoquestionnaire'), $options);
        $mform->setType('allchoices', PARAM_RAW);
        $mform->addRule('allchoices', null, 'required', null, 'client');
        $mform->addHelpButton('allchoices', $helpname, 'pimenkoquestionnaire');
        $mform->addElement('html', '</div>');
        $mform->addElement('hidden', 'num_choices', $numchoices);
        $mform->setType('num_choices', PARAM_INT);
        return $allchoices;
    }

    /**
     * Override and return a form template if provided. Output of question_survey_display is iterpreted based on this.
     *
     * @return boolean | string
     */
    public function question_template() {
        return 'mod_pimenkoquestionnaire/question_teacherselect';
    }

    protected function question_survey_display( $data, $dependants, $blankpimenkoquestionnaire = false ) {
        $otherempty = false;
        if (!empty($data)) {
            if (!isset($data->{'q' . $this->id}) || !is_array($data->{'q' . $this->id})) {
                $data->{'q' . $this->id} = [];
            }
            // Verify that number of checked boxes (nbboxes) is within set limits (length = min; precision = max).
            if ($data->{'q' . $this->id}) {
                $otherempty = false;
                $boxes = $data->{'q' . $this->id};
                $nbboxes = count($boxes);
                foreach ($boxes as $box) {
                    $pos = strpos($box, 'other_');
                    if (is_int($pos) == true) {
                        $resp = 'q' . $this->id . '' . substr($box, 5);
                        if (isset($data->$resp) && (trim($data->$resp) == false)) {
                            $otherempty = true;
                        }
                    }
                }
                $nbchoices = count($this->choices);
                $min = $this->length;
                $max = $this->precise;
                if ($max == 0) {
                    $max = $nbchoices;
                }
                if ($min > $max) {
                    $min = $max; // Sanity check.
                }
                $min = min($nbchoices, $min);
                if ($nbboxes < $min || $nbboxes > $max) {
                    $msg = get_string('boxesnbreq', 'pimenkoquestionnaire');
                    if ($min == $max) {
                        $msg .= '&nbsp;' . get_string('boxesnbexact', 'pimenkoquestionnaire', $min);
                    } else {
                        if ($min && ($nbboxes < $min)) {
                            $msg .= get_string('boxesnbmin', 'pimenkoquestionnaire', $min);
                            if ($nbboxes > $max) {
                                $msg .= ' & ' . get_string('boxesnbmax', 'pimenkoquestionnaire', $max);
                            }
                        } else {
                            if ($nbboxes > $max) {
                                $msg .= get_string('boxesnbmax', 'pimenkoquestionnaire', $max);
                            }
                        }
                    }
                    $this->add_notification($msg);
                }
            }
        }


        $choicetags = new \stdClass();
        $choicetags->qelements = [];
        foreach ($this->choices as $id => $choice) {

            $other = strpos($choice->content, '!other');
            $checkbox = new \stdClass();
            if ($other !== 0) { // This is a normal check box.
                $contents = pimenkoquestionnaire_choice_values($choice->content);
                $checked = false;
                if (!empty($data)) {
                    $checked = in_array($id, $data->{'q' . $this->id});
                }
                $checkbox->name = 'q' . $this->id . '[]';
                $checkbox->value = $id;
                $checkbox->id = 'checkbox_' . $id;
                $checkbox->label = format_text($contents->text, FORMAT_HTML, ['noclean' => true]) . $contents->image;
                if ($checked) {
                    $checkbox->checked = $checked;
                }
            } else {             // Check box with associated !other text field.
                // In case length field has been used to enter max number of choices, set it to 20.
                $othertext = preg_replace(
                        ["/^!other=/", "/^!other/"],
                        ['', get_string('other', 'pimenkoquestionnaire')],
                        $choice->content);
                $cid = 'q' . $this->id . '_' . $id;
                if (!empty($data) && isset($data->$cid) && (trim($data->$cid) != false)) {
                    $checked = true;
                } else {
                    $checked = false;
                }
                $name = 'q' . $this->id . '[]';
                $value = 'other_' . $id;

                $checkbox->name = $name;
                $checkbox->oname = $cid;
                $checkbox->value = $value;
                $checkbox->ovalue = (isset($data->$cid) && !empty($data->$cid) ? stripslashes($data->$cid) : '');
                $checkbox->id = 'checkbox_' . $id;
                $checkbox->label = format_text($othertext . '', FORMAT_HTML, ['noclean' => true]);
                if ($checked) {
                    $checkbox->checked = $checked;
                }
            }
            $choicetags->qelements[] = (object) ['choice' => $checkbox];
        }
        if ($otherempty) {
            $this->add_notification(get_string('otherempty', 'pimenkoquestionnaire'));
        }

        return $choicetags;
    }

    /**
     * Override and return a form template if provided. Output of response_survey_display is iterpreted based on this.
     *
     * @return boolean | string
     */
    public function response_template() {
        return 'mod_pimenkoquestionnaire/response_check';
    }

    protected function response_survey_display( $data ) {
        static $uniquetag = 0;  // To make sure all radios have unique names.

        $resptags = new \stdClass();
        $resptags->choices = [];

        if (!isset($data->{'q' . $this->id}) || !is_array($data->{'q' . $this->id})) {
            $data->{'q' . $this->id} = [];
        }

        foreach ($this->choices as $id => $choice) {
            $chobj = new \stdClass();
            if (strpos($choice->content, '!other') !== 0) {
                $contents = pimenkoquestionnaire_choice_values($choice->content);
                $choice->content = $contents->text . $contents->image;
                if (in_array($id, $data->{'q' . $this->id})) {
                    $chobj->selected = 1;
                }
                $chobj->name = $id . $uniquetag++;
                $chobj->content = (($choice->content === '') ? $id : format_text($choice->content, FORMAT_HTML,
                        ['noclean' => true]));
            } else {
                $othertext = preg_replace(
                        ["/^!other=/", "/^!other/"],
                        ['', get_string('other', 'pimenkoquestionnaire')],
                        $choice->content);
                $cid = 'q' . $this->id . '_' . $id;

                if (isset($data->$cid)) {
                    $chobj->selected = 1;
                    $chobj->othercontent = (!empty($data->$cid) ? htmlspecialchars($data->$cid) : '&nbsp;');
                }
                $chobj->name = $id . $uniquetag++;
                $chobj->content = (($othertext === '') ? $id : $othertext);
            }
            $resptags->choices[] = $chobj;
        }
        return $resptags;
    }

    protected function form_length( \MoodleQuickForm $mform, $helpname = '' ) {
        return base::form_length_hidden($mform);
    }

    protected function form_precise( \MoodleQuickForm $mform, $helpname = '' ) {
        return base::form_precise_hidden($mform);
    }
}