<?php
/** Internships listing + enquiry form. */
require __DIR__ . '/includes/config.php';
$page_title = 'Internships';
$items = db_all("SELECT * FROM internships WHERE deleted_at IS NULL AND is_active = 1 ORDER BY (deadline IS NULL), deadline ASC, id DESC");
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
      <span class="eyebrow">Learn by doing</span>
      <h1 class="section-heading">Internships</h1>
      <p class="section-subheading mx-auto">Gain hands-on experience in the social sector while making a real difference.</p>
    </div>
    <?php if ($items === []): ?>
      <div class="rounded-card border border-dashed border-edge p-12 text-center text-content-muted">Internship openings will be listed here soon.</div>
    <?php else: ?>
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <?php foreach ($items as $i => $s): ?>
          <div class="flex flex-col rounded-card border border-edge bg-surface p-6 shadow-card" data-aos="fade-up" data-aos-delay="<?= ($i % 2) * 100 ?>">
            <h3 class="font-display text-lg font-semibold text-content"><?= e($s['title']) ?></h3>
            <div class="mt-2 flex flex-wrap gap-3 text-xs text-content-muted">
              <?php if (!empty($s['duration'])): ?><span><i class="fa-regular fa-clock mr-1"></i><?= e($s['duration']) ?></span><?php endif; ?>
              <?php if (!empty($s['location'])): ?><span><i class="fa-solid fa-location-dot mr-1"></i><?= e($s['location']) ?></span><?php endif; ?>
            </div>
            <?php if (!empty($s['summary'])): ?><p class="mt-3 text-sm leading-relaxed text-content-muted"><?= e($s['summary']) ?></p><?php endif; ?>
            <div class="mt-auto flex items-center justify-between pt-4">
              <?php if (!empty($s['deadline'])): ?><span class="text-xs font-medium text-danger-600">Apply by <?= e(date('d M Y', strtotime((string) $s['deadline']))) ?></span><?php else: ?><span></span><?php endif; ?>
              <a href="#apply" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-on-brand hover:bg-brand-700">Apply</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div id="apply" class="mx-auto mt-16 max-w-2xl">
      <h2 class="section-heading text-center text-2xl">Apply for an internship</h2>
      <p class="mt-2 text-center text-sm text-content-muted">Interns receive a certificate on successful completion.</p>
      <form id="internship-form" class="mt-6 rounded-card border border-edge bg-surface p-6 shadow-card">
        <?= csrf_field() ?>
        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div><label class="block text-sm font-medium text-content">Full name</label><input name="name" required class="esk-input"></div>
          <div><label class="block text-sm font-medium text-content">Email</label><input type="email" name="email" required class="esk-input"></div>
          <div><label class="block text-sm font-medium text-content">Phone</label><input name="phone" class="esk-input"></div>
          <div><label class="block text-sm font-medium text-content">Internship</label>
            <select name="subject" class="esk-input">
              <option value="Internship enquiry">General enquiry</option>
              <?php foreach ($items as $s): ?><option value="Internship: <?= e($s['title']) ?>"><?= e($s['title']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mt-4"><label class="block text-sm font-medium text-content">Tell us about yourself</label><textarea name="message" rows="4" required class="esk-input" placeholder="Your background, skills, and availability."></textarea></div>
        <button type="submit" class="mt-5 w-full rounded-lg bg-brand-600 px-5 py-3 text-sm font-semibold text-on-brand transition hover:bg-brand-700">Submit application</button>
      </form>
    </div>
  </div>
</section>
<style>.esk-input{margin-top:.35rem;width:100%;border-radius:.625rem;border:1px solid rgb(var(--edge));background:rgb(var(--surface));padding:.6rem .75rem;font-size:.875rem;color:rgb(var(--content))}.esk-input:focus{outline:none;border-color:rgb(var(--brand-500));box-shadow:0 0 0 3px rgb(var(--brand-500)/.2)}</style>
<?php
$page_scripts = '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>'
  . '<script>(function(){var f=document.getElementById("internship-form");if(!f)return;f.addEventListener("submit",function(e){e.preventDefault();var b=f.querySelector("[type=submit]");b.disabled=true;'
  . 'fetch("' . e(url('api/contact.php')) . '",{method:"POST",headers:{Accept:"application/json"},body:new FormData(f)}).then(function(r){return r.json();}).then(function(d){b.disabled=false;'
  . 'if(d.ok){f.reset();Swal.fire({icon:"success",title:"Thank you!",text:d.message||"Your application has been received.",confirmButtonColor:"#4f46e5"});}'
  . 'else{Swal.fire({icon:"error",title:"Oops",text:d.message||"Something went wrong.",confirmButtonColor:"#4f46e5"});}}).catch(function(){b.disabled=false;Swal.fire({icon:"error",title:"Network error",text:"Please try again.",confirmButtonColor:"#4f46e5"});});});})();</script>';
require __DIR__ . '/includes/footer.php';
