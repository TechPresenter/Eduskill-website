# Student Management + Learning (LMS)

Extends the School module into a full Student Information System and a Learning
Management System. Students are the existing **`school_students`** records
(now with academic/family/document/admission fields); the student **portal**
runs on the existing **Member** login (a student is linked to a member account).

No new dependencies — the QR encoder, GD card renderer and minimal PDF writer
are reused; certificates/marksheets print via the browser (print-to-PDF).

---

## 1. Install / upgrade

```bash
C:\xampp\mysql\bin\mysql.exe -u root pwf < database\eduskill.sql
```

There is one SQL file for the whole project. On a database that already holds
real data, run **Section 2 only** (see the banner inside the file). The tables
and settings this module needs are listed below.

Adds profile/portal columns to `school_students`, `discount`/`scholarship` to
`school_fee_payments`, and the tables: `admissions`, `student_documents`,
`student_certificates`, `marksheets` (+`marksheet_subjects`), `assignments`
(+`assignment_submissions`), `notices`; and the LMS: `courses`,
`course_lessons`, `course_resources`, `course_batches`, `course_enrollments`,
`lesson_progress`, `exams`, `exam_questions`, `exam_attempts`, `live_sessions`.

---

## 2. Student Management (5.6)

| Feature | Where |
|---|---|
| **Admission system** (public form + doc upload → review → enrol) | `admission-apply.php` → `admin/admissions.php` |
| **Student profiles** (personal/academic/family/contact/documents) | `admin/student-profile.php` |
| **Student ID cards** (photo, ID, school, QR) | `admin/student-card.php`, self-service `student-id-card.php` |
| **Certificates** (auto/participation, digital signature, QR) | `admin/certificates.php`, print `admin/certificate-view.php` |
| **Marksheets** (subject-wise, grade, rank, result) | `admin/marksheets.php`, print `admin/marksheet-view.php` |
| **Attendance** (daily, rate) | `admin/school-attendance.php` (school module) |
| **Assignments** (assign → submit → grade) | `admin/assignments.php`, portal `my-assignments.php` |
| **Notices** (targeted board + email/SMS) | `admin/notices.php` |
| **Performance reports** (exam/attendance/assignments) | `admin/student-performance.php` |
| **Fee management** (collection, receipts, dues, scholarship) | `admin/school-fees.php` + `cron/fee-reminders.php` |

**Admissions flow:** applicant submits `admission-apply` → admin reviews in
`admin/admissions` → **Approve** enrols them (`admission_enroll()`), assigns a
student code + QR, and can create a linked Member login (emails a set-password
link). Certificates and marksheets carry a serial + QR that resolve at the
public **`/verify-award?serial=…`** page; ID cards resolve at
**`/verify-student?token=…`**.

**Certificates & marksheets** are viewable/printable by admins and by the
student who owns them (guard `student_portal_or_admin_guard()`), then
print-to-PDF from the browser.

---

## 3. LMS (5.7)

| Feature | Where |
|---|---|
| **Course creation** (category, thumbnail, objectives, prerequisites) | `admin/courses.php` |
| **Video lessons** (YouTube/Vimeo embed or upload) + **PDF resources** | `admin/course-lessons.php` |
| **Online exams** + **quizzes** (question bank, auto-grading) | `admin/exams.php`, `admin/exam-questions.php`, `admin/exam-attempts.php` |
| **Certificate generator** (auto-issue on completion, templates) | course setting + `student_certificates` |
| **Progress tracking** (per-lesson %, completion) | `lesson_progress`, `learn.php` |
| **Batch management** (seats, dates) | `admin/course-batches.php` |
| **Enrolments** | `admin/course-enrollments.php`, self-enrol on `course.php` |
| **Live class integration** (Zoom / Google Meet links) | `admin/live-sessions.php` |

**Grading:** MCQ and True/False auto-grade instantly; short answers are flagged
`needs_review` and graded by a teacher in `admin/exam-attempts.php`. Completing
all lessons of a course sets the enrolment to *completed* and (if the course has
certificates enabled) auto-issues a completion certificate.

**Student portal** (Member login → `/account` → *My Learning*):
- `my-learning.php` — enrolled courses + progress + notices
- `learn.php` — lesson player (video/PDF/text), mark-complete, resources, live classes, exams
- `take-exam.php` — timed exam/quiz with auto-grading
- `my-assignments.php` — submit + track grades
- `my-awards.php` — certificates + marksheets (download + verify)
- `student-id-card.php` — the student's own ID card

Access is always scoped to the signed-in member's own student record / enrolments.

---

## 4. Settings & cron

- Settings (group `student`): `admission_prefix` (ADM), `certificate_prefix`
  (CERT), `marksheet_prefix` (MS), `student_portal_enabled`.
- **Fee due reminders:** `cron/fee-reminders.php` (run weekly) emails/SMS
  guardians of students with outstanding balances. CLI or token URL
  (`/cron/fee-reminders?token=<membership cron token>`).

Video upload is capped by `UPLOAD_MAX_BYTES` (5 MB) — prefer YouTube/Vimeo
embeds for lessons; increase the limit in `config.php` for direct uploads.
