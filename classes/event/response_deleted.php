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
 * The mod_pimenkoquestionnaire response_deleted viewed event.
 *
 * @package    mod_pimenkoquestionnaire
 * @copyright  2014 Joseph Rézeau <moodle@rezeau.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pimenkoquestionnaire\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_pimenkoquestionnaire response_deleted event class.
 *
 * @package    mod_pimenkoquestionnaire
 * @since      Moodle 2.7
 * @copyright  2014 Joseph Rézeau <moodle@rezeau.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_deleted extends \core\event\base {

    /**
     * Set basic properties for the event.
     */
    protected function init() {
        $this->data['objecttable'] = 'pimenkoquestionnaire';
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_response_deleted', 'mod_pimenkoquestionnaire');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' deleted the response by the user with id '$this->relateduserid'
            to the pimenkoquestionnaire with course module id '$this->contextinstanceid'.";
    }
}
