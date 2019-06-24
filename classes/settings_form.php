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
 * @package mod_pimenkoquestionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pimenkoquestionnaire;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class settings_form extends \moodleform {

    public function definition() {
        global $pimenkoquestionnaire, $pimenkoquestionnairerealms;

        $mform    =& $this->_form;

        $mform->addElement('header', 'contenthdr', get_string('contentoptions', 'pimenkoquestionnaire'));

        $capabilities = pimenkoquestionnaire_load_capabilities($pimenkoquestionnaire->cm->id);
        if (!$capabilities->createtemplates) {
            unset($pimenkoquestionnairerealms['template']);
        }
        if (!$capabilities->createpublic) {
            unset($pimenkoquestionnairerealms['public']);
        }
        if (isset($pimenkoquestionnairerealms['public']) || isset($pimenkoquestionnairerealms['template'])) {
            $mform->addElement('select', 'realm', get_string('realm', 'pimenkoquestionnaire'), $pimenkoquestionnairerealms);
            $mform->setDefault('realm', $pimenkoquestionnaire->survey->realm);
            $mform->addHelpButton('realm', 'realm', 'pimenkoquestionnaire');
        } else {
            $mform->addElement('hidden', 'realm', 'private');
        }
        $mform->setType('realm', PARAM_RAW);

        $mform->addElement('text', 'title', get_string('title', 'pimenkoquestionnaire'), array('size' => '60'));
        $mform->setDefault('title', $pimenkoquestionnaire->survey->title);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addHelpButton('title', 'title', 'pimenkoquestionnaire');

        $mform->addElement('text', 'subtitle', get_string('subtitle', 'pimenkoquestionnaire'), array('size' => '60'));
        $mform->setDefault('subtitle', $pimenkoquestionnaire->survey->subtitle);
        $mform->setType('subtitle', PARAM_TEXT);
        $mform->addHelpButton('subtitle', 'subtitle', 'pimenkoquestionnaire');

        $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'trusttext' => true);
        $mform->addElement('editor', 'info', get_string('additionalinfo', 'pimenkoquestionnaire'), null, $editoroptions);
        $mform->setDefault('info', $pimenkoquestionnaire->survey->info);
        $mform->setType('info', PARAM_RAW);
        $mform->addHelpButton('info', 'additionalinfo', 'pimenkoquestionnaire');

        $mform->addElement('header', 'submithdr', get_string('submitoptions', 'pimenkoquestionnaire'));

        $mform->addElement('text', 'thanks_page', get_string('url', 'pimenkoquestionnaire'), array('size' => '60'));
        $mform->setType('thanks_page', PARAM_TEXT);
        $mform->setDefault('thanks_page', $pimenkoquestionnaire->survey->thanks_page);
        $mform->addHelpButton('thanks_page', 'url', 'pimenkoquestionnaire');

        $mform->addElement('static', 'confmes', get_string('confalts', 'pimenkoquestionnaire'));
        $mform->addHelpButton('confmes', 'confpage', 'pimenkoquestionnaire');

        $mform->addElement('text', 'thank_head', get_string('headingtext', 'pimenkoquestionnaire'), array('size' => '30'));
        $mform->setType('thank_head', PARAM_TEXT);
        $mform->setDefault('thank_head', $pimenkoquestionnaire->survey->thank_head);

        $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'trusttext' => true);
        $mform->addElement('editor', 'thank_body', get_string('bodytext', 'pimenkoquestionnaire'), null, $editoroptions);
        $mform->setType('thank_body', PARAM_RAW);
        $mform->setDefault('thank_body', $pimenkoquestionnaire->survey->thank_body);

        $mform->addElement('text', 'email', get_string('email', 'pimenkoquestionnaire'), array('size' => '75'));
        $mform->setType('email', PARAM_TEXT);
        $mform->setDefault('email', $pimenkoquestionnaire->survey->email);
        $mform->addHelpButton('email', 'sendemail', 'pimenkoquestionnaire');

        // Hidden fields.
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sid', 0);
        $mform->setType('sid', PARAM_INT);
        $mform->addElement('hidden', 'name', '');
        $mform->setType('name', PARAM_TEXT);
        $mform->addElement('hidden', 'courseid', '');
        $mform->setType('courseid', PARAM_RAW);

        // Buttons.

        $submitlabel = get_string('savechangesanddisplay');
        $submit2label = get_string('savechangesandreturntocourse');
        $mform = $this->_form;

        // Elements in a row need a group.
        $buttonarray = array();
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton2', $submit2label);
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', $submitlabel);
        $buttonarray[] = &$mform->createElement('cancel');

        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->setType('buttonar', PARAM_RAW);
        $mform->closeHeaderBefore('buttonar');

    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }
}