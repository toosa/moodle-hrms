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
 * HRIS external services and functions
 *
 * @package    local_hris
 * @copyright  2025 Prihantoosa <pht854@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Define the web service functions to install.
$functions = [
    'local_hris_get_active_courses' => [
        'classname'   => 'local_hris_external',
        'methodname'  => 'get_active_courses',
        'classpath'   => 'local/hris/classes/external.php',
        'description' => 'Get list of active courses',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => '',
    ],
    'local_hris_get_all_active_courses' => [
        'classname'   => 'local_hris_external',
        'methodname'  => 'get_active_courses',
        'classpath'   => 'local/hris/classes/external.php',
        'description' => 'Get list of active courses (alias)',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => '',
    ],
    'local_hris_get_course_participants' => [
        'classname'   => 'local_hris_external',
        'methodname'  => 'get_course_participants', 
        'classpath'   => 'local/hris/classes/external.php',
        'description' => 'Get participants enrolled in courses',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => '',
    ],
    'local_hris_get_course_results' => [
        'classname'   => 'local_hris_external',
        'methodname'  => 'get_course_results',
        'classpath'   => 'local/hris/classes/external.php', 
        'description' => 'Get course results with pre-test and post-test scores',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => '',
    ],
    'local_hris_get_all_course_results' => [
        'classname'   => 'local_hris_external',
        'methodname'  => 'get_all_course_results',
        'classpath'   => 'local/hris/classes/external.php',
        'description' => 'Get all course results with questionnaire scores',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => '',
    ]
];

// Define services to install as pre-build services.
$services = [
    'HRIS Integration Service' => [
        'functions' => [
            'local_hris_get_active_courses',
            'local_hris_get_all_active_courses',
            'local_hris_get_course_participants',
            'local_hris_get_course_results',
            'local_hris_get_all_course_results'
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'hris_service',
        'downloadfiles' => 0,
        'uploadfiles' => 0
    ]
];