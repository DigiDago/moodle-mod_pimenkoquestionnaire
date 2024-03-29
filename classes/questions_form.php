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
 * @author     Mike Churchward & Joseph Rézeau
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pimenkoquestionnaire;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class questions_form extends \moodleform {

    public function __construct( $action, $moveq = false ) {
        $this->moveq = $moveq;
        return parent::__construct($action);
    }

    public function definition() {
        global $CFG, $pimenkoquestionnaire, $SESSION;
        global $DB;

        $sid = $pimenkoquestionnaire->survey->id;
        $mform =& $this->_form;

        $mform->addElement('header', 'questionhdr', get_string('addquestions', 'pimenkoquestionnaire'));
        $mform->addHelpButton('questionhdr', 'questiontypes', 'pimenkoquestionnaire');

        $strremove = get_string('remove', 'pimenkoquestionnaire');
        $strmove = get_string('move');
        $strmovehere = get_string('movehere');
        $strposition = get_string('position', 'pimenkoquestionnaire');

        if (!isset($pimenkoquestionnaire->questions)) {
            $pimenkoquestionnaire->questions = [];
        }
        if ($this->moveq) {
            $moveqposition = $pimenkoquestionnaire->questions[$this->moveq]->position;
        }

        $pos = 0;
        $select = '';
        if (!($qtypes = $DB->get_records_select_menu('pimenko_question_type', $select, null, '', 'typeid,type'))) {
            $qtypes = [];
        }
        // Get the names of each question type in the appropriate language.
        foreach ($qtypes as $key => $qtype) {
            // Do not allow "Page Break" to be selected as first element of a Questionnaire.
            if (empty($pimenkoquestionnaire->questions) && ($qtype == 'Page Break')) {
                unset($qtypes[$key]);
            } else {
                $qtypes[$key] = pimenkoquestionnaire_get_type($key);
            }
        }
        natsort($qtypes);
        $addqgroup = [];
        $addqgroup[] =& $mform->createElement('select', 'type_id', '', $qtypes);

        // The 'sticky' type_id value for further new questions.
        if (isset($SESSION->pimenkoquestionnaire->type_id)) {
            $mform->setDefault('type_id', $SESSION->pimenkoquestionnaire->type_id);
        }

        $addqgroup[] =& $mform->createElement('submit', 'addqbutton', get_string('addselqtype', 'pimenkoquestionnaire'));

        $pimenkoquestionnairehasdependencies = $pimenkoquestionnaire->has_dependencies();

        $mform->addGroup($addqgroup, 'addqgroup', '', ' ', false);

        if (isset($SESSION->pimenkoquestionnaire->validateresults) && $SESSION->pimenkoquestionnaire->validateresults != '') {
            $mform->addElement('static', 'validateresult', '', '<div class="qdepend warning">' .
                    $SESSION->pimenkoquestionnaire->validateresults . '</div>');
            $SESSION->pimenkoquestionnaire->validateresults = '';
        }

        $qnum = 0;

        // JR skip logic :: to prevent moving child higher than parent OR parent lower than child
        // we must get now the parent and child positions.

        if ($pimenkoquestionnairehasdependencies) {
            $parentpositions = pimenkoquestionnaire_get_parent_positions($pimenkoquestionnaire->questions);
            $childpositions = pimenkoquestionnaire_get_child_positions($pimenkoquestionnaire->questions);
        }

        $mform->addElement('header', 'manageq', get_string('managequestions', 'pimenkoquestionnaire'));
        $mform->addHelpButton('manageq', 'managequestions', 'pimenkoquestionnaire');

        $mform->addElement('html', '<div class="qcontainer">');

        foreach ($pimenkoquestionnaire->questions as $question) {

            $manageqgroup = [];

            $qid = $question->id;
            $tid = $question->type_id;
            $qtype = $question->type;
            $required = $question->required;

            // Get displayable list of parents for the questions in questions_form.
            if ($pimenkoquestionnairehasdependencies) {
                // TODO - Perhaps this should be a function called by the pimenkoquestionnaire after it loads all questions?
                $pimenkoquestionnaire->load_parents($question);
                $dependencies = $pimenkoquestionnaire->renderer->get_dependency_html($question->id, $question->dependencies);
            } else {
                $dependencies = '';
            }

            $pos = $question->position;

            // No page break in first position!
            if ($tid == QUESPAGEBREAK && $pos == 1) {
                $DB->set_field('pimenko_question', 'deleted', 'y', ['id' => $qid, 'surveyid' => $sid]);
                if ($records = $DB->get_records_select('pimenko_question', $select, null, 'position ASC')) {
                    foreach ($records as $record) {
                        $DB->set_field('pimenko_question', 'position', $record->position - 1, ['id' => $record->id]);
                    }
                }
                redirect($CFG->wwwroot . '/mod/pimenkoquestionnaire/questions.php?id=' . $pimenkoquestionnaire->cm->id);
            }

            if ($tid != QUESPAGEBREAK && $tid != QUESSECTIONTEXT) {
                $qnum++;
            }

            // Needed for non-English languages JR.
            $qtype = '[' . pimenkoquestionnaire_get_type($tid) . ']';
            $content = '';
            // If question text is "empty", i.e. 2 non-breaking spaces were inserted, do not display any question text.
            if ($question->content == '<p>  </p>') {
                $question->content = '';
            }
            if ($tid != QUESPAGEBREAK) {
                // Needed to print potential media in question text.
                $content = format_text(file_rewrite_pluginfile_urls($question->content, 'pluginfile.php',
                        $question->context->id, 'mod_pimenkoquestionnaire', 'question', $question->id), FORMAT_HTML,
                        ['noclean' => true]);
            }
            $moveqgroup = [];

            $spacer = $pimenkoquestionnaire->renderer->image_url('spacer');

            if (!$this->moveq) {
                $mform->addElement('html', '<div class="qn-container">'); // Begin div qn-container.
                $mextra = ['value' => $question->id,
                        'alt' => $strmove,
                        'title' => $strmove];
                $eextra = ['value' => $question->id,
                        'alt' => get_string('edit', 'pimenkoquestionnaire'),
                        'title' => get_string('edit', 'pimenkoquestionnaire')];
                $rextra = ['value' => $question->id,
                        'alt' => $strremove,
                        'title' => $strremove];

                if ($tid == QUESPAGEBREAK) {
                    $esrc = $spacer;
                    $eextra = ['disabled' => 'disabled'];
                } else {
                    $esrc = $pimenkoquestionnaire->renderer->image_url('t/edit');
                }
                $rsrc = $pimenkoquestionnaire->renderer->image_url('t/delete');

                // Question numbers.
                $manageqgroup[] =& $mform->createElement('static', 'qnums', '',
                        '<div class="qnums">' . $strposition . ' ' . $pos . '</div>');

                // Need to index by 'id' since IE doesn't return assigned 'values' for image inputs.
                $manageqgroup[] =& $mform->createElement('static', 'opentag_' . $question->id, '', '');
                $msrc = $pimenkoquestionnaire->renderer->image_url('t/move');

                if ($pimenkoquestionnairehasdependencies) {
                    // Do not allow moving parent question at position #1 to be moved down if it has a child at position < 4.
                    if ($pos == 1) {
                        if (isset($childpositions[$qid])) {
                            $maxdown = $childpositions[$qid];
                            if ($maxdown < 4) {
                                $strdisabled = get_string('movedisabled', 'pimenkoquestionnaire');
                                $msrc = $pimenkoquestionnaire->renderer->image_url('t/block');
                                $mextra = ['value' => $question->id,
                                        'alt' => $strdisabled,
                                        'title' => $strdisabled];
                                $mextra += ['disabled' => 'disabled'];
                            }
                        }
                    }

                    // Do not allow moving or deleting a page break if immediately followed by a child question
                    // or immediately preceded by a question with a dependency and followed by a non-dependent question.
                    if ($tid == QUESPAGEBREAK) {
                        if ($nextquestion = $DB->get_record('pimenko_question',
                                ['surveyid' => $sid, 'position' => $pos + 1, 'deleted' => 'n'], 'id, name, content')) {

                            $nextquestiondependencies = $DB->get_records('pimenko_dependency',
                                    ['questionid' => $nextquestion->id, 'surveyid' => $sid], 'id ASC');

                            if ($previousquestion = $DB->get_record('pimenko_question',
                                    ['surveyid' => $sid, 'position' => $pos - 1, 'deleted' => 'n'], 'id, name, content')) {

                                $previousquestiondependencies = $DB->get_records('pimenko_dependency',
                                        ['questionid' => $previousquestion->id, 'surveyid' => $sid], 'id ASC');

                                if (!empty($nextquestiondependencies) ||
                                        (!empty($previousquestiondependencies) && empty($nextquestiondependencies))) {
                                    $strdisabled = get_string('movedisabled', 'pimenkoquestionnaire');
                                    $msrc = $pimenkoquestionnaire->renderer->image_url('t/block');
                                    $mextra = ['value' => $question->id,
                                            'alt' => $strdisabled,
                                            'title' => $strdisabled];
                                    $mextra += ['disabled' => 'disabled'];

                                    $rsrc = $msrc;
                                    $strdisabled = get_string('deletedisabled', 'pimenkoquestionnaire');
                                    $rextra = ['value' => $question->id,
                                            'alt' => $strdisabled,
                                            'title' => $strdisabled];
                                    $rextra += ['disabled' => 'disabled'];
                                }
                            }
                        }
                    }
                }
                $manageqgroup[] =& $mform->createElement('image', 'movebutton[' . $question->id . ']',
                        $msrc, $mextra);
                $manageqgroup[] =& $mform->createElement('image', 'editbutton[' . $question->id . ']', $esrc, $eextra);
                $manageqgroup[] =& $mform->createElement('image', 'removebutton[' . $question->id . ']', $rsrc, $rextra);

                if ($tid != QUESPAGEBREAK && $tid != QUESSECTIONTEXT) {
                    if ($required == 'y') {
                        $reqsrc = $pimenkoquestionnaire->renderer->image_url('t/stop');
                        $strrequired = get_string('required', 'pimenkoquestionnaire');
                    } else {
                        $reqsrc = $pimenkoquestionnaire->renderer->image_url('t/go');
                        $strrequired = get_string('notrequired', 'pimenkoquestionnaire');
                    }
                    $strrequired .= ' ' . get_string('clicktoswitch', 'pimenkoquestionnaire');
                    $reqextra = ['value' => $question->id,
                            'alt' => $strrequired,
                            'title' => $strrequired];
                    $manageqgroup[] =& $mform->createElement('image', 'requiredbutton[' . $question->id . ']', $reqsrc, $reqextra);
                }
                $manageqgroup[] =& $mform->createElement('static', 'closetag_' . $question->id, '', '');

            } else {
                $manageqgroup[] =& $mform->createElement('static', 'qnum', '',
                        '<div class="qnums">' . $strposition . ' ' . $pos . '</div>');
                $moveqgroup[] =& $mform->createElement('static', 'qnum', '', '');

                $display = true;
                if ($pimenkoquestionnairehasdependencies) {
                    // Prevent moving child to higher position than its parent.
                    if (isset($parentpositions[$this->moveq])) {
                        $maxup = $parentpositions[$this->moveq];
                        if ($pos <= $maxup) {
                            $display = false;
                        }
                    }
                    // Prevent moving parent to lower position than its (first) child.
                    if (isset($childpositions[$this->moveq])) {
                        $maxdown = $childpositions[$this->moveq];
                        if ($pos >= $maxdown) {
                            $display = false;
                        }
                    }
                }

                $typeid = $DB->get_field('pimenko_question', 'type_id', ['id' => $this->moveq]);

                if ($display) {
                    // Do not move a page break to first position.
                    if ($typeid == QUESPAGEBREAK && $pos == 1) {
                        $manageqgroup[] =& $mform->createElement('static', 'qnums', '', '');
                    } else {
                        if ($this->moveq == $question->id) {
                            $moveqgroup[] =& $mform->createElement('cancel', 'cancelbutton', get_string('cancel'));
                        } else {
                            $mextra = ['value' => $question->id,
                                    'alt' => $strmove,
                                    'title' => $strmovehere . ' (position ' . $pos . ')'];
                            $msrc = $pimenkoquestionnaire->renderer->image_url('movehere');
                            $moveqgroup[] =& $mform->createElement('static', 'opentag_' . $question->id, '', '');
                            $moveqgroup[] =& $mform->createElement('image', 'moveherebutton[' . $pos . ']', $msrc, $mextra);
                            $moveqgroup[] =& $mform->createElement('static', 'closetag_' . $question->id, '', '');
                        }
                    }
                } else {
                    $manageqgroup[] =& $mform->createElement('static', 'qnums', '', '');
                    $moveqgroup[] =& $mform->createElement('static', 'qnums', '', '');
                }
            }
            if ($question->name) {
                $qname = '(' . $question->name . ')';
            } else {
                $qname = '';
            }
            $manageqgroup[] =& $mform->createElement('static', 'qinfo_' . $question->id, '', $qtype . ' ' . $qname);

            if (!empty($dependencies)) {
                $mform->addElement('static', 'qdepend_' . $question->id, '', $dependencies);
            }

            if ($tid != QUESPAGEBREAK) {
                if ($tid != QUESSECTIONTEXT) {
                    $qnumber = '<div class="qn-info"><h2 class="qn-number">' . $qnum . '</h2></div>';
                } else {
                    $qnumber = '';
                }
            }

            if ($this->moveq && $pos < $moveqposition) {
                $mform->addGroup($moveqgroup, 'moveqgroup', '', '', false);
            }
            if ($this->moveq) {
                if ($this->moveq == $question->id && $display) {
                    $mform->addElement('html', '<div class="moving" title="' . $strmove . '">'); // Begin div qn-container.
                } else {
                    $mform->addElement('html', '<div class="qn-container">'); // Begin div qn-container.
                }
            }
            $mform->addGroup($manageqgroup, 'manageqgroup', '', '&nbsp;', false);
            if ($tid != QUESPAGEBREAK) {
                $mform->addElement('static', 'qcontent_' . $question->id, '',
                        $qnumber . '<div class="qn-question">' . $content . '</div>');
            }
            $mform->addElement('html', '</div>'); // End div qn-container.

            if ($this->moveq && $pos >= $moveqposition) {
                $mform->addGroup($moveqgroup, 'moveqgroup', '', '', false);
            }
        }

        if ($this->moveq) {
            $mform->addElement('hidden', 'moveq', $this->moveq);
        }

        // Hidden fields.
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sid', 0);
        $mform->setType('sid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'main');
        $mform->setType('action', PARAM_RAW);
        $mform->setType('moveq', PARAM_RAW);

        $mform->addElement('html', '</div>');
    }

    public function validation( $data, $files ) {
        $errors = parent::validation($data, $files);
        return $errors;
    }

}