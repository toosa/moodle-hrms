# HRIS Integration - Testing Guide

## Test Script

Run the test script to verify all API functions work correctly:

```bash
php local/hris/tests/test_api.php
```

## Manual Testing via Web Service

### 1. Enable Web Services
- Go to **Site Administration > Advanced features**
- Enable "Enable web services"

### 2. Test Endpoints

#### Get Active Courses
```bash
curl -X POST "https://your-moodle-site/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_hris_get_active_courses" \
  -d "moodlewsrestformat=json" \
  -d "apikey=YOUR_API_KEY"
```

#### Get Course Participants
```bash
# All courses
curl -X POST "https://your-moodle-site/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_hris_get_course_participants" \
  -d "moodlewsrestformat=json" \
  -d "apikey=YOUR_API_KEY" \
  -d "courseid=0"

# Specific course
curl -X POST "https://your-moodle-site/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_hris_get_course_participants" \
  -d "moodlewsrestformat=json" \
  -d "apikey=YOUR_API_KEY" \
  -d "courseid=2"
```

#### Get Course Results
```bash
# All courses, all users
curl -X POST "https://your-moodle-site/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_hris_get_course_results" \
  -d "moodlewsrestformat=json" \
  -d "apikey=YOUR_API_KEY" \
  -d "courseid=0" \
  -d "userid=0"

# Specific course
curl -X POST "https://your-moodle-site/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_hris_get_course_results" \
  -d "moodlewsrestformat=json" \
  -d "apikey=YOUR_API_KEY" \
  -d "courseid=2" \
  -d "userid=0"

# Specific user
curl -X POST "https://your-moodle-site/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_hris_get_course_results" \
  -d "moodlewsrestformat=json" \
  -d "apikey=YOUR_API_KEY" \
  -d "courseid=0" \
  -d "userid=123"
```

## Verification Checklist

### ✓ Custom Field Configuration
- [x] Custom field 'jenis_quiz' exists
- [x] Options: Normal (1), PreTest (2), PostTest (3)
- [ ] All pre-test quizzes marked with value '2'
- [ ] All post-test quizzes marked with value '3'

### ✓ Profile Field Configuration
- [x] User profile field 'branch' exists
- [ ] Users have branch/company data filled

### ✓ API Functions
- [x] `get_active_courses` - Returns list of visible courses
- [x] `get_course_participants` - Returns enrolled users with branch data
- [x] `get_course_results` - Returns grades, pre-test, and post-test scores

## Common Issues

### No Pre-test/Post-test Scores
**Problem**: Pre-test and post-test scores are always 0

**Solutions**:
1. Check if quizzes have the custom field 'jenis_quiz' set:
   ```sql
   SELECT q.id, q.name, cd.value 
   FROM mdl_quiz q
   LEFT JOIN mdl_customfield_data cd ON cd.instanceid = q.id 
   LEFT JOIN mdl_customfield_field cf ON cd.fieldid = cf.id AND cf.shortname = 'jenis_quiz'
   WHERE q.course = YOUR_COURSE_ID;
   ```

2. Verify quiz attempts exist:
   ```sql
   SELECT COUNT(*) 
   FROM mdl_quiz_attempts 
   WHERE quiz = YOUR_QUIZ_ID AND state = 'finished';
   ```

### No Branch/Company Data
**Problem**: company_name is always empty

**Solutions**:
1. Check if users have branch data:
   ```sql
   SELECT u.username, uid.data as branch
   FROM mdl_user u
   JOIN mdl_user_info_data uid ON u.id = uid.userid
   JOIN mdl_user_info_field uif ON uid.fieldid = uif.id
   WHERE uif.shortname = 'branch';
   ```

2. Verify profile field shortname is exactly 'branch'

## Database Structure

### Custom Field Tables
- `mdl_customfield_field` - Custom field definitions
- `mdl_customfield_data` - Custom field values per instance
- `mdl_customfield_category` - Field categories

### User Profile Tables
- `mdl_user_info_field` - User profile field definitions
- `mdl_user_info_data` - User profile field values

### Quiz Tables
- `mdl_quiz` - Quiz definitions
- `mdl_quiz_attempts` - Quiz attempts
- `mdl_quiz_grades` - Quiz grades
