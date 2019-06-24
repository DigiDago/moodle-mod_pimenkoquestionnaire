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
 * PHPUnit pimenkoquestionnaire generator tests
 *
 * @package    mod_pimenkoquestionnaire
 * @copyright  2015 Mike Churchward (mike@churchward.ca)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for {@link pimenkoquestionnaire_generator_testcase}.
 * @group mod_pimenkoquestionnaire
 */
class mod_pimenkoquestionnaire_generator_testcase extends advanced_testcase {
    public function test_create_instance() {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $this->assertFalse($DB->record_exists('pimenkoquestionnaire', array('course' => $course->id)));

        /** @var mod_pimenkoquestionnaire_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_pimenkoquestionnaire');
        $this->assertInstanceOf('mod_pimenkoquestionnaire_generator', $generator);
        $this->assertEquals('pimenkoquestionnaire', $generator->get_modulename());

        $pimenkoquestionnaire = $generator->create_instance(array('course' => $course->id));
        $this->assertEquals(1, $DB->count_records('pimenkoquestionnaire'));

        $cm = get_coursemodule_from_instance('pimenkoquestionnaire', $pimenkoquestionnaire->id);
        $this->assertEquals($pimenkoquestionnaire->id, $cm->instance);
        $this->assertEquals('pimenkoquestionnaire', $cm->modname);
        $this->assertEquals($course->id, $cm->course);

        $context = context_module::instance($cm->id);
        $this->assertEquals($pimenkoquestionnaire->cmid, $context->instanceid);

        $survey = $DB->get_record('pimenkoquestionnaire_survey', array('id' => $pimenkoquestionnaire->sid));
        $this->assertEquals($survey->id, $pimenkoquestionnaire->sid);
        $this->assertEquals($pimenkoquestionnaire->name, $survey->name);
        $this->assertEquals($pimenkoquestionnaire->name, $survey->title);

        // Should test creating a public pimenkoquestionnaire, template pimenkoquestionnaire and creating one from a template.

        // Should test event creation if open dates and close dates are specified?
    }

    public function test_create_content() {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_pimenkoquestionnaire');
        $pimenkoquestionnaire = $generator->create_instance(array('course' => $course->id));
        $cm = get_coursemodule_from_instance('pimenkoquestionnaire', $pimenkoquestionnaire->id);
        $pimenkoquestionnaire = new pimenkoquestionnaire($pimenkoquestionnaire->id, null, $course, $cm, false);

        $newcontent = array(
            'title'         => 'New title',
            'email'         => 'test@email.com',
            'subtitle'      => 'New subtitle',
            'info'          => 'New info',
            'thanks_page'   => 'http://thankurl.com',
            'thank_head'    => 'New thank header',
            'thank_body'    => 'New thank body',
        );
        $sid = $generator->create_content($pimenkoquestionnaire, $newcontent);
        $this->assertEquals($sid, $pimenkoquestionnaire->sid);
        $survey = $DB->get_record('pimenkoquestionnaire_survey', array('id' => $sid));
        foreach ($newcontent as $name => $value) {
            $this->assertEquals($survey->{$name}, $value);
        }
    }
}