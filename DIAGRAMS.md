# HRIS Plugin - Sequence Diagrams

This document contains all sequence diagrams for the HRIS Integration Plugin in Mermaid format.

## Table of Contents
1. [Complete Request-Response Cycle](#1-complete-request-response-cycle)
2. [Get Active Courses Flow](#2-get-active-courses-flow)
3. [Get Course Participants Flow](#3-get-course-participants-flow)
4. [Get Course Results Flow](#4-get-course-results-flow)
5. [Get All Course Results Flow (with Questionnaire)](#5-get-all-course-results-flow-with-questionnaire)
6. [Questionnaire Scoring Flow](#6-questionnaire-scoring-flow)
7. [Authentication Flow](#7-authentication-flow)
8. [Error Handling Flow](#8-error-handling-flow)

---

## 1. Complete Request-Response Cycle

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

---

## 2. Get Active Courses Flow

```mermaid
sequenceDiagram
    participant HRIS as HRIS System
    participant WS as Moodle Web Service
    participant API as local_hris_external
    participant DB as Moodle Database
    
    HRIS->>WS: POST /webservice/rest/server.php
    Note over HRIS,WS: wstoken + apikey + wsfunction
    
    WS->>API: local_hris_get_active_courses(apikey)
    
    API->>API: validate_parameters(apikey)
    API->>API: validate_api_key(apikey)
    
    alt API Key Invalid
        API-->>HRIS: Error: Invalid API Key
    else API Key Valid
        API->>API: validate_context(system)
        API->>DB: SELECT courses WHERE visible=1
        DB-->>API: Course records
        
        loop For each course
            API->>API: Format course data
        end
        
        API-->>WS: Array of courses
        WS-->>HRIS: JSON Response
    end
```

### Detailed Flow with Validation

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

---

## 3. Get Course Participants Flow

```mermaid
sequenceDiagram
    participant HRIS as HRIS System
    participant WS as Moodle Web Service
    participant API as local_hris_external
    participant DB as Moodle Database
    
    HRIS->>WS: POST /webservice/rest/server.php
    Note over HRIS,WS: wstoken + apikey + wsfunction + courseid
    
    WS->>API: get_course_participants(apikey, courseid)
    
    API->>API: validate_parameters()
    API->>API: validate_api_key(apikey)
    
    alt API Key Invalid
        API-->>HRIS: Error: Invalid API Key
    else API Key Valid
        API->>API: validate_context(system)
        
        alt courseid > 0
            API->>DB: SELECT users WHERE course_id=courseid
        else courseid = 0
            API->>DB: SELECT users FROM all courses
        end
        
        DB-->>API: Enrollment records with user info
        
        loop For each participant
            API->>API: Format participant data
        end
        
        API-->>WS: Array of participants
        WS-->>HRIS: JSON Response
    end
```

### Detailed Flow with Filtering

```mermaid
sequenceDiagram
    participant Client
    participant API as local_hris_external
    participant DB as Database
    
    Client->>API: get_course_participants(apikey, courseid)
    
    API->>API: Validate parameters & API key
    
    alt courseid = 0
        Note over API: Get all participants from all courses
        API->>DB: SELECT users FROM all courses<br/>JOIN user_enrolments<br/>JOIN enrol<br/>JOIN course<br/>LEFT JOIN user_info_data (branch)
    else courseid > 0
        Note over API: Get participants for specific course
        API->>DB: SELECT users WHERE course_id = courseid<br/>JOIN user_enrolments<br/>JOIN enrol<br/>JOIN course<br/>LEFT JOIN user_info_data (branch)
    end
    
    DB-->>API: Participant records
    
    loop For each participant
        API->>API: Format data:<br/>- user_id<br/>- email<br/>- firstname, lastname<br/>- company_name (from branch)<br/>- course info<br/>- enrollment_date
    end
    
    API-->>Client: Array of participants (JSON)
```

---

## 4. Get Course Results Flow

```mermaid
sequenceDiagram
    participant HRIS as HRIS System
    participant WS as Moodle Web Service
    participant API as local_hris_external
    participant DB as Moodle Database
    
    HRIS->>WS: POST /webservice/rest/server.php
    Note over HRIS,WS: wstoken + apikey + wsfunction + courseid + userid
    
    WS->>API: get_course_results(apikey, courseid, userid)
    
    API->>API: validate_parameters()
    API->>API: validate_api_key(apikey)
    
    alt API Key Invalid
        API-->>HRIS: Error: Invalid API Key
    else API Key Valid
        API->>API: validate_context(system)
        
        alt Filters applied
            API->>DB: SELECT with courseid/userid filters
        else No filters
            API->>DB: SELECT all results
        end
        
        DB-->>API: Enrollment & grade records
        
        loop For each enrollment
            API->>DB: get_quiz_score(userid, courseid, 'pre')
            DB-->>API: Pre-test score
            
            API->>DB: get_quiz_score(userid, courseid, 'post')
            DB-->>API: Post-test score
            
            API->>API: Format result data
        end
        
        API-->>WS: Array of results
        WS-->>HRIS: JSON Response
    end
```

### Detailed Flow with Score Calculation

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
        Note over API,DB: Get pre-test score via custom field (value=2)
        API->>DB: SELECT MAX(gg.finalgrade)<br/>FROM mdl_course_modules cm<br/>JOIN mdl_customfield_data cfd<br/>WHERE cfd.value = '2' AND cm.course = courseid
        DB-->>API: pre_score
        
        Note over API,DB: Get post-test score via custom field (value=3)
        API->>DB: SELECT MAX(gg.finalgrade)<br/>FROM mdl_course_modules cm<br/>JOIN mdl_customfield_data cfd<br/>WHERE cfd.value = '3' AND cm.course = courseid
        DB-->>API: post_score
        
        API->>API: Build result object:<br/>- user info<br/>- course info<br/>- final_grade<br/>- pretest_score<br/>- posttest_score<br/>- completion_date<br/>- is_completed
    end
    
    API-->>Client: Array of results (JSON)
```

---

## 5. Get All Course Results Flow (with Questionnaire)

```mermaid
sequenceDiagram
    participant HRIS as HRIS System
    participant WS as Moodle Web Service
    participant API as local_hris_external
    participant DB as Moodle Database
    
    HRIS->>WS: POST /webservice/rest/server.php
    Note over HRIS,WS: wstoken + apikey + wsfunction + format
    
    WS->>API: get_all_course_results(apikey, format)
    
    API->>API: validate_parameters()
    API->>API: validate_api_key(apikey)
    
    alt API Key Invalid
        API-->>HRIS: Error: Invalid API Key
    else API Key Valid
        API->>API: validate_context(system)
        
        API->>DB: SELECT all enrollments with grades
        DB-->>API: All enrollment records
        
        loop For each enrollment
            API->>DB: get_quiz_score(userid, courseid, 'pre')
            DB-->>API: Pre-test score
            
            API->>DB: get_quiz_score(userid, courseid, 'post')
            DB-->>API: Post-test score
            
            API->>DB: get_questionnaire_scores(userid, courseid)
            Note over API,DB: Complex questionnaire analysis
            DB-->>API: Questionnaire scores object
            
            API->>API: Merge all data:<br/>- User & course info<br/>- Grades & completion<br/>- Test scores<br/>- Questionnaire scores
        end
        
        API-->>WS: Array of comprehensive results
        WS-->>HRIS: JSON Response with all metrics
    end
```

### Detailed Flow with All Metrics

```mermaid
sequenceDiagram
    participant Client
    participant API as local_hris_external
    participant Helper as Helper Methods
    participant DB as Database
    
    Client->>API: get_all_course_results(apikey, format='json')
    
    API->>API: Validate parameters & API key & context
    
    API->>DB: SELECT u.id, u.email, u.firstname, u.lastname,<br/>c.id, c.shortname, c.fullname,<br/>cc.timecompleted, gg.finalgrade<br/>FROM users JOIN enrollments JOIN courses<br/>LEFT JOIN completions, grades, user_info
    
    DB-->>API: All enrollment records
    
    loop For each (user, course) pair
        Note over API,Helper: Calculate Pre-test Score
        API->>Helper: get_quiz_score(userid, courseid, 'pre')
        Helper->>DB: SELECT MAX(finalgrade)<br/>WHERE custom_field.value = '2'
        DB-->>Helper: pretest_score
        Helper-->>API: pretest_score (0.00 if none)
        
        Note over API,Helper: Calculate Post-test Score
        API->>Helper: get_quiz_score(userid, courseid, 'post')
        Helper->>DB: SELECT MAX(finalgrade)<br/>WHERE custom_field.value = '3'
        DB-->>Helper: posttest_score
        Helper-->>API: posttest_score (0.00 if none)
        
        Note over API,Helper: Calculate Questionnaire Scores
        API->>Helper: get_questionnaire_scores(userid, courseid)
        Helper->>Helper: See Questionnaire Scoring Flow (Section 6)
        Helper-->>API: {
            questionnaire_available,
            score_materi,
            score_trainer,
            score_tempat,
            score_total
        }
        
        API->>API: Merge into result object:
        Note over API: course_id, course_name, course_shortname,<br/>user_id, firstname, lastname, email,<br/>company_name, final_grade,<br/>pretest_score, posttest_score,<br/>completion_date, is_completed,<br/>questionnaire_available,<br/>score_materi, score_trainer,<br/>score_tempat, score_total
    end
    
    API-->>Client: Array of comprehensive results (JSON)
```

---

## 6. Questionnaire Scoring Flow

### Complete Questionnaire Analysis Process

```mermaid
sequenceDiagram
    participant API as get_questionnaire_scores()
    participant DB as Database
    
    Note over API: Initialize default response:<br/>all scores = 0, available = 0
    
    API->>DB: Find questionnaire module
    Note over DB: SELECT cm.id, q.id<br/>FROM course_modules cm<br/>JOIN modules m (name='questionnaire')<br/>JOIN questionnaire q<br/>WHERE course = courseid AND visible = 1
    
    alt No questionnaire found
        DB-->>API: NULL
        API-->>API: Return default (all zeros)
    else Questionnaire exists
        DB-->>API: questionnaire_id
        
        API->>DB: Find Rate question (type_id=8)
        Note over DB: SELECT * FROM questionnaire_question<br/>WHERE surveyid = questionnaire_id<br/>AND type_id = 8 (QUESRATE)
        
        alt No Rate question
            DB-->>API: NULL
            API-->>API: Return default (all zeros)
        else Rate question found
            DB-->>API: rate_question_id
            
            API->>DB: Count choices
            Note over DB: SELECT COUNT(*)<br/>FROM questionnaire_quest_choice<br/>WHERE question_id = rate_question_id
            DB-->>API: choice_count
            
            API->>DB: Get user's response
            Note over DB: SELECT * FROM questionnaire_response<br/>WHERE questionnaireid = questionnaire_id<br/>AND userid = userid
            
            alt No response from user
                DB-->>API: NULL
                API-->>API: Return default (all zeros)
            else Response exists
                DB-->>API: response_id
                
                API->>DB: Get all rating values
                Note over DB: SELECT qrr.rankvalue<br/>FROM questionnaire_response_rank qrr<br/>JOIN questionnaire_quest_choice qqc<br/>WHERE response_id = response_id<br/>AND question_id = rate_question_id<br/>ORDER BY qqc.id ASC
                
                DB-->>API: Array of rankvalues [v1, v2, ..., vN]
                
                alt Empty responses
                    API-->>API: Return default (all zeros)
                else Has responses
                    API->>API: Calculate score_total = average(all values)
                    
                    alt Response count != choice count
                        Note over API: Mismatch detected
                        API-->>API: Return {
                            questionnaire_available: 1 if total > 0,
                            score_materi: 0,
                            score_trainer: 0,
                            score_tempat: 0,
                            score_total: calculated
                        }
                    else choice_count == 9
                        Note over API: Perfect match with 9 choices
                        API->>API: score_materi = avg(v1, v2, v3)
                        API->>API: score_trainer = avg(v4, v5, v6)
                        API->>API: score_tempat = avg(v7, v8, v9)
                        API-->>API: Return {
                            questionnaire_available: 1,
                            score_materi: calculated,
                            score_trainer: calculated,
                            score_tempat: calculated,
                            score_total: calculated
                        }
                    else Other choice count
                        Note over API: Valid but not 9 choices
                        API-->>API: Return {
                            questionnaire_available: 1 if total > 0,
                            score_materi: 0,
                            score_trainer: 0,
                            score_tempat: 0,
                            score_total: calculated
                        }
                    end
                end
            end
        end
    end
```

### Questionnaire Scoring Decision Tree

```mermaid
flowchart TD
    Start([get_questionnaire_scores<br/>userid, courseid]) --> FindQ[Find Questionnaire Module]
    
    FindQ --> HasQ{Questionnaire<br/>Exists?}
    HasQ -->|No| ReturnZero1[Return all zeros<br/>available=0]
    HasQ -->|Yes| FindRate[Find Rate Question<br/>type_id=8]
    
    FindRate --> HasRate{Rate Question<br/>Found?}
    HasRate -->|No| ReturnZero2[Return all zeros<br/>available=0]
    HasRate -->|Yes| CountChoice[Count Choices]
    
    CountChoice --> GetResp[Get User Response]
    
    GetResp --> HasResp{User has<br/>Response?}
    HasResp -->|No| ReturnZero3[Return all zeros<br/>available=0]
    HasResp -->|Yes| GetRank[Get All Rank Values<br/>Ordered by choice_id]
    
    GetRank --> HasRank{Has Rank<br/>Values?}
    HasRank -->|No| ReturnZero4[Return all zeros<br/>available=0]
    HasRank -->|Yes| CalcTotal[Calculate score_total<br/>= average of all values]
    
    CalcTotal --> CheckMatch{Response count<br/>== Choice count?}
    CheckMatch -->|No| ReturnTotal1[Return:<br/>available=1 if total>0<br/>only score_total<br/>others=0]
    
    CheckMatch -->|Yes| Check9{Choice count<br/>== 9?}
    Check9 -->|No| ReturnTotal2[Return:<br/>available=1 if total>0<br/>only score_total<br/>others=0]
    
    Check9 -->|Yes| CalcBreakdown[Calculate breakdown:<br/>materi = avg v1-v3<br/>trainer = avg v4-v6<br/>tempat = avg v7-v9]
    
    CalcBreakdown --> ReturnAll[Return:<br/>available=1<br/>All 4 scores<br/>with breakdown]
    
    ReturnZero1 --> End([Return to caller])
    ReturnZero2 --> End
    ReturnZero3 --> End
    ReturnZero4 --> End
    ReturnTotal1 --> End
    ReturnTotal2 --> End
    ReturnAll --> End
    
    style Start fill:#e1f5ff
    style End fill:#e1f5ff
    style ReturnAll fill:#c8e6c9
    style ReturnTotal1 fill:#fff9c4
    style ReturnTotal2 fill:#fff9c4
    style ReturnZero1 fill:#ffcdd2
    style ReturnZero2 fill:#ffcdd2
    style ReturnZero3 fill:#ffcdd2
    style ReturnZero4 fill:#ffcdd2
```

---

## 7. Authentication Flow

```mermaid
sequenceDiagram
    participant Client as External Client
    participant WS as Moodle Web Service
    participant Auth as Token Validation
    participant API as local_hris_external
    participant Config as Plugin Config
    
    Client->>WS: Request with wstoken
    WS->>Auth: Validate web service token
    
    alt Token Invalid
        Auth-->>Client: Error: Invalid Token
    else Token Valid
        Auth->>API: Call web service function
        API->>API: Extract apikey parameter
        API->>Config: get_config('local_hris', 'api_key')
        Config-->>API: Stored API key
        
        alt API Key Mismatch
            API-->>Client: Error: Invalid API Key
        else API Key Match
            API->>API: Process request
            API-->>Client: Success Response
        end
    end
```

### Detailed Authentication with User Context

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

## 8. Error Handling Flow

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

### Detailed Error Scenarios

```mermaid
sequenceDiagram
    participant Client
    participant WS as Web Service
    participant API as local_hris_external
    participant DB as Database
    
    Note over Client,DB: Scenario 1: Invalid Token
    Client->>WS: POST with invalid wstoken
    WS->>WS: validate_token()
    WS-->>Client: 401 Unauthorized<br/>webservice_access_exception
    
    Note over Client,DB: Scenario 2: Invalid API Key
    Client->>WS: POST with valid wstoken, invalid apikey
    WS->>API: Call function
    API->>API: validate_api_key(apikey)
    API-->>Client: 403 Forbidden<br/>moodle_exception: invalidapikey
    
    Note over Client,DB: Scenario 3: Missing Required Parameter
    Client->>WS: POST without required parameter
    WS->>API: Call function
    API->>API: validate_parameters()
    API-->>Client: 400 Bad Request<br/>invalid_parameter_exception
    
    Note over Client,DB: Scenario 4: Database Error
    Client->>WS: POST with valid credentials
    WS->>API: Call function
    API->>DB: Execute query
    DB-->>API: SQL Error
    API-->>Client: 500 Internal Server Error<br/>dml_exception
    
    Note over Client,DB: Scenario 5: No Data Found
    Client->>WS: POST with valid credentials
    WS->>API: Call function
    API->>DB: Execute query
    DB-->>API: Empty ResultSet
    API->>API: Process results
    API-->>Client: 200 OK<br/>[] (empty array)
```

---

## Usage Instructions

### Viewing Diagrams

These diagrams use Mermaid syntax. To view them:

1. **GitHub**: GitHub automatically renders Mermaid diagrams in markdown files
2. **VS Code**: Install the "Markdown Preview Mermaid Support" extension
3. **Online**: Copy to [Mermaid Live Editor](https://mermaid.live/)
4. **Documentation Sites**: Use MkDocs with mermaid2 plugin

### Editing Diagrams

To modify these diagrams:

1. Use the Mermaid syntax reference: https://mermaid.js.org/
2. Test changes in the Mermaid Live Editor
3. Common elements:
   - `participant`: Define an actor in the sequence
   - `->`: Solid arrow (synchronous call)
   - `-->>`: Dashed arrow (return/response)
   - `Note over`: Add notes above actors
   - `alt/else/end`: Conditional logic
   - `loop/end`: Repetitive logic

### Exporting Diagrams

To export as images:

1. Use Mermaid CLI: `mmdc -i DIAGRAMS.md -o diagram.png`
2. Or use the Mermaid Live Editor's export function
3. Or use VS Code with Mermaid export extension

---

## Integration with Documentation

These diagrams are referenced in:
- [README.md](README.md) - Main documentation
- [DESIGN.md](DESIGN.md) - Detailed design documentation

---

**Last Updated**: 2026-02-01  
**Version**: 1.1  
**Author**: Prihantoosa

## Changelog

### Version 1.1 (2026-02-01)
- Added Get All Course Results Flow with questionnaire integration
- Added comprehensive Questionnaire Scoring Flow with decision tree
- Added detailed sequence diagrams for questionnaire analysis
- Added flowchart for questionnaire scoring logic
- Updated all section numbering

### Version 1.0 (2025-01-05)
- Initial release with basic API flows
- Authentication and error handling diagrams
