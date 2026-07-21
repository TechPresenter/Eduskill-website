<?php
/** Contact page — form submits via Fetch to api/contact.php (JSON), feedback via SweetAlert2. */
require __DIR__ . '/includes/config.php';
$page_title = 'Contact Us';
$email = (string) setting('contact_email', 'hello@eduskill.org.in');
$phone = (string) setting('contact_phone', '+91 00000 00000');
$address = (string) setting('contact_address', 'New Delhi, India');
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
      <span class="eyebrow">Get in touch</span>
      <h1 class="section-heading">Contact us</h1>
      <p class="section-subheading mx-auto">Whether you want to donate, volunteer, or partner with us — we'd love to hear from you.</p>
    </div>
    <div class="mx-auto grid max-w-4xl grid-cols-1 gap-8 lg:grid-cols-5">
      <div class="lg:col-span-2">
        <div class="space-y-6">
          <div class="flex items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><i class="fa-solid fa-envelope"></i></span><div><div class="text-sm font-semibold text-content">Email</div><div class="text-sm text-content-muted"><?= e($email) ?></div></div></div>
          <div class="flex items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><i class="fa-solid fa-phone"></i></span><div><div class="text-sm font-semibold text-content">Phone</div><div class="text-sm text-content-muted"><?= e($phone) ?></div></div></div>
          <div class="flex items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><i class="fa-solid fa-location-dot"></i></span><div><div class="text-sm font-semibold text-content">Address</div><div class="text-sm text-content-muted"><?= e($address) ?></div></div></div>
        </div>
      </div>
      <div class="lg:col-span-3">
        <form id="contact-form" class="rounded-card border border-edge bg-surface p-6 shadow-card">
          <?= csrf_field() ?>
          <!-- Honeypot: real users leave it empty; bots fill it. -->
          <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div><label class="block text-sm font-medium text-content">Name</label><input name="name" required class="esk-input"></div>
            <div><label class="block text-sm font-medium text-content">Email</label><input type="email" name="email" required class="esk-input"></div>
            <div><label class="block text-sm font-medium text-content">Phone</label><input name="phone" class="esk-input"></div>
            <div><label class="block text-sm font-medium text-content">Subject</label><input name="subject" class="esk-input"></div>
          </div>
          <div class="mt-4"><label class="block text-sm font-medium text-content">Message</label><textarea name="message" rows="5" required class="esk-input"></textarea></div>
          <button type="submit" class="mt-5 w-full rounded-lg bg-brand-600 px-5 py-3 text-sm font-semibold text-on-brand transition hover:bg-brand-700">Send message</button>
        </form>
      </div>
    </div>
  </div>
</section>
<style>.esk-input{margin-top:.35rem;width:100%;border-radius:.625rem;border:1px solid rgb(var(--edge));background:rgb(var(--surface));padding:.6rem .75rem;font-size:.875rem;color:rgb(var(--content))}.esk-input:focus{outline:none;border-color:rgb(var(--brand-500));box-shadow:0 0 0 3px rgb(var(--brand-500)/.2)}</style>
<?php
$page_scripts = '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>'
  . '<script>(function(){var f=document.getElementById("contact-form");if(!f)return;'
  . 'f.addEventListener("submit",function(e){e.preventDefault();var btn=f.querySelector("button");btn.disabled=true;'
  . 'fetch("' . e(url('api/contact.php')) . '",{method:"POST",headers:{"Accept":"application/json"},body:new FormData(f)})'
  . '.then(function(r){return r.json();}).then(function(d){btn.disabled=false;'
  . 'if(d.ok){f.reset();Swal.fire({icon:"success",title:"Thank you!",text:d.message||"Your message has been sent.",confirmButtonColor:"#4f46e5"});}'
  . 'else{Swal.fire({icon:"error",title:"Oops",text:d.message||"Something went wrong.",confirmButtonColor:"#4f46e5"});}})'
  . '.catch(function(){btn.disabled=false;Swal.fire({icon:"error",title:"Network error",text:"Please try again.",confirmButtonColor:"#4f46e5"});});});})();</script>';
require __DIR__ . '/includes/footer.php';
