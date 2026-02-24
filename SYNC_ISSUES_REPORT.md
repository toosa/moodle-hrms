# Documentation Sync Report - external.php

**Date**: January 25, 2026  
**Status**: ❌ OUT OF SYNC - 7 Critical Issues Found

---

## Summary of Issues

| Issue | Severity | Document | Type |
|-------|----------|----------|------|
| User profile field name mismatch | 🔴 Critical | README.md, DESIGN.md | Outdated info |
| Pre/Post test detection method changed | 🔴 Critical | README.md, DESIGN.md, DIAGRAMS.md | Outdated info |
| Quiz score query implementation gap | 🔴 Critical | README.md, QUICKREF.md | Missing doc |
| Query examples use old method | 🔴 Critical | DESIGN.md | Outdated SQL |
| Changelog version discrepancies | 🟡 Warning | CHANGELOG.md | Incomplete info |
| Custom field configuration missing | 🟡 Warning | README.md, DESIGN.md | Missing details |
| Response field inconsistencies | 🟡 Warning | Multiple docs | Minor discrepancies |

---

## Detailed Findings

### 1. 🔴 CRITICAL: User Profile Field Name

**Status**: ❌ MISMATCH FOUND

**In external.php** (Line 132, 197, 224):
```php
LEFT JOIN {user_info_field} uif ON uif.shortname = 'branch'
LEFT JOIN {user_info_data} uid ON u.id = uid.userid AND uid.fieldid = uif.id
```
- Uses custom field: **`branch`**

**In Documentation**:
- README.md (Line 520): "Company name (from user profile)"
- README.md (Line 535): "company_name field"
- DESIGN.md (Line 423-426): Shows 'company' in queries
- QUICKREF.md: References company_name field
- CHANGELOG.md (v1.0.2): "Updated profile field from 'company' to 'branch'"

**Issue**: Documentation still refers to 'company' in several places but code uses 'branch'

**Affected Docs**: 
- [README.md](README.md#L520)
- [DESIGN.md](DESIGN.md#L423)
- [DIAGRAMS.md](DIAGRAMS.md#L180)

---

### 2. 🔴 CRITICAL: Pre/Post Test Detection Method

**Status**: ❌ IMPLEMENTATION CHANGED

**In external.php** (Lines 310-331):
```php
private static function get_quiz_score($userid, $courseid, $type) {
    $fieldvalue = $type === 'pre' ? '2' : '3';
    
    $sql = "SELECT MAX(gg.finalgrade) as score
            FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
            JOIN {customfield_data} cfd ON cfd.instanceid = cm.id AND cfd.value = :fieldvalue
            JOIN {grade_items} gi ON gi.iteminstance = cm.instance...";
```
- Uses: **Custom field values** (2 = PreTest, 3 = PostTest)
- Source: **Course module custom fields**

**In Documentation**:
- README.md (Line 525-535): Still describes old quiz name pattern matching
- DESIGN.md (Line 647): Shows old query with quiz name ILIKE
- DIAGRAMS.md (Line 230-245): Shows old flow with name patterns

**Old Pattern** (Documented but not implemented):
```sql
WHERE q.name ILIKE '%pre%test%'  -- OLD WAY
```

**New Pattern** (Implemented):
```sql
WHERE cfd.value = '2'  -- NEW WAY - Custom field
```

**Affected Docs**:
- [README.md](README.md#L525-L535)
- [DESIGN.md](DESIGN.md#L647)
- [DIAGRAMS.md](DIAGRAMS.md#L230)

---

### 3. 🔴 CRITICAL: Missing Documentation

**Status**: ❌ FEATURE NOT DOCUMENTED

**In external.php** (Lines 310-331):
- Complete `get_quiz_score()` private method implementation
- Custom field mapping explanation
- Field values documentation (2 = PreTest, 3 = PostTest)

**Missing From**:
- README.md - No explanation of how scores are retrieved
- DESIGN.md - Old method shown, new method not documented
- QUICKREF.md - No troubleshooting for custom field issues
- CHANGELOG.md - Upgrade instructions incomplete

**Example Missing Info**:
```markdown
# Custom Field Setup (MISSING)

For pre/post test scores to work:

1. Create custom field on course modules:
   - Field name: 'jenis_quiz'
   - Type: Dropdown/Select
   - Values: 
     * 1 = Normal
     * 2 = PreTest (used in get_quiz_score)
     * 3 = PostTest (used in get_quiz_score)

2. For each quiz:
   - Course Admin > Quiz settings
   - Set custom field 'jenis_quiz' value
```

---

### 4. 🔴 CRITICAL: Query Examples Use Old Method

**Status**: ❌ INCORRECT DOCUMENTATION

**DESIGN.md** (Lines 647-658):
```sql
-- DOCUMENTED (WRONG):
SELECT MAX(qa.sumgrades) as score
FROM mdl_quiz_attempts qa
JOIN mdl_quiz q ON qa.quiz = q.id
WHERE qa.userid = :userid
  AND q.course = :courseid
  AND q.name ILIKE :namepattern  -- '%pre%test%' or '%post%test%'
  AND qa.state = 'finished';
```

**ACTUAL IMPLEMENTATION** (external.php Lines 310-327):
```sql
-- ACTUAL (CORRECT):
SELECT MAX(gg.finalgrade) as score
FROM {course_modules} cm
JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
JOIN {customfield_data} cfd ON cfd.instanceid = cm.id AND cfd.value = :fieldvalue
JOIN {grade_items} gi ON gi.iteminstance = cm.instance AND gi.itemmodule = 'quiz'
LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
WHERE cm.course = :courseid
```

**Affected Docs**:
- [DESIGN.md Lines 647-658](DESIGN.md#L647-L658)
- [README.md Lines 515-519](README.md#L515-L519)

---

### 5. 🟡 WARNING: Incomplete Changelog

**Status**: ⚠️ MISSING UPGRADE DETAILS

**CHANGELOG.md** (v1.1.0):
- ✅ Lists changes
- ✅ Mentions custom field mapping
- ❌ Missing: Step-by-step migration instructions
- ❌ Missing: Database verification queries
- ❌ Missing: Troubleshooting section for migrations

**Should Include**:
```markdown
### Migration Checklist

- [ ] Verify custom field exists: 'jenis_quiz'
- [ ] Check all quizzes have field value set
- [ ] Test: SELECT COUNT(*) FROM mdl_customfield_data WHERE field = 'jenis_quiz'
- [ ] Verify: SELECT MAX(gg.finalgrade) returns scores
```

---

### 6. 🟡 WARNING: Custom Field Configuration

**Status**: ⚠️ CONFIGURATION STEPS UNCLEAR

**Missing Information**:
1. Which plugin creates custom fields? (not specified)
2. How to create custom field on course_modules?
3. Where to set the field value per quiz?
4. What happens if field is not set?

**Actual Code Uses** (external.php):
```php
JOIN {customfield_data} cfd ON cfd.instanceid = cm.id AND cfd.value = :fieldvalue
```
- Assumes custom field data exists
- No error handling if field missing
- Returns 0.00 if no matching records

---

### 7. 🟡 WARNING: Response Field Consistency

**Status**: ⚠️ MINOR NAMING INCONSISTENCIES

**In external.php** (Lines 195-205):
```php
'company_name' => $participant->company_name ?: ''
```

**In Documentation**:
- README.md: "company_name" ✅
- QUICKREF.md: "company_name" ✅
- DIAGRAMS.md: "company_name" ✅

**Issue**: Field is named correctly, but derived from 'branch' not 'company' - confusing naming

**Recommendation**: Consider renaming to `branch_name` for clarity

---

## Impact Assessment

### Breaking Changes from v1.0 to v1.1
- ✅ No breaking API changes (endpoints same)
- ⚠️ Data retrieval method changed (custom field vs naming)
- ⚠️ Requires database setup (custom field creation)

### Risk Level: 🔴 HIGH
- Users upgrading without creating custom field will get 0.00 scores
- Documentation doesn't explain this clearly
- No warning about migration requirement

---

## Recommendations

### Immediate Fixes (Priority 1)
1. ✅ Update README.md to show correct custom field name ('branch' not 'company')
2. ✅ Update DESIGN.md SQL queries to use custom field method
3. ✅ Add custom field setup section to README.md
4. ✅ Update DIAGRAMS.md flow to show custom field lookup
5. ✅ Add migration troubleshooting to CHANGELOG.md

### Documentation Improvements (Priority 2)
1. ✅ Add explicit custom field configuration guide
2. ✅ Add SQL verification queries for setup
3. ✅ Add troubleshooting section for missing fields
4. ✅ Update FAQ with custom field questions
5. ✅ Create separate MIGRATION.md for v1.0 to v1.1 upgrades

### Code Documentation (Priority 3)
1. ✅ Add PHPDoc comment explaining custom field mapping
2. ✅ Add inline comments for field value meanings
3. ✅ Document error handling for missing fields

---

## Files Requiring Updates

| File | Issues | Priority |
|------|--------|----------|
| [README.md](README.md) | 4 issues (field name, old detection method, missing setup) | 🔴 P1 |
| [DESIGN.md](DESIGN.md) | 3 issues (SQL queries, field references, flow diagram) | 🔴 P1 |
| [DIAGRAMS.md](DIAGRAMS.md) | 2 issues (flow descriptions outdated) | 🔴 P1 |
| [QUICKREF.md](QUICKREF.md) | 1 issue (missing troubleshooting) | 🟡 P2 |
| [CHANGELOG.md](CHANGELOG.md) | 1 issue (missing migration guide) | 🟡 P2 |

---

## Verification Checklist

- [ ] README.md mentions 'branch' field (not 'company')
- [ ] README.md explains custom field setup requirement
- [ ] DESIGN.md SQL queries use customfield_data joins
- [ ] DIAGRAMS.md shows correct flow for score retrieval
- [ ] Migration steps documented clearly
- [ ] Custom field values documented (2=pre, 3=post)
- [ ] Field name: 'jenis_quiz' documented consistently
- [ ] Troubleshooting section added

---

**Generated**: January 25, 2026  
**Next Review**: After all fixes applied
