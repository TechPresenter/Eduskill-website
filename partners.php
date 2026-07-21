<?php
/** Become a Partner — partnership tiers + enquiry form. */
require __DIR__ . '/includes/config.php';
$page_title = 'Become a Partner';
$tiers = [
    ['icon' => 'fa-building', 'name' => 'Corporate / CSR', 'desc' => 'Fund programmes, sponsor campaigns, and engage your employees in high-impact volunteering.'],
    ['icon' => 'fa-handshake-angle', 'name' => 'NGO / Institutional', 'desc' => 'Collaborate on the ground, share resources, and co-deliver programmes at scale.'],
    ['icon' => 'fa-school', 'name' => 'Schools & Colleges', 'desc' => 'Bring skilling, scholarships and mentoring to your students through joint initiatives.'],
    ['icon' => 'fa-user-tie', 'name' => 'Individual / Philanthropist', 'desc' => 'Back a cause you care about with flexible, transparent giving and regular impact updates.'],
];
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
      <span class="eyebrow">Let's work together</span>
      <h1 class="section-heading">Become a partner</h1>
      <p class="section-subheading mx-auto">Partnerships multiply our impact. Choose the path that fits you — or tell us what you have in mind.</p>
    </div>
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <?php foreach ($tiers as $i => $t): ?>
        <div class="rounded-card border border-edge bg-surface p-6 shadow-card" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 80 ?>">
          <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><i class="fa-solid <?= e($t['icon']) ?> text-lg"></i></div>
          <h3 class="font-display text-base font-semibold text-content"><?= e($t['name']) ?></h3>
          <p class="mt-2 text-sm leading-relaxed text-content-muted"><?= e($t['desc']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <div id="enquire" class="mx-auto mt-16 max-w-2xl">
      <h2 class="section-heading text-center text-2xl">Start a conversation</h2>
      <form id="partner-form" class="mt-6 rounded-card border border-edge bg-surface p-6 shadow-card">
        <?= csrf_field() ?>
        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div><label class="block text-sm font-medium text-content">Contact name</label><input name="name" required class="esk-input"></div>
          <div><label class="block text-sm font-medium text-content">Work email</label><input type="email" name="email" required class="esk-input"></div>
          <div><label class="block text-sm font-medium text-content">Organisation &amp; phone</label><input name="phone" class="esk-input" placeholder="Organisation, phone"></div>
          <div><label class="block text-sm font-medium text-content">Partnership type</label>
            <select name="subject" class="esk-input">
              <?php foreach ($tiers as $t): ?><option value="Partnership: <?= e($t['name']) ?>"><?= e($t['name']) ?></option><?php endforeach; ?>
              <option value="Partnership: Other">Other</option>
            </select>
          </div>
        </div>
        <div class="mt-4"><label class="block text-sm font-medium text-content">What do you have in mind?</label><textarea name="message" rows="4" required class="esk-input" placeholder="Tell us about your organisation and how you'd like to partner."></textarea></div>
        <button type="submit" class="mt-5 w-full rounded-lg bg-brand-600 px-5 py-3 text-sm font-semibold text-on-brand transition hover:bg-brand-700">Send enquiry</button>
      </form>
    </div>
  </div>
</section>
<style>.esk-input{margin-top:.35rem;width:100%;border-radius:.625rem;border:1px solid rgb(var(--edge));background:rgb(var(--surface));padding:.6rem .75rem;font-size:.875rem;color:rgb(var(--content))}.esk-input:focus{outline:none;border-color:rgb(var(--brand-500));box-shadow:0 0 0 3px rgb(var(--brand-500)/.2)}</style>
<?php
$page_scripts = '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>'
  . '<script>(function(){var f=document.getElementById("partner-form");if(!f)return;f.addEventListener("submit",function(e){e.preventDefault();var b=f.querySelector("[type=submit]");b.disabled=true;'
  . 'fetch("' . e(url('api/contact.php')) . '",{method:"POST",headers:{Accept:"application/json"},body:new FormData(f)}).then(function(r){return r.json();}).then(function(d){b.disabled=false;'
  . 'if(d.ok){f.reset();Swal.fire({icon:"success",title:"Thank you!",text:d.message||"We\'ll be in touch soon.",confirmButtonColor:"#4f46e5"});}'
  . 'else{Swal.fire({icon:"error",title:"Oops",text:d.message||"Something went wrong.",confirmButtonColor:"#4f46e5"});}}).catch(function(){b.disabled=false;Swal.fire({icon:"error",title:"Network error",text:"Please try again.",confirmButtonColor:"#4f46e5"});});});})();</script>';
require __DIR__ . '/includes/footer.php';
