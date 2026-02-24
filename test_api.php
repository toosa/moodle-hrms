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
 * Test API endpoint for HRIS Integration
 *
 * @package    local_hris
 * @copyright  2025 Prihantoosa <pht854@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

global $CFG, $PAGE, $OUTPUT;

// Set up the page
$PAGE->set_url('/local/hris/test_api.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('HRIS API Test');
$PAGE->set_heading('HRIS API Test');

// Check if user is admin
require_login();
require_capability('moodle/site:config', context_system::instance());

echo $OUTPUT->header();

?>
<div class="container mt-4">
    <h2>HRIS API Testing Interface</h2>
    
    <div class="alert alert-info">
        <strong>API Base URL:</strong> <?php echo $CFG->wwwroot; ?>/webservice/rest/server.php
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4>API Configuration</h4>
                </div>
                <div class="card-body">
                    <p><strong>Web Services Enabled:</strong> 
                        <?php echo get_config('core', 'enablewebservices') ? 
                            '<span class="badge badge-success">Yes</span>' : 
                            '<span class="badge badge-danger">No</span>'; ?>
                    </p>
                    <p><strong>HRIS API Enabled:</strong> 
                        <?php echo get_config('local_hris', 'api_enabled') ? 
                            '<span class="badge badge-success">Yes</span>' : 
                            '<span class="badge badge-danger">No</span>'; ?>
                    </p>
                    <p><strong>API Key Set:</strong> 
                        <?php echo !empty(get_config('local_hris', 'api_key')) ? 
                            '<span class="badge badge-success">Yes</span>' : 
                            '<span class="badge badge-danger">No</span>'; ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4>Available Functions</h4>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><code>local_hris_get_active_courses</code></li>
                        <li class="list-group-item"><code>local_hris_get_course_participants</code></li>
                        <li class="list-group-item"><code>local_hris_get_course_results</code></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <div class="card">
            <div class="card-header">
                <h4>Sample API Calls</h4>
            </div>
            <div class="card-body">
                <h5>1. Get Active Courses</h5>
                <pre><code>POST <?php echo $CFG->wwwroot; ?>/webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded

wstoken=YOUR_WS_TOKEN&
wsfunction=local_hris_get_active_courses&
moodlewsrestformat=json&
apikey=YOUR_API_KEY</code></pre>

                <h5>2. Get Course Participants (All Courses)</h5>
                <pre><code>POST <?php echo $CFG->wwwroot; ?>/webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded

wstoken=YOUR_WS_TOKEN&
wsfunction=local_hris_get_course_participants&
moodlewsrestformat=json&
apikey=YOUR_API_KEY</code></pre>

                <h5>3. Get Course Participants (Specific Course)</h5>
                <pre><code>POST <?php echo $CFG->wwwroot; ?>/webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded

wstoken=YOUR_WS_TOKEN&
wsfunction=local_hris_get_course_participants&
moodlewsrestformat=json&
apikey=YOUR_API_KEY&
courseid=COURSE_ID</code></pre>

                <h5>4. Get Course Results</h5>
                <pre><code>POST <?php echo $CFG->wwwroot; ?>/webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded

wstoken=YOUR_WS_TOKEN&
wsfunction=local_hris_get_course_results&
moodlewsrestformat=json&
apikey=YOUR_API_KEY&
courseid=COURSE_ID&
userid=USER_ID</code></pre>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <div class="card">
            <div class="card-header">
                <h4>Setup Instructions</h4>
            </div>
            <div class="card-body">
                <ol>
                    <li>Enable Web Services: <strong>Site administration > Advanced features > Enable web services</strong></li>
                    <li>Enable protocols: <strong>Site administration > Plugins > Web services > Manage protocols > Enable REST</strong></li>
                    <li>Create a web service user or use existing user</li>
                    <li>Create external service: <strong>Site administration > Plugins > Web services > External services</strong></li>
                    <li>Add functions to the service: <code>local_hris_get_active_courses</code>, <code>local_hris_get_course_participants</code>, <code>local_hris_get_course_results</code></li>
                    <li>Create token for the service user</li>
                    <li>Set API key in plugin settings: <strong>Site administration > Plugins > Local plugins > HRIS Integration</strong></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
?>