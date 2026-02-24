# HRIS Plugin - Design Documentation

## Table of Contents
1. [System Architecture](#system-architecture)
2. [Component Design](#component-design)
3. [Sequence Diagrams](#sequence-diagrams)
4. [Database Design](#database-design)
5. [Security Architecture](#security-architecture)
6. [API Design Patterns](#api-design-patterns)

---

## System Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    External Integration                      │
│                      (HRIS System)                           │
└────────────────────────┬────────────────────────────────────┘
                         │
                         │ HTTPS/REST
                         │ (POST requests)
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                    Web Server Layer                          │
│                  (Apache/Nginx + SSL)                        │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│              Moodle Core Web Service Layer                   │
│                                                              │
│  ┌──────────────────────────────────────────────────┐      │
│  │  REST Protocol Handler                            │      │
│  │  - Parse incoming requests                        │      │
│  │  - Validate web service token                     │      │
│  │  - Route to appropriate function                  │      │
│  │  - Format response (JSON/XML)                     │      │
│  └────────────────────┬─────────────────────────────┘      │
│                       │                                      │
└───────────────────────┼──────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│                 local_hris Plugin Layer                      │
│                                                              │
│  ┌──────────────────────────────────────────────────┐      │
│  │  local_hris_external Class                        │      │
│  │                                                    │      │
│  │  1. API Key Validation                            │      │
│  │  2. Parameter Validation                          │      │
│  │  3. Context Validation                            │      │
│  │  4. Business Logic Execution                      │      │
│  │  5. Data Formatting                               │      │
│  └────────────────────┬─────────────────────────────┘      │
│                       │                                      │
└───────────────────────┼──────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│                  Moodle Data Layer                           │
│                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   Courses    │  │    Users     │  │    Grades    │     │
│  │   Table      │  │    Table     │  │    Table     │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
│                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │ Enrollments  │  │ Completions  │  │    Quiz      │     │
│  │   Table      │  │    Table     │  │  Attempts    │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
```

### Request Flow Diagram

```
┌──────────┐
│  HRIS    │
│ System   │
└────┬─────┘
     │
     │ 1. POST Request
     │    - wstoken
     │    - wsfunction
     │    - apikey
     │    - parameters
     ▼
┌─────────────────┐
│  Moodle Web     │
│  Service Layer  │──────────► Validate Token
└────┬────────────┘              │
     │                            │ Valid?
     │◄───────────────────────────┘
     │
     │ 2. Route to Function
     ▼
┌─────────────────┐
│  local_hris_    │
│  external       │──────────► Validate API Key
└────┬────────────┘              │
     │                            │ Valid?
     │◄───────────────────────────┘
     │
     │ 3. Execute Business Logic
     ▼
┌─────────────────┐
│  Database       │
│  Queries        │
└────┬────────────┘
     │
     │ 4. Process Results
     ▼
┌─────────────────┐
│  Format         │
│  Response       │
└────┬────────────┘
     │
     │ 5. Return JSON/XML
     ▼
┌──────────┐
│  HRIS    │
│ System   │
└──────────┘
```

---

## Component Design

### Class Diagram

```
┌─────────────────────────────────────────────────┐
│         external_api (Moodle Core)              │
│                                                 │
│  + validate_parameters()                        │
│  + validate_context()                           │
└────────────────┬────────────────────────────────┘
                 │
                 │ extends
                 ▼
┌─────────────────────────────────────────────────┐
│         local_hris_external                     │
├─────────────────────────────────────────────────┤
│  Static Methods:                                │
│                                                 │
│  + get_active_courses_parameters()              │
│  + get_active_courses(apikey)                   │
│  + get_active_courses_returns()                 │
│                                                 │
│  + get_course_participants_parameters()         │
│  + get_course_participants(apikey, courseid)    │
│  + get_course_participants_returns()            │
│                                                 │
│  + get_course_results_parameters()              │
│  + get_course_results(apikey, courseid, userid) │
│  + get_course_results_returns()                 │
│                                                 │
│  + get_all_course_results_parameters()          │
│  + get_all_course_results(apikey, format)       │
│  + get_all_course_results_returns()             │
│                                                 │
│  - validate_api_key(apikey) : bool              │
│  - get_quiz_score(userid, courseid, type)       │
│  - get_questionnaire_scores(userid, courseid)   │
└─────────────────────────────────────────────────┘
```

### Function Pattern

Each API function follows this pattern:

```php
// 1. Parameter Definition
public static function {function_name}_parameters() {
    return new external_function_parameters([
        'param1' => new external_value(TYPE, 'Description'),
        'param2' => new external_value(TYPE, 'Description', VALUE_OPTIONAL, default)
    ]);
}

// 2. Function Implementation
public static function {function_name}($param1, $param2 = default) {
    // 2.1 Validate parameters
    $params = self::validate_parameters(
        self::{function_name}_parameters(), 
        ['param1' => $param1, 'param2' => $param2]
    );
    
    // 2.2 Validate API key
    if (!self::validate_api_key($params['apikey'])) {
        throw new moodle_exception('invalidapikey', 'local_hris');
    }
    
    // 2.3 Validate context
    $context = context_system::instance();
    self::validate_context($context);
    
    // 2.4 Execute business logic
    $result = // ... database queries and processing
    
    // 2.5 Return formatted data
    return $result;
}

// 3. Return Value Definition
public static function {function_name}_returns() {
    return new external_multiple_structure(
        new external_single_structure([
            'field1' => new external_value(TYPE, 'Description'),
            'field2' => new external_value(TYPE, 'Description')
        ])
    );
}
```

### Helper Methods

The plugin also includes private helper methods for complex operations:

```php
/**
 * Validate API key
 */
private static function validate_api_key($apikey) {
    $stored_key = get_config('local_hris', 'api_key');
    return !empty($stored_key) && $apikey === $stored_key;
}

/**
 * Get quiz score based on custom field
 * @param int $userid User ID
 * @param int $courseid Course ID
 * @param string $type 'pre' or 'post'
 * @return float Quiz score
 */
private static function get_quiz_score($userid, $courseid, $type) {
    global $DB;
    
    // fieldvalue: 2 = PreTest, 3 = PostTest
    $fieldvalue = $type === 'pre' ? '2' : '3';
    
    $sql = "SELECT MAX(gg.finalgrade) as score
            FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
            JOIN {customfield_data} cfd ON cfd.instanceid = cm.id 
            JOIN {grade_items} gi ON gi.iteminstance = cm.instance
            LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
            WHERE cm.course = :courseid AND cfd.value = :fieldvalue";
    
    $result = $DB->get_record_sql($sql, [
        'userid' => $userid,
        'courseid' => $courseid,
        'fieldvalue' => $fieldvalue
    ]);
    
    return $result && $result->score ? round($result->score, 2) : 0.00;
}

/**
 * Get questionnaire scores for a user in a course
 * @param int $userid User ID
 * @param int $courseid Course ID
 * @return array Associative array with questionnaire scores
 */
private static function get_questionnaire_scores($userid, $courseid) {
    global $DB;
    
    // Default response structure
    $default = [
        'questionnaire_available' => 0,
        'score_materi' => 0.00,
        'score_trainer' => 0.00,
        'score_tempat' => 0.00,
        'score_total' => 0.00
    ];
    
    try {
        // Find questionnaire, Rate question, count choices
        // Get user responses ordered by choice_id
        // Calculate scores based on choice count
        // Return scores with proper rounding
        
        // If exactly 9 choices: calculate materi, trainer, tempat
        // Otherwise: only calculate score_total
        
    } catch (Exception $e) {
        error_log("Questionnaire error: " . $e->getMessage());
        return $default;
    }
}
```

---

## Sequence Diagrams

### 1. Complete Request-Response Cycle

```mermaid
sequenceDiagram
    participant Client as HRIS Client
    participant Server as Web Server
    participant WS as Moodle Web Service
    participant Token as Token Validator
    participant API as local_hris_external
    participant DB as Moodle Database
    
    Client->>Server: HTTPS POST Request
    Note over Client,Server: Headers + Body with wstoken
    
    Server->>WS: Forward to webservice/rest/server.php
    
    WS->>Token: Validate wstoken
    
    alt Token Invalid
        Token-->>Client: 401 Unauthorized
    else Token Valid
        Token->>WS: Token OK
        WS->>API: Call function with parameters
        
        API->>API: Validate API Key
        
        alt API Key Invalid
            API-->>Client: Error: Invalid API Key
        else API Key Valid
            API->>API: Validate Parameters
            API->>API: Validate Context
            
            API->>DB: Execute Query
            DB-->>API: Raw Data
            
            API->>API: Process & Format Data
            
            API-->>WS: Return Array
            WS-->>Server: JSON Response
            Server-->>Client: HTTPS Response
        end
    end
```

### 2. Get Active Courses - Detailed Flow

```mermaid
sequenceDiagram
    participant Client
    participant API as local_hris_external
    participant Validator
    participant DB as Database
    
    Client->>API: get_active_courses(apikey)
    
    API->>Validator: validate_parameters()
    Validator-->>API: params validated
    
    API->>Validator: validate_api_key(apikey)
    
    alt Invalid Key
        Validator-->>Client: Exception: invalidapikey
    else Valid Key
        Validator-->>API: key OK
        
        API->>Validator: validate_context(system)
        Validator-->>API: context OK
        
        Note over API,DB: SQL Query
        API->>DB: SELECT id, shortname, fullname, summary,<br/>startdate, enddate, visible<br/>FROM mdl_course<br/>WHERE id != 1 AND visible = 1<br/>ORDER BY fullname
        
        DB-->>API: ResultSet
        
        loop For each course
            API->>API: Format course data<br/>- Remove HTML from summary<br/>- Ensure all fields present
        end
        
        API-->>Client: Array of courses (JSON)
    end
```

### 3. Get Course Participants - Detailed Flow

```mermaid
sequenceDiagram
    participant Client
    participant API as local_hris_external
    participant DB as Database
    
    Client->>API: get_course_participants(apikey, courseid)
    
    API->>API: Validate parameters & API key
    
    alt courseid = 0
        Note over API: Get all participants from all courses
        API->>DB: SELECT users FROM all courses<br/>JOIN user_enrolments<br/>JOIN enrol<br/>JOIN course<br/>LEFT JOIN user_info_data (company)
    else courseid > 0
        Note over API: Get participants for specific course
        API->>DB: SELECT users WHERE course_id = courseid<br/>JOIN user_enrolments<br/>JOIN enrol<br/>JOIN course<br/>LEFT JOIN user_info_data (company)
    end
    
    DB-->>API: Participant records
    
    loop For each participant
        API->>API: Format data:<br/>- user_id<br/>- email<br/>- firstname, lastname<br/>- company_name<br/>- course info<br/>- enrollment_date
    end
    
    API-->>Client: Array of participants (JSON)
```

### 4. Get Course Results - Detailed Flow

```mermaid
sequenceDiagram
    participant Client
    participant API as local_hris_external
    participant DB as Database
    
    Client->>API: get_course_results(apikey, courseid, userid)
    
    API->>API: Validate parameters & API key
    
    API->>DB: SELECT users with enrollments,<br/>completions, grades<br/>WHERE conditions based on filters
    
    DB-->>API: Enrollment records
    
    loop For each enrollment
        Note over API,DB: Get pre-test score via custom field
        API->>DB: SELECT MAX(gg.finalgrade)<br/>FROM course_modules cm<br/>JOIN customfield_data cfd<br/>WHERE cfd.value = '2' (PreTest)<br/>AND cm.course = courseid
        DB-->>API: pre_score
        
        Note over API,DB: Get post-test score via custom field
        API->>DB: SELECT MAX(gg.finalgrade)<br/>FROM course_modules cm<br/>JOIN customfield_data cfd<br/>WHERE cfd.value = '3' (PostTest)<br/>AND cm.course = courseid
        DB-->>API: post_score
        
        API->>API: Build result object:<br/>- user info<br/>- course info<br/>- final_grade<br/>- pretest_score<br/>- posttest_score<br/>- completion_date<br/>- is_completed
    end
    
    API-->>Client: Array of results (JSON)
```

### 5. Error Handling Flow

```mermaid
sequenceDiagram
    participant Client
    participant WS as Web Service
    participant API as local_hris
    
    Client->>WS: Request with invalid token
    WS->>WS: Validate token
    WS-->>Client: webservice_access_exception
    
    Client->>WS: Request with valid token
    WS->>API: Call function
    API->>API: Validate API key
    API-->>Client: moodle_exception: invalidapikey
    
    Client->>WS: Request with missing parameters
    WS->>API: Call function
    API->>API: validate_parameters()
    API-->>Client: invalid_parameter_exception
    
    Client->>WS: Valid request but no data
    WS->>API: Call function
    API->>API: Execute query
    API-->>Client: Empty array []
```

---

## Database Design

### Entity Relationship Diagram

```
┌─────────────────┐         ┌─────────────────┐
│     Course      │         │      User       │
├─────────────────┤         ├─────────────────┤
│ id (PK)         │         │ id (PK)         │
│ shortname       │         │ email           │
│ fullname        │         │ firstname       │
│ summary         │         │ lastname        │
│ startdate       │         │ deleted         │
│ enddate         │         │ confirmed       │
│ visible         │         └────────┬────────┘
└────────┬────────┘                  │
         │                           │
         │ 1                         │ 1
         │                           │
         │         N        N        │
         └──────┐         ┌─────────┘
                │         │
                ▼         ▼
         ┌──────────────────────┐
         │   User_enrolments    │
         ├──────────────────────┤
         │ id (PK)              │
         │ enrolid (FK)         │
         │ userid (FK)          │
         │ timecreated          │
         └──────────┬───────────┘
                    │
                    │ N
                    │
                    │ 1
                    ▼
         ┌──────────────────────┐
         │       Enrol          │
         ├──────────────────────┤
         │ id (PK)              │
         │ courseid (FK)        │
         │ enrol                │
         │ status               │
         └──────────────────────┘

┌─────────────────┐         ┌─────────────────┐
│ Course_         │         │  Grade_items    │
│ Completions     │         ├─────────────────┤
├─────────────────┤         │ id (PK)         │
│ userid (FK)     │         │ courseid (FK)   │
│ course (FK)     │         │ itemtype        │
│ timecompleted   │         └────────┬────────┘
└─────────────────┘                  │
                                     │ 1
                                     │
                                     │ N
                                     ▼
                          ┌─────────────────┐
                          │  Grade_grades   │
                          ├─────────────────┤
                          │ id (PK)         │
                          │ itemid (FK)     │
                          │ userid (FK)     │
                          │ finalgrade      │
                          └─────────────────┘

┌─────────────────┐         ┌─────────────────┐
│      Quiz       │         │  Quiz_attempts  │
├─────────────────┤         ├─────────────────┤
│ id (PK)         │──1:N────│ quiz (FK)       │
│ course (FK)     │         │ userid (FK)     │
│ name            │         │ sumgrades       │
└─────────────────┘         │ state           │
                            └─────────────────┘

┌─────────────────┐         ┌─────────────────┐
│ User_info_field │         │ User_info_data  │
├─────────────────┤         ├─────────────────┤
│ id (PK)         │──1:N────│ userid (FK)     │
│ shortname       │         │ fieldid (FK)    │
│ name            │         │ data            │
└─────────────────┘         └─────────────────┘

┌──────────────────┐         ┌──────────────────┐
│ Questionnaire    │         │ Questionnaire_   │
├──────────────────┤         │ Question         │
│ id (PK)          │──1:N────├──────────────────┤
│ course (FK)      │         │ id (PK)          │
│ name             │         │ surveyid (FK)    │
└──────────────────┘         │ type_id          │
                             │ (8=QUESRATE)     │
                             └────────┬─────────┘
                                      │ 1
                                      │
                                      │ N
                             ┌────────┴─────────┐
                             │ Questionnaire_   │
                             │ Quest_Choice     │
                             ├──────────────────┤
                             │ id (PK)          │
                             │ question_id (FK) │
                             │ content          │
                             └──────────────────┘

┌──────────────────┐         ┌──────────────────┐
│ Questionnaire_   │         │ Questionnaire_   │
│ Response         │         │ Response_Rank    │
├──────────────────┤         ├──────────────────┤
│ id (PK)          │──1:N────│ response_id (FK) │
│ questionnaireid  │         │ question_id (FK) │
│ userid (FK)      │         │ choice_id (FK)   │
└──────────────────┘         │ rankvalue        │
                             └──────────────────┘

┌──────────────────┐         ┌──────────────────┐
│ Customfield_     │         │ Customfield_     │
│ Field            │         │ Data             │
├──────────────────┤         ├──────────────────┤
│ id (PK)          │──1:N────│ fieldid (FK)     │
│ shortname        │         │ instanceid (FK)  │
│ (jenis_quiz)     │         │ value            │
└──────────────────┘         │ (2=Pre, 3=Post)  │
                             └──────────────────┘
```

### Key Queries

#### Query 1: Active Courses
```sql
SELECT 
    c.id, 
    c.shortname, 
    c.fullname, 
    c.summary, 
    c.startdate, 
    c.enddate, 
    c.visible
FROM mdl_course c 
WHERE c.id != 1 
  AND c.visible = 1
ORDER BY c.fullname;
```

#### Query 2: Course Participants
```sql
SELECT DISTINCT 
    u.id,
    u.email,
    u.firstname,
    u.lastname,
    COALESCE(uid_branch.data, '') as company_name,
    c.id as course_id,
    c.shortname,
    c.fullname as course_name,
    ue.timecreated as enrollment_date
FROM mdl_user u
JOIN mdl_user_enrolments ue ON u.id = ue.userid
JOIN mdl_enrol e ON ue.enrolid = e.id
JOIN mdl_course c ON e.courseid = c.id
LEFT JOIN mdl_user_info_field uif_branch 
    ON uif_branch.shortname = 'branch'
LEFT JOIN mdl_user_info_data uid_branch 
    ON u.id = uid_branch.userid 
    AND uid_branch.fieldid = uif_branch.id
WHERE u.deleted = 0 
  AND u.confirmed = 1
  AND c.id != 1
  AND c.visible = 1
  [AND c.id = :courseid]  -- Optional filter
ORDER BY c.fullname, u.lastname, u.firstname;
```

#### Query 3: Course Results
```sql
SELECT DISTINCT 
    u.id as user_id,
    u.email,
    u.firstname,
    u.lastname,
    COALESCE(uid_branch.data, '') as company_name,
    c.id as course_id,
    c.shortname,
    c.fullname as course_name,
    cc.timecompleted,
    COALESCE(gg.finalgrade, 0) as final_grade
FROM mdl_user u
JOIN mdl_user_enrolments ue ON u.id = ue.userid
JOIN mdl_enrol e ON ue.enrolid = e.id
JOIN mdl_course c ON e.courseid = c.id
LEFT JOIN mdl_course_completions cc 
    ON u.id = cc.userid 
    AND c.id = cc.course
LEFT JOIN mdl_grade_items gi 
    ON c.id = gi.courseid 
    AND gi.itemtype = 'course'
LEFT JOIN mdl_grade_grades gg 
    ON u.id = gg.userid 
    AND gi.id = gg.itemid
LEFT JOIN mdl_user_info_field uif_branch 
    ON uif_branch.shortname = 'branch'
LEFT JOIN mdl_user_info_data uid_branch 
    ON u.id = uid_branch.userid 
    AND uid_branch.fieldid = uif_branch.id
WHERE u.deleted = 0 
  AND u.confirmed = 1
  AND c.id != 1
  AND c.visible = 1
  [AND c.id = :courseid]  -- Optional filter
  [AND u.id = :userid]    -- Optional filter
ORDER BY c.fullname, u.lastname, u.firstname;
```

#### Query 4: Quiz Scores (Pre/Post Test) - Using Custom Fields
```sql
-- This query retrieves quiz scores using custom field values
-- Custom field 'jenis_quiz' with values: 2=PreTest, 3=PostTest

SELECT MAX(gg.finalgrade) as score
FROM mdl_course_modules cm
JOIN mdl_modules m ON m.id = cm.module AND m.name = 'quiz'
JOIN mdl_customfield_data cfd ON cfd.instanceid = cm.id AND cfd.value = :fieldvalue
JOIN mdl_grade_items gi ON gi.iteminstance = cm.instance AND gi.itemmodule = 'quiz'
LEFT JOIN mdl_grade_grades gg ON gg.itemid = gi.id AND gg.userid = :userid
WHERE cm.course = :courseid
  AND cfd.value IN ('2', '3');  -- 2 for PreTest, 3 for PostTest

-- Note: Custom field 'jenis_quiz' must be created and configured
-- on course modules for this to work
```

#### Query 5: Questionnaire Scores - Rate Question Analysis
```sql
-- Step 1: Find questionnaire module in the course
SELECT cm.id, q.id as questionnaire_id
FROM mdl_course_modules cm
JOIN mdl_modules m ON m.id = cm.module AND m.name = 'questionnaire'
JOIN mdl_questionnaire q ON q.id = cm.instance
WHERE cm.course = :courseid AND cm.visible = 1;

-- Step 2: Find Rate question (type_id = 8 for QUESRATE)
SELECT * FROM mdl_questionnaire_question
WHERE surveyid = :questionnaire_id
  AND type_id = 8;

-- Step 3: Count choices for the Rate question
SELECT COUNT(*) as choice_count
FROM mdl_questionnaire_quest_choice
WHERE question_id = :rate_question_id;

-- Step 4: Get user's response
SELECT * FROM mdl_questionnaire_response
WHERE questionnaireid = :questionnaire_id
  AND userid = :userid;

-- Step 5: Get all rating values for the Rate question
SELECT qrr.id, qrr.response_id, qrr.question_id, 
       qrr.choice_id, qrr.rankvalue,
       qqc.id as choice_id_in_table
FROM mdl_questionnaire_response_rank qrr
JOIN mdl_questionnaire_quest_choice qqc ON qqc.id = qrr.choice_id
WHERE qrr.response_id = :response_id 
  AND qrr.question_id = :rate_question_id
ORDER BY qqc.id ASC;

-- Note: If exactly 9 choices exist:
--   score_materi = average of choices 1-3
--   score_trainer = average of choices 4-6
--   score_tempat = average of choices 7-9
--   score_total = average of all 9 choices
-- Otherwise only score_total is returned
```

---

## Security Architecture

### Defense in Depth Strategy

```
Layer 1: Network Security
┌─────────────────────────────────────────┐
│ • Firewall rules                        │
│ • IP whitelisting                       │
│ • DDoS protection                       │
│ • Rate limiting                         │
└─────────────────────────────────────────┘
                 ↓
Layer 2: Transport Security
┌─────────────────────────────────────────┐
│ • HTTPS/TLS 1.2+                        │
│ • Valid SSL certificate                 │
│ • Strong cipher suites                  │
└─────────────────────────────────────────┘
                 ↓
Layer 3: Application Security
┌─────────────────────────────────────────┐
│ • Web service token validation          │
│ • Token expiration                      │
│ • User authentication                   │
└─────────────────────────────────────────┘
                 ↓
Layer 4: Plugin Security
┌─────────────────────────────────────────┐
│ • Custom API key validation             │
│ • API enable/disable toggle             │
│ • Request logging                       │
└─────────────────────────────────────────┘
                 ↓
Layer 5: Data Security
┌─────────────────────────────────────────┐
│ • Parameter type validation             │
│ • SQL injection prevention              │
│ • XSS protection                        │
│ • Context validation                    │
└─────────────────────────────────────────┘
                 ↓
Layer 6: Business Logic Security
┌─────────────────────────────────────────┐
│ • Capability checks                     │
│ • Data visibility rules                 │
│ • Audit logging                         │
└─────────────────────────────────────────┘
```

### Authentication Sequence

```mermaid
sequenceDiagram
    participant Client
    participant WS as Web Service
    participant TokenDB as Token Storage
    participant API as local_hris
    participant ConfigDB as Config Storage
    
    Client->>WS: Request + wstoken
    
    WS->>TokenDB: Validate token
    TokenDB-->>WS: Token details (user, service, validity)
    
    alt Token Invalid/Expired
        WS-->>Client: 401 Unauthorized
    else Token Valid
        WS->>WS: Check service enabled
        WS->>WS: Check user has capability
        
        WS->>API: Call function + apikey
        
        API->>ConfigDB: get_config('local_hris', 'api_key')
        ConfigDB-->>API: stored_key
        
        API->>API: Compare apikey === stored_key
        
        alt API Key Mismatch
            API-->>Client: Exception: Invalid API Key
        else API Key Match
            API->>API: Execute function
            API-->>Client: Success response
        end
    end
```

---

## API Design Patterns

### API Endpoints

The plugin exposes 4 main API endpoints:

#### 1. get_active_courses
Returns a list of all visible courses (excluding site course).

**Parameters:**
- `apikey` (required): API key for authentication

**Returns:** Array of course objects with id, shortname, fullname, summary, startdate, enddate, visible

#### 2. get_course_participants
Returns participants enrolled in courses with their company information.

**Parameters:**
- `apikey` (required): API key for authentication
- `courseid` (optional, default=0): Specific course ID, or 0 for all courses

**Returns:** Array of participant objects including user info, course info, and enrollment date

#### 3. get_course_results
Returns course results including grades, pre/post-test scores, and completion data.

**Parameters:**
- `apikey` (required): API key for authentication
- `courseid` (optional, default=0): Filter by course ID
- `userid` (optional, default=0): Filter by user ID

**Returns:** Array of result objects with grades, test scores, completion status

#### 4. get_all_course_results
Returns comprehensive results for all enrollments including questionnaire scores.

**Parameters:**
- `apikey` (required): API key for authentication
- `format` (optional, default='json'): Response format

**Returns:** Array of result objects with:
- Basic user and course info
- Final grade, pre-test and post-test scores
- Completion status and date
- Questionnaire scores (score_materi, score_trainer, score_tempat, score_total)
- questionnaire_available flag

### Questionnaire Scoring Logic

The `get_questionnaire_scores()` helper method implements sophisticated logic for questionnaire analysis:

**Requirements:**
1. Course must have a questionnaire module (visible)
2. Questionnaire must contain a Rate question (type_id = 8)
3. User must have submitted a response

**Scoring Rules:**

**Case 1: Exactly 9 choices with responses**
- `score_materi` = average of choices 1-3 (Material quality)
- `score_trainer` = average of choices 4-6 (Trainer quality)
- `score_tempat` = average of choices 7-9 (Venue quality)
- `score_total` = average of all 9 choices
- `questionnaire_available` = 1

**Case 2: Different number of choices**
- Only `score_total` is calculated (average of all responses)
- Other scores = 0
- `questionnaire_available` = 1 if score_total > 0, else 0

**Case 3: No questionnaire/question/responses**
- All scores = 0
- `questionnaire_available` = 0

**Response Ordering:**
- Responses are ordered by `choice_id` in ascending order
- This ensures consistent mapping to score categories
- Each choice's `rankvalue` represents the user's rating (typically 1-5 scale)

### RESTful Principles

1. **Resource-Oriented**: Each function represents a resource
   - `/courses` → get_active_courses
   - `/participants` → get_course_participants
   - `/results` → get_course_results
   - `/all_results` → get_all_course_results (with questionnaire data)

2. **Stateless**: Each request is independent
   - No session management
   - All auth info in each request

3. **Cacheable**: Responses can be cached
   - Use HTTP cache headers
   - Data changes infrequently

4. **Uniform Interface**: Consistent patterns
   - Same authentication for all endpoints
   - Same error format
   - Same response structure

### Response Patterns

#### Success Response Pattern
```json
{
  "type": "array",
  "items": {
    "type": "object",
    "properties": {
      "id": "integer",
      "name": "string",
      ...
    }
  }
}
```

#### Error Response Pattern
```json
{
  "exception": "exception_class",
  "errorcode": "error_code",
  "message": "Human readable message"
}
```

### Pagination Considerations

For future versions, pagination would follow this pattern:

```php
public static function get_active_courses_parameters() {
    return new external_function_parameters([
        'apikey' => new external_value(PARAM_TEXT, 'API key'),
        'page' => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 1),
        'perpage' => new external_value(PARAM_INT, 'Items per page', VALUE_DEFAULT, 50)
    ]);
}
```

Response with pagination:
```json
{
  "data": [...],
  "pagination": {
    "page": 1,
    "perpage": 50,
    "total": 150,
    "pages": 3
  }
}
```

---

## Implementation Examples

### Example 1: Using get_all_course_results

**Request:**
```bash
curl -X POST "https://yourmoodle.com/webservice/rest/server.php" \
  -d "wstoken=YOUR_WEBSERVICE_TOKEN" \
  -d "wsfunction=local_hris_get_all_course_results" \
  -d "moodlewsrestformat=json" \
  -d "apikey=YOUR_API_KEY" \
  -d "format=json"
```

**Response:**
```json
[
  {
    "course_id": 5,
    "course_name": "Customer Service Training",
    "course_shortname": "CST-2025",
    "user_id": 123,
    "firstname": "John",
    "lastname": "Doe",
    "email": "john.doe@company.com",
    "company_name": "Jakarta Branch",
    "final_grade": 85.50,
    "pretest_score": 70.00,
    "posttest_score": 90.00,
    "completion_date": 1704067200,
    "is_completed": 1,
    "questionnaire_available": 1,
    "score_materi": 4.33,
    "score_trainer": 4.67,
    "score_tempat": 4.00,
    "score_total": 4.33
  }
]
```

### Example 2: Questionnaire Structure for 9-Choice Rate Question

To get the full breakdown of scores (materi, trainer, tempat), create a questionnaire with a Rate question having exactly 9 choices:

**Choices 1-3 (Material):**
1. Quality of training materials
2. Clarity of content
3. Relevance to job tasks

**Choices 4-6 (Trainer):**
4. Trainer's knowledge
5. Communication skills
6. Ability to answer questions

**Choices 7-9 (Venue):**
7. Room comfort
8. Facilities and equipment
9. Location accessibility

Each choice is rated on a scale (typically 1-5), and the plugin automatically calculates:
- `score_materi` = (choice1 + choice2 + choice3) / 3
- `score_trainer` = (choice4 + choice5 + choice6) / 3  
- `score_tempat` = (choice7 + choice8 + choice9) / 3
- `score_total` = (all choices) / 9

### Example 3: Error Handling

**Invalid API Key:**
```json
{
  "exception": "moodle_exception",
  "errorcode": "invalidapikey",
  "message": "Invalid API key provided"
}
```

**Missing Parameters:**
```json
{
  "exception": "invalid_parameter_exception",
  "errorcode": "invalidparameter",
  "message": "Missing required parameter: apikey"
}
```

---

## Performance Considerations

### Query Optimization

1. **Use Indexes**: Ensure proper database indexes
   ```sql
   CREATE INDEX idx_course_visible ON mdl_course(visible, id);
   CREATE INDEX idx_user_deleted_confirmed ON mdl_user(deleted, confirmed);
   ```

2. **Limit Result Sets**: Use LIMIT in queries when appropriate

3. **Avoid N+1 Queries**: Use JOINs instead of loops with individual queries

4. **Cache Results**: Use Moodle's cache API for frequently accessed data

### Scalability

```
┌────────────────────────────────────────────┐
│         Load Balancing Strategy            │
├────────────────────────────────────────────┤
│                                            │
│  Multiple Web Servers                      │
│  ├── Server 1 (API requests)               │
│  ├── Server 2 (API requests)               │
│  └── Server 3 (API requests)               │
│                                            │
│  Single Database (with replication)        │
│  ├── Master (writes)                       │
│  └── Slaves (reads)                        │
│                                            │
│  Redis Cache Layer                         │
│  └── Shared cache for all servers          │
└────────────────────────────────────────────┘
```

---

## Monitoring & Logging

### Logging Strategy

```php
// Enable web service logging in Moodle
// Site Admin → Development → Debugging

// Custom logging in plugin
debugging('HRIS API: ' . $function_name . ' called', DEBUG_DEVELOPER);

// Log to Moodle logs
add_to_log(
    SITEID, 
    'webservice', 
    'function_call',
    '', 
    $function_name
);
```

### Metrics to Monitor

1. **API Usage**
   - Requests per hour/day
   - Most called functions
   - Response times

2. **Error Rates**
   - Failed authentication attempts
   - Invalid API key attempts
   - Exception frequency

3. **Performance**
   - Average response time
   - Slow query detection
   - Database load

4. **Security**
   - Suspicious access patterns
   - IP-based anomalies
   - Token usage patterns

---

## Future Enhancements

### Phase 1: Completed ✓
1. ✓ Basic API endpoints (courses, participants, results)
2. ✓ API key authentication
3. ✓ Pre-test and post-test score retrieval using custom fields
4. ✓ Questionnaire scoring with Rate question analysis
5. ✓ Comprehensive result endpoint with all metrics

### Phase 2: Planned Features
1. **Filtering and Pagination**
   - Add pagination support to all list endpoints
   - Advanced filtering (date ranges, completion status)
   - Sorting options

2. **Performance Optimization**
   - Caching layer for frequently accessed data
   - Query optimization for large datasets
   - Batch processing for bulk operations

3. **Additional Endpoints**
   - User enrollment management
   - Course assignment tracking
   - Certificate generation and download
   - Attendance tracking integration

### Phase 3: Advanced Features
1. **Webhook Support**
   - Real-time notifications for course completions
   - Grade update notifications
   - Enrollment change events

2. **Enhanced Reporting**
   - Custom report builder API
   - Aggregated analytics
   - Export to Excel/PDF formats
   - Dashboard data API

3. **Questionnaire Extensions**
   - Support for different question types beyond Rate
   - Custom scoring formulas
   - Configurable choice grouping
   - Text response analysis

### Phase 4: Enterprise Features
1. **OAuth 2.0 Authentication**
   - Replace or complement API key with OAuth 2.0
   - Token refresh mechanism
   - Scope-based permissions

2. **GraphQL API Option**
   - Alternative to REST API
   - Flexible query structure
   - Reduced over-fetching

3. **Multi-tenant Support**
   - Organization-level data isolation
   - Custom branding per tenant
   - Separate API keys per organization

### Phase 5: Integration Ecosystem
1. **Pre-built HRIS Connectors**
   - SAP SuccessFactors connector
   - Workday integration
   - BambooHR plugin
   - Generic HRIS adapter

2. **Developer Tools**
   - SDK libraries (PHP, Python, Node.js, Java)
   - Postman/Insomnia collections
   - OpenAPI/Swagger specification
   - Code examples and tutorials

3. **Middleware Service**
   - Data transformation layer
   - Request queuing and throttling
   - Error retry logic
   - Audit logging service

---

## Changelog

### Version 1.1 (Current)
- Added `get_all_course_results()` endpoint
- Implemented questionnaire scoring with Rate question
- Added support for 9-choice questionnaire breakdown
- Enhanced error handling with try-catch blocks
- Added `questionnaire_available` flag
- Improved documentation with implementation examples

### Version 1.0 (Initial Release)
- Basic API endpoints: get_active_courses, get_course_participants, get_course_results
- API key authentication mechanism
- Pre-test and post-test score retrieval via custom fields
- Integration with Moodle web services framework

---

## Appendix

### Glossary

- **API Key**: Custom authentication token specific to HRIS plugin
- **Web Service Token**: Moodle's standard token for web service access
- **External API**: Moodle's web service function class
- **Context**: Moodle's permission scope (system, course, user)
- **PARAM_**: Moodle's parameter type constants for validation
- **Rate Question**: Questionnaire question type (type_id=8) for rating scale responses
- **Custom Field**: Moodle's custom field API for extending modules with additional metadata
- **jenis_quiz**: Custom field shortname used to distinguish pre-test (2) vs post-test (3)

### Configuration Requirements

**Custom Fields:**
- `jenis_quiz` (Course Module): Values 2=PreTest, 3=PostTest
- `branch` (User Profile): Company/branch name

**Questionnaire Setup:**
- Must use "Rate" question type (QUESRATE)
- For detailed scores, create exactly 9 choices
- Choices ordered by ID (1-9 for proper categorization)

### References

- [Moodle Web Services Documentation](https://docs.moodle.org/dev/Web_services)
- [External Functions API](https://docs.moodle.org/dev/External_functions_API)
- [Moodle Database API](https://docs.moodle.org/dev/Data_manipulation_API)
- [Custom Fields API](https://docs.moodle.org/dev/Custom_fields_API)
- [Questionnaire Plugin](https://moodle.org/plugins/mod_questionnaire)

---

**Last Updated**: 2026-02-01  
**Version**: 1.1  
**Author**: Prihantoosa
