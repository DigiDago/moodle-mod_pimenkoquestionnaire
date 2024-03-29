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

namespace mod_pimenkoquestionnaire\question;

use mod_pimenkoquestionnaire\edit_question_form;
use \pimenkoquestionnaire;
use context_course;

defined('MOODLE_INTERNAL') || die();

use \html_writer;

/**
 * This file contains the parent class for pimenkoquestionnaire question types.
 *
 * @author  Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questiontypes
 */

/**
 * Class for describing a question
 *
 * @author  Mike Churchward
 * @package questiontypes
 */

// Constants.
define('QUESCHOOSE', 0);
define('QUESYESNO', 1);
define('QUESTEXT', 2);
define('QUESESSAY', 3);
define('QUESRADIO', 4);
define('QUESCHECK', 5);
define('QUESDROP', 6);
define('QUESRATE', 8);
define('QUESDATE', 9);
define('QUESNUMERIC', 10);
define('QUESTEACHERSELECT', 11);
define('QUESPAGEBREAK', 99);
define('QUESSECTIONTEXT', 100);

global $idcounter, $CFG;
$idcounter = 0;

require_once($CFG->dirroot . '/mod/pimenkoquestionnaire/locallib.php');

abstract class base {

    // Class Properties.
    /** @var array $qtypenames List of all question names. */
    private static $qtypenames = [
            QUESYESNO => 'yesno',
            QUESTEXT => 'text',
            QUESESSAY => 'essay',
            QUESRADIO => 'radio',
            QUESCHECK => 'check',
            QUESDROP => 'drop',
            QUESRATE => 'rate',
            QUESDATE => 'date',
            QUESNUMERIC => 'numerical',
            QUESPAGEBREAK => 'pagebreak',
            QUESSECTIONTEXT => 'sectiontext',
            QUESTEACHERSELECT => 'teacherselect'
    ];
    /** @var int $id The database id of this question. */
    public $id = 0;
    /** @var int $surveyid The database id of the survey this question belongs to. */
    public $surveyid = 0;
    /** @var string $name The name of this question. */
    public $name = '';
    /** @var string $type The name of the question type. */
    public $type = '';
    /** @var array $choices Array holding any choices for this question. */
    public $choices = [];
    /** @var array $dependencies Array holding any dependencies for this question. */
    public $dependencies = [];
    /** @var string $responsetable The table name for responses. */
    public $responsetable = '';
    /** @var int $length The length field. */
    public $length = 0;
    /** @var int $precise The precision field. */
    public $precise = 0;
    /** @var int $position Position in the pimenkoquestionnaire */
    public $position = 0;
    /** @var string $content The question's content. */
    public $content = '';
    /** @var string $allchoices The list of all question's choices. */
    public $allchoices = '';
    /** @var boolean $required The required flag. */
    public $required = 'n';
    /** @var boolean $deleted The deleted flag. */
    public $deleted = 'n';
    /** @var array $notifications Array of extra messages for display purposes. */
    private $notifications = [];

    // Class Methods.

    /**
     * The class constructor
     *
     */
    public function __construct($id = 0, $question = null, $context = null, $params = []) {
        global $DB;
        static $qtypes = null;

        if ($qtypes === null) {
            $qtypes = $DB->get_records('pimenko_question_type', [], 'typeid',
                    'typeid, type, has_choices, response_table');
        }

        if ($id) {
            $question = $DB->get_record('pimenko_question', ['id' => $id]);
        }

        if (is_object($question)) {
            $this->id = $question->id;
            $this->surveyid = $question->surveyid;
            $this->name = $question->name;
            $this->length = $question->length;
            $this->precise = $question->precise;
            $this->position = $question->position;
            $this->content = $question->content;
            $this->required = $question->required;
            $this->deleted = $question->deleted;

            $this->type_id = $question->type_id;
            $this->type = $qtypes[$this->type_id]->type;
            $this->responsetable = $qtypes[$this->type_id]->response_table;
            if ($qtypes[$this->type_id]->has_choices == 'y') {
                $this->get_choices();
            }
            // Added for dependencies.
            $this->get_dependencies();
        }
        $this->context = $context;

        foreach ($params as $property => $value) {
            $this->$property = $value;
        }

        if ($respclass = $this->responseclass()) {
            $this->response = new $respclass($this);
        }
    }

    private function get_choices() {
        global $DB, $COURSE, $PAGE;

        if ($choices = $DB->get_records('pimenko_quest_choice', ['question_id' => $this->id], 'id ASC')) {
            foreach ($choices as $choice) {
                $this->choices[$choice->id] = new \stdClass();
                $this->choices[$choice->id]->content = $choice->content;
                if(!$choice->value) {
                    $this->choices[$choice->id]->value = $choice->content;
                } else {
                    $this->choices[$choice->id]->value = $choice->value;
                }
            }

            // Typeid always the same.
            // Here we need to remove old teacher from the list.
            // This code will remove Teacher who are no longer enrol as teacher from the select teacher list.
            $type = $PAGE->pagetype;
            if ( $this->type_id == 11 && ($type == "mod-pimenkoquestionnaire-complete" || $type == "mod-pimenkoquestionnaire-preview")
            ) {
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

                foreach ($this->choices as $key => $choice) {
                    $same = false;
                    foreach ($teachers as $teacher) {
                        $value = $teacher->firstname . ' ' . $teacher->lastname;
                        if (stristr($choice->value, $value) !== false) {
                            $same = true;
                            break;
                        }
                    }
                    if ($same === false) {
                        unset($this->choices[$key]);
                    }
                }
            }
        } else {
            $this->choices = [];
        }
    }

    private function get_dependencies() {
        global $DB;

        $this->dependencies = [];
        $dependencies = $DB->get_records('pimenko_dependency',
                ['questionid' => $this->id, 'surveyid' => $this->surveyid], 'id ASC');
        foreach ($dependencies as $dependency) {
            $this->dependencies[$dependency->id] = new \stdClass();
            $this->dependencies[$dependency->id]->dependquestionid = $dependency->dependquestionid;
            $this->dependencies[$dependency->id]->dependchoiceid = $dependency->dependchoiceid;
            $this->dependencies[$dependency->id]->dependlogic = $dependency->dependlogic;
            $this->dependencies[$dependency->id]->dependandor = $dependency->dependandor;
        }
    }

    /**
     * Each question type must define its response class.
     *
     * @return object The response object based off of pimenko_response_base.
     *
     */
    abstract protected function responseclass();

    /**
     * Build a question from data.
     *
     * @return A question object.
     * @var int|array|object $qdata Either the id of the record, or a structure containing the question data, or null.
     * @var object $context The context for the question.
     * @var int $qtype The question type code.
     */
    static public function question_builder($qtype, $qdata = null, $context = null) {
        $qclassname = '\\mod_pimenkoquestionnaire\\question\\' . self::qtypename($qtype);
        $qid = 0;
        if (!empty($qdata) && is_array($qdata)) {
            $qdata = (object) $qdata;
        } else if (!empty($qdata) && is_int($qdata)) {
            $qid = $qdata;
        }
        return new $qclassname($qid, $qdata, $context, ['type_id' => $qtype]);
    }

    /**
     * Return the different question type names.
     *
     * @return array
     */
    static public function qtypename($qtype) {
        if (array_key_exists($qtype, self::$qtypenames)) {
            return self::$qtypenames[$qtype];
        } else {
            return ('');
        }
    }

    static public function form_length_hidden(\MoodleQuickForm $mform, $value = 0) {
        $mform->addElement('hidden', 'length', $value);
        $mform->setType('length', PARAM_INT);
        return $mform;
    }

    static public function form_precise_hidden(\MoodleQuickForm $mform, $value = 0) {
        $mform->addElement('hidden', 'precise', $value);
        $mform->setType('precise', PARAM_INT);
        return $mform;
    }

    /**
     * Return true if all dependencies or this question have been fulfilled, or there aren't any.
     *
     * @param int $rid The response ID to check.
     * @param array $questions An array containing all possible parent question objects.
     *
     * @return bool
     */
    public function dependency_fulfilled($rid, $questions) {
        if (!$this->has_dependencies()) {
            $fulfilled = true;
        } else {
            foreach ($this->dependencies as $dependency) {
                $choicematches = $questions[$dependency->dependquestionid]->response_has_choice($rid, $dependency->dependchoiceid);

                // Note: dependencies are sorted, first all and-dependencies, then or-dependencies.
                if ($dependency->dependandor == 'and') {
                    $dependencyandfulfilled = false;
                    // This answer given.
                    if (($dependency->dependlogic == 1) && $choicematches) {
                        $dependencyandfulfilled = true;
                    }

                    // This answer NOT given.
                    if (($dependency->dependlogic == 0) && !$choicematches) {
                        $dependencyandfulfilled = true;
                    }

                    // Something mandatory not fulfilled? Stop looking and continue to next question.
                    if ($dependencyandfulfilled == false) {
                        break;
                    }

                    // In case we have no or-dependencies.
                    $dependencyorfulfilled = true;
                }

                // Note: dependencies are sorted, first all and-dependencies, then or-dependencies.
                if ($dependency->dependandor == 'or') {
                    $dependencyorfulfilled = false;
                    // To reach this point, the and-dependencies have all been fultilled or do not exist, so set them ok.
                    $dependencyandfulfilled = true;
                    // This answer given.
                    if (($dependency->dependlogic == 1) && $choicematches) {
                        $dependencyorfulfilled = true;
                    }

                    // This answer NOT given.
                    if (($dependency->dependlogic == 0) && !$choicematches) {
                        $dependencyorfulfilled = true;
                    }

                    // Something fulfilled? A single match is sufficient so continue to next question.
                    if ($dependencyorfulfilled == true) {
                        break;
                    }
                }

            }
            $fulfilled = ($dependencyandfulfilled && $dependencyorfulfilled);
        }
        return $fulfilled;
    }

    /**
     * Return true if the question has defined dependencies.
     *
     * @return boolean
     */
    public function has_dependencies() {
        return !empty($this->dependencies);
    }

    /**
     * Return true if the specified response for this question contains the specified choice.
     *
     * @param $rid
     * @param $choiceid
     *
     * @return bool
     */
    public function response_has_choice($rid, $choiceid) {
        global $DB;
        $choiceval = $this->response->transform_choiceid($choiceid);
        return $DB->record_exists($this->response_table(),
                ['response_id' => $rid, 'question_id' => $this->id, 'choice_id' => $choiceval]);
    }

    public function response_table() {
        return $this->response->response_table();
    }

    /**
     * Insert response data method.
     */
    public function insert_response($rid, $val) {
        if (isset ($this->response) && is_object($this->response) &&
                is_subclass_of($this->response, '\\mod_pimenkoquestionnaire\\response\\base')) {
            return $this->response->insert_response($rid, $val);
        } else {
            return false;
        }
    }

    /**
     * Get results data method.
     */
    public function get_results($rids = false) {
        if (isset ($this->response) && is_object($this->response) &&
                is_subclass_of($this->response, '\\mod_pimenkoquestionnaire\\response\\base')) {
            return $this->response->get_results($rids);
        } else {
            return false;
        }
    }

    /**
     * Display results method.
     */
    public function display_results($rids = false, $sort = '', $anonymous = false) {
        if (isset ($this->response) && is_object($this->response) &&
                is_subclass_of($this->response, '\\mod_pimenkoquestionnaire\\response\\base')) {
            return $this->response->display_results($rids, $sort, $anonymous);
        } else {
            return false;
        }
    }

    /**
     * Add a notification.
     *
     * @param string $message
     */
    public function add_notification($message) {
        $this->notifications[] = $message;
    }

    /**
     * Get any notifications.
     *
     * @return array | boolean The notifications array or false.
     */
    public function get_notifications() {
        if (empty($this->notifications)) {
            return false;
        } else {
            return $this->notifications;
        }
    }

    /**
     * True if question type allows responses.
     */
    public function supports_responses() {
        return !empty($this->responseclass());
    }

    /**
     * True if question type supports feedback scores and weights. Same as supports_feedback() by default.
     */
    public function supports_feedback_scores() {
        return $this->supports_feedback();
    }

    /**
     * True if question type supports feedback options. False by default.
     */
    public function supports_feedback() {
        return false;
    }

    /**
     * Provide the feedback scores for all requested response id's. This should be provided only by questions that provide feedback.
     *
     * @param array $rids
     *
     * @return array | boolean
     */
    public function get_feedback_scores(array $rids) {
        if ($this->valid_feedback() && isset($this->response) && is_object($this->response) &&
                is_subclass_of($this->response, '\\mod_pimenkoquestionnaire\\response\\base')) {
            return $this->response->get_feedback_scores($rids);
        } else {
            return false;
        }
    }

    /**
     * True if the question supports feedback and has valid settings for feedback. Override if the default logic is not enough.
     */
    public function valid_feedback() {
        if ($this->supports_feedback() && $this->has_choices() && $this->required() && !empty($this->name)) {
            foreach ($this->choices as $choice) {
                if ($choice->value != null) {
                    return true;
                }
            }
        } else {
            return false;
        }
    }

    /**
     * Override and return true if the question has choices.
     */
    public function has_choices() {
        return false;
    }

    /**
     * Return true if this question has been marked as required.
     *
     * @return boolean
     */
    public function required() {
        return ($this->required == 'y');
    }

    /**
     * Get the maximum score possible for feedback if appropriate. Override if default behaviour is not correct.
     *
     * @return int | boolean
     */
    public function get_feedback_maxscore() {
        if ($this->valid_feedback()) {
            $maxscore = 0;
            foreach ($this->choices as $choice) {
                if (isset($choice->value) && ($choice->value != null)) {
                    if ($choice->value > $maxscore) {
                        $maxscore = $choice->value;
                    }
                }
            }
        } else {
            $maxscore = false;
        }
        return $maxscore;
    }

    /**
     * Check question's form data for complete response.
     *
     * @param object $responsedata The data entered into the response.
     *
     * @return boolean
     */
    public function response_complete($responsedata) {
        return !($this->required() && ($this->deleted == 'n') &&
                (!isset($responsedata->{'q' . $this->id}) || $responsedata->{'q' . $this->id} == ''));
    }

    /**
     * Check question's form data for valid response. Override this if type has specific format requirements.
     *
     * @param object $responsedata The data entered into the response.
     *
     * @return boolean
     */
    public function response_valid($responsedata) {
        return true;
    }

    /**
     * Set the question required field in the object and database.
     *
     * @param boolean $required Whether question should be required or not.
     */
    public function set_required($required) {
        global $DB;
        $rval = $required ? 'y' : 'n';
        // Need to fix this messed-up qid/id issue.
        if (isset($this->qid) && ($this->qid > 0)) {
            $qid = $this->qid;
        } else {
            $qid = $this->id;
        }
        $this->required = $rval;
        return $DB->set_field('pimenko_question', 'required', $rval, ['id' => $qid]);
    }

    /**
     * Override and return a form template if provided. Output of question_survey_display is iterpreted based on this.
     *
     * @return boolean | string
     */
    public function question_template() {
        return false;
    }

    /**
     * Override and return a form template if provided. Output of response_survey_display is iterpreted based on this.
     *
     * @return boolean | string
     */
    public function response_template() {
        return false;
    }

    /**
     * Override and return a form template if provided. Output of results_output is iterpreted based on this.
     *
     * @return boolean | string
     */
    public function results_template() {
        if (isset ($this->response) && is_object($this->response) &&
                is_subclass_of($this->response, '\\mod_pimenkoquestionnaire\\response\\base')) {
            return $this->response->results_template();
        } else {
            return false;
        }
    }

    /**
     * Get the output for question renderers / templates.
     *
     * @param object $formdata
     * @param array $dependants Array of all questions/choices depending on this question.
     * @param integer $qnum
     * @param boolean $blankpimenkoquestionnaire
     */
    public function question_output($formdata, $dependants = [], $qnum = '', $blankpimenkoquestionnaire) {
        $pagetags = $this->questionstart_survey_display($qnum, $formdata);
        $pagetags->qformelement = $this->question_survey_display($formdata, $dependants, $blankpimenkoquestionnaire);
        return $pagetags;
    }

    /**
     * Get the output for the start of the questions in a survey.
     *
     * @param integer $qnum
     * @param object $formdata
     */
    public function questionstart_survey_display($qnum, $formdata = '') {
        global $OUTPUT, $SESSION, $pimenkoquestionnaire, $PAGE;

        $pagetags = new \stdClass();
        $currenttab = $SESSION->pimenkoquestionnaire->current_tab;
        $pagetype = $PAGE->pagetype;
        $skippedquestion = false;
        $skippedclass = '';
        $autonum = $pimenkoquestionnaire->autonum;
        // If no questions autonumbering.
        $nonumbering = false;
        if ($autonum != 1 && $autonum != 3) {
            $qnum = '';
            $nonumbering = true;
        }
        // If we are on report page and this pimenkoquestionnaire has dependquestions and this question was skipped.
        if (($pagetype == 'mod-pimenkoquestionnaire-myreport' || $pagetype == 'mod-pimenkoquestionnaire-report') &&
                ($nonumbering == false) && !empty($formdata) && !empty($this->dependencies) &&
                !array_key_exists('q' . $this->id, $formdata)) {
            $skippedquestion = true;
            $skippedclass = ' unselected';
            $qnum = '<span class="' . $skippedclass . '">(' . $qnum . ')</span>';
        }
        // In preview mode, hide children questions that have not been answered.
        // In report mode, If pimenkoquestionnaire is set to no numbering,
        // also hide answers to questions that have not been answered.
        $displayclass = 'qn-container';
        if ($pagetype == 'mod-pimenkoquestionnaire-preview' || ($nonumbering &&
                        ($currenttab == 'mybyresponse' || $currenttab == 'individualresp'))) {
            // This needs to be done to ensure all dependency data is loaded.
            // TODO - Perhaps this should be a function called by the pimenkoquestionnaire after it loads all questions?
            $pimenkoquestionnaire->load_parents($this);
            // Want this to come from the renderer, meaning we need $pimenkoquestionnaire.
            $pagetags->dependencylist = $pimenkoquestionnaire->renderer->get_dependency_html($this->id, $this->dependencies);
        }

        $pagetags->fieldset = (object) ['id' => $this->id, 'class' => $displayclass];

        // Do not display the info box for the label question type.
        if ($this->type_id != QUESSECTIONTEXT) {
            if (!$nonumbering) {
                $pagetags->qnum = $qnum;
            }
            $required = '';
            if ($this->required()) {
                $required = html_writer::start_tag('div', ['class' => 'accesshide']);
                $required .= get_string('required', 'pimenkoquestionnaire');
                $required .= html_writer::end_tag('div');
                $required .= html_writer::empty_tag('img',
                        ['class' => 'req', 'title' => get_string('required', 'pimenkoquestionnaire'),
                                'alt' => get_string('required', 'pimenkoquestionnaire'), 'src' => $OUTPUT->image_url('req')]);
            }
            $pagetags->required = $required; // Need to replace this with better renderer / template?
        }
        // If question text is "empty", i.e. 2 non-breaking spaces were inserted, empty it.
        if ($this->content == '<p>  </p>') {
            $this->content = '';
        }
        $pagetags->skippedclass = $skippedclass;
        if ($this->type_id == QUESNUMERIC || $this->type_id == QUESTEXT) {
            $pagetags->label = (object) ['for' => self::qtypename($this->type_id) . $this->id];
        } else if ($this->type_id == QUESDROP) {
            $pagetags->label = (object) ['for' => self::qtypename($this->type_id) . $this->name];
        } else if ($this->type_id == QUESESSAY) {
            $pagetags->label = (object) ['for' => 'edit-q' . $this->id];
        }
        $options = ['noclean' => true, 'para' => false, 'filter' => true, 'context' => $this->context, 'overflowdiv' => true];
        $content = format_text(file_rewrite_pluginfile_urls($this->content, 'pluginfile.php',
                $this->context->id, 'mod_pimenkoquestionnaire', 'question', $this->id), FORMAT_HTML, $options);
        $pagetags->qcontent = $content;

        return $pagetags;
    }

    /**
     * Question specific display method.
     *
     * @param object $formdata
     * @param array $descendantdata
     * @param boolean $blankpimenkoquestionnaire
     *
     */
    abstract protected function question_survey_display($formdata, $descendantsdata, $blankpimenkoquestionnaire);

    /**
     * Get the output for question renderers / templates.
     *
     * @param object $formdata
     * @param string $descendantdata
     * @param integer $qnum
     * @param boolean $blankpimenkoquestionnaire
     */
    public function response_output($data, $qnum = '') {
        $pagetags = $this->questionstart_survey_display($qnum, $data);
        $pagetags->qformelement = $this->response_survey_display($data);
        return $pagetags;
    }

    /**
     * Question specific response display method.
     *
     * @param object $data
     * @param integer $qnum
     *
     */
    abstract protected function response_survey_display($data);

    /**
     * Override this, or any of the internal methods, to provide specific form data for editing the question type.
     * The structure of the elements here is the default layout for the question form.
     *
     * @param edit_question_form $form The main moodleform object.
     * @param pimenkoquestionnaire $pimenkoquestionnaire The pimenkoquestionnaire being edited.
     *
     * @return bool
     */
    public function edit_form(edit_question_form $form, pimenkoquestionnaire $pimenkoquestionnaire) {
        $mform =& $form->_form;
        $this->form_header($mform);
        $this->form_name($mform);
        $this->form_required($mform);
        $this->form_length($mform);
        $this->form_precise($mform);
        $this->form_question_text($mform, $form->_customdata['modcontext']);

        if ($this->has_choices()) {
            $this->allchoices = $this->form_choices($mform, $this->choices);
        }

        // Added for advanced dependencies, parameter $editformobject is needed to use repeat_elements.
        if ($pimenkoquestionnaire->navigate > 0) {
            $this->form_dependencies($form, $pimenkoquestionnaire->questions);
        }

        // Exclude the save/cancel buttons from any collapsing sections.
        $mform->closeHeaderBefore('buttonar');

        // Hidden fields.
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'qid', 0);
        $mform->setType('qid', PARAM_INT);
        $mform->addElement('hidden', 'sid', 0);
        $mform->setType('sid', PARAM_INT);
        $mform->addElement('hidden', 'type_id', $this->type_id);
        $mform->setType('type_id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'question');
        $mform->setType('action', PARAM_ALPHA);

        // Buttons.
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        if (isset($this->qid)) {
            $buttonarray[] = &$mform->createElement('submit', 'makecopy', get_string('saveasnew', 'pimenkoquestionnaire'));
        }
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);

        return true;
    }

    protected function form_header(\MoodleQuickForm $mform, $helpname = '') {
        // Display different messages for new question creation and existing question modification.
        if (isset($this->qid) && !empty($this->qid)) {
            $header = get_string('editquestion', 'pimenkoquestionnaire', pimenkoquestionnaire_get_type($this->type_id));
        } else {
            $header = get_string('addnewquestion', 'pimenkoquestionnaire', pimenkoquestionnaire_get_type($this->type_id));
        }
        if (empty($helpname)) {
            $helpname = $this->helpname();
        }

        $mform->addElement('header', 'questionhdredit', $header);
        $mform->addHelpButton('questionhdredit', $helpname, 'pimenkoquestionnaire');
    }

    /**
     * Short name for this question type - no spaces, etc..
     *
     * @return string
     */
    abstract public function helpname();

    protected function form_name(\MoodleQuickForm $mform) {
        $mform->addElement('text', 'name', get_string('optionalname', 'pimenkoquestionnaire'),
                ['size' => '30', 'maxlength' => '30']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addHelpButton('name', 'optionalname', 'pimenkoquestionnaire');
        return $mform;
    }

    protected function form_required(\MoodleQuickForm $mform) {
        $reqgroup = [];
        $reqgroup[] =& $mform->createElement('radio', 'required', '', get_string('yes'), 'y');
        $reqgroup[] =& $mform->createElement('radio', 'required', '', get_string('no'), 'n');
        $mform->addGroup($reqgroup, 'reqgroup', get_string('required', 'pimenkoquestionnaire'), ' ', false);
        $mform->addHelpButton('reqgroup', 'required', 'pimenkoquestionnaire');
        return $mform;
    }

    protected function form_length(\MoodleQuickForm $mform, $helpname = '') {
        self::form_length_text($mform, $helpname);
    }

    static public function form_length_text(\MoodleQuickForm $mform, $helpname = '', $value = 0) {
        $mform->addElement('text', 'length', get_string($helpname, 'pimenkoquestionnaire'), ['size' => '1'], $value);
        $mform->setType('length', PARAM_INT);
        if (!empty($helpname)) {
            $mform->addHelpButton('length', $helpname, 'pimenkoquestionnaire');
        }
        return $mform;
    }

    protected function form_precise(\MoodleQuickForm $mform, $helpname = '') {
        self::form_precise_text($mform, $helpname);
    }

    static public function form_precise_text(\MoodleQuickForm $mform, $helpname = '', $value = 0) {
        $mform->addElement('text', 'precise', get_string($helpname, 'pimenkoquestionnaire'), ['size' => '1']);
        $mform->setType('precise', PARAM_INT);
        if (!empty($helpname)) {
            $mform->addHelpButton('precise', $helpname, 'pimenkoquestionnaire');
        }
        return $mform;
    }

    protected function form_question_text(\MoodleQuickForm $mform, $context) {
        $editoroptions = ['maxfiles' => EDITOR_UNLIMITED_FILES, 'trusttext' => true, 'context' => $context];
        $mform->addElement('editor', 'content', get_string('text', 'pimenkoquestionnaire'), null, $editoroptions);
        $mform->setType('content', PARAM_RAW);
        $mform->addRule('content', null, 'required', null, 'client');
        return $mform;
    }

    protected function form_choices(\MoodleQuickForm $mform, array $choices, $helpname = '') {
        $numchoices = count($choices);
        $allchoices = '';
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

    // This section contains functions for editing the specific question types.
    // There are required methods that must be implemented, and helper functions that can be used.

    // Required functions that can be overridden by the question type.

    /**
     * @param \MoodleQuickForm $mform The moodle form to add elements to.
     * @param                  $pimenkoquestionnaire
     * @param                  $editquestionformobject
     *
     * @return bool
     */
    protected function form_dependencies($form, $questions) {
        // Create a new area for multiple dependencies.
        $mform = $form->_form;
        $position = ($this->position !== 0) ? $this->position : count($questions) + 1;
        $dependencies = [];
        $dependencies[''][0] = get_string('choosedots');
        foreach ($questions as $question) {
            if (($question->position < $position) && !empty($question->name) &&
                    !empty($dependopts = $question->get_dependency_options())) {
                $dependencies[$question->name] = $dependopts;
            }
        }

        $children = [];
        if (isset($this->qid)) {
            // Use also for the delete dialogue later.
            foreach ($questions as $questionlistitem) {
                if ($questionlistitem->has_dependencies()) {
                    foreach ($questionlistitem->dependencies as $key => $outerdependencies) {
                        if ($outerdependencies->dependquestionid == $this->qid) {
                            $children[$key] = $outerdependencies;
                        }
                    }
                }
            }
        }

        if (count($dependencies) > 1) {
            $mform->addElement('header', 'dependencies_hdr', get_string('dependencies', 'pimenkoquestionnaire'));
            $mform->setExpanded('dependencies_hdr');
            $mform->closeHeaderBefore('qst_and_choices_hdr');

            $dependenciescountand = 0;
            $dependenciescountor = 0;

            foreach ($this->dependencies as $dependency) {
                if ($dependency->dependandor == "and") {
                    $dependenciescountand++;
                } else if ($dependency->dependandor == "or") {
                    $dependenciescountor++;
                }
            }

            /* I decided to allow changing dependencies of parent questions, because forcing the editor to remove dependencies
             * bottom up, starting at the lowest child question is a pain for large pimenkoquestionnaires.
             * So the following "if" becomes the default and the else-branch is completely commented.
             * TODO Since the best way to get the list of child questions is currently to click on delete (and choose not to
             * delete), one might consider to list the child questions in addition here.
             */

            // Area for "must"-criteria.
            $mform->addElement('static', 'mandatory', '',
                    '<div class="dimmed_text">' . get_string('mandatory', 'pimenkoquestionnaire') . '</div>');
            $selectand = $mform->createElement('select', 'dependlogic_and', get_string('condition', 'pimenkoquestionnaire'),
                    [get_string('answernotgiven', 'pimenkoquestionnaire'), get_string('answergiven', 'pimenkoquestionnaire')]);
            $selectand->setSelected('1');
            $groupitemsand = [];
            $groupitemsand[] =& $mform->createElement('selectgroups', 'dependquestions_and',
                    get_string('parent', 'pimenkoquestionnaire'), $dependencies);
            $groupitemsand[] =& $selectand;
            $groupand =
                    $mform->createElement('group', 'selectdependencies_and', get_string('dependquestion', 'pimenkoquestionnaire'),
                            $groupitemsand, ' ', false);
            $form->repeat_elements([$groupand], $dependenciescountand + 1, [],
                    'numdependencies_and', 'adddependencies_and', 2, null, true);

            // Area for "can"-criteria.
            $mform->addElement('static', 'optional', '',
                    '<div class="dimmed_text">' . get_string('optional', 'pimenkoquestionnaire') . '</div>');
            $selector = $mform->createElement('select', 'dependlogic_or', get_string('condition', 'pimenkoquestionnaire'),
                    [get_string('answernotgiven', 'pimenkoquestionnaire'), get_string('answergiven', 'pimenkoquestionnaire')]);
            $selector->setSelected('1');
            $groupitemsor = [];
            $groupitemsor[] =& $mform->createElement('selectgroups', 'dependquestions_or',
                    get_string('parent', 'pimenkoquestionnaire'), $dependencies);
            $groupitemsor[] =& $selector;
            $groupor = $mform->createElement('group', 'selectdependencies_or', get_string('dependquestion', 'pimenkoquestionnaire'),
                    $groupitemsor, ' ', false);
            $form->repeat_elements([$groupor], $dependenciescountor + 1, [], 'numdependencies_or',
                    'adddependencies_or', 2, null, true);
        }
        return true;
    }

    /**
     * Create and update question data from the forms.
     */
    public function form_update($formdata, $pimenkoquestionnaire) {
        global $DB;

        $this->form_preprocess_data($formdata);
        if (!empty($formdata->qid)) {

            // Update existing question.
            // Handle any attachments in the content.
            $formdata->itemid = $formdata->content['itemid'];
            $formdata->format = $formdata->content['format'];
            $formdata->content = $formdata->content['text'];
            $formdata->content =
                    file_save_draft_area_files($formdata->itemid, $pimenkoquestionnaire->context->id, 'mod_pimenkoquestionnaire',
                            'question', $formdata->qid, ['subdirs' => true], $formdata->content);

            $fields = ['name', 'type_id', 'length', 'precise', 'required', 'content'];
            $questionrecord = new \stdClass();
            $questionrecord->id = $formdata->qid;
            foreach ($fields as $f) {
                if (isset($formdata->$f)) {
                    $questionrecord->$f = trim($formdata->$f);
                }
            }

            $this->update($questionrecord, false);

            if ($pimenkoquestionnaire->has_dependencies()) {
                pimenkoquestionnaire_check_page_breaks($pimenkoquestionnaire);
            }
        } else {
            // Create new question:
            // Need to update any image content after the question is created, so create then update the content.
            $formdata->surveyid = $formdata->sid;
            $fields = ['surveyid', 'name', 'type_id', 'length', 'precise', 'required', 'position'];
            $questionrecord = new \stdClass();
            foreach ($fields as $f) {
                if (isset($formdata->$f)) {
                    $questionrecord->$f = trim($formdata->$f);
                }
            }
            $questionrecord->content = '';

            $this->add($questionrecord);

            // Handle any attachments in the content.
            $formdata->itemid = $formdata->content['itemid'];
            $formdata->format = $formdata->content['format'];
            $formdata->content = $formdata->content['text'];
            $content = file_save_draft_area_files($formdata->itemid, $pimenkoquestionnaire->context->id, 'mod_pimenkoquestionnaire',
                    'question', $this->qid, ['subdirs' => true], $formdata->content);
            $DB->set_field('pimenko_question', 'content', $content, ['id' => $this->qid]);
        }
        if ($this->has_choices()) {
            // Now handle any choice updates.
            $cidx = 0;
            if (isset($this->choices) && !isset($formdata->makecopy)) {
                $oldcount = count($this->choices);
                $echoice = reset($this->choices);
                $ekey = key($this->choices);
            } else {
                $oldcount = 0;
            }

            $newchoices = explode("\n", $formdata->allchoices);
            $nidx = 0;
            $newcount = count($newchoices);

            while (($nidx < $newcount) && ($cidx < $oldcount)) {
                if ($newchoices[$nidx] != $echoice->content) {
                    $choicerecord = new \stdClass();
                    $choicerecord->id = $ekey;
                    $choicerecord->question_id = $this->qid;
                    $choicerecord->content = trim($newchoices[$nidx]);
                    $r = preg_match_all("/^(\d{1,2})(=.*)$/", $newchoices[$nidx], $matches);
                    // This choice has been attributed a "score value" OR this is a rate question type.
                    if ($r) {
                        $newscore = $matches[1][0];
                        $choicerecord->value = $newscore;
                    } else {     // No score value for this choice.
                        $choicerecord->value = null;
                    }
                    $this->update_choice($choicerecord);
                }
                $nidx++;
                $echoice = next($this->choices);
                $ekey = key($this->choices);
                $cidx++;
            }

            while ($nidx < $newcount) {
                // New choices...
                $choicerecord = new \stdClass();
                $choicerecord->question_id = $this->qid;
                $choicerecord->content = trim($newchoices[$nidx]);
                $r = preg_match_all("/^(\d{1,2})(=.*)$/", $choicerecord->content, $matches);
                // This choice has been attributed a "score value" OR this is a rate question type.
                if ($r) {
                    $choicerecord->value = $matches[1][0];
                }
                $this->add_choice($choicerecord);
                $nidx++;
            }

            while ($cidx < $oldcount) {
                end($this->choices);
                $ekey = key($this->choices);
                $this->delete_choice($ekey);
                $cidx++;
            }
        }

        // Now handle the dependencies the same way as choices.
        // Shouldn't the MOODLE-API provide this case of insert/update/delete?.
        // First handle dependendies updates.
        if (!isset($formdata->fixed_deps)) {
            if ($this->has_dependencies() && !isset($formdata->makecopy)) {
                $oldcount = count($this->dependencies);
                $edependency = reset($this->dependencies);
                $ekey = key($this->dependencies);
            } else {
                $oldcount = 0;
            }

            $cidx = 0;
            $nidx = 0;

            // All 3 arrays in this object have the same length.
            if (isset($formdata->dependquestion)) {
                $newcount = count($formdata->dependquestion);
            } else {
                $newcount = 0;
            }
            while (($nidx < $newcount) && ($cidx < $oldcount)) {
                if ($formdata->dependquestion[$nidx] != $edependency->dependquestionid ||
                        $formdata->dependchoice[$nidx] != $edependency->dependchoiceid ||
                        $formdata->dependlogic_cleaned[$nidx] != $edependency->dependlogic ||
                        $formdata->dependandor[$nidx] != $edependency->dependandor) {

                    $dependencyrecord = new \stdClass();
                    $dependencyrecord->id = $ekey;
                    $dependencyrecord->questionid = $this->qid;
                    $dependencyrecord->surveyid = $this->surveyid;
                    $dependencyrecord->dependquestionid = $formdata->dependquestion[$nidx];
                    $dependencyrecord->dependchoiceid = $formdata->dependchoice[$nidx];
                    $dependencyrecord->dependlogic = $formdata->dependlogic_cleaned[$nidx];
                    $dependencyrecord->dependandor = $formdata->dependandor[$nidx];

                    $this->update_dependency($dependencyrecord);
                }
                $nidx++;
                $edependency = next($this->dependencies);
                $ekey = key($this->dependencies);
                $cidx++;
            }

            while ($nidx < $newcount) {
                // New dependencies.
                $dependencyrecord = new \stdClass();
                $dependencyrecord->questionid = $this->qid;
                $dependencyrecord->surveyid = $formdata->sid;
                $dependencyrecord->dependquestionid = $formdata->dependquestion[$nidx];
                $dependencyrecord->dependchoiceid = $formdata->dependchoice[$nidx];
                $dependencyrecord->dependlogic = $formdata->dependlogic_cleaned[$nidx];
                $dependencyrecord->dependandor = $formdata->dependandor[$nidx];

                $this->add_dependency($dependencyrecord);
                $nidx++;
            }

            while ($cidx < $oldcount) {
                end($this->dependencies);
                $ekey = key($this->dependencies);
                $this->delete_dependency($ekey);
                $cidx++;
            }
        }
    }

    /**
     * Any preprocessing of general data.
     */
    protected function form_preprocess_data($formdata) {
        if ($this->has_choices()) {
            // Eliminate trailing blank lines.
            $formdata->allchoices = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $formdata->allchoices);
            // Trim to eliminate potential trailing carriage return.
            $formdata->allchoices = trim($formdata->allchoices);
            $this->form_preprocess_choicedata($formdata);
        }

        // Dependencies logic does not (yet) need preprocessing, might change with more complex conditions.
        // Check, if entries exist and whether they are not only 0 (form elements created but no value selected).
        if (isset($formdata->dependquestions_and) &&
                !(count(array_keys($formdata->dependquestions_and, 0, true)) == count($formdata->dependquestions_and))) {
            for ($i = 0; $i < count($formdata->dependquestions_and); $i++) {
                $dependency = explode(",", $formdata->dependquestions_and[$i]);

                if ($dependency[0] != 0) {
                    $formdata->dependquestion[] = $dependency[0];
                    $formdata->dependchoice[] = $dependency[1];
                    $formdata->dependlogic_cleaned[] = $formdata->dependlogic_and[$i];
                    $formdata->dependandor[] = "and";
                }
            }
        }

        if (isset($formdata->dependquestions_or) &&
                !(count(array_keys($formdata->dependquestions_or, 0, true)) == count($formdata->dependquestions_or))) {
            for ($i = 0; $i < count($formdata->dependquestions_or); $i++) {
                $dependency = explode(",", $formdata->dependquestions_or[$i]);

                if ($dependency[0] != 0) {
                    $formdata->dependquestion[] = $dependency[0];
                    $formdata->dependchoice[] = $dependency[1];
                    $formdata->dependlogic_cleaned[] = $formdata->dependlogic_or[$i];
                    $formdata->dependandor[] = "or";
                }
            }
        }
        return true;
    }

    /**
     * Override this function for question specific choice preprocessing.
     */
    protected function form_preprocess_choicedata($formdata) {
        if (empty($formdata->allchoices)) {
            error(get_string('enterpossibleanswers', 'pimenkoquestionnaire'));
        }
        return false;
    }

    /**
     * Update data record from object or optional question data.
     *
     * @param object $questionrecord An object with all updated question record data.
     * @param boolean $updatechoices True if choices should also be updated.
     */
    public function update($questionrecord = null, $updatechoices = true) {
        global $DB;

        if ($questionrecord === null) {
            $questionrecord = new \stdClass();
            $questionrecord->id = $this->id;
            $questionrecord->surveyid = $this->surveyid;
            $questionrecord->name = $this->name;
            $questionrecord->type_id = $this->type_id;
            $questionrecord->result_id = $this->result_id;
            $questionrecord->length = $this->length;
            $questionrecord->precise = $this->precise;
            $questionrecord->position = $this->position;
            $questionrecord->content = $this->content;
            $questionrecord->required = $this->required;
            $questionrecord->deleted = $this->deleted;
            $questionrecord->dependquestion = $this->dependquestion;
            $questionrecord->dependchoice = $this->dependchoice;
        } else {
            // Make sure the "id" field is this question's.
            if (isset($this->qid) && ($this->qid > 0)) {
                $questionrecord->id = $this->qid;
            } else {
                $questionrecord->id = $this->id;
            }
        }
        $DB->update_record('pimenko_question', $questionrecord);

        if ($updatechoices && $this->has_choices()) {
            $this->update_choices();
        }
    }

    public function update_choices() {
        $retvalue = true;
        if ($this->has_choices() && isset($this->choices)) {
            // Need to fix this messed-up qid/id issue.
            if (isset($this->qid) && ($this->qid > 0)) {
                $qid = $this->qid;
            } else {
                $qid = $this->id;
            }
            foreach ($this->choices as $key => $choice) {
                $choicerecord = new \stdClass();
                $choicerecord->id = $key;
                $choicerecord->question_id = $qid;
                $choicerecord->content = $choice->content;
                $choicerecord->value = $choice->value;
                $retvalue &= $this->update_choice($choicerecord);
            }
        }
        return $retvalue;
    }

    public function update_choice($choicerecord) {
        global $DB;
        return $DB->update_record('pimenko_quest_choice', $choicerecord);
    }

    /**
     * Add the question to the database from supplied arguments.
     *
     * @param object $questionrecord The required data for adding the question.
     * @param array $choicerecords An array of choice records with 'content' and 'value' properties.
     * @param boolean $calcposition Whether or not to calculate the next available position in the survey.
     */
    public function add($questionrecord, array $choicerecords = null, $calcposition = true) {
        global $DB, $COURSE;

        // Create new question.
        if ($calcposition) {
            // Set the position to the end.
            $sql = 'SELECT MAX(position) as maxpos ' .
                    'FROM {pimenko_question} ' .
                    'WHERE surveyid = ? AND deleted = ?';
            $params = ['surveyid' => $questionrecord->surveyid, 'deleted' => 'n'];
            if ($record = $DB->get_record_sql($sql, $params)) {
                $questionrecord->position = $record->maxpos + 1;
            } else {
                $questionrecord->position = 1;
            }
        }

        // Make sure we add all necessary data.
        if (!isset($questionrecord->type_id) || empty($questionrecord->type_id)) {
            $questionrecord->type_id = $this->type_id;
        }

        $this->qid = $DB->insert_record('pimenko_question', $questionrecord);
        if ($this->has_choices() && !empty($choicerecords)) {
            foreach ($choicerecords as $choicerecord) {
                $choicerecord->question_id = $this->qid;
                $this->add_choice($choicerecord);
            }
        } else if ($questionrecord->type_id == 11) {
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

            foreach ($teachers as $teacher) {
                $choicerecord = new \stdClass();
                $choicerecord->question_id = $this->qid;
                $choicerecord->content = $teacher->firstname . ' ' . $teacher->lastname;
                $choicerecord->value = $choicerecord->content;
                $this->add_choice($choicerecord);
            }
        }
    }

    public function add_choice($choicerecord) {
        global $DB;
        $retvalue = true;

        // avoid double entries.
        $sql = "SELECT * FROM {pimenko_quest_choice}
                            WHERE question_id = " . $choicerecord->question_id . "
                            AND content = '" . str_replace("'", "''", $choicerecord->content) . "'";
        $existing = $DB->get_record_sql($sql);

        if ($existing) {
            return false;
        }
        if ($cid = $DB->insert_record('pimenko_quest_choice', $choicerecord)) {
            $this->choices[$cid] = new \stdClass();
            $this->choices[$cid]->content = str_replace("'", "''", $choicerecord->content);
            $this->choices[$cid]->value = isset($choicerecord->value) ? $choicerecord->value : null;
        } else {
            $retvalue = false;
        }
        return $retvalue;
    }

    // Helper functions for commonly used editing functions.

    /**
     * Delete the choice from the question object and the database.
     *
     * @param integer|object $choice Either the integer id of the choice, or the choice record.
     */
    public function delete_choice($choice) {
        global $DB;

        $retvalue = true;
        if (is_int($choice)) {
            $cid = $choice;
        } else {
            $cid = $choice->id;
        }
        if ($DB->delete_records('pimenko_quest_choice', ['id' => $cid])) {
            unset($this->choices[$cid]);
        } else {
            $retvalue = false;
        }
        return $retvalue;
    }

    public function update_dependency($dependencyrecord) {
        global $DB;
        return $DB->update_record('pimenko_dependency', $dependencyrecord);
    }

    public function add_dependency($dependencyrecord) {
        global $DB;

        $retvalue = true;
        if ($did = $DB->insert_record('pimenko_dependency', $dependencyrecord)) {
            $this->dependencies[$did] = new \stdClass();
            $this->dependencies[$did]->dependquestionid = $dependencyrecord->dependquestionid;
            $this->dependencies[$did]->dependchoiceid = $dependencyrecord->dependchoiceid;
            $this->dependencies[$did]->dependlogic = $dependencyrecord->dependlogic;
            $this->dependencies[$did]->dependandor = $dependencyrecord->dependandor;
        } else {
            $retvalue = false;
        }
        return $retvalue;
    }

    /**
     * Delete the dependency from the question object and the database.
     *
     * @param integer|object $dependency Either the integer id of the dependency, or the dependency record.
     */
    public function delete_dependency($dependency) {
        global $DB;

        $retvalue = true;
        if (is_int($dependency)) {
            $did = $dependency;
        } else {
            $did = $dependency->id;
        }
        if ($DB->delete_records('pimenko_dependency', ['id' => $did])) {
            unset($this->dependencies[$did]);
        } else {
            $retvalue = false;
        }
        return $retvalue;
    }

    /**
     * Returns an array of dependency options for the question as an array of id value / display value pairs. Override in specific
     * question types that support this differently.
     *
     * @return array An array of valid pair options.
     */
    protected function get_dependency_options() {
        $options = [];
        if ($this->allows_dependents() && $this->has_choices()) {
            foreach ($this->choices as $key => $choice) {
                $contents = pimenkoquestionnaire_choice_values($choice->content);
                if (!empty($contents->modname)) {
                    $choice->content = $contents->modname;
                } else if (!empty($contents->title)) { // Must be an image; use its title for the dropdown list.
                    $choice->content = format_string($contents->title);
                } else {
                    $choice->content = format_string($contents->text);
                }
                $options[$this->id . ',' . $key] = $this->name . '->' . $choice->content;
            }
        }
        return $options;
    }

    /**
     * Override this and return true if the question type allows dependent questions.
     *
     * @return boolean
     */
    public function allows_dependents() {
        return false;
    }

    private function compare_object($a, $b) {

    }
}