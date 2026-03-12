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
            'apikey' => new external_value(PARAM_TEXT, 'API key for authentication')
        ]);
    }

    /**
     * Get list of active courses
     * @param string $apikey API key
     * @return array List of active courses
     */
    public static function get_active_courses($apikey) {
        global $DB;

        // Validate parameters
        $params = self::validate_parameters(self::get_active_courses_parameters(), [
            'apikey' => $apikey
        ]);

        // Validate API key
        if (!self::validate_api_key($params['apikey'])) {
            throw new moodle_exception('invalidapikey', 'local_hrms');
        }

        // Get context
        $context = context_system::instance();
        self::validate_context($context);

        // Get active courses (exclude site course)
        $sql = "SELECT c.id, c.idnumber, c.shortname, c.fullname, c.summary,
                       c.startdate, c.enddate, c.visible,
                       cc.id as category_id, cc.name as category_name,
                       COALESCE(cfd.value, '') as jp
                FROM {course} c
                JOIN {course_categories} cc ON cc.id = c.category
                LEFT JOIN {customfield_category} cfc ON cfc.component = 'core_course' AND cfc.area = 'course'
                LEFT JOIN {customfield_field} cff ON cff.shortname = 'jp' AND cff.categoryid = cfc.id
                LEFT JOIN {customfield_data} cfd ON cfd.instanceid = c.id AND cfd.fieldid = cff.id
                WHERE c.id != :siteid 
                AND c.visible = 1
                ORDER BY cc.name, c.fullname";
        
        $courses = $DB->get_records_sql($sql, ['siteid' => SITEID]);
        
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
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_OPTIONAL, 0)
        ]);
    }

    /**
     * Get participants in courses
     * @param string $apikey API key
     * @param int $courseid Course ID (0 for all courses)
     * @return array List of participants
     */
    public static function get_course_participants($apikey, $courseid = 0) {
        global $DB;

        // Validate parameters
        $params = self::validate_parameters(self::get_course_participants_parameters(), [
            'apikey' => $apikey,
            'courseid' => $courseid
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
                       COALESCE(uid.data, '') as company_name,
                       c.id as course_id, c.shortname, c.fullname as course_name,
                       ue.timecreated as enrollment_date
                FROM {user} u
                JOIN {user_enrolments} ue ON u.id = ue.userid
                JOIN {enrol} e ON ue.enrolid = e.id
                JOIN {course} c ON e.courseid = c.id
                LEFT JOIN {user_info_field} uif ON uif.shortname = 'branch'
                LEFT JOIN {user_info_data} uid ON u.id = uid.userid AND uid.fieldid = uif.id
                WHERE u.deleted = 0 
                AND u.confirmed = 1
                AND c.id != :siteid
                AND c.visible = 1";

        $sqlparams = ['siteid' => SITEID];

        if ($params['courseid'] > 0) {
            $sql .= " AND c.id = :courseid";
            $sqlparams['courseid'] = $params['courseid'];
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
                'course_shortname' => $participant->shortname,
                'course_name' => $participant->course_name,
                'enrollment_date' => $participant->enrollment_date
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
                'course_shortname' => new external_value(PARAM_TEXT, 'Course short name'),
                'course_name' => new external_value(PARAM_TEXT, 'Course name'),
                'enrollment_date' => new external_value(PARAM_INT, 'Enrollment date')
            ])
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_course_results_parameters() {
        return new external_function_parameters([
            'apikey' => new external_value(PARAM_TEXT, 'API key for authentication'),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_OPTIONAL, 0),
            'userid' => new external_value(PARAM_INT, 'User ID', VALUE_OPTIONAL, 0)
        ]);
    }

    /**
     * Get course results with pre-test and post-test scores
     * @param string $apikey API key
     * @param int $courseid Course ID (0 for all courses)
     * @param int $userid User ID (0 for all users)
     * @return array List of course results
     */
    public static function get_course_results($apikey, $courseid = 0, $userid = 0) {
        global $DB;

        // Validate parameters
        $params = self::validate_parameters(self::get_course_results_parameters(), [
            'apikey' => $apikey,
            'courseid' => $courseid,
            'userid' => $userid
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
                       COALESCE(uid.data, '') as company_name,
                       c.id as course_id, c.shortname, c.fullname as course_name,
                       cc.timecompleted,
                       COALESCE(gg.finalgrade, 0) as final_grade
                FROM {user} u
                JOIN {user_enrolments} ue ON u.id = ue.userid
                JOIN {enrol} e ON ue.enrolid = e.id
                JOIN {course} c ON e.courseid = c.id
                LEFT JOIN {course_completions} cc ON u.id = cc.userid AND c.id = cc.course
                LEFT JOIN {grade_items} gi ON c.id = gi.courseid AND gi.itemtype = 'course'
                LEFT JOIN {grade_grades} gg ON u.id = gg.userid AND gi.id = gg.itemid
                LEFT JOIN {user_info_field} uif ON uif.shortname = 'branch'
                LEFT JOIN {user_info_data} uid ON u.id = uid.userid AND uid.fieldid = uif.id
                WHERE u.deleted = 0 
                AND u.confirmed = 1
                AND c.id != :siteid
                AND c.visible = 1";

        $sqlparams = ['siteid' => SITEID];

        if ($params['courseid'] > 0) {
            $sql .= " AND c.id = :courseid";
            $sqlparams['courseid'] = $params['courseid'];
        }

        if ($params['userid'] > 0) {
            $sql .= " AND u.id = :userid";
            $sqlparams['userid'] = $params['userid'];
        }

        $sql .= " ORDER BY c.fullname, u.lastname, u.firstname";

        $results = $DB->get_records_sql($sql, $sqlparams);

        $final_results = [];
        foreach ($results as $result) {
            // Get pre-test and post-test scores
            $pretest_score = self::get_quiz_score($result->user_id, $result->course_id, 'pre');
            $posttest_score = self::get_quiz_score($result->user_id, $result->course_id, 'post');

            $final_results[] = [
                'user_id' => $result->user_id,
                'email' => $result->email,
                'firstname' => $result->firstname,
                'lastname' => $result->lastname,
                'company_name' => $result->company_name ?: '',
                'course_id' => $result->course_id,
                'course_shortname' => $result->shortname,
                'course_name' => $result->course_name,
                'final_grade' => round($result->final_grade, 2),
                'pretest_score' => $pretest_score,
                'posttest_score' => $posttest_score,
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
                'course_shortname' => new external_value(PARAM_TEXT, 'Course short name'),
                'course_name' => new external_value(PARAM_TEXT, 'Course name'),
                'final_grade' => new external_value(PARAM_FLOAT, 'Final grade'),
                'pretest_score' => new external_value(PARAM_FLOAT, 'Pre-test score'),
                'posttest_score' => new external_value(PARAM_FLOAT, 'Post-test score'),
                'completion_date' => new external_value(PARAM_INT, 'Course completion date'),
                'is_completed' => new external_value(PARAM_INT, 'Is course completed')
            ])
        );
    }

    /**
     * Get quiz score based on custom field 'jenis_quiz'
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
            'apikey' => new external_value(PARAM_TEXT, 'API key for authentication'),
            'status' => new external_value(PARAM_ALPHA, 'Filter by status: all, active, suspended', VALUE_OPTIONAL, 'all'),
        ]);
    }

    /**
     * Get list of users with optional suspension filter
     * @param string $apikey API key
     * @param string $status Filter: 'all' | 'active' | 'suspended'
     * @return array List of users
     */
    public static function get_users($apikey, $status = 'all') {
        global $DB;

        $params = self::validate_parameters(self::get_users_parameters(), [
            'apikey' => $apikey,
            'status' => $status,
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

        $sql = "SELECT u.id, u.username, u.email, u.firstname, u.lastname,
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
            'userid'    => new external_value(PARAM_INT, 'User ID', VALUE_OPTIONAL, 0),
            'email'     => new external_value(PARAM_EMAIL, 'User email', VALUE_OPTIONAL, ''),
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
            'idnumber'   => new external_value(PARAM_TEXT, 'Course ID number', VALUE_OPTIONAL, ''),
            'summary'    => new external_value(PARAM_RAW,  'Course summary', VALUE_OPTIONAL, ''),
            'categoryid' => new external_value(PARAM_INT,  'Category ID', VALUE_OPTIONAL, 1),
            'startdate'  => new external_value(PARAM_INT,  'Course start date (unix timestamp)', VALUE_OPTIONAL, 0),
            'enddate'    => new external_value(PARAM_INT,  'Course end date (unix timestamp)', VALUE_OPTIONAL, 0),
            'visible'    => new external_value(PARAM_INT,  'Visibility (1=visible, 0=hidden)', VALUE_OPTIONAL, 0),
            'jp'         => new external_value(PARAM_INT,  'JP custom field value', VALUE_OPTIONAL, 1),
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
        $apikey, $fullname, $shortname, $idnumber = '', $summary = '',
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
        foreach ($fieldsdata as $fielddata) {
            if ($fielddata->get_field()->get('shortname') === 'jp') {
                $fielddata->set('value', $params['jp']);
                $fielddata->set('valueformat', FORMAT_PLAIN);
                $fielddata->save();
                break;
            }
        }

        return [
            'id'         => (int) $course->id,
            'shortname'  => $course->shortname,
            'fullname'   => $course->fullname,
            'idnumber'   => $course->idnumber ?: '',
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
            'fullname'     => new external_value(PARAM_TEXT,  'New course full name',                     VALUE_OPTIONAL, ''),
            'shortname'    => new external_value(PARAM_TEXT,  'New course short name',                    VALUE_OPTIONAL, ''),
            'new_idnumber' => new external_value(PARAM_TEXT,  'New course ID number (rename idnumber)',   VALUE_OPTIONAL, ''),
            'summary'      => new external_value(PARAM_RAW,   'New course summary',                       VALUE_OPTIONAL, ''),
            'categoryid'   => new external_value(PARAM_INT,   'New category ID (0 = no change)',          VALUE_OPTIONAL, 0),
            'startdate'    => new external_value(PARAM_INT,   'New start date unix timestamp (0 = no change)', VALUE_OPTIONAL, 0),
            'enddate'      => new external_value(PARAM_INT,   'New end date unix timestamp (-1 = no change)',  VALUE_OPTIONAL, -1),
            'visible'      => new external_value(PARAM_INT,   'Visibility: 1=visible, 0=hidden, -1=no change', VALUE_OPTIONAL, -1),
            'jp'           => new external_value(PARAM_INT,   'JP custom field value (0 = no change)',    VALUE_OPTIONAL, 0),
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
                    $fielddata->set('value', $params['jp']);
                    $fielddata->set('valueformat', FORMAT_PLAIN);
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
            'categoryid' => new external_value(PARAM_INT,  'Category ID'),
            'startdate'  => new external_value(PARAM_INT,  'Start date'),
            'enddate'    => new external_value(PARAM_INT,  'End date'),
            'visible'    => new external_value(PARAM_INT,  'Visibility (1=visible, 0=hidden)'),
            'jp'         => new external_value(PARAM_INT,  'JP custom field value'),
        ]);
    }

}