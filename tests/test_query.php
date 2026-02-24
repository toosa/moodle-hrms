<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

$CFG->debug = DEBUG_DEVELOPER;
$CFG->debugdisplay = true;

echo "Testing query...\n\n";

$quizmodule = $DB->get_record('modules', ['name' => 'quiz']);
echo "Quiz module ID: {$quizmodule->id}\n\n";

$sql = "SELECT
            u.id AS user_id,
            u.email AS email,
            u.firstname,
            u.lastname,
            COALESCE(uid.data, '') as company_name,
            c.id as course_id,
            c.shortname,
            c.fullname AS course_name,
            cc.timecompleted,
            ROUND(MAX(CASE WHEN mcd.value = '2' THEN gg.finalgrade END), 2) AS pretest_score,
            ROUND(MAX(CASE WHEN mcd.value = '3' THEN gg.finalgrade END), 2) AS posttest_score,
            ROUND(COALESCE(ggi.finalgrade, 0), 2) as final_grade
        FROM {user} u
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid
        JOIN {course} c ON c.id = e.courseid
        LEFT JOIN {course_modules} mcm ON mcm.course = c.id AND mcm.module = :moduleid
        LEFT JOIN {customfield_data} mcd ON mcd.instanceid = mcm.id AND mcd.value IN ('2', '3')
        LEFT JOIN {grade_items} gi ON gi.iteminstance = mcm.instance AND gi.itemmodule = 'quiz'
        LEFT JOIN {grade_grades} gg ON gg.userid = u.id AND gg.itemid = gi.id
        LEFT JOIN {grade_items} ggi ON ggi.courseid = c.id AND ggi.itemtype = 'course'
        LEFT JOIN {grade_grades} ggg ON ggg.userid = u.id AND ggg.itemid = ggi.id
        LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
        LEFT JOIN {user_info_field} uif ON uif.shortname = 'branch'
        LEFT JOIN {user_info_data} uid ON uid.userid = u.id AND uid.fieldid = uif.id
        WHERE u.deleted = 0
        AND u.confirmed = 1
        AND c.id != :siteid
        AND c.visible = 1
        AND EXISTS (
            SELECT 1
            FROM {role_assignments} ra
            JOIN {context} ctx ON ctx.id = ra.contextid
            WHERE ra.userid = u.id
            AND ctx.instanceid = c.id
            AND ctx.contextlevel = 50
            AND ra.roleid = 5
        )
        GROUP BY u.id, u.email, u.firstname, u.lastname, uid.data, c.id, c.shortname, c.fullname, cc.timecompleted, ggi.finalgrade
        ORDER BY c.fullname, u.lastname, u.firstname
        LIMIT 5";

$sqlparams = [
    'moduleid' => $quizmodule->id,
    'siteid' => SITEID
];

try {
    echo "Executing query...\n";
    $results = $DB->get_records_sql($sql, $sqlparams);
    echo "Success! Found " . count($results) . " results\n\n";
    
    foreach ($results as $r) {
        echo "User: {$r->firstname} {$r->lastname}\n";
        echo "  Course: {$r->course_name}\n";
        echo "  Final: {$r->final_grade}, Pre: {$r->pretest_score}, Post: {$r->posttest_score}\n\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
