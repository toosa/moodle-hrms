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
 * HRMS external services and functions
 *
 * @package    local_hrms
 * @copyright  2025 Prihantoosa <pht854@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Define the web service functions to install.
$functions = [
    'local_hrms_get_active_courses' => [
        'classname'   => 'local_hrms_external',
        'methodname'  => 'get_active_courses',
        'classpath'   => 'local/hrms/classes/external.php',
        'description' => 'Get list of active courses',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => '',
    ],
    'local_hrms_get_all_active_courses' => [
        'classname'   => 'local_hrms_external',
        'methodname'  => 'get_active_courses',
        'classpath'   => 'local/hrms/classes/external.php',
        'description' => 'Get list of active courses (alias)',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => '',
    ],
    'local_hrms_get_course_participants' => [
        'classname'   => 'local_hrms_external',
        'methodname'  => 'get_course_participants', 
        'classpath'   => 'local/hrms/classes/external.php',
        'description' => 'Get participants enrolled in courses',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => '',
    ],
    'local_hrms_get_course_results' => [
        'classname'   => 'local_hrms_external',
        'methodname'  => 'get_course_results',
        'classpath'   => 'local/hrms/classes/external.php', 
        'description' => 'Get course results with pre-test and post-test scores',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => '',
    ],
    'local_hrms_set_user_suspension' => [
        'classname'   => 'local_hrms_external',
        'methodname'  => 'set_user_suspension',
        'classpath'   => 'local/hrms/classes/external.php',
        'description' => 'Suspend or unsuspend a user by userid or email',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'moodle/user:update',
    ],
    'local_hrms_get_users' => [
        'classname'   => 'local_hrms_external',
        'methodname'  => 'get_users',
        'classpath'   => 'local/hrms/classes/external.php',
        'description' => 'Get all users with optional filter by status (all, active, suspended)',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => '',
    ],
    'local_hrms_create_course' => [
        'classname'   => 'local_hrms_external',
        'methodname'  => 'create_course',
        'classpath'   => 'local/hrms/classes/external.php',
        'description' => 'Create a new course',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'moodle/course:create',
    ],
    'local_hrms_update_course' => [
        'classname'   => 'local_hrms_external',
        'methodname'  => 'update_course',
        'classpath'   => 'local/hrms/classes/external.php',
        'description' => 'Update course settings by course ID number (idnumber)',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'moodle/course:update',
    ],
];

// Define services to install as pre-build services.
$services = [
    'HRMS Integration Service' => [
        'functions' => [
            'local_hrms_get_active_courses',
            'local_hrms_get_all_active_courses',
            'local_hrms_get_course_participants',
            'local_hrms_get_course_results',
            'local_hrms_set_user_suspension',
            'local_hrms_get_users',
            'local_hrms_create_course',
            'local_hrms_update_course',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'hrms_service',
        'downloadfiles' => 0,
        'uploadfiles' => 0
    ]
];