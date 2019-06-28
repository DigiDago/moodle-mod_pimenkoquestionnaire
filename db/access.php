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
 * Capability definitions for the quiz module.
 *
 * @package    mod_pimenkoquestionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Ability to add a new pimenkoquestionnaire instance to the course.
        'mod/pimenkoquestionnaire:addinstance' => [

                'riskbitmask' => RISK_XSS,
                'captype' => 'write',
                'contextlevel' => CONTEXT_COURSE,
                'archetypes' => [
                        'editingteacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                ],
                'clonepermissionsfrom' => 'moodle/course:manageactivities'
        ],

    // Ability to see that the pimenkoquestionnaire exists, and the basic information
    // about it.
        'mod/pimenkoquestionnaire:view' => [

                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => [
                        'student' => CAP_ALLOW,
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'coursecreator' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                ]
        ],

    // Ability to complete the pimenkoquestionnaire and submit.
        'mod/pimenkoquestionnaire:submit' => [

                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => [
                        'student' => CAP_ALLOW
                ]
        ],

    // Ability to view individual responses to the pimenkoquestionnaire.
        'mod/pimenkoquestionnaire:viewsingleresponse' => [

                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => [
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                ]
        ],

    // Receive a notificaton for every submission.
        'mod/pimenkoquestionnaire:submissionnotification' => [

                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => [
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                ]
        ],

    // Ability to download responses in a CSV file.
        'mod/pimenkoquestionnaire:downloadresponses' => [

                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => [
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                ]
        ],

    // Ability to delete someone's (or own) previous responses.
        'mod/pimenkoquestionnaire:deleteresponses' => [

                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => [
                        'editingteacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                ]
        ],

    // Ability to create and edit surveys.
        'mod/pimenkoquestionnaire:manage' => [

                'riskbitmask' => RISK_XSS,
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => [
                        'editingteacher' => CAP_ALLOW,
                        'coursecreator' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                ]
        ],

    // Ability to edit survey questions.
        'mod/pimenkoquestionnaire:editquestions' => [

                'riskbitmask' => RISK_XSS,
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => [
                        'editingteacher' => CAP_ALLOW,
                        'coursecreator' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                ]
        ],

    // Ability to create template surveys which can be copied, but not used.
        'mod/pimenkoquestionnaire:createtemplates' => [

                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => [
                        'coursecreator' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                ]
        ],

    // Ability to create public surveys which can be accessed from multiple places.
        'mod/pimenkoquestionnaire:createpublic' => [

                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => [
                        'coursecreator' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                ]
        ],

    // Ability to read own previous responses to pimenkoquestionnaires.
        'mod/pimenkoquestionnaire:readownresponses' => [

                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => [
                        'manager' => CAP_ALLOW,
                        'student' => CAP_ALLOW
                ]
        ],

    // Ability to read others' previous responses to pimenkoquestionnaires.
    // Subject to constraints on whether responses can be viewed whilst
    // pimenkoquestionnaire still open or user has not yet responded themselves.
        'mod/pimenkoquestionnaire:readallresponses' => [

                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => [
                        'manager' => CAP_ALLOW,
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'student' => CAP_ALLOW
                ]
        ],

    // Ability to read others's responses without the above checks.
        'mod/pimenkoquestionnaire:readallresponseanytime' => [

                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => [
                        'manager' => CAP_ALLOW,
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW
                ]
        ],

    // Ability to print a blank pimenkoquestionnaire.
        'mod/pimenkoquestionnaire:printblank' => [

                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => [
                        'manager' => CAP_ALLOW,
                        'coursecreator' => CAP_ALLOW,
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'student' => CAP_ALLOW
                ]
        ],

    // Ability to preview a pimenkoquestionnaire.
        'mod/pimenkoquestionnaire:preview' => [

                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => [
                        'manager' => CAP_ALLOW,
                        'coursecreator' => CAP_ALLOW,
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW
                ]
        ],

    // Ability to message students from a pimenkoquestionnaire.
        'mod/pimenkoquestionnaire:message' => [

                'riskbitmask' => RISK_SPAM,
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => [
                        'manager' => CAP_ALLOW,
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW
                ]
        ]

];