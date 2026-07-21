<?php
/** Volunteer registration form. */
require __DIR__ . '/includes/config.php';
$page_title = 'Volunteer';
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
      <span class="eyebrow">Lend your time &amp; skills</span>
      <h1 class="section-heading">Become a volunteer</h1>
      <p class="section-subheading mx-auto">Whether you can give an hour a week or a whole weekend, there's a place for you at Eduskill.</p>
    </div>
    <div class="mx-auto grid max-w-4xl grid-cols-1 gap-8 lg:grid-cols-5">
      <div class="lg:col-span-2" data-aos="fade-up">
        <div class="space-y-5">
          <div class="flex items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><i class="fa-solid fa-chalkboard-user"></i></span><div><div class="text-sm font-semibold text-content">Teach &amp; mentor</div><div class="text-sm text-content-muted">Help students with lessons, exam prep and guidance.</div></div></div>
          <div class="flex items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><i class="fa-solid fa-hands-holding-child"></i></span><div><div class="text-sm font-semibold text-content">Support events</div><div class="text-sm text-content-muted">Lend a hand at camps, drives and community programmes.</div></div></div>
          <div class="flex items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><i class="fa-solid fa-laptop-code"></i></span><div><div class="text-sm font-semibold text-content">Skills-based</div><div class="text-sm text-content-muted">Offer design, tech, writing or fundraising expertise pro bono.</div></div></div>
        </div>
      </div>
      <div class="lg:col-span-3">
        <form id="volunteer-form" class="rounded-card border border-edge bg-surface p-6 shadow-card">
          <?= csrf_field() ?>
          <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div><label class="block text-sm font-medium text-content">Full name</label><input name="name" required class="esk-input"></div>
            <div><label class="block text-sm font-medium text-content">Email</label><input type="email" name="email" required class="esk-input"></div>
            <div><label class="block text-sm font-medium text-content">Phone</label><input name="phone" class="esk-input"></div>
            <div><label class="block text-sm font-medium text-content">How you'd like to help</label>
              <select name="subject" class="esk-input">
                <option value="Volunteer: Teaching &amp; mentoring">Teaching &amp; mentoring</option>
                <option value="Volunteer: Event support">Event support</option>
                <option value="Volunteer: Skills-based (pro bono)">Skills-based (pro bono)</option>
                <option value="Volunteer: Wherever needed">Wherever needed</option>
              </select>
            </div>
          </div>
          <div class="mt-4"><label class="block text-sm font-medium text-content">Your skills &amp; availability</label><textarea name="message" rows="4" required class="esk-input" placeholder="Tell us about your skills, and when you're free (days/hours per week)."></textarea></div>
          <button type="submit" class="mt-5 w-full rounded-lg bg-brand-600 px-5 py-3 text-sm font-semibold text-on-brand transition hover:bg-brand-700">Register as a volunteer</button>
        </form>
      </div>
    </div>
  </div>
</section>
<style>.esk-input{margin-top:.35rem;width:100%;border-radius:.625rem;border:1px solid rgb(var(--edge));background:rgb(var(--surface));padding:.6rem .75rem;font-size:.875rem;color:rgb(var(--content))}.esk-input:focus{outline:none;border-color:rgb(var(--brand-500));box-shadow:0 0 0 3px rgb(var(--brand-500)/.2)}</style>
<?php
$page_scripts = '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>'
  . '<script>(function(){var f=document.getElementById("volunteer-form");if(!f)return;f.addEventListener("submit",function(e){e.preventDefault();var b=f.querySelector("[type=submit]");b.disabled=true;'
  . 'fetch("' . e(url('api/contact.php')) . '",{method:"POST",headers:{Accept:"application/json"},body:new FormData(f)}).then(function(r){return r.json();}).then(function(d){b.disabled=false;'
  . 'if(d.ok){f.reset();Swal.fire({icon:"success",title:"Welcome aboard!",text:d.message||"Thanks for signing up. We\'ll be in touch.",confirmButtonColor:"#4f46e5"});}'
  . 'else{Swal.fire({icon:"error",title:"Oops",text:d.message||"Something went wrong.",confirmButtonColor:"#4f46e5"});}}).catch(function(){b.disabled=false;Swal.fire({icon:"error",title:"Network error",text:"Please try again.",confirmButtonColor:"#4f46e5"});});});})();</script>';
require __DIR__ . '/includes/footer.php';
