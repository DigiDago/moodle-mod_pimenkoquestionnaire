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
 * The mod_pimenkoquestionnaire all_responses_viewed event.
 *
 * @package    mod_pimenkoquestionnaire
 * @copyright  2014 Joseph Rézeau <moodle@rezeau.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pimenkoquestionnaire\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_pimenkoquestionnaire all_responses_viewed event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - string action: (optional) report view.
 *      - int groupid: (optional) report for groupid.
 * }
 *
 * @package    mod_pimenkoquestionnaire
 * @since      Moodle 2.7
 * @copyright  2014 Joseph Rézeau <moodle@rezeau.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class all_responses_viewed extends \core\event\base {

    /**
     * Set basic properties for the event.
     */
    protected function init() {
        $this->data['objecttable'] = 'pimenkoquestionnaire';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_all_responses_viewed', 'mod_pimenkoquestionnaire');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' viewed the all responses report for the pimenkoquestionnaire
            with course module id '$this->contextinstanceid'.";
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        $params = [];
        $params['id'] = $this->contextinstanceid;
        if (isset($this->other['action'])) {
            $params['action'] = $this->other['action'];
            $params['instance'] = $this->other['instance'];
            $params['group'] = $this->other['groupid'];
        }
        return new \moodle_url("/mod/pimenkoquestionnaire/report.php", $params);
    }

    /**
     * Return the legacy event log data.
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        return [$this->courseid, "pimenkoquestionnaire", "view report", "report.php?id=" . $this->contextinstanceid,
                $this->objectid,
                $this->contextinstanceid];
    }
}
