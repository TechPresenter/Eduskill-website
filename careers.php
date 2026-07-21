<?php
/** Careers — open positions + application form. */
require __DIR__ . '/includes/config.php';
$page_title = 'Careers';
$jobs = db_all("SELECT * FROM jobs WHERE deleted_at IS NULL AND is_active = 1 ORDER BY (deadline IS NULL), deadline ASC, id DESC");
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
      <span class="eyebrow">Join the team</span>
      <h1 class="section-heading">Careers</h1>
      <p class="section-subheading mx-auto">Build your career while building stronger communities. Explore our current openings.</p>
    </div>
    <?php if ($jobs === []): ?>
      <div class="rounded-card border border-dashed border-edge p-12 text-center text-content-muted">There are no open positions right now. Send us your CV using the form below and we'll keep you in mind.</div>
    <?php else: ?>
      <div class="mx-auto max-w-3xl space-y-4">
        <?php foreach ($jobs as $i => $j): ?>
          <div class="flex flex-col gap-3 rounded-card border border-edge bg-surface p-6 shadow-card sm:flex-row sm:items-center sm:justify-between" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 80 ?>">
            <div>
              <h3 class="font-display text-lg font-semibold text-content"><?= e($j['title']) ?></h3>
              <div class="mt-1 flex flex-wrap gap-3 text-xs text-content-muted">
                <?php if (!empty($j['department'])): ?><span><i class="fa-solid fa-sitemap mr-1"></i><?= e($j['department']) ?></span><?php endif; ?>
                <?php if (!empty($j['location'])): ?><span><i class="fa-solid fa-location-dot mr-1"></i><?= e($j['location']) ?></span><?php endif; ?>
                <?php if (!empty($j['employment_type'])): ?><span><i class="fa-regular fa-clock mr-1"></i><?= e($j['employment_type']) ?></span><?php endif; ?>
              </div>
              <?php if (!empty($j['summary'])): ?><p class="mt-2 max-w-xl text-sm text-content-muted"><?= e($j['summary']) ?></p><?php endif; ?>
            </div>
            <a href="#apply" class="shrink-0 rounded-lg bg-brand-600 px-4 py-2 text-center text-sm font-semibold text-on-brand hover:bg-brand-700">Apply now</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div id="apply" class="mx-auto mt-16 max-w-2xl">
      <h2 class="section-heading text-center text-2xl">Apply</h2>
      <p class="mt-2 text-center text-sm text-content-muted">Share your details and we'll get back to you. Attach your CV link in the message.</p>
      <form id="career-form" class="mt-6 rounded-card border border-edge bg-surface p-6 shadow-card">
        <?= csrf_field() ?>
        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div><label class="block text-sm font-medium text-content">Full name</label><input name="name" required class="esk-input"></div>
          <div><label class="block text-sm font-medium text-content">Email</label><input type="email" name="email" required class="esk-input"></div>
          <div><label class="block text-sm font-medium text-content">Phone</label><input name="phone" class="esk-input"></div>
          <div><label class="block text-sm font-medium text-content">Position</label>
            <select name="subject" class="esk-input">
              <option value="Career: General / open application">General / open application</option>
              <?php foreach ($jobs as $j): ?><option value="Career: <?= e($j['title']) ?>"><?= e($j['title']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mt-4"><label class="block text-sm font-medium text-content">Cover note &amp; CV link</label><textarea name="message" rows="4" required class="esk-input" placeholder="A short note about you, and a link to your CV / portfolio."></textarea></div>
        <button type="submit" class="mt-5 w-full rounded-lg bg-brand-600 px-5 py-3 text-sm font-semibold text-on-brand transition hover:bg-brand-700">Submit application</button>
      </form>
    </div>
  </div>
</section>
<style>.esk-input{margin-top:.35rem;width:100%;border-radius:.625rem;border:1px solid rgb(var(--edge));background:rgb(var(--surface));padding:.6rem .75rem;font-size:.875rem;color:rgb(var(--content))}.esk-input:focus{outline:none;border-color:rgb(var(--brand-500));box-shadow:0 0 0 3px rgb(var(--brand-500)/.2)}</style>
<?php
$page_scripts = '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>'
  . '<script>(function(){var f=document.getElementById("career-form");if(!f)return;f.addEventListener("submit",function(e){e.preventDefault();var b=f.querySelector("[type=submit]");b.disabled=true;'
  . 'fetch("' . e(url('api/contact.php')) . '",{method:"POST",headers:{Accept:"application/json"},body:new FormData(f)}).then(function(r){return r.json();}).then(function(d){b.disabled=false;'
  . 'if(d.ok){f.reset();Swal.fire({icon:"success",title:"Thank you!",text:d.message||"Your application has been received.",confirmButtonColor:"#4f46e5"});}'
  . 'else{Swal.fire({icon:"error",title:"Oops",text:d.message||"Something went wrong.",confirmButtonColor:"#4f46e5"});}}).catch(function(){b.disabled=false;Swal.fire({icon:"error",title:"Network error",text:"Please try again.",confirmButtonColor:"#4f46e5"});});});})();</script>';
require __DIR__ . '/includes/footer.php';
