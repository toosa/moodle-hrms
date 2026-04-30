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
 * External API for HRMS Integration
 *
 * @package    local_hrms
 * @copyright  2025 Prihantoosa <pht854@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * HRMS external functions
 */
class local_hrms_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_active_courses_parameters() {
        return new external_function_parameters([
            'apikey'   => new external_value(PARAM_TEXT, 'API key for authentication'),
            'courseid' => new external_value(PARAM_INT,  'Course ID filter', VALUE_DEFAULT, 0),
            'idnumber' => new external_value(PARAM_TEXT, 'Course ID number filter', VALUE_DEFAULT, ''),
            'visible'  => new external_value(PARAM_INT,  'Visibility filter: 1=active only, 0=inactive only, -1=all', VALUE_DEFAULT, 1)
        ]);
    }

    /**
     * Get list of courses with optional visibility filter
     * @param string $apikey API key
     * @param int $courseid Course ID filter (0 = all courses)
     * @param string $idnumber Course ID number filter (empty = all courses, ignored if courseid > 0)
     * @param int $visible Visibility filter: 1 = active/visible only (default), 0 = inactive/hidden only, -1 = all
     * @return array List of courses
     */
    public static function get_active_courses($apikey, $courseid = 0, $idnumber = '', $visible = 1) {
        global $DB;

        // Validate parameters
        $params = self::validate_parameters(self::get_active_courses_parameters(), [
            'apikey'   => $apikey,
            'courseid' => $courseid,
            'idnumber' => $idnumber,
            'visible'  => $visible
        ]);

        // Validate API key
        if (!self::validate_api_key($params['apikey'])) {
            throw new moodle_exception('invalidapikey', 'local_hrms');
        }

        // Get context
        $context = context_system::instance();
        self::validate_context($context);

        // Get courses (exclude site course)
        $sql = "SELECT c.id, c.idnumber, c.shortname, c.fullname, c.summary,
                       c.startdate, c.enddate, c.visible,
                       cc.id as category_id, cc.name as category_name,
                       COALESCE(cfd.value, '') as jp
                FROM {course} c
                JOIN {course_categories} cc ON cc.id = c.category
                LEFT JOIN {customfield_category} cfc ON cfc.component = 'core_course' AND cfc.area = 'course'
                LEFT JOIN {customfield_field} cff ON cff.shortname = 'jp' AND cff.categoryid = cfc.id
                LEFT JOIN {customfield_data} cfd ON cfd.instanceid = c.id AND cfd.fieldid = cff.id
                WHERE c.id != :siteid";

        $sqlparams = ['siteid' => SITEID];

        // Apply visibility filter: 1 = visible only, 0 = hidden only, -1 = all
        if ($params['visible'] === 1) {
            $sql .= " AND c.visible = 1";
        } else if ($params['visible'] === 0) {
            $sql .= " AND c.visible = 0";
        }
        // -1 = no visibility filter applied

        if ($params['courseid'] > 0) {
            $sql .= " AND c.id = :courseid";
            $sqlparams['courseid'] = $params['courseid'];
        } else if (!empty($params['idnumber'])) {
            $sql .= " AND c.idnumber = :idnumber";
            $sqlparams['idnumber'] = $params['idnumber'];
        }

        $sql .= " ORDER BY cc.name, c.fullname";

        $courses = $DB->get_records_sql($sql, $sqlparams);
        
        $result = [];
        foreach ($courses as $course) {
            $result[] = [
                'id' => $course->id,
                'idnumber' => $course->idnumber ?: '',
                'shortname' => $course->shortname,
                'fullname' => $course->fullname,
                'summary' => strip_tags($course->summary),
                'category_id' => $course->category_id,
                'category_name' => $course->category_name,
                'startdate' => $course->startdate,
                'enddate' => $course->enddate,
                'visible' => $course->visible,
                'jp' => $course->jp ?: ''
            ];
        }

        return $result;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_active_courses_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Course ID'),
                'idnumber' => new external_value(PARAM_TEXT, 'Course ID number'),
                'shortname' => new external_value(PARAM_TEXT, 'Course short name'),
                'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
                'summary' => new external_value(PARAM_TEXT, 'Course summary'),
                'category_id' => new external_value(PARAM_INT, 'Category ID'),
                'category_name' => new external_value(PARAM_TEXT, 'Category name'),
                'startdate' => new external_value(PARAM_INT, 'Course start date'),
                'enddate' => new external_value(PARAM_INT, 'Course end date'),
                'visible' => new external_value(PARAM_INT, 'Course visibility'),
                'jp' => new external_value(PARAM_TEXT, 'Course custom field JP')
            ])
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_course_participants_parameters() {
        return new external_function_parameters([
            'apikey' => new external_value(PARAM_TEXT, 'API key for authentication'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'idnumber' => new external_value(PARAM_TEXT, 'Course ID number', VALUE_DEFAULT, '')
        ]);
    }

    /**
     * Get participants in courses
     * @param string $apikey API key
     * @param int $courseid Course ID (0 for all courses)
     * @param string $idnumber Course ID number (empty for all courses, overridden by courseid if both given)
     * @return array List of participants
     */
    public static function get_course_participants($apikey, $courseid = 0, $idnumber = '') {
        global $DB;

        // Validate parameters
        $params = self::validate_parameters(self::get_course_participants_parameters(), [
            'apikey' => $apikey,
            'courseid' => $courseid,
            'idnumber' => $idnumber
        ]);

        // Validate API key
        if (!self::validate_api_key($params['apikey'])) {
            throw new moodle_exception('invalidapikey', 'local_hrms');
        }

        // Get context
        $context = context_system::instance();
        self::validate_context($context);

        // Build SQL based on course filter
        // IMPORTANT: Use CONCAT to create unique key (user_id-course_id) to prevent duplicates
        $sql = "SELECT CONCAT(u.id, '-', c.id) as id,
                       u.id as user_id, u.email, u.firstname, u.lastname, 
                       COALESCE(u.institution, '') as company_name,
                       c.id as course_id, c.idnumber as course_idnumber, c.shortname, c.fullname as course_name,
                       ue.timecreated as enrollment_date,
                       COALESCE((
                           SELECT r.shortname
                           FROM {role_assignments} ra
                           JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                           JOIN {role} r ON r.id = ra.roleid
                           WHERE ra.userid = u.id AND ctx.instanceid = c.id
                           ORDER BY r.sortorder ASC
                           LIMIT 1
                       ), '') as role
                FROM {user} u
                JOIN {user_enrolments} ue ON u.id = ue.userid
                JOIN {enrol} e ON ue.enrolid = e.id
                JOIN {course} c ON e.courseid = c.id
                WHERE u.deleted = 0 
                AND u.confirmed = 1
                AND c.id != :siteid
                AND c.visible = 1";

        $sqlparams = ['siteid' => SITEID];

        if ($params['courseid'] > 0) {
            $sql .= " AND c.id = :courseid";
            $sqlparams['courseid'] = $params['courseid'];
        } else if (!empty($params['idnumber'])) {
            $course = $DB->get_record('course', ['idnumber' => $params['idnumber']], 'id', MUST_EXIST);
            $sql .= " AND c.id = :courseid";
            $sqlparams['courseid'] = $course->id;
        }

        $sql .= " ORDER BY c.fullname, u.lastname, u.firstname";

        $participants = $DB->get_records_sql($sql, $sqlparams);

        $result = [];
        foreach ($participants as $participant) {
            $result[] = [
                'user_id' => $participant->user_id,
                'email' => $participant->email,
                'firstname' => $participant->firstname,
                'lastname' => $participant->lastname,
                'company_name' => $participant->company_name ?: '',
                'course_id' => $participant->course_id,
                'course_idnumber' => $participant->course_idnumber ?: '',
                'course_shortname' => $participant->shortname,
                'course_name' => $participant->course_name,
                'enrollment_date' => $participant->enrollment_date,
                'role' => $participant->role ?: ''
            ];
        }

        return $result;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_course_participants_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'user_id' => new external_value(PARAM_INT, 'User ID'),
                'email' => new external_value(PARAM_EMAIL, 'User email'),
                'firstname' => new external_value(PARAM_TEXT, 'User first name'),
                'lastname' => new external_value(PARAM_TEXT, 'User last name'),
                'company_name' => new external_value(PARAM_TEXT, 'Company name'),
                'course_id' => new external_value(PARAM_INT, 'Course ID'),
                'course_idnumber' => new external_value(PARAM_TEXT, 'Course ID number'),
                'course_shortname' => new external_value(PARAM_TEXT, 'Course short name'),
                'course_name' => new external_value(PARAM_TEXT, 'Course name'),
                'enrollment_date' => new external_value(PARAM_INT, 'Enrollment date'),
                'role' => new external_value(PARAM_TEXT, 'User role in course (e.g. student, editingteacher, teacher)')
            ])
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_course_results_parameters() {
        return new external_function_parameters([
            'apikey'   => new external_value(PARAM_TEXT, 'API key for authentication'),
            'courseid' => new external_value(PARAM_INT,  'Course ID', VALUE_DEFAULT, 0),
            'userid'   => new external_value(PARAM_INT,  'User ID', VALUE_DEFAULT, 0),
            'idnumber' => new external_value(PARAM_TEXT, 'Course ID number', VALUE_DEFAULT, '')
        ]);
    }

    /**
     * Get course results with pre-test and post-test scores
     * @param string $apikey API key
     * @param int $courseid Course ID (0 for all courses)
     * @param int $userid User ID (0 for all users)
     * @param string $idnumber Course ID number (empty = all courses, ignored if courseid > 0)
     * @return array List of course results
     */
    public static function get_course_results($apikey, $courseid = 0, $userid = 0, $idnumber = '') {
        global $DB;

        // Validate parameters
        $params = self::validate_parameters(self::get_course_results_parameters(), [
            'apikey'   => $apikey,
            'courseid' => $courseid,
            'userid'   => $userid,
            'idnumber' => $idnumber
        ]);

        // Validate API key
        if (!self::validate_api_key($params['apikey'])) {
            throw new moodle_exception('invalidapikey', 'local_hrms');
        }

        // Get context
        $context = context_system::instance();
        self::validate_context($context);

        // Base SQL for getting enrollments
        // IMPORTANT: Use CONCAT to create unique key (user_id-course_id) to prevent duplicates
        // when same user is enrolled in multiple courses
        $sql = "SELECT CONCAT(u.id, '-', c.id) as id,
                       u.id as user_id, u.email, u.firstname, u.lastname,
                       COALESCE(u.institution, '') as company_name,
                       c.id as course_id, c.idnumber as course_idnumber, c.shortname, c.fullname as course_name,
                       cc.timecompleted,
                       COALESCE(gg.finalgrade, 0) as final_grade
                FROM {user} u
                JOIN {user_enrolments} ue ON u.id = ue.userid
                JOIN {enrol} e ON ue.enrolid = e.id
                JOIN {course} c ON e.courseid = c.id
                LEFT JOIN {course_completions} cc ON u.id = cc.userid AND c.id = cc.course
                LEFT JOIN {grade_items} gi ON c.id = gi.courseid AND gi.itemtype = 'course'
                LEFT JOIN {grade_grades} gg ON u.id = gg.userid AND gi.id = gg.itemid
                WHERE u.deleted = 0 
                AND u.confirmed = 1
                AND c.id != :siteid
                AND c.visible = 1
                AND EXISTS (
                    SELECT 1
                    FROM {role_assignments} ra
                    JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                    JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                    WHERE ra.userid = u.id AND ctx.instanceid = c.id
                )";

        $sqlparams = ['siteid' => SITEID];

        if ($params['courseid'] > 0) {
            $sql .= " AND c.id = :courseid";
            $sqlparams['courseid'] = $params['courseid'];
        } else if (!empty($params['idnumber'])) {
            $course = $DB->get_record('course', ['idnumber' => $params['idnumber']], 'id', MUST_EXIST);
            $sql .= " AND c.id = :courseid";
            $sqlparams['courseid'] = $course->id;
        }

        if ($params['userid'] > 0) {
            $sql .= " AND u.id = :userid";
            $sqlparams['userid'] = $params['userid'];
        }

        $sql .= " ORDER BY c.fullname, u.lastname, u.firstname";

        $results = $DB->get_records_sql($sql, $sqlparams);

        $final_results = [];
        foreach ($results as $result) {
            $final_results[] = [
                'user_id' => $result->user_id,
                'email' => $result->email,
                'firstname' => $result->firstname,
                'lastname' => $result->lastname,
                'company_name' => $result->company_name ?: '',
                'course_id' => $result->course_id,
                'course_idnumber' => $result->course_idnumber ?: '',
                'course_shortname' => $result->shortname,
                'course_name' => $result->course_name,
                'final_grade' => round($result->final_grade, 2),
                'completion_date' => $result->timecompleted ?: 0,
                'is_completed' => $result->timecompleted ? 1 : 0
            ];
        }

        return $final_results;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_course_results_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'user_id' => new external_value(PARAM_INT, 'User ID'),
                'email' => new external_value(PARAM_EMAIL, 'User email'),
                'firstname' => new external_value(PARAM_TEXT, 'User first name'),
                'lastname' => new external_value(PARAM_TEXT, 'User last name'),
                'company_name' => new external_value(PARAM_TEXT, 'Company name'),
                'course_id' => new external_value(PARAM_INT, 'Course ID'),
                'course_idnumber' => new external_value(PARAM_TEXT, 'Course ID number'),
                'course_shortname' => new external_value(PARAM_TEXT, 'Course short name'),
                'course_name' => new external_value(PARAM_TEXT, 'Course name'),
                'final_grade' => new external_value(PARAM_FLOAT, 'Final grade'),
                'completion_date' => new external_value(PARAM_INT, 'Course completion date'),
                'is_completed' => new external_value(PARAM_INT, 'Is course completed')
            ])
        );
    }

    /**
     * Get quiz score based on custom field 'jenis_quiz'
     *
     * NOTE: This method is currently unused but retained for future use.
     * It may be called if pre-test/post-test score retrieval is needed again.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param string $type Type of quiz (pre or post)
     * @return float Quiz score
     */
    private static function get_quiz_score($userid, $courseid, $type) {
        global $DB;

        // Determine which value to look for (2 = PreTest, 3 = PostTest)
        $fieldvalue = $type === 'pre' ? '2' : '3';
        
        $sql = "SELECT MAX(gg.finalgrade) as score
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                JOIN {customfield_data} cfd ON cfd.instanceid = cm.id AND cfd.value = :fieldvalue
                JOIN {grade_items} gi ON gi.iteminstance = cm.instance AND gi.itemmodule = 'quiz'
                LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
                WHERE cm.course = :courseid";

        $result = $DB->get_record_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
            'fieldvalue' => $fieldvalue
        ]);

        return $result && $result->score ? round($result->score, 2) : 0.00;
    }

    /**
     * Get questionnaire scores for a user in a course
     * 
     * Looks for a Rate question in the questionnaire with exactly 9 choices.
     * Each choice is rated by user on a scale (typically 1-5).
     * 
     * If Rate question has exactly 9 choices and responses exist:
     *   - score_materi = average of choices 1-3
     *   - score_trainer = average of choices 4-6
    *   - score_fasilitas = average of choices 7-9
     *   - score_total = average of all 9 choices
     *   - questionnaire_available = 1
     *
     * If Rate question exists but doesn't have exactly 9 choices:
     *   - Returns only score_total (average of all choices)
     *   - questionnaire_available = 0
     *   - Other scores = 0
     *
     * If no questionnaire, no Rate question, or no responses:
     *   - Returns all scores as 0
     *   - questionnaire_available = 0
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
    * @return array Array with questionnaire_available, score_materi, score_trainer, score_fasilitas, score_total
     */
    private static function get_questionnaire_scores($userid, $courseid) {
        global $DB;

        // Default response
        $default_response = [
            'questionnaire_available' => 0,
            'score_materi' => 0.00,
            'score_trainer' => 0.00,
            'score_fasilitas' => 0.00,
            'score_total' => 0.00
        ];

        try {
            // Step 1: Check if questionnaire module exists in the course
            $questionnaire = $DB->get_record_sql(
                "SELECT cm.id, q.id as questionnaire_id
                 FROM {course_modules} cm
                 JOIN {modules} m ON m.id = cm.module AND m.name = 'questionnaire'
                 JOIN {questionnaire} q ON q.id = cm.instance
                 WHERE cm.course = ? AND cm.visible = 1",
                [$courseid]
            );

            if (!$questionnaire) {
                return $default_response;
            }

            // Step 2: Find the Rate question (type_id = 8 for QUESRATE) in this questionnaire
            $rate_question = $DB->get_record('questionnaire_question', [
                'surveyid' => $questionnaire->questionnaire_id,
                'type_id' => 8  // QUESRATE = 8
            ]);

            if (!$rate_question) {
                return $default_response;
            }

            // Step 3: Count choices for this Rate question
            $choice_count = $DB->count_records('questionnaire_quest_choice', [
                'question_id' => $rate_question->id
            ]);

            // Step 4: Get response record for this user
            $response_record = $DB->get_record('questionnaire_response', [
                'questionnaireid' => $questionnaire->questionnaire_id,
                'userid' => $userid
            ]);

            if (!$response_record) {
                return $default_response;
            }

            // Step 5: Get all rating responses for the Rate question
            // Rate question responses are stored in questionnaire_response_rank table
            // Order by choice_id to maintain consistent order (choice 1, 2, 3, ...)
            $sql = "SELECT qrr.id, qrr.response_id, qrr.question_id, qrr.choice_id, qrr.rankvalue,
                           qqc.id as choice_id_in_table
                    FROM {questionnaire_response_rank} qrr
                    JOIN {questionnaire_quest_choice} qqc ON qqc.id = qrr.choice_id
                    WHERE qrr.response_id = ? AND qrr.question_id = ?
                    ORDER BY qqc.id ASC";

            $responses = $DB->get_records_sql($sql, [$response_record->id, $rate_question->id]);

            if (empty($responses)) {
                return $default_response;
            }

            // Step 6: Extract rankvalues (scores) in order
            // Ensure we have exactly the number of responses matching choice count
            $response_values = [];
            foreach ($responses as $response) {
                $rankvalue = (float) $response->rankvalue;
                // Include all rankvalues (even 0) to maintain position
                $response_values[] = $rankvalue;
            }


            // Validate we got all responses
            $score_total = round(
                array_sum($response_values) / count($response_values),
                2
            );

            if (count($response_values) !== $choice_count) {
                // Mismatch between choices and responses - return only total score
                $has_score = $score_total > 0;
                return [
                    'questionnaire_available' => $has_score ? 1 : 0,
                    'score_materi' => 0.00,
                    'score_trainer' => 0.00,
                    'score_fasilitas' => 0.00,
                    'score_total' => $score_total
                ];
            }

            // Step 8: Check if exactly 9 choices for breakdown scores
            if ($choice_count === 9) {
                $score_materi = round(
                    ($response_values[0] + $response_values[1] + $response_values[2]) / 3,
                    2
                );
                $score_trainer = round(
                    ($response_values[3] + $response_values[4] + $response_values[5]) / 3,
                    2
                );
                $score_fasilitas = round(
                    ($response_values[6] + $response_values[7] + $response_values[8]) / 3,
                    2
                );
                $has_score = ($score_materi > 0 || $score_trainer > 0 || $score_fasilitas > 0 || $score_total > 0);
                return [
                    'questionnaire_available' => $has_score ? 1 : 0,
                    'score_materi' => $score_materi,
                    'score_trainer' => $score_trainer,
                    'score_fasilitas' => $score_fasilitas,
                    'score_total' => $score_total
                ];
            }

            // Not exactly 9 choices - return only score_total
            $has_score = $score_total > 0;
            return [
                'questionnaire_available' => $has_score ? 1 : 0,
                'score_materi' => 0.00,
                'score_trainer' => 0.00,
                'score_fasilitas' => 0.00,
                'score_total' => $score_total
            ];

        } catch (Exception $e) {
            // Log error for debugging but return safe default
            error_log("Questionnaire error for user {$userid}, course {$courseid}: " . $e->getMessage());
            return $default_response;
        }
    }

    /**
     * Returns description of method parameters for get_users
     * @return external_function_parameters
     */
    public static function get_users_parameters() {
        return new external_function_parameters([
            'apikey'  => new external_value(PARAM_TEXT,  'API key for authentication'),
            'status'  => new external_value(PARAM_ALPHA, 'Filter by status: all, active, suspended', VALUE_DEFAULT, 'all'),
            'email'   => new external_value(PARAM_TEXT, 'Filter by exact email address (empty or 0 = no filter)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Get list of users with optional suspension filter
     * @param string $apikey API key
     * @param string $status Filter: 'all' | 'active' | 'suspended'
     * @param string $email  Filter by exact email address (empty = all)
     * @return array List of users
     */
    public static function get_users($apikey, $status = 'all', $email = '') {
        global $DB;

        $params = self::validate_parameters(self::get_users_parameters(), [
            'apikey'  => $apikey,
            'status'  => $status,
            'email'   => $email,
        ]);

        if (!self::validate_api_key($params['apikey'])) {
            throw new moodle_exception('invalidapikey', 'local_hrms');
        }

        $context = context_system::instance();
        self::validate_context($context);

        $allowedstatuses = ['all', 'active', 'suspended'];
        if (!in_array($params['status'], $allowedstatuses, true)) {
            throw new moodle_exception('invalidstatus', 'local_hrms');
        }

        $where = 'u.deleted = 0 AND u.confirmed = 1 AND u.id != :guestid';
        $sqlparams = ['guestid' => guest_user()->id];

        if ($params['status'] === 'active') {
            $where .= ' AND u.suspended = 0';
        } else if ($params['status'] === 'suspended') {
            $where .= ' AND u.suspended = 1';
        }

        // Treat '0', '0.0', or non-email strings as empty (no filter)
        $emailfilter = trim((string)$params['email']);
        if (!empty($emailfilter) && $emailfilter !== '0' && filter_var($emailfilter, FILTER_VALIDATE_EMAIL)) {
            $where .= ' AND u.email = :email';
            $sqlparams['email'] = $emailfilter;
        }

        $sql = "SELECT u.id, u.username, u.email, u.firstname, u.lastname,
                       COALESCE(u.institution, '') as institution,
                       u.suspended, u.timecreated, u.lastlogin
                FROM {user} u
                WHERE {$where}
                ORDER BY u.lastname, u.firstname";

        $users = $DB->get_records_sql($sql, $sqlparams);

        $result = [];
        foreach ($users as $user) {
            $result[] = [
                'id'          => (int) $user->id,
                'username'    => $user->username,
                'email'       => $user->email,
                'firstname'   => $user->firstname,
                'lastname'    => $user->lastname,
                'institution' => $user->institution ?: '',
                'suspended'   => (int) $user->suspended,
                'timecreated' => (int) $user->timecreated,
                'lastlogin'   => (int) $user->lastlogin,
            ];
        }

        return $result;
    }

    /**
     * Returns description of method result value for get_users
     * @return external_description
     */
    public static function get_users_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id'          => new external_value(PARAM_INT,   'User ID'),
                'username'    => new external_value(PARAM_TEXT,  'Username'),
                'email'       => new external_value(PARAM_EMAIL, 'User email'),
                'firstname'   => new external_value(PARAM_TEXT,  'First name'),
                'lastname'    => new external_value(PARAM_TEXT,  'Last name'),
                'institution' => new external_value(PARAM_TEXT,  'Institution / company name'),
                'suspended'   => new external_value(PARAM_INT,   'Suspended status (1=suspended, 0=active)'),
                'timecreated' => new external_value(PARAM_INT,   'Account creation timestamp'),
                'lastlogin'   => new external_value(PARAM_INT,   'Last login timestamp'),
            ])
        );
    }

    /**
     * Validate API key
     * @param string $apikey API key to validate
     * @return bool True if valid, false otherwise
     */
    private static function validate_api_key($apikey) {
        $stored_key = get_config('local_hrms', 'api_key');
        return !empty($stored_key) && $apikey === $stored_key;
    }

    /**
     * Returns description of method parameters for set_user_suspension
     * @return external_function_parameters
     */
    public static function set_user_suspension_parameters() {
        return new external_function_parameters([
            'apikey'    => new external_value(PARAM_TEXT, 'API key for authentication'),
            'userid'    => new external_value(PARAM_INT, 'User ID', VALUE_DEFAULT, 0),
            'email'     => new external_value(PARAM_EMAIL, 'User email', VALUE_DEFAULT, ''),
            'suspended' => new external_value(PARAM_INT, 'Suspend (1) or unsuspend (0) the user'),
        ]);
    }

    /**
     * Suspend or unsuspend a user by userid and/or email
     * @param string $apikey API key
     * @param int $userid User ID (0 = not used)
     * @param string $email User email (empty = not used)
     * @param int $suspended 1 = suspend, 0 = unsuspend
     * @return array Result status
     */
    public static function set_user_suspension($apikey, $userid = 0, $email = '', $suspended = 1) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        $params = self::validate_parameters(self::set_user_suspension_parameters(), [
            'apikey'    => $apikey,
            'userid'    => $userid,
            'email'     => $email,
            'suspended' => $suspended,
        ]);

        if (!self::validate_api_key($params['apikey'])) {
            throw new moodle_exception('invalidapikey', 'local_hrms');
        }

        $context = context_system::instance();
        self::validate_context($context);

        // Resolve user
        $user = null;
        if ($params['userid'] > 0) {
            $user = $DB->get_record('user', ['id' => $params['userid'], 'deleted' => 0]);
        } else if (!empty($params['email'])) {
            $user = $DB->get_record('user', ['email' => $params['email'], 'deleted' => 0]);
        }

        if (!$user) {
            throw new moodle_exception('invaliduser', 'error');
        }

        // Prevent suspending site admins
        if (is_siteadmin($user->id)) {
            throw new moodle_exception('useradminodelete', 'error');
        }

        $suspendvalue = $params['suspended'] ? 1 : 0;

        // Only update if the value actually changes
        if ((int)$user->suspended !== $suspendvalue) {
            $updateuser = (object)[
                'id'        => $user->id,
                'suspended' => $suspendvalue,
            ];
            user_update_user($updateuser, false, true);
        }

        return [
            'success'   => 1,
            'userid'    => (int)$user->id,
            'email'     => $user->email,
            'suspended' => $suspendvalue,
            'message'   => $suspendvalue ? 'User suspended' : 'User unsuspended',
        ];
    }

    /**
     * Returns description of method result value for set_user_suspension
     * @return external_description
     */
    public static function set_user_suspension_returns() {
        return new external_single_structure([
            'success'   => new external_value(PARAM_INT, 'Operation success (1)'),
            'userid'    => new external_value(PARAM_INT, 'User ID'),
            'email'     => new external_value(PARAM_EMAIL, 'User email'),
            'suspended' => new external_value(PARAM_INT, 'New suspension status (1=suspended, 0=active)'),
            'message'   => new external_value(PARAM_TEXT, 'Result message'),
        ]);
    }

    /**
     * Returns description of method parameters for create_course
     * @return external_function_parameters
     */
    public static function create_course_parameters() {
        return new external_function_parameters([
            'apikey'     => new external_value(PARAM_TEXT, 'API key for authentication'),
            'fullname'   => new external_value(PARAM_TEXT, 'Course full name'),
            'shortname'  => new external_value(PARAM_TEXT, 'Course short name'),
            'idnumber'   => new external_value(PARAM_TEXT, 'Course ID number'),
            'summary'    => new external_value(PARAM_RAW,  'Course summary', VALUE_DEFAULT, ''),
            'categoryid' => new external_value(PARAM_INT,  'Category ID', VALUE_DEFAULT, 1),
            'startdate'  => new external_value(PARAM_INT,  'Course start date (unix timestamp)', VALUE_DEFAULT, 0),
            'enddate'    => new external_value(PARAM_INT,  'Course end date (unix timestamp)', VALUE_DEFAULT, 0),
            'visible'    => new external_value(PARAM_INT,  'Visibility (1=visible, 0=hidden)', VALUE_DEFAULT, 0),
            'jp'         => new external_value(PARAM_INT,  'JP custom field value', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Create a new course
     * @param string $apikey
     * @param string $fullname
     * @param string $shortname
     * @param string $idnumber
     * @param string $summary
     * @param int    $categoryid
     * @param int    $startdate
     * @param int    $enddate
     * @param int    $visible
     * @param int    $jp
     * @return array Created course info
     */
    public static function create_course(
        $apikey, $fullname, $shortname, $idnumber, $summary = '',
        $categoryid = 1, $startdate = 0, $enddate = 0, $visible = 0, $jp = 1
    ) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::create_course_parameters(), [
            'apikey'     => $apikey,
            'fullname'   => $fullname,
            'shortname'  => $shortname,
            'idnumber'   => $idnumber,
            'summary'    => $summary,
            'categoryid' => $categoryid,
            'startdate'  => $startdate,
            'enddate'    => $enddate,
            'visible'    => $visible,
            'jp'         => $jp,
        ]);

        if (!self::validate_api_key($params['apikey'])) {
            throw new moodle_exception('invalidapikey', 'local_hrms');
        }

        $context = context_system::instance();
        self::validate_context($context);

        // Verify category exists.
        core_course_category::get($params['categoryid'], MUST_EXIST);

        // Check shortname uniqueness.
        if ($DB->record_exists('course', ['shortname' => $params['shortname']])) {
            throw new moodle_exception('shortnametaken', 'error', '', $params['shortname']);
        }

        $coursedata = (object) [
            'fullname'      => $params['fullname'],
            'shortname'     => $params['shortname'],
            'idnumber'      => $params['idnumber'],
            'summary'       => $params['summary'],
            'summaryformat' => FORMAT_HTML,
            'category'      => $params['categoryid'],
            'startdate'     => $params['startdate'] ?: time(),
            'enddate'       => $params['enddate'],
            'visible'       => $params['visible'] ? 1 : 0,
            'format'        => 'topics',
        ];

        $course = create_course($coursedata);

        // Set JP custom field.
        $handler = \core_customfield\handler::get_handler('core_course', 'course');
        $fieldsdata = $handler->get_instance_data($course->id, true);
        $coursecontext = context_course::instance($course->id);
        foreach ($fieldsdata as $fielddata) {
            if ($fielddata->get_field()->get('shortname') === 'jp') {
                $fielddata->set('contextid', $coursecontext->id);
                $fielddata->set('decvalue', (float)$params['jp']);
                $fielddata->set('value', (string)$params['jp']);
                $fielddata->save();
                break;
            }
        }

        return [
            'id'         => (int) $course->id,
            'shortname'  => $course->shortname,
            'fullname'   => $course->fullname,
            'idnumber'   => $course->idnumber ?: '',
            'summary'    => $course->summary ?: '',
            'categoryid' => (int) $course->category,
            'startdate'  => (int) $course->startdate,
            'enddate'    => (int) $course->enddate,
            'visible'    => (int) $course->visible,
            'jp'         => $params['jp'],
        ];
    }

    /**
     * Returns description of method result value for create_course
     * @return external_description
     */
    public static function create_course_returns() {
        return new external_single_structure([
            'id'         => new external_value(PARAM_INT,  'New course ID'),
            'shortname'  => new external_value(PARAM_TEXT, 'Course short name'),
            'fullname'   => new external_value(PARAM_TEXT, 'Course full name'),
            'idnumber'   => new external_value(PARAM_TEXT, 'Course ID number'),
            'summary'    => new external_value(PARAM_RAW,  'Course summary'),
            'categoryid' => new external_value(PARAM_INT,  'Category ID'),
            'startdate'  => new external_value(PARAM_INT,  'Start date'),
            'enddate'    => new external_value(PARAM_INT,  'End date'),
            'visible'    => new external_value(PARAM_INT,  'Visibility (1=visible, 0=hidden)'),
            'jp'         => new external_value(PARAM_INT,  'JP custom field value'),
        ]);
    }

    /**
     * Returns description of method parameters for update_course
     * @return external_function_parameters
     */
    public static function update_course_parameters() {
        return new external_function_parameters([
            'apikey'       => new external_value(PARAM_TEXT,  'API key for authentication'),
            'idnumber'     => new external_value(PARAM_TEXT,  'Course ID number to identify the course'),
            'fullname'     => new external_value(PARAM_TEXT,  'New course full name',                     VALUE_DEFAULT, ''),
            'shortname'    => new external_value(PARAM_TEXT,  'New course short name',                    VALUE_DEFAULT, ''),
            'new_idnumber' => new external_value(PARAM_TEXT,  'New course ID number (rename idnumber)',   VALUE_DEFAULT, ''),
            'summary'      => new external_value(PARAM_RAW,   'New course summary',                       VALUE_DEFAULT, ''),
            'categoryid'   => new external_value(PARAM_INT,   'New category ID (0 = no change)',          VALUE_DEFAULT, 0),
            'startdate'    => new external_value(PARAM_INT,   'New start date unix timestamp (0 = no change)', VALUE_DEFAULT, 0),
            'enddate'      => new external_value(PARAM_INT,   'New end date unix timestamp (-1 = no change)',  VALUE_DEFAULT, -1),
            'visible'      => new external_value(PARAM_INT,   'Visibility: 1=visible, 0=hidden, -1=no change', VALUE_DEFAULT, -1),
            'jp'           => new external_value(PARAM_INT,   'JP custom field value (0 = no change)',    VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Update an existing course identified by its ID number
     *
     * @param string $apikey       API key
     * @param string $idnumber     Course idnumber to look up
     * @param string $fullname     New full name (empty = no change)
     * @param string $shortname    New short name (empty = no change)
     * @param string $new_idnumber New idnumber (empty = no change)
     * @param string $summary      New summary (empty = no change)
     * @param int    $categoryid   New category ID (0 = no change)
     * @param int    $startdate    New start date timestamp (0 = no change)
     * @param int    $enddate      New end date timestamp (-1 = no change)
     * @param int    $visible      Visibility 1/0 (-1 = no change)
     * @param int    $jp           JP custom field value (0 = no change)
     * @return array Updated course info
     */
    public static function update_course(
        $apikey, $idnumber, $fullname = '', $shortname = '', $new_idnumber = '',
        $summary = '', $categoryid = 0, $startdate = 0, $enddate = -1, $visible = -1, $jp = 0
    ) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::update_course_parameters(), [
            'apikey'       => $apikey,
            'idnumber'     => $idnumber,
            'fullname'     => $fullname,
            'shortname'    => $shortname,
            'new_idnumber' => $new_idnumber,
            'summary'      => $summary,
            'categoryid'   => $categoryid,
            'startdate'    => $startdate,
            'enddate'      => $enddate,
            'visible'      => $visible,
            'jp'           => $jp,
        ]);

        if (!self::validate_api_key($params['apikey'])) {
            throw new moodle_exception('invalidapikey', 'local_hrms');
        }

        $context = context_system::instance();
        self::validate_context($context);

        // Find course by idnumber.
        $course = $DB->get_record('course', ['idnumber' => $params['idnumber']], '*', MUST_EXIST);

        $coursedata = (object) ['id' => $course->id];

        if ($params['fullname'] !== '') {
            $coursedata->fullname = $params['fullname'];
        }

        if ($params['shortname'] !== '') {
            // Ensure new shortname is not taken by a different course.
            if ($DB->record_exists_select('course', 'shortname = :sn AND id != :cid',
                    ['sn' => $params['shortname'], 'cid' => $course->id])) {
                throw new moodle_exception('shortnametaken', 'error', '', $params['shortname']);
            }
            $coursedata->shortname = $params['shortname'];
        }

        if ($params['new_idnumber'] !== '') {
            // Ensure new idnumber is not taken by a different course.
            if ($DB->record_exists_select('course', 'idnumber = :idn AND id != :cid',
                    ['idn' => $params['new_idnumber'], 'cid' => $course->id])) {
                throw new moodle_exception('courseidnumbertaken', 'error', '', $params['new_idnumber']);
            }
            $coursedata->idnumber = $params['new_idnumber'];
        }

        if ($params['summary'] !== '') {
            $coursedata->summary       = $params['summary'];
            $coursedata->summaryformat = FORMAT_HTML;
        }

        if ($params['categoryid'] > 0) {
            core_course_category::get($params['categoryid'], MUST_EXIST);
            $coursedata->category = $params['categoryid'];
        }

        if ($params['startdate'] > 0) {
            $coursedata->startdate = $params['startdate'];
        }

        if ($params['enddate'] >= 0) {
            $coursedata->enddate = $params['enddate'];
        }

        if ($params['visible'] >= 0) {
            $coursedata->visible = $params['visible'] ? 1 : 0;
        }

        update_course($coursedata);

        // Update JP custom field if requested.
        if ($params['jp'] > 0) {
            $handler    = \core_customfield\handler::get_handler('core_course', 'course');
            $fieldsdata = $handler->get_instance_data($course->id, true);
            foreach ($fieldsdata as $fielddata) {
                if ($fielddata->get_field()->get('shortname') === 'jp') {
                    $fielddata->set('decvalue', (float)$params['jp']);
                    $fielddata->set('value', (string)$params['jp']);
                    $fielddata->save();
                    break;
                }
            }
        }

        // Reload updated record.
        $updated = $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);

        // Read current JP value for return.
        $jpvalue = 0;
        $handler    = \core_customfield\handler::get_handler('core_course', 'course');
        $fieldsdata = $handler->get_instance_data($updated->id, true);
        foreach ($fieldsdata as $fielddata) {
            if ($fielddata->get_field()->get('shortname') === 'jp') {
                $jpvalue = (int) $fielddata->get('value');
                break;
            }
        }

        return [
            'id'         => (int) $updated->id,
            'shortname'  => $updated->shortname,
            'fullname'   => $updated->fullname,
            'idnumber'   => $updated->idnumber ?: '',
            'summary'    => $updated->summary ?: '',
            'categoryid' => (int) $updated->category,
            'startdate'  => (int) $updated->startdate,
            'enddate'    => (int) $updated->enddate,
            'visible'    => (int) $updated->visible,
            'jp'         => $jpvalue,
        ];
    }

    /**
     * Returns description of method result value for update_course
     * @return external_description
     */
    public static function update_course_returns() {
        return new external_single_structure([
            'id'         => new external_value(PARAM_INT,  'Course ID'),
            'shortname'  => new external_value(PARAM_TEXT, 'Course short name'),
            'fullname'   => new external_value(PARAM_TEXT, 'Course full name'),
            'idnumber'   => new external_value(PARAM_TEXT, 'Course ID number'),
            'summary'    => new external_value(PARAM_RAW,  'Course summary'),
            'categoryid' => new external_value(PARAM_INT,  'Category ID'),
            'startdate'  => new external_value(PARAM_INT,  'Start date'),
            'enddate'    => new external_value(PARAM_INT,  'End date'),
            'visible'    => new external_value(PARAM_INT,  'Visibility (1=visible, 0=hidden)'),
            'jp'         => new external_value(PARAM_INT,  'JP custom field value'),
        ]);
    }

    /**
     * Returns description of method parameters for create_user
     * @return external_function_parameters
     */
    public static function create_user_parameters() {
        return new external_function_parameters([
            'apikey'      => new external_value(PARAM_TEXT,  'API key for authentication'),
            'username'    => new external_value(PARAM_USERNAME, 'Username (lowercase, no spaces)'),
            'email'       => new external_value(PARAM_EMAIL, 'Email address'),
            'firstname'   => new external_value(PARAM_TEXT,  'First name'),
            'lastname'    => new external_value(PARAM_TEXT,  'Last name'),
            'password'    => new external_value(PARAM_RAW,   'Plain-text password'),
            'institution' => new external_value(PARAM_TEXT,  'Institution / company name', VALUE_DEFAULT, ''),
            'department'  => new external_value(PARAM_TEXT,  'Department', VALUE_DEFAULT, ''),
            'phone1'      => new external_value(PARAM_TEXT,  'Phone number', VALUE_DEFAULT, ''),
            'city'        => new external_value(PARAM_TEXT,  'City', VALUE_DEFAULT, ''),
            'country'     => new external_value(PARAM_ALPHA, 'Two-letter country code (e.g. ID)', VALUE_DEFAULT, ''),
            'auth'        => new external_value(PARAM_PLUGIN, 'Auth plugin (default: manual)', VALUE_DEFAULT, 'manual'),
        ]);
    }

    /**
     * Create a new Moodle user
     * @param string $apikey
     * @param string $username
     * @param string $email
     * @param string $firstname
     * @param string $lastname
     * @param string $password
     * @param string $institution
     * @param string $department
     * @param string $phone1
     * @param string $city
     * @param string $country
     * @param string $auth
     * @return array Created user info
     */
    public static function create_user(
        $apikey, $username, $email, $firstname, $lastname, $password,
        $institution = '', $department = '', $phone1 = '', $city = '', $country = '', $auth = 'manual'
    ) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');

        $params = self::validate_parameters(self::create_user_parameters(), [
            'apikey'      => $apikey,
            'username'    => $username,
            'email'       => $email,
            'firstname'   => $firstname,
            'lastname'    => $lastname,
            'password'    => $password,
            'institution' => $institution,
            'department'  => $department,
            'phone1'      => $phone1,
            'city'        => $city,
            'country'     => $country,
            'auth'        => $auth,
        ]);

        if (!self::validate_api_key($params['apikey'])) {
            throw new moodle_exception('invalidapikey', 'local_hrms');
        }

        $context = context_system::instance();
        self::validate_context($context);

        // Check email uniqueness.
        if ($DB->record_exists('user', ['email' => $params['email'], 'deleted' => 0])) {
            throw new moodle_exception('emailalreadyused', 'local_hrms');
        }

        // Check username uniqueness.
        if ($DB->record_exists('user', ['username' => $params['username'], 'mnethostid' => $CFG->mnet_localhost_id])) {
            throw new moodle_exception('usernameexists', 'error');
        }

        // Validate auth plugin exists.
        if (!exists_auth_plugin($params['auth'])) {
            throw new moodle_exception('authpluginnotfound', 'debug', '', $params['auth']);
        }

        $userdata = (object) [
            'username'    => $params['username'],
            'email'       => $params['email'],
            'firstname'   => $params['firstname'],
            'lastname'    => $params['lastname'],
            'password'    => $params['password'],
            'institution' => $params['institution'],
            'department'  => $params['department'],
            'phone1'      => $params['phone1'],
            'city'        => $params['city'],
            'country'     => $params['country'],
            'auth'        => $params['auth'],
            'confirmed'   => 1,
            'mnethostid'  => $CFG->mnet_localhost_id,
            'lang'        => $CFG->lang ?: 'en',
        ];

        $userid = user_create_user($userdata, true, true);

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        return [
            'id'          => (int) $user->id,
            'username'    => $user->username,
            'email'       => $user->email,
            'firstname'   => $user->firstname,
            'lastname'    => $user->lastname,
            'institution' => $user->institution ?: '',
            'department'  => $user->department ?: '',
            'phone1'      => $user->phone1 ?: '',
            'city'        => $user->city ?: '',
            'country'     => $user->country ?: '',
            'auth'        => $user->auth,
            'timecreated' => (int) $user->timecreated,
        ];
    }

    /**
     * Returns description of method result value for create_user
     * @return external_description
     */
    public static function create_user_returns() {
        return new external_single_structure([
            'id'          => new external_value(PARAM_INT,   'New user ID'),
            'username'    => new external_value(PARAM_TEXT,  'Username'),
            'email'       => new external_value(PARAM_EMAIL, 'Email address'),
            'firstname'   => new external_value(PARAM_TEXT,  'First name'),
            'lastname'    => new external_value(PARAM_TEXT,  'Last name'),
            'institution' => new external_value(PARAM_TEXT,  'Institution / company name'),
            'department'  => new external_value(PARAM_TEXT,  'Department'),
            'phone1'      => new external_value(PARAM_TEXT,  'Phone number'),
            'city'        => new external_value(PARAM_TEXT,  'City'),
            'country'     => new external_value(PARAM_TEXT,  'Country code (e.g. ID)'),
            'auth'        => new external_value(PARAM_TEXT,  'Auth plugin used'),
            'timecreated' => new external_value(PARAM_INT,   'Account creation timestamp'),
        ]);
    }

    /**
     * Returns description of method parameters for update_user
     * @return external_function_parameters
     */
    public static function update_user_parameters() {
        return new external_function_parameters([
            'apikey'      => new external_value(PARAM_TEXT,  'API key for authentication'),
            'userid'      => new external_value(PARAM_INT,   'User ID', VALUE_DEFAULT, 0),
            'email'       => new external_value(PARAM_EMAIL, 'User email to identify user', VALUE_DEFAULT, ''),
            'new_email'   => new external_value(PARAM_EMAIL, 'New email address (empty = no change)', VALUE_DEFAULT, ''),
            'firstname'   => new external_value(PARAM_TEXT,  'New first name (empty = no change)', VALUE_DEFAULT, ''),
            'lastname'    => new external_value(PARAM_TEXT,  'New last name (empty = no change)', VALUE_DEFAULT, ''),
            'institution' => new external_value(PARAM_TEXT,  'New institution/company name (empty = no change)', VALUE_DEFAULT, ''),
            'department'  => new external_value(PARAM_TEXT,     'New department (empty = no change)', VALUE_DEFAULT, ''),
            'phone1'      => new external_value(PARAM_TEXT,     'New phone number (empty = no change)', VALUE_DEFAULT, ''),
            'password'    => new external_value(PARAM_RAW,      'New plain-text password (empty = no change)', VALUE_DEFAULT, ''),
            'username'    => new external_value(PARAM_USERNAME, 'New username (empty = no change)', VALUE_DEFAULT, ''),
            'auth'        => new external_value(PARAM_TEXT,     'New authentication method e.g. manual, ldap (empty = no change)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Update user data (firstname, lastname, institution, email)
     * @param string $apikey API key
     * @param int    $userid User ID (0 = identify by email)
     * @param string $email  Current email to identify user (if userid = 0)
     * @param string $new_email   New email (empty = no change)
     * @param string $firstname   New first name (empty = no change)
     * @param string $lastname    New last name (empty = no change)
     * @param string $institution New institution (empty = no change)
     * @param string $department  New department (empty = no change)
     * @param string $phone1      New phone number (empty = no change)
     * @param string $password    New password (empty = no change)
     * @param string $username    New username (empty = no change)
     * @param string $auth        New auth method (empty = no change)
     * @return array Updated user info
     */
    public static function update_user(
        $apikey, $userid = 0, $email = '', $new_email = '',
        $firstname = '', $lastname = '', $institution = '',
        $department = '', $phone1 = '', $password = '',
        $username = '', $auth = ''
    ) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');

        $params = self::validate_parameters(self::update_user_parameters(), [
            'apikey'      => $apikey,
            'userid'      => $userid,
            'email'       => $email,
            'new_email'   => $new_email,
            'firstname'   => $firstname,
            'lastname'    => $lastname,
            'institution' => $institution,
            'department'  => $department,
            'phone1'      => $phone1,
            'password'    => $password,
            'username'    => $username,
            'auth'        => $auth,
        ]);

        if (!self::validate_api_key($params['apikey'])) {
            throw new moodle_exception('invalidapikey', 'local_hrms');
        }

        $context = context_system::instance();
        self::validate_context($context);

        // Resolve user by userid or email.
        $user = null;
        if ($params['userid'] > 0) {
            $user = $DB->get_record('user', ['id' => $params['userid'], 'deleted' => 0]);
        } else if (!empty($params['email'])) {
            $user = $DB->get_record('user', ['email' => $params['email'], 'deleted' => 0]);
        }

        if (!$user) {
            throw new moodle_exception('invaliduser', 'error');
        }

        // Prevent editing site admins.
        if (is_siteadmin($user->id)) {
            throw new moodle_exception('useradminodelete', 'error');
        }

        $updateuser = (object) ['id' => $user->id];

        if ($params['firstname'] !== '') {
            $updateuser->firstname = $params['firstname'];
        }

        if ($params['lastname'] !== '') {
            $updateuser->lastname = $params['lastname'];
        }

        if ($params['institution'] !== '') {
            $updateuser->institution = $params['institution'];
        }

        if ($params['department'] !== '') {
            $updateuser->department = $params['department'];
        }

        if ($params['phone1'] !== '') {
            $updateuser->phone1 = $params['phone1'];
        }

        if ($params['new_email'] !== '') {
            // Check new email is not already in use by another user.
            if ($DB->record_exists_select('user', 'email = :email AND id != :uid AND deleted = 0',
                    ['email' => $params['new_email'], 'uid' => $user->id])) {
                throw new moodle_exception('emailalreadyused', 'local_hrms');
            }
            $updateuser->email = $params['new_email'];
        }

        if ($params['password'] !== '') {
            $updateuser->password = $params['password'];
        }

        if ($params['username'] !== '') {
            $updateuser->username = $params['username'];
        }

        if ($params['auth'] !== '') {
            $updateuser->auth = $params['auth'];
        }

        user_update_user($updateuser, $params['password'] !== '', true);

        // Reload updated record.
        $updated = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);

        return [
            'id'          => (int) $updated->id,
            'username'    => $updated->username,
            'email'       => $updated->email,
            'firstname'   => $updated->firstname,
            'lastname'    => $updated->lastname,
            'institution' => $updated->institution ?: '',
            'department'  => $updated->department ?: '',
            'phone1'      => $updated->phone1 ?: '',
            'auth'        => $updated->auth ?: '',
        ];
    }

    /**
     * Returns description of method result value for update_user
     * @return external_description
     */
    public static function update_user_returns() {
        return new external_single_structure([
            'id'          => new external_value(PARAM_INT,   'User ID'),
            'username'    => new external_value(PARAM_TEXT,  'Username'),
            'email'       => new external_value(PARAM_EMAIL, 'Email address (after update)'),
            'firstname'   => new external_value(PARAM_TEXT,  'First name (after update)'),
            'lastname'    => new external_value(PARAM_TEXT,  'Last name (after update)'),
            'institution' => new external_value(PARAM_TEXT,  'Institution / company name (after update)'),
            'department'  => new external_value(PARAM_TEXT,  'Department (after update)'),
            'phone1'      => new external_value(PARAM_TEXT,  'Phone number (after update)'),
            'auth'        => new external_value(PARAM_TEXT,  'Authentication method (after update)'),
        ]);
    }

    /**
     * Returns description of method parameters for enrol_user
     * @return external_function_parameters
     */
    public static function enrol_user_parameters() {
        return new external_function_parameters([
            'apikey'   => new external_value(PARAM_TEXT,  'API key for authentication'),
            'userid'   => new external_value(PARAM_INT,   'User ID (0 = identify by email)',          VALUE_DEFAULT, 0),
            'email'    => new external_value(PARAM_EMAIL, 'User email (if userid = 0)',               VALUE_DEFAULT, ''),
            'courseid' => new external_value(PARAM_INT,   'Course ID (0 = identify by idnumber)',     VALUE_DEFAULT, 0),
            'idnumber' => new external_value(PARAM_TEXT,  'Course ID number (if courseid = 0)',       VALUE_DEFAULT, ''),
            'role'     => new external_value(PARAM_TEXT,  'Role shortname e.g. student, teacher (empty = default role of enrol instance)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Enrol a user into a course using the manual enrolment plugin
     * @param string $apikey
     * @param int    $userid   User ID (0 = use email)
     * @param string $email    User email (if userid = 0)
     * @param int    $courseid Course ID (0 = use idnumber)
     * @param string $idnumber Course ID number (if courseid = 0)
     * @param string $role     Role shortname e.g. student, teacher (empty = default role from enrol instance)
     * @return array Result
     */
    public static function enrol_user($apikey, $userid = 0, $email = '', $courseid = 0, $idnumber = '', $role = '') {
        global $DB;

        $params = self::validate_parameters(self::enrol_user_parameters(), [
            'apikey'   => $apikey,
            'userid'   => $userid,
            'email'    => $email,
            'courseid' => $courseid,
            'idnumber' => $idnumber,
            'role'     => $role,
        ]);

        if (!self::validate_api_key($params['apikey'])) {
            throw new moodle_exception('invalidapikey', 'local_hrms');
        }

        $context = context_system::instance();
        self::validate_context($context);

        // Resolve user.
        $user = null;
        if ($params['userid'] > 0) {
            $user = $DB->get_record('user', ['id' => $params['userid'], 'deleted' => 0]);
        } else if (!empty($params['email'])) {
            $user = $DB->get_record('user', ['email' => $params['email'], 'deleted' => 0]);
        }
        if (!$user) {
            throw new moodle_exception('invaliduser', 'error');
        }

        // Resolve course.
        if ($params['courseid'] > 0) {
            $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        } else if (!empty($params['idnumber'])) {
            $course = $DB->get_record('course', ['idnumber' => $params['idnumber']], '*', MUST_EXIST);
        } else {
            throw new moodle_exception('missingparam', 'error', '', 'courseid or idnumber');
        }

        if ($course->id == SITEID) {
            throw new moodle_exception('invalidcourseid', 'error');
        }

        // Get manual enrol plugin.
        $enrolplugin = enrol_get_plugin('manual');
        if (!$enrolplugin) {
            throw new moodle_exception('enrolnotinstalled', 'enrol', '', 'manual');
        }

        // Find or create the manual enrol instance for this course.
        $instances = enrol_get_instances($course->id, true);
        $manualinstance = null;
        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {
                $manualinstance = $instance;
                break;
            }
        }
        if (!$manualinstance) {
            $instanceid = $enrolplugin->add_default_instance($course);
            $manualinstance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        }

        // Resolve role: look up by shortname or fall back to the enrol instance's default role.
        if ($params['role'] !== '') {
            $rolerecord = $DB->get_record('role', ['shortname' => $params['role']]);
            if (!$rolerecord) {
                throw new moodle_exception('invalidrole', 'error');
            }
            $finalroleid = (int) $rolerecord->id;
        } else {
            $finalroleid = (int) $manualinstance->roleid;
        }
        $roleshortname = $DB->get_field('role', 'shortname', ['id' => $finalroleid]);

        // Enrol user (idempotent; Moodle updates the enrolment if the user is already enrolled).
        $enrolplugin->enrol_user($manualinstance, $user->id, $finalroleid);

        return [
            'success'  => 1,
            'userid'   => (int) $user->id,
            'email'    => $user->email,
            'courseid' => (int) $course->id,
            'idnumber' => $course->idnumber ?: '',
            'role'     => $roleshortname,
            'message'  => 'User enrolled successfully',
        ];
    }

    /**
     * Returns description of method result value for enrol_user
     * @return external_description
     */
    public static function enrol_user_returns() {
        return new external_single_structure([
            'success'  => new external_value(PARAM_INT,   'Operation success (1)'),
            'userid'   => new external_value(PARAM_INT,   'User ID'),
            'email'    => new external_value(PARAM_EMAIL, 'User email address'),
            'courseid' => new external_value(PARAM_INT,   'Course ID'),
            'idnumber' => new external_value(PARAM_TEXT,  'Course ID number'),
            'role'     => new external_value(PARAM_TEXT,  'Role shortname used for enrolment'),
            'message'  => new external_value(PARAM_TEXT,  'Result message'),
        ]);
    }

    /**
     * Returns description of method parameters for unenrol_user
     * @return external_function_parameters
     */
    public static function unenrol_user_parameters() {
        return new external_function_parameters([
            'apikey'   => new external_value(PARAM_TEXT,  'API key for authentication'),
            'userid'   => new external_value(PARAM_INT,   'User ID (0 = identify by email)',      VALUE_DEFAULT, 0),
            'email'    => new external_value(PARAM_EMAIL, 'User email (if userid = 0)',           VALUE_DEFAULT, ''),
            'courseid' => new external_value(PARAM_INT,   'Course ID (0 = identify by idnumber)', VALUE_DEFAULT, 0),
            'idnumber' => new external_value(PARAM_TEXT,  'Course ID number (if courseid = 0)',   VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Unenrol a user from a course (removes all enrolment instances)
     * @param string $apikey
     * @param int    $userid   User ID (0 = use email)
     * @param string $email    User email (if userid = 0)
     * @param int    $courseid Course ID (0 = use idnumber)
     * @param string $idnumber Course ID number (if courseid = 0)
     * @return array Result
     */
    public static function unenrol_user($apikey, $userid = 0, $email = '', $courseid = 0, $idnumber = '') {
        global $DB;

        $params = self::validate_parameters(self::unenrol_user_parameters(), [
            'apikey'   => $apikey,
            'userid'   => $userid,
            'email'    => $email,
            'courseid' => $courseid,
            'idnumber' => $idnumber,
        ]);

        if (!self::validate_api_key($params['apikey'])) {
            throw new moodle_exception('invalidapikey', 'local_hrms');
        }

        $context = context_system::instance();
        self::validate_context($context);

        // Resolve user.
        $user = null;
        if ($params['userid'] > 0) {
            $user = $DB->get_record('user', ['id' => $params['userid'], 'deleted' => 0]);
        } else if (!empty($params['email'])) {
            $user = $DB->get_record('user', ['email' => $params['email'], 'deleted' => 0]);
        }
        if (!$user) {
            throw new moodle_exception('invaliduser', 'error');
        }

        // Resolve course.
        if ($params['courseid'] > 0) {
            $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        } else if (!empty($params['idnumber'])) {
            $course = $DB->get_record('course', ['idnumber' => $params['idnumber']], '*', MUST_EXIST);
        } else {
            throw new moodle_exception('missingparam', 'error', '', 'courseid or idnumber');
        }

        if ($course->id == SITEID) {
            throw new moodle_exception('invalidcourseid', 'error');
        }

        // Verify user is currently enrolled.
        if (!is_enrolled(context_course::instance($course->id), $user->id)) {
            throw new moodle_exception('notenrolled', 'enrol');
        }

        // Unenrol from all enrol instances in the course.
        $instances = enrol_get_instances($course->id, false);
        foreach ($instances as $instance) {
            $enrolplugin = enrol_get_plugin($instance->enrol);
            if ($enrolplugin) {
                $enrolplugin->unenrol_user($instance, $user->id);
            }
        }

        return [
            'success'  => 1,
            'userid'   => (int) $user->id,
            'courseid' => (int) $course->id,
            'message'  => 'User unenrolled successfully',
        ];
    }

    /**
     * Returns description of method result value for unenrol_user
     * @return external_description
     */
    public static function unenrol_user_returns() {
        return new external_single_structure([
            'success'  => new external_value(PARAM_INT,  'Operation success (1)'),
            'userid'   => new external_value(PARAM_INT,  'User ID'),
            'courseid' => new external_value(PARAM_INT,  'Course ID'),
            'message'  => new external_value(PARAM_TEXT, 'Result message'),
        ]);
    }

    /**
     * Returns description of method parameters for get_course_progress
     * @return external_function_parameters
     */
    public static function get_course_progress_parameters() {
        return new external_function_parameters([
            'apikey'   => new external_value(PARAM_TEXT,  'API key for authentication'),
            'courseid' => new external_value(PARAM_INT,   'Course ID (0 = identify by idnumber)', VALUE_DEFAULT, 0),
            'idnumber' => new external_value(PARAM_TEXT,  'Course ID number (if courseid = 0)',   VALUE_DEFAULT, ''),
            'userid'   => new external_value(PARAM_INT,   'User ID filter (0 = all users)',        VALUE_DEFAULT, 0),
            'email'    => new external_value(PARAM_EMAIL, 'Filter by exact user email address',   VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Get course progress summary per enrolled user
     * @param string $apikey   API key
     * @param int    $courseid Course ID (0 = use idnumber)
     * @param string $idnumber Course ID number (if courseid = 0)
     * @param int    $userid   User ID filter (0 = all users)
     * @param string $email    Filter by exact user email address (empty = all users)
     * @return array Progress records
     */
    public static function get_course_progress($apikey, $courseid = 0, $idnumber = '', $userid = 0, $email = '') {
        global $DB;

        $params = self::validate_parameters(self::get_course_progress_parameters(), [
            'apikey'   => $apikey,
            'courseid' => $courseid,
            'idnumber' => $idnumber,
            'userid'   => $userid,
            'email'    => $email,
        ]);

        if (!self::validate_api_key($params['apikey'])) {
            throw new moodle_exception('invalidapikey', 'local_hrms');
        }

        $context = context_system::instance();
        self::validate_context($context);

        // Resolve course filter.
        $sqlparams = ['siteid' => SITEID];
        $coursecondition = '';

        if ($params['courseid'] > 0) {
            $coursecondition = ' AND c.id = :courseid';
            $sqlparams['courseid'] = $params['courseid'];
        } else if (!empty($params['idnumber'])) {
            $course = $DB->get_record('course', ['idnumber' => $params['idnumber']], 'id', MUST_EXIST);
            $coursecondition = ' AND c.id = :courseid';
            $sqlparams['courseid'] = $course->id;
        }

        $usercondition = '';
        if ($params['userid'] > 0) {
            $usercondition = ' AND u.id = :userid';
            $sqlparams['userid'] = $params['userid'];
        } else if (!empty($params['email'])) {
            $usercondition = ' AND u.email = :email';
            $sqlparams['email'] = $params['email'];
        }

        // Main query: one row per enrolled user per course.
        // modules_total  = visible course modules that have completion tracking enabled.
        // modules_completed = those modules the user has marked complete (completionstate >= 1).
        $sql = "SELECT CONCAT(u.id, '-', c.id) as id,
                       u.id as user_id, u.email, u.firstname, u.lastname,
                       COALESCE(u.institution, '') as company_name,
                       c.id as course_id, c.shortname, c.fullname as course_name,
                       COALESCE(cc.timecompleted, 0) as timecompleted,
                       (
                           SELECT COUNT(*)
                           FROM {course_modules} cm
                           WHERE cm.course = c.id
                             AND cm.completion > 0
                             AND cm.visible = 1
                             AND cm.deletioninprogress = 0
                       ) as modules_total,
                       (
                           SELECT COUNT(*)
                           FROM {course_modules} cm
                           JOIN {course_modules_completion} cmc
                               ON cmc.coursemoduleid = cm.id
                              AND cmc.userid = u.id
                              AND cmc.completionstate >= 1
                           WHERE cm.course = c.id
                             AND cm.completion > 0
                             AND cm.visible = 1
                             AND cm.deletioninprogress = 0
                       ) as modules_completed
                FROM {user} u
                JOIN {user_enrolments} ue ON u.id = ue.userid
                JOIN {enrol} e ON ue.enrolid = e.id
                JOIN {course} c ON e.courseid = c.id
                LEFT JOIN {course_completions} cc ON u.id = cc.userid AND c.id = cc.course
                WHERE u.deleted = 0
                AND u.confirmed = 1
                AND c.id != :siteid
                AND c.visible = 1
                {$coursecondition}
                {$usercondition}
                ORDER BY c.fullname, u.lastname, u.firstname";

        $rows = $DB->get_records_sql($sql, $sqlparams);

        $result = [];
        foreach ($rows as $row) {
            $modules_total     = (int) $row->modules_total;
            $modules_completed = (int) $row->modules_completed;
            $percentage = $modules_total > 0
                ? round($modules_completed / $modules_total * 100, 2)
                : 0.00;

            $result[] = [
                'user_id'              => (int) $row->user_id,
                'email'                => $row->email,
                'firstname'            => $row->firstname,
                'lastname'             => $row->lastname,
                'company_name'         => $row->company_name ?: '',
                'course_id'            => (int) $row->course_id,
                'course_shortname'     => $row->shortname,
                'course_name'          => $row->course_name,
                'modules_total'        => $modules_total,
                'modules_completed'    => $modules_completed,
                'completion_percentage'=> $percentage,
                'is_completed'         => $row->timecompleted ? 1 : 0,
                'completion_date'      => (int) $row->timecompleted,
            ];
        }

        return $result;
    }

    /**
     * Returns description of method result value for get_course_progress
     * @return external_description
     */
    public static function get_course_progress_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'user_id'               => new external_value(PARAM_INT,   'User ID'),
                'email'                 => new external_value(PARAM_EMAIL, 'User email'),
                'firstname'             => new external_value(PARAM_TEXT,  'First name'),
                'lastname'              => new external_value(PARAM_TEXT,  'Last name'),
                'company_name'          => new external_value(PARAM_TEXT,  'Company / institution name'),
                'course_id'             => new external_value(PARAM_INT,   'Course ID'),
                'course_shortname'      => new external_value(PARAM_TEXT,  'Course short name'),
                'course_name'           => new external_value(PARAM_TEXT,  'Course full name'),
                'modules_total'         => new external_value(PARAM_INT,   'Total activities with completion tracking enabled'),
                'modules_completed'     => new external_value(PARAM_INT,   'Activities completed by the user'),
                'completion_percentage' => new external_value(PARAM_FLOAT, 'Completion percentage (0-100)'),
                'is_completed'          => new external_value(PARAM_INT,   'Course completion status: 1 = completed, 0 = in progress'),
                'completion_date'       => new external_value(PARAM_INT,   'Course completion timestamp (0 if not yet completed)'),
            ])
        );
    }

}