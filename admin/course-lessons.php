<?php
/**
 * =============================================================================
 *  Admin — Course Lessons & Resources (curriculum builder for one course)
 * =============================================================================
 *  Scoped to a single parent course via ?course=<id> (required on every entry
 *  point — if the course is missing we flash + redirect to the course list).
 *
 *  Two things live on this page, both keyed on the same course context:
 *    - LESSONS  : list (ordered by sort_order, grouped by module) + a full
 *                 create/edit form (?action=create|edit). Types: video / pdf /
 *                 text / live. Video embeds (YouTube/Vimeo) are primary; an
 *                 optional direct upload (mp4/webm, 5 MB) is also accepted.
 *    - RESOURCES: a downloadable-files panel (course_resources) with an inline
 *                 add form + delete (removes the stored upload too).
 *
 *  Standard admin pattern: CSRF-guarded POST handlers keyed on _do, prepared
 *  statements only, escaping on every echo, activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

/* -------------------------------------------------------------- CONTEXT */
$courseId = (int) (get('course') ?: post('course', 0));
$course   = $courseId ? find('courses', $courseId) : null;
if (!$course) {
    set_flash('error', 'Course not found.');
    redirect(admin_url('courses'));
}

$action = get('action', 'list');
$id     = (int) get('id', 0);

$base = 'course-lessons?course=' . $courseId; // list/self path (context preserved)
$self = admin_url($base);

$lessonTypes    = ['video' => 'Video', 'pdf' => 'PDF / Document', 'text' => 'Text', 'live' => 'Live session'];
$videoProviders = ['youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'upload' => 'Direct upload'];

$typePill = static fn(string $t): string =>
    ['video' => 'pill-blue', 'pdf' => 'pill-violet', 'text' => 'pill-gray', 'live' => 'pill-amber'][$t] ?? 'pill-gray';

/* =============================================================================
 |  SAVE LESSON (create / edit)
 |============================================================================*/
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $old    = $editId ? find('course_lessons', $editId) : null;
    if ($editId && (!$old || (int) $old['course_id'] !== $courseId)) {
        set_flash('error', 'Lesson not found for this course.');
        redirect($self);
    }

    $title   = clean(post('title'));
    $formRet = admin_url($base . '&action=' . ($editId ? 'edit&id=' . $editId : 'create'));

    if ($title === '') {
        flash_old($_POST);
        set_flash('error', 'Lesson title is required.');
        redirect($formRet);
    }

    $type     = isset($lessonTypes[(string) post('type')]) ? post('type') : 'video';
    $provider = isset($videoProviders[(string) post('video_provider')]) ? post('video_provider') : null;

    $data = [
        'course_id'      => $courseId,
        'module'         => clean(post('module')) ?: null,
        'title'          => $title,
        'type'           => $type,
        'video_provider' => $type === 'video' ? $provider : null,
        'video_url'      => clean(post('video_url')) ?: null,
        'content'        => trim((string) post('content')) ?: null,
        'duration_min'   => (int) post('duration_min') ?: null,
        'is_preview'     => !empty(post('is_preview')) ? 1 : 0,
        'sort_order'     => (int) post('sort_order', 0),
    ];

    // Optional PDF / document upload (kept unless replaced).
    if (!empty($_FILES['pdf_file']['name'])) {
        $up = upload_document($_FILES['pdf_file'], 'courses', ['allowed' => 'pdf']);
        if (!$up['success']) {
            flash_old($_POST);
            set_flash('error', 'PDF: ' . $up['error']);
            redirect($formRet);
        }
        $data['pdf_file'] = $up['path'];
        if ($editId && !empty($old['pdf_file'])) { delete_upload($old['pdf_file']); }
    }

    // Optional direct video upload (mp4/webm, capped at UPLOAD_MAX_BYTES = 5 MB).
    if (!empty($_FILES['video_file']['name'])) {
        $up = upload_file($_FILES['video_file'], 'courses', ['allowed' => 'mp4,webm']);
        if (!$up['success']) {
            flash_old($_POST);
            set_flash('error', 'Video: ' . $up['error']);
            redirect($formRet);
        }
        $data['video_file'] = $up['path'];
        if ($editId && !empty($old['video_file'])) { delete_upload($old['video_file']); }
    }

    if ($editId) {
        db_update('course_lessons', $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'courses', 'Updated lesson #' . $editId . ' in course #' . $courseId);
        set_flash('success', 'Lesson updated.');
    } else {
        $newId = db_insert('course_lessons', $data);
        log_activity('create', 'courses', 'Added lesson #' . $newId . ' to course #' . $courseId);
        set_flash('success', 'Lesson added.');
    }
    redirect($self);
}

/* =============================================================================
 |  DELETE LESSON
 |============================================================================*/
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find('course_lessons', $delId);
    if ($row && (int) $row['course_id'] === $courseId) {
        if (!empty($row['pdf_file']))   { delete_upload($row['pdf_file']); }
        if (!empty($row['video_file'])) { delete_upload($row['video_file']); }
        db_delete('course_lessons', 'id = :id', [':id' => $delId]); // progress rows cascade
        log_activity('delete', 'courses', 'Deleted lesson #' . $delId . ' from course #' . $courseId);
        set_flash('success', 'Lesson deleted.');
    }
    redirect($self);
}

/* =============================================================================
 |  ADD RESOURCE (course_resources)
 |============================================================================*/
if (is_post() && post('_do') === 'res_add') {
    require_csrf();
    $title = clean(post('title'));
    if ($title === '') {
        flash_old($_POST);
        set_flash('error', 'Resource title is required.');
        redirect($self);
    }
    if (empty($_FILES['file']['name'])) {
        flash_old($_POST);
        set_flash('error', 'Please choose a file to attach.');
        redirect($self);
    }
    $up = upload_document($_FILES['file'], 'courses');
    if (!$up['success']) {
        flash_old($_POST);
        set_flash('error', 'File: ' . $up['error']);
        redirect($self);
    }

    // lesson_id (optional) must belong to this course.
    $lessonId = (int) post('lesson_id', 0) ?: null;
    if ($lessonId) {
        $ok = db_value(
            'SELECT id FROM course_lessons WHERE id = :l AND course_id = :c LIMIT 1',
            [':l' => $lessonId, ':c' => $courseId]
        );
        if (!$ok) { $lessonId = null; }
    }

    $resId = db_insert('course_resources', [
        'course_id'    => $courseId,
        'lesson_id'    => $lessonId,
        'title'        => $title,
        'file_path'    => $up['path'],
        'downloadable' => !empty(post('downloadable')) ? 1 : 0,
        'watermark'    => !empty(post('watermark')) ? 1 : 0,
    ]);
    log_activity('create', 'courses', 'Added resource #' . $resId . ' to course #' . $courseId);
    set_flash('success', 'Resource added.');
    redirect($self);
}

/* =============================================================================
 |  DELETE RESOURCE
 |============================================================================*/
if (is_post() && post('_do') === 'res_delete') {
    require_csrf();
    $resId = (int) post('id', 0);
    $row   = find('course_resources', $resId);
    if ($row && (int) $row['course_id'] === $courseId) {
        if (!empty($row['file_path'])) { delete_upload($row['file_path']); }
        db_delete('course_resources', 'id = :id', [':id' => $resId]);
        log_activity('delete', 'courses', 'Deleted resource #' . $resId . ' from course #' . $courseId);
        set_flash('success', 'Resource deleted.');
    }
    redirect($self);
}

/* =============================================================================
 |  LESSON FORM (create / edit)
 |============================================================================*/
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find('course_lessons', $id) : [];
    if ($action === 'edit' && (!$row || (int) $row['course_id'] !== $courseId)) {
        set_flash('error', 'Lesson not found for this course.');
        redirect($self);
    }

    $nextSort = (int) db_value(
        'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM course_lessons WHERE course_id = :c',
        [':c' => $courseId]
    );

    $o          = static fn(string $k, $d = '') => e(old($k, $row[$k] ?? $d));
    $curType    = (string) old('type', $row['type'] ?? 'video');
    $curProv    = (string) old('video_provider', $row['video_provider'] ?? 'youtube');
    $page_title = $action === 'edit' ? 'Edit Lesson' : 'Add Lesson';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted"><?= e($course['title']) ?> / <?= $action === 'edit' ? 'Edit lesson' : 'New lesson' ?></span></div>
        <a class="btn btn-secondary" href="<?= e($self) ?>">← Back to curriculum</a>
    </div>

    <form class="admin-form" method="post" action="<?= e($self) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="course" value="<?= (int) $courseId ?>">
        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">Lesson details</h3>

            <div class="grid-2">
                <div class="form-group"><label class="form-label">Title <span class="req">*</span></label>
                    <input class="form-control" name="title" required value="<?= $o('title') ?>" placeholder="e.g. Introduction to Fractions"></div>
                <div class="form-group"><label class="form-label">Module</label>
                    <input class="form-control" name="module" value="<?= $o('module') ?>" placeholder="e.g. Unit 1 — Basics">
                    <small class="form-hint">Lessons are grouped by module on the curriculum list.</small></div>
            </div>

            <div class="grid-3">
                <div class="form-group"><label class="form-label">Type</label>
                    <select class="form-select" name="type" id="lessonType">
                        <?php foreach ($lessonTypes as $k => $l): ?>
                            <option value="<?= e($k) ?>" <?= $curType === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">Duration (min)</label>
                    <input class="form-control" type="number" min="0" name="duration_min" value="<?= $o('duration_min') ?>" placeholder="e.g. 12"></div>
                <div class="form-group"><label class="form-label">Sort order</label>
                    <input class="form-control" type="number" name="sort_order" value="<?= e(old('sort_order', $row['sort_order'] ?? $nextSort)) ?>"></div>
            </div>

            <!-- Video (embed is primary; direct upload optional) -->
            <div class="lesson-field" data-when="video">
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Video provider</label>
                        <select class="form-select" name="video_provider">
                            <?php foreach ($videoProviders as $k => $l): ?>
                                <option value="<?= e($k) ?>" <?= $curProv === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Video URL / embed</label>
                        <input class="form-control" name="video_url" value="<?= $o('video_url') ?>" placeholder="https://youtu.be/… or https://vimeo.com/…"></div>
                </div>
                <div class="form-group"><label class="form-label">Direct video file (optional)</label>
                    <input class="form-control" type="file" name="video_file" accept="video/mp4,video/webm">
                    <small class="form-hint">mp4 or webm, max <?= e(human_filesize(UPLOAD_MAX_BYTES)) ?>. An embed URL above is the primary method.<?php if (!empty($row['video_file'])): ?> Current file kept unless replaced.<?php endif; ?></small>
                </div>
            </div>

            <!-- PDF / document -->
            <div class="lesson-field" data-when="pdf">
                <div class="form-group"><label class="form-label">PDF file</label>
                    <input class="form-control" type="file" name="pdf_file" accept="application/pdf">
                    <small class="form-hint">PDF only, max <?= e(human_filesize(UPLOAD_MAX_BYTES)) ?>.<?php if (!empty($row['pdf_file'])): ?> Current: <a href="<?= e(upload_url($row['pdf_file'])) ?>" target="_blank" rel="noopener">view</a> — kept unless replaced.<?php endif; ?></small>
                </div>
            </div>

            <!-- Text lesson body -->
            <div class="lesson-field" data-when="text">
                <div class="form-group"><label class="form-label">Content</label>
                    <textarea class="form-textarea" name="content" data-wysiwyg style="min-height:160px;" placeholder="Lesson text / notes…"><?= e(old('content', $row['content'] ?? '')) ?></textarea></div>
            </div>

            <div class="form-group">
                <label class="checkbox"><input type="checkbox" name="is_preview" value="1" <?= !empty(old('is_preview', $row['is_preview'] ?? 0)) ? 'checked' : '' ?>> Free preview (viewable without enrolment)</label>
            </div>

            <div class="form-actions mt-3">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Add' ?> lesson</button>
                <a class="btn btn-ghost" href="<?= e($self) ?>">Cancel</a>
            </div>
        </div></div>
    </form>

    <script>
    (function () {
        var sel = document.getElementById('lessonType');
        if (!sel) { return; }
        function sync() {
            var t = sel.value;
            document.querySelectorAll('.lesson-field').forEach(function (el) {
                el.style.display = (el.getAttribute('data-when') === t) ? '' : 'none';
            });
        }
        sel.addEventListener('change', sync);
        sync();
    })();
    </script>
    <?php
    clear_old();
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* =============================================================================
 |  MAIN VIEW — curriculum (lessons) + resources
 |============================================================================*/
$lessons = db_all(
    'SELECT * FROM course_lessons WHERE course_id = :c ORDER BY sort_order ASC, id ASC',
    [':c' => $courseId]
);
$resources = db_all(
    'SELECT r.*, l.title AS lesson_title
       FROM course_resources r
       LEFT JOIN course_lessons l ON l.id = r.lesson_id
      WHERE r.course_id = :c
      ORDER BY r.id DESC',
    [':c' => $courseId]
);

$lessonCount  = count($lessons);
$previewCount = 0;
$totalMin     = 0;
foreach ($lessons as $lRow) {
    if (!empty($lRow['is_preview'])) { $previewCount++; }
    $totalMin += (int) $lRow['duration_min'];
}

$page_title = 'Curriculum · ' . $course['title'];
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div>
        <h1><?= e($course['title']) ?></h1>
        <span class="muted">Curriculum · <?= (int) $lessonCount ?> lesson<?= $lessonCount === 1 ? '' : 's' ?> · <?= (int) count($resources) ?> resource<?= count($resources) === 1 ? '' : 's' ?></span>
    </div>
    <div class="flex" style="gap:.5rem;">
        <a class="btn btn-secondary" href="<?= e(admin_url('courses')) ?>">← Courses</a>
        <a class="btn btn-primary" href="<?= e(admin_url($base . '&action=create')) ?>">+ Add Lesson</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('book-open') ?></div><div><div class="stat-value"><?= (int) $lessonCount ?></div><div class="stat-label">Lessons</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('eye') ?></div><div><div class="stat-value"><?= (int) $previewCount ?></div><div class="stat-label">Free previews</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('clock') ?></div><div><div class="stat-value"><?= (int) $totalMin ?></div><div class="stat-label">Total minutes</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('paperclip') ?></div><div><div class="stat-value"><?= (int) count($resources) ?></div><div class="stat-label">Resources</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="flex items-center justify-between mb-3">
        <h3 style="margin:0;">Lessons</h3>
        <a class="btn btn-outline btn-sm" href="<?= e(admin_url($base . '&action=create')) ?>"><?= lucide('plus') ?> Add lesson</a>
    </div>

    <?php if ($lessons): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th style="width:56px;">#</th><th>Lesson</th><th>Type</th><th>Duration</th><th>Preview</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($lessons as $r): ?>
            <tr>
                <td><span class="text-muted"><?= (int) $r['sort_order'] ?></span></td>
                <td>
                    <strong><?= e($r['title']) ?></strong>
                    <?php if (!empty($r['module'])): ?><br><small class="text-muted"><?= e($r['module']) ?></small><?php endif; ?>
                </td>
                <td><span class="pill <?= e($typePill($r['type'])) ?>"><?= e($lessonTypes[$r['type']] ?? $r['type']) ?></span></td>
                <td><?= $r['duration_min'] ? (int) $r['duration_min'] . ' min' : '<span class="text-muted">—</span>' ?></td>
                <td><?= !empty($r['is_preview']) ? '<span class="pill pill-green">Preview</span>' : '<span class="text-muted">—</span>' ?></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e(admin_url($base . '&action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e($self) ?>" data-confirm="Delete this lesson? Any learner progress on it will also be removed." style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('book-open') ?></div>
            No lessons yet. <a href="<?= e(admin_url($base . '&action=create')) ?>">Add the first one</a>.</div>
    <?php endif; ?>
</div></div>

<div class="panel"><div class="panel-body">
    <h3 class="mb-3">Resources</h3>

    <form class="admin-form" method="post" action="<?= e($self) ?>" enctype="multipart/form-data" style="margin-bottom:1.25rem;">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="res_add">
        <input type="hidden" name="course" value="<?= (int) $courseId ?>">
        <div class="grid-3">
            <div class="form-group"><label class="form-label">Title <span class="req">*</span></label>
                <input class="form-control" name="title" required value="<?= e(old('title')) ?>" placeholder="e.g. Worksheet 1"></div>
            <div class="form-group"><label class="form-label">File <span class="req">*</span></label>
                <input class="form-control" type="file" name="file" required>
                <small class="form-hint">Max <?= e(human_filesize(UPLOAD_MAX_BYTES)) ?>.</small></div>
            <div class="form-group"><label class="form-label">Attach to lesson</label>
                <select class="form-select" name="lesson_id">
                    <option value="">— Whole course —</option>
                    <?php foreach ($lessons as $lRow): ?>
                        <option value="<?= (int) $lRow['id'] ?>" <?= (int) old('lesson_id', 0) === (int) $lRow['id'] ? 'selected' : '' ?>><?= e($lRow['title']) ?></option>
                    <?php endforeach; ?>
                </select></div>
        </div>
        <div class="flex items-center" style="gap:1.5rem;flex-wrap:wrap;">
            <label class="checkbox"><input type="checkbox" name="downloadable" value="1" <?= old('downloadable', 1) ? 'checked' : '' ?>> Allow download</label>
            <label class="checkbox"><input type="checkbox" name="watermark" value="1" <?= !empty(old('watermark', 0)) ? 'checked' : '' ?>> Apply watermark</label>
            <button class="btn btn-primary btn-sm" type="submit"><?= lucide('upload') ?> Add resource</button>
        </div>
    </form>

    <?php if ($resources): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Resource</th><th>Lesson</th><th>Download</th><th>Watermark</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($resources as $r): ?>
            <tr>
                <td><a href="<?= e(upload_url($r['file_path'])) ?>" target="_blank" rel="noopener"><strong><?= e($r['title']) ?></strong></a></td>
                <td><?= $r['lesson_title'] ? e($r['lesson_title']) : '<span class="text-muted">Whole course</span>' ?></td>
                <td><?= !empty($r['downloadable']) ? '<span class="pill pill-green">Yes</span>' : '<span class="pill pill-gray">No</span>' ?></td>
                <td><?= !empty($r['watermark']) ? '<span class="pill pill-amber">On</span>' : '<span class="text-muted">—</span>' ?></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e(upload_url($r['file_path'])) ?>" target="_blank" rel="noopener" title="Open"><?= lucide('external-link') ?></a>
                    <form method="post" action="<?= e($self) ?>" data-confirm="Delete this resource and its file?" style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="res_delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('paperclip') ?></div>No resources uploaded yet.</div>
    <?php endif; ?>
</div></div>

<?php
clear_old();
include __DIR__ . '/partials/foot.php';
