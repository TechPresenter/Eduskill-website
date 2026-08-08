<?php
/**
 * =============================================================================
 *  LMS (Course Management) domain layer
 * =============================================================================
 *  Enrolment, per-lesson progress rollup, exam/quiz auto-grading (MCQ + T/F
 *  automatic, short-answer flagged for manual review) and course completion →
 *  certificate. Loaded by bootstrap.php.
 * =============================================================================
 */

declare(strict_types=1);

/* =============================================================================
 |  ENROLMENT
 |============================================================================*/

/** Find an existing enrolment for a member or student on a course. */
function lms_find_enrollment(int $courseId, ?int $memberId = null, ?int $studentId = null): ?array
{
    if ($memberId) {
        $e = db_row('SELECT * FROM course_enrollments WHERE course_id = :c AND member_id = :m LIMIT 1', [':c' => $courseId, ':m' => $memberId]);
        if ($e) { return $e; }
    }
    if ($studentId) {
        return db_row('SELECT * FROM course_enrollments WHERE course_id = :c AND student_id = :s LIMIT 1', [':c' => $courseId, ':s' => $studentId]);
    }
    return null;
}

/** Enrol a member/student into a course (idempotent). Returns the enrolment row. */
function lms_enroll(int $courseId, array $opts = []): ?array
{
    if (!find('courses', $courseId)) {
        return null;
    }
    $memberId  = (int) ($opts['member_id'] ?? 0) ?: null;
    $studentId = (int) ($opts['student_id'] ?? 0) ?: null;

    // Cross-fill so an enrolment carries both ids when the student is linked to
    // a member account — the portal resolves enrolments by member_id.
    if ($studentId && !$memberId) {
        $st = find('school_students', $studentId);
        if ($st && !empty($st['member_id'])) { $memberId = (int) $st['member_id']; }
    }
    if ($memberId && !$studentId) {
        $st = db_row('SELECT id FROM school_students WHERE member_id = :m LIMIT 1', [':m' => $memberId]);
        if ($st) { $studentId = (int) $st['id']; }
    }

    $existing = lms_find_enrollment($courseId, $memberId, $studentId);
    if ($existing) {
        return $existing;
    }
    $id = db_insert('course_enrollments', [
        'course_id'   => $courseId,
        'batch_id'    => (int) ($opts['batch_id'] ?? 0) ?: null,
        'student_id'  => $studentId,
        'member_id'   => $memberId,
        'enrolled_at' => date('Y-m-d H:i:s'),
        'status'      => 'active',
    ]);
    return find('course_enrollments', $id);
}

/* =============================================================================
 |  PROGRESS
 |============================================================================*/

function lms_course_lesson_count(int $courseId): int
{
    return db_count('course_lessons', 'course_id = :c', [':c' => $courseId]);
}

/**
 * Upsert a lesson's progress for an enrolment, then recompute the course %.
 * $completed marks the lesson done (100%).
 */
function lms_set_lesson_progress(int $enrollmentId, int $lessonId, float $percent = 0.0, int $position = 0, bool $completed = false): void
{
    $percent = max(0.0, min(100.0, $percent));
    $status  = $completed || $percent >= 100 ? 'completed' : 'in_progress';
    if ($completed) { $percent = 100.0; }

    $existing = db_row('SELECT id FROM lesson_progress WHERE enrollment_id = :e AND lesson_id = :l LIMIT 1', [':e' => $enrollmentId, ':l' => $lessonId]);
    if ($existing) {
        db_update('lesson_progress', [
            'status'           => $status,
            'progress_percent' => $percent,
            'last_position'    => $position,
        ], 'id = :id', [':id' => (int) $existing['id']]);
    } else {
        db_insert('lesson_progress', [
            'enrollment_id'    => $enrollmentId,
            'lesson_id'        => $lessonId,
            'status'           => $status,
            'progress_percent' => $percent,
            'last_position'    => $position,
        ]);
    }
    lms_recompute_progress($enrollmentId);
}

/** Recompute an enrolment's overall % from completed lessons; auto-complete at 100%. */
function lms_recompute_progress(int $enrollmentId): void
{
    $enr = find('course_enrollments', $enrollmentId);
    if (!$enr) {
        return;
    }
    $total = lms_course_lesson_count((int) $enr['course_id']);
    $done  = (int) db_value(
        "SELECT COUNT(*) FROM lesson_progress WHERE enrollment_id = :e AND status = 'completed'",
        [':e' => $enrollmentId]
    );
    $percent = $total > 0 ? round(($done / $total) * 100, 2) : 0.0;
    db_update('course_enrollments', ['progress_percent' => $percent], 'id = :id', [':id' => $enrollmentId]);

    // Only an ACTIVE enrolment may auto-complete — a dropped/completed one must
    // not be re-completed (which would re-issue a certificate).
    if ($total > 0 && $done >= $total && $enr['status'] === 'active') {
        lms_complete_enrollment($enrollmentId);
    }
}

/** Mark an enrolment complete and issue a certificate when eligible. */
function lms_complete_enrollment(int $enrollmentId): void
{
    $enr = find('course_enrollments', $enrollmentId);
    if (!$enr || $enr['status'] === 'completed') {
        return;
    }
    db_update('course_enrollments', [
        'status'       => 'completed',
        'completed_at' => date('Y-m-d H:i:s'),
        'progress_percent' => 100,
    ], 'id = :id', [':id' => $enrollmentId]);

    $course = find('courses', (int) $enr['course_id']);
    if ($course && !empty($course['certificate_enabled']) && !empty($enr['student_id']) && empty($enr['certificate_id'])
        && function_exists('certificate_issue')) {
        $certId = certificate_issue((int) $enr['student_id'], [
            'course_id' => (int) $course['id'],
            'type'      => 'completion',
            'title'     => 'Certificate of Completion — ' . $course['title'],
            'template'  => $course['certificate_template'] ?: 'classic',
        ]);
        if ($certId) {
            db_update('course_enrollments', ['certificate_id' => $certId], 'id = :id', [':id' => $enrollmentId]);
        }
    }
}

/* =============================================================================
 |  EXAMS / QUIZZES
 |============================================================================*/

/** Attempts a member/student has already made on an exam. */
function exam_attempt_count(int $examId, ?int $memberId, ?int $studentId): int
{
    if ($memberId) {
        return (int) db_value('SELECT COUNT(*) FROM exam_attempts WHERE exam_id = :e AND member_id = :m', [':e' => $examId, ':m' => $memberId]);
    }
    if ($studentId) {
        return (int) db_value('SELECT COUNT(*) FROM exam_attempts WHERE exam_id = :e AND student_id = :s', [':e' => $examId, ':s' => $studentId]);
    }
    return 0;
}

/** Start an attempt if within the allowed limit. Returns [ok, attempt|message]. */
function exam_start(int $examId, ?int $memberId = null, ?int $studentId = null): array
{
    $exam = find('exams', $examId);
    if (!$exam || $exam['status'] !== 'published') {
        return ['ok' => false, 'message' => 'This exam is not available.'];
    }
    $now = time();
    if (!empty($exam['available_from']) && strtotime((string) $exam['available_from']) > $now) {
        return ['ok' => false, 'message' => 'This exam has not opened yet.'];
    }
    if (!empty($exam['available_to']) && strtotime((string) $exam['available_to']) < $now) {
        return ['ok' => false, 'message' => 'This exam has closed.'];
    }
    if (exam_attempt_count($examId, $memberId, $studentId) >= (int) $exam['max_attempts']) {
        return ['ok' => false, 'message' => 'You have used all your attempts.'];
    }
    $id = db_insert('exam_attempts', [
        'exam_id'    => $examId,
        'member_id'  => $memberId,
        'student_id' => $studentId,
        'started_at' => date('Y-m-d H:i:s'),
        'status'     => 'in_progress',
    ]);
    return ['ok' => true, 'attempt' => find('exam_attempts', $id)];
}

/** Normalise a stored/posted answer to a comparable form. */
function exam_norm($v): array
{
    if (is_array($v)) {
        $out = array_map(static fn($x) => (string) $x, $v);
    } elseif ($v === null || $v === '') {
        $out = [];
    } else {
        $out = [(string) $v];
    }
    sort($out);
    return $out;
}

/**
 * Grade a submitted attempt. Auto-grades MCQ + True/False; short-answer is left
 * for manual review (0 until graded, needs_review flagged). Returns the score.
 */
function exam_grade(int $attemptId): array
{
    $attempt = find('exam_attempts', $attemptId);
    if (!$attempt) {
        return ['score' => 0, 'total' => 0, 'passed' => false];
    }
    $exam      = find('exams', (int) $attempt['exam_id']);
    $questions = db_all('SELECT * FROM exam_questions WHERE exam_id = :e', [':e' => (int) $attempt['exam_id']]);
    $answers   = json_column($attempt['answers']);

    $score = 0.0; $total = 0.0; $needsReview = false;
    foreach ($questions as $q) {
        $marks = (float) $q['marks'];
        $total += $marks;
        $given = $answers[$q['id']] ?? ($answers[(string) $q['id']] ?? null);

        if ($q['type'] === 'short') {
            $needsReview = true; // manual grading
            continue;
        }
        $correctRaw = json_decode((string) $q['correct'], true);
        if ($correctRaw === null && $q['correct'] !== null && $q['correct'] !== '') {
            $correctRaw = $q['correct']; // stored as plain string
        }
        if (exam_norm($given) === exam_norm($correctRaw)) {
            $score += $marks;
        }
    }

    $pass    = (float) ($exam['pass_marks'] ?? 0);
    $passed  = !$needsReview ? ($score >= $pass) : null;
    db_update('exam_attempts', [
        'score'        => $score,
        'total'        => $total,
        'passed'       => $passed === null ? null : ($passed ? 1 : 0),
        'auto_graded'  => $needsReview ? 0 : 1,
        'needs_review' => $needsReview ? 1 : 0,
        'status'       => $needsReview ? 'submitted' : 'graded',
    ], 'id = :id', [':id' => $attemptId]);

    return ['score' => $score, 'total' => $total, 'passed' => $passed, 'needs_review' => $needsReview];
}

/** Save answers + submit + grade an attempt. */
function exam_submit(int $attemptId, array $answers): array
{
    $attempt = find('exam_attempts', $attemptId);
    if (!$attempt || $attempt['status'] !== 'in_progress') {
        return ['score' => 0, 'total' => 0, 'passed' => false];
    }
    db_update('exam_attempts', [
        'answers'      => json_encode($answers, JSON_UNESCAPED_UNICODE),
        'submitted_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', [':id' => $attemptId]);
    return exam_grade($attemptId);
}

/** Recompute an exam's total_marks from its question bank. */
function exam_sync_total(int $examId): void
{
    $total = (float) db_value('SELECT COALESCE(SUM(marks),0) FROM exam_questions WHERE exam_id = :e', [':e' => $examId]);
    db_update('exams', ['total_marks' => $total], 'id = :id', [':id' => $examId]);
}

/* =============================================================================
 |  STATUS PILLS
 |============================================================================*/

function course_status_pill(string $s): string
{
    return ['published' => 'pill-green', 'draft' => 'pill-amber', 'archived' => 'pill-gray'][$s] ?? 'pill-gray';
}

function exam_status_pill(string $s): string
{
    return ['published' => 'pill-green', 'draft' => 'pill-amber', 'closed' => 'pill-red'][$s] ?? 'pill-gray';
}

/** All courses for dropdowns: [id => title]. */
function course_options(bool $publishedOnly = false): array
{
    $where = $publishedOnly ? "WHERE status = 'published'" : '';
    $out = [];
    foreach (db_all("SELECT id, title FROM courses $where ORDER BY title") as $r) {
        $out[(int) $r['id']] = $r['title'];
    }
    return $out;
}
