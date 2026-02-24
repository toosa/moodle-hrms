# HRIS Integration - Changelog

## Version 1.1.0 (2026-01-12)

### Major Changes
- **Optimized Query Performance**: Implemented production-proven SQL query from askara-int.com LMS
- **Custom Field Integration**: Updated to use course module custom fields instead of quiz naming patterns
- **Single Query Approach**: Replaced multiple quiz score queries with efficient CASE WHEN aggregation

### Technical Details

#### Custom Field Configuration
The plugin now uses custom fields on **course modules** (not directly on quiz):
- Custom field: `jenis_quiz`
- Applied to: Course modules (quiz instances)
- Values:
  - `1` = Normal
  - `2` = PreTest  
  - `3` = PostTest

#### Database Structure
```sql
-- Custom field is linked to course_modules.id (not quiz.id)
customfield_data.instanceid = course_modules.id
```

#### Query Optimization
Before (v1.0.x):
- 1 main query to get enrollments
- N queries to get pre-test scores (one per user/course)
- N queries to get post-test scores (one per user/course)
- Total: **1 + 2N queries**

After (v1.1.0):
- 1 single query with CASE WHEN aggregation
- Total: **1 query** ✅

#### Performance Impact
- **Reduced database calls**: From O(n) to O(1)
- **Faster response time**: Especially for large datasets
- **Lower server load**: Single query vs multiple queries

### Configuration Requirements

1. **Install Custom Field Plugin**: `local_modcustomfields`
2. **Create Custom Field**:
   - Go to: Site administration > Plugins > Activity modules > Quiz
   - Add custom field: `jenis_quiz` (type: dropdown)
   - Options: Normal, PreTest, PostTest
3. **Set Quiz Type**: For each quiz, set the custom field value

### Migration Notes

If you have existing quizzes identified by naming patterns:
1. Install custom field plugin (local_modcustomfields or equivalent)
2. Create the custom field `jenis_quiz` on course modules:
   - Type: Select/Dropdown
   - Values: 1=Normal, 2=PreTest, 3=PostTest
3. Manually set the field value for each quiz:
   - Pre-test quizzes → set to "2" (PreTest)
   - Post-test quizzes → set to "3" (PostTest)
4. Verify with SQL:
   ```sql
   SELECT COUNT(*) FROM mdl_customfield_data 
   WHERE fieldid = (SELECT id FROM mdl_customfield_field WHERE shortname='jenis_quiz')
   AND value IN ('2','3');
   ```

### Troubleshooting Migration

**Issue**: Pre/post test scores showing as 0.00 after upgrade
**Solution**: 
- Verify custom field exists: Check mdl_customfield_field table
- Verify field values are set: Check mdl_customfield_data table
- Re-run: `php admin/cli/upgrade.php --non-interactive`

**Issue**: Custom field not appearing in quiz settings
**Solution**:
- Ensure plugin is enabled that creates custom fields
- Go to: Site Admin > Plugins > Course modules > Quiz
- Verify custom field 'jenis_quiz' is listed
- If not visible, create manually via database

### API Changes
No breaking changes. API endpoints remain the same:
- `local_hris_get_active_courses`
- `local_hris_get_course_participants`
- `local_hris_get_course_results`

### Bug Fixes
- Fixed MySQL GROUP BY compatibility issue
- Fixed profile field reference (company → branch)
- Improved error handling for missing custom fields

---

## Version 1.0.2 (2026-01-12)
- Updated profile field from 'company' to 'branch'
- Added custom field support for quiz type detection

## Version 1.0.1 (2026-01-12)
- Initial version with basic API functionality

## Version 1.0.0 (2025-12-26)
- First release
