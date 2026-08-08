<?php
/**
 * =============================================================================
 *  seed_email.php — 26 premium email templates + demo mailbox/contacts data.
 * =============================================================================
 *  Idempotent: templates keyed by slug (INSERT … ON DUPLICATE KEY refresh of
 *  category/description/thumbnail only, never clobbering an admin-edited body
 *  unless --force). Demo messages/contacts are only inserted when their tables
 *  are empty, so re-running never piles up duplicates.
 *
 *    C:\xampp\php\php.exe database\seed_email.php          (safe, additive)
 *    C:\xampp\php\php.exe database\seed_email.php --force   (overwrite bodies)
 * =============================================================================
 */
require __DIR__ . '/../includes/bootstrap.php';

$force = in_array('--force', $argv ?? [], true);

/* A branded content block (the mail_layout shell wraps this at send time). */
$btn = static fn(string $label, string $url = '{{action_url}}', string $color = '#063566'): string =>
    '<table cellpadding="0" cellspacing="0" style="margin:22px auto;"><tr><td style="border-radius:10px;background:' . $color . ';">'
    . '<a href="' . $url . '" style="display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;">' . $label . '</a>'
    . '</td></tr></table>';

$p = static fn(string $t): string => '<p style="margin:0 0 16px;line-height:1.7;">' . $t . '</p>';
$h = static fn(string $t): string => '<h2 style="margin:0 0 14px;font-size:22px;color:#111827;">' . $t . '</h2>';
$hi = static fn(string $t): string => '<div style="background:#f0f9ff;border-left:4px solid #084881;padding:14px 18px;border-radius:8px;margin:0 0 18px;">' . $t . '</div>';

/**
 * The 26 templates. slug => [name, category, thumbnail(hex), description,
 * subject, body, variables].
 */
$T = [
    ['newsletter', 'Newsletter', 'Newsletter', '#ec4899', 'Monthly newsletter with your latest impact stories',
        '{{site_name}} Newsletter — {{month}} {{year}}',
        $h('Hello {{name}}, here\'s what we\'ve been up to') . $p('Thank you for standing with us. This month your support helped us reach more families across Bihar than ever before.') . $hi('<strong>{{highlight}}</strong>') . $p('Read the full stories, see the numbers, and meet the people whose lives you changed.') . $btn('Read the Newsletter', '{{action_url}}', '#ec4899'),
        'name, month, highlight, action_url'],

    ['welcome-email', 'Onboarding', 'Onboarding', '#6366f1', 'Warm welcome for a new member or subscriber',
        'Welcome to {{site_name}}, {{name}}! 🎉',
        $h('Welcome aboard, {{name}}!') . $p('We\'re thrilled to have you join the {{site_name}} family. Together we\'re building a brighter future for communities across Bihar.') . $p('Here\'s what you can do next:') . '<ul style="line-height:1.9;color:#374151;"><li>Explore our programs and causes</li><li>See where your support goes</li><li>Join a volunteer drive near you</li></ul>' . $btn('Get Started', '{{action_url}}', '#6366f1'),
        'name, action_url'],

    ['membership-confirmation', 'Membership', 'Membership', '#E67B1D', 'Confirms a new membership is active',
        'Your {{site_name}} membership is confirmed ✅',
        $h('Membership Confirmed') . $p('Dear {{name}}, your <strong>{{plan}}</strong> membership is now active. Welcome to a community of changemakers!') . $hi('Membership ID: <strong>{{member_id}}</strong><br>Valid until: <strong>{{expiry_date}}</strong>') . $p('Your digital membership card is ready to download.') . $btn('View Membership Card', '{{action_url}}', '#E67B1D'),
        'name, plan, member_id, expiry_date, action_url'],

    ['donation-receipt', 'Donations', 'Donations', '#58A42F', 'Official 80G-eligible donation receipt',
        'Receipt for your donation — {{receipt_no}}',
        $h('Thank you for your generosity') . $p('Dear {{name}}, we gratefully acknowledge your donation. This receipt is valid for tax exemption under Section 80G.') . $hi('Receipt No: <strong>{{receipt_no}}</strong><br>Amount: <strong>{{amount}}</strong><br>Date: <strong>{{date}}</strong><br>PAN: {{pan}}') . $p('Every rupee goes directly to the cause. Thank you for making our work possible.') . $btn('Download 80G Receipt', '{{action_url}}', '#58A42F'),
        'name, receipt_no, amount, date, pan, action_url'],

    ['donation-thank-you', 'Donations', 'Donations', '#58A42F', 'Heartfelt thank-you after a gift',
        'Your kindness is changing lives, {{name}} ❤️',
        $h('You made this possible') . $p('Dear {{name}}, your gift of <strong>{{amount}}</strong> is already at work — funding meals, medicines, books and hope for families who need it most.') . $p('We\'ll keep you updated on exactly where your generosity goes.') . $btn('See Your Impact', '{{action_url}}', '#58A42F'),
        'name, amount, action_url'],

    ['course-enrollment', 'Learning', 'Learning', '#8b5cf6', 'Confirms enrollment in a course',
        'You\'re enrolled: {{course_name}} 📚',
        $h('Welcome to {{course_name}}') . $p('Hi {{name}}, you\'re all set! Your free course is ready and waiting.') . $hi('Course: <strong>{{course_name}}</strong><br>Duration: {{duration}}<br>Level: {{level}}') . $btn('Start Learning', '{{action_url}}', '#8b5cf6'),
        'name, course_name, duration, level, action_url'],

    ['course-completion', 'Learning', 'Learning', '#084881', 'Congratulates a learner on finishing',
        'Congratulations — you completed {{course_name}}! 🎓',
        $h('You did it, {{name}}!') . $p('You\'ve successfully completed <strong>{{course_name}}</strong>. We\'re proud of your dedication.') . $p('Your certificate of completion is ready to download and share.') . $btn('Get Your Certificate', '{{action_url}}', '#084881'),
        'name, course_name, action_url'],

    ['certificate-issued', 'Learning', 'Certificates', '#084881', 'Notifies that a certificate is available',
        'Your certificate is ready 📜',
        $h('Certificate Issued') . $p('Dear {{name}}, your certificate for <strong>{{title}}</strong> has been issued and is verifiable online.') . $hi('Certificate No: <strong>{{certificate_no}}</strong>') . $btn('Download Certificate', '{{action_url}}', '#084881'),
        'name, title, certificate_no, action_url'],

    ['event-registration', 'Events', 'Events', '#E67B1D', 'Confirms registration for an event',
        'You\'re registered for {{event_name}} 🎟️',
        $h('See you at {{event_name}}') . $p('Hi {{name}}, your spot is confirmed. We can\'t wait to see you there!') . $hi('📅 {{event_date}}<br>📍 {{venue}}') . $btn('View Event Details', '{{action_url}}', '#E67B1D'),
        'name, event_name, event_date, venue, action_url'],

    ['event-reminder', 'Events', 'Events', '#fb923c', 'Reminds an attendee an event is soon',
        'Reminder: {{event_name}} is {{when}} ⏰',
        $h('Don\'t forget — {{event_name}}') . $p('Hi {{name}}, this is a friendly reminder that <strong>{{event_name}}</strong> is coming up {{when}}.') . $hi('📅 {{event_date}}<br>📍 {{venue}}') . $btn('Get Directions', '{{action_url}}', '#fb923c'),
        'name, event_name, when, event_date, venue, action_url'],

    ['volunteer-registration', 'Volunteers', 'Volunteers', '#084881', 'Welcomes a new volunteer',
        'Welcome to the team, {{name}}! 🤝',
        $h('Thank you for volunteering') . $p('Hi {{name}}, thank you for stepping up to make a difference. Volunteers are the heart of {{site_name}}.') . $p('Our team will reach out shortly with your first opportunity.') . $btn('Volunteer Dashboard', '{{action_url}}', '#084881'),
        'name, action_url'],

    ['internship-offer', 'Careers', 'Careers', '#14b8a6', 'Extends an internship offer',
        'Internship Offer — {{role}} at {{site_name}} 🌟',
        $h('Congratulations, {{name}}!') . $p('We\'re delighted to offer you the position of <strong>{{role}}</strong> intern at {{site_name}}.') . $hi('Start date: <strong>{{start_date}}</strong><br>Duration: {{duration}}') . $p('Please review and accept your offer using the link below.') . $btn('View Offer Letter', '{{action_url}}', '#14b8a6'),
        'name, role, start_date, duration, action_url'],

    ['internship-completion', 'Careers', 'Careers', '#084881', 'Certifies internship completion',
        'Your internship is complete — thank you! 🎓',
        $h('Well done, {{name}}') . $p('You\'ve successfully completed your internship as <strong>{{role}}</strong>. It was a pleasure having you on the team.') . $p('Your completion certificate is attached and verifiable online.') . $btn('Download Certificate', '{{action_url}}', '#084881'),
        'name, role, action_url'],

    ['password-reset', 'Account', 'Account', '#ef4444', 'Secure password reset link',
        'Reset your {{site_name}} password',
        $h('Password reset requested') . $p('Hi {{name}}, we received a request to reset your password. Click below to choose a new one.') . $btn('Reset Password', '{{action_url}}', '#ef4444') . $p('<span style="color:#6b7280;font-size:13px;">This link expires in 30 minutes. If you didn\'t request this, you can safely ignore this email.</span>'),
        'name, action_url'],

    ['email-verification', 'Account', 'Account', '#084881', 'Verify a new email address',
        'Verify your email address',
        $h('Confirm your email') . $p('Hi {{name}}, please confirm your email address to activate your {{site_name}} account.') . $btn('Verify Email', '{{action_url}}', '#084881') . $p('<span style="color:#6b7280;font-size:13px;">If you didn\'t create an account, no action is needed.</span>'),
        'name, action_url'],

    ['otp-verification', 'Account', 'Account', '#063566', 'One-time passcode',
        'Your verification code is {{otp}}',
        $h('Your one-time code') . $p('Hi {{name}}, use the code below to complete your verification:') . '<div style="text-align:center;margin:22px 0;"><span style="display:inline-block;font-size:34px;letter-spacing:10px;font-weight:800;color:#111827;background:#f3f4f6;padding:16px 26px;border-radius:12px;">{{otp}}</span></div>' . $p('<span style="color:#6b7280;font-size:13px;">This code expires in 15 minutes. Never share it with anyone.</span>'),
        'name, otp'],

    ['contact-form-reply', 'Support', 'Support', '#084881', 'Reply to a contact form enquiry',
        'Re: your message to {{site_name}}',
        $h('Thanks for reaching out') . $p('Hi {{name}}, thank you for contacting us. Here\'s our response to your enquiry:') . $hi('{{reply_message}}') . $p('If you have further questions, just reply to this email.'),
        'name, reply_message'],

    ['admin-notification', 'Internal', 'Internal', '#64748b', 'Internal alert for the admin team',
        '[{{site_name}}] {{alert_title}}',
        $h('{{alert_title}}') . $p('{{alert_message}}') . $hi('Triggered: <strong>{{timestamp}}</strong>') . $btn('Open Admin Panel', '{{action_url}}', '#475569'),
        'alert_title, alert_message, timestamp, action_url'],

    ['partner-invitation', 'Outreach', 'Outreach', '#8b5cf6', 'Invites an organization to partner',
        'An invitation to partner with {{site_name}} 🤝',
        $h('Let\'s create change together') . $p('Dear {{name}}, we admire the work of {{organization}} and would love to explore a partnership to amplify our shared mission.') . $btn('Explore Partnership', '{{action_url}}', '#8b5cf6'),
        'name, organization, action_url'],

    ['sponsor-invitation', 'Outreach', 'Outreach', '#E67B1D', 'Invites a sponsor to support a cause',
        'Sponsor a cause that changes lives 🌟',
        $h('Your brand + our mission') . $p('Dear {{name}}, sponsoring {{site_name}} puts {{organization}} at the heart of real, measurable community impact — with full transparency and recognition.') . $btn('View Sponsorship Deck', '{{action_url}}', '#E67B1D'),
        'name, organization, action_url'],

    ['campaign-launch', 'Campaigns', 'Campaigns', '#e11d48', 'Announces a new fundraising campaign',
        'New campaign: {{campaign_name}} — join us 🚀',
        $h('{{campaign_name}} is live!') . $p('Dear {{name}}, we\'ve just launched a new campaign and we need your help to reach our goal.') . $hi('Goal: <strong>{{goal}}</strong>') . $p('{{campaign_summary}}') . $btn('Donate Now', '{{action_url}}', '#e11d48'),
        'name, campaign_name, goal, campaign_summary, action_url'],

    ['announcement', 'Campaigns', 'Announcements', '#084881', 'General announcement to your audience',
        '{{announcement_title}}',
        $h('{{announcement_title}}') . $p('Dear {{name}},') . $p('{{announcement_body}}') . $btn('Learn More', '{{action_url}}', '#084881'),
        'name, announcement_title, announcement_body, action_url'],

    ['holiday-greetings', 'Greetings', 'Greetings', '#58A42F', 'Seasonal holiday greeting',
        'Season\'s Greetings from {{site_name}} 🎄',
        $h('Warm wishes this festive season') . $p('Dear {{name}}, as the year draws to a close, we want to thank you for your compassion and support. From all of us at {{site_name}}, we wish you and your loved ones joy, health and peace.') . $btn('Spread the Joy — Donate', '{{action_url}}', '#58A42F'),
        'name, action_url'],

    ['birthday-wishes', 'Greetings', 'Greetings', '#ec4899', 'Personal birthday message',
        'Happy Birthday, {{name}}! 🎂',
        $h('Wishing you a wonderful day!') . $p('Dear {{name}}, everyone at {{site_name}} wishes you a very happy birthday filled with joy and good health. Thank you for being part of our family.') . $btn('Celebrate with a Gift', '{{action_url}}', '#ec4899'),
        'name, action_url'],

    ['anniversary-greetings', 'Greetings', 'Greetings', '#E67B1D', 'Marks a supporter anniversary',
        'Celebrating {{years}} year(s) together! 🎉',
        $h('What a journey, {{name}}!') . $p('Dear {{name}}, it\'s been <strong>{{years}} year(s)</strong> since you joined the {{site_name}} family. Your loyalty means the world to us and to the communities you\'ve helped.') . $btn('See What We\'ve Achieved', '{{action_url}}', '#E67B1D'),
        'name, years, action_url'],

    ['custom-template', 'Custom', 'Custom', '#64748b', 'Blank branded starting point',
        '{{subject}}',
        $h('{{heading}}') . $p('Dear {{name}},') . $p('{{body}}') . $btn('{{button_label}}', '{{action_url}}', '#063566'),
        'name, subject, heading, body, button_label, action_url'],
];

$ins = 0; $upd = 0; $sort = 10;
foreach ($T as [$slug, $group, $category, $thumb, $desc, $subject, $body, $vars]) {
    $existing = find_by('email_templates', 'slug', $slug);
    $sort += 10;
    if ($existing) {
        $data = [
            'category' => $category, 'description' => $desc, 'thumbnail' => $thumb,
            'is_premium' => 1, 'sort_order' => $sort,
        ];
        if ($force) { $data['subject'] = $subject; $data['body'] = $body; $data['variables'] = $vars; }
        db_update('email_templates', $data, 'id = :id', [':id' => (int) $existing['id']]);
        $upd++;
    } else {
        db_insert('email_templates', [
            'name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug,
            'category' => $category, 'description' => $desc, 'thumbnail' => $thumb,
            'is_premium' => 1, 'sort_order' => $sort,
            'subject' => $subject, 'body' => $body, 'variables' => $vars, 'status' => 1,
        ]);
        $ins++;
    }
}
echo "Templates: $ins inserted, $upd updated. Total now: " . db_value("SELECT COUNT(*) FROM email_templates") . "\n";

/* ---- Default signature + account (only if none) ---- */
if ((int) db_value("SELECT COUNT(*) FROM email_signatures") === 0) {
    db_insert('email_signatures', [
        'name' => 'Default', 'is_default' => 1,
        'body_html' => '<strong>' . e(get_setting('site_name', SITE_NAME)) . '</strong><br>'
            . e(get_setting('contact_email', SITE_EMAIL)) . ' &middot; ' . e(get_setting('contact_phone', SITE_PHONE)) . '<br>'
            . '<span style="color:#9ca3af;font-size:12px;">' . e(get_setting('contact_address', SITE_ADDRESS)) . '</span>',
    ]);
    echo "Default signature created.\n";
}
if ((int) db_value("SELECT COUNT(*) FROM email_accounts") === 0) {
    db_insert('email_accounts', [
        'name' => get_setting('site_name', SITE_NAME),
        'email' => get_setting('contact_email', SITE_EMAIL),
        'reply_to' => get_setting('contact_email', SITE_EMAIL),
        'smtp_port' => 587, 'smtp_secure' => 'tls',
        'imap_port' => 993, 'imap_secure' => 'ssl',
        'pop3_port' => 995, 'pop3_secure' => 'ssl',
        'is_default' => 1, 'status' => 1,
    ]);
    echo "Default mail account created (configure SMTP/IMAP in Email Settings).\n";
}

/* ---- Demo mailbox messages + contacts (only when empty) ---- */
if ((int) db_value("SELECT COUNT(*) FROM email_messages") === 0) {
    $now = time();
    $demo = [
        ['inbox','incoming','Priya Sharma','priya@example.com','Thank you for the update','I just wanted to say the health camp last week was wonderful. My mother was treated with such care.',0,1,0, $now-3600],
        ['inbox','incoming','Rahul Verma','rahul@example.com','Volunteering this weekend?','Hi team, I\'d love to help with the Saturday book drive. Where should I report?',0,0,1, $now-7200],
        ['inbox','incoming','Corporate CSR','csr@bizcorp.example','Partnership enquiry','We represent BizCorp and are interested in sponsoring your education program for FY26.',1,0,1, $now-86400],
        ['sent','outgoing','','donor.list@pwf.test','Your December impact report','Dear friends, here is what your generosity achieved this month across Bihar...',1,0,0, $now-43200],
        ['sent','outgoing','','priya@example.com','Re: Thank you for the update','Dear Priya, thank you so much for your kind words. It means a lot to our team!',1,1,0, $now-1800],
        ['drafts','outgoing','','','New Year greetings (draft)','Dear {{name}}, as we step into a new year...',1,0,0, $now-600],
    ];
    foreach ($demo as [$folder,$dir,$fn,$addr,$subj,$body,$read,$star,$imp,$ts]) {
        $isIncoming = $dir === 'incoming';
        db_insert('email_messages', [
            'direction' => $dir, 'folder' => $folder,
            'from_name' => $isIncoming ? $fn : get_setting('site_name', SITE_NAME),
            'from_email' => $isIncoming ? $addr : get_setting('contact_email', SITE_EMAIL),
            'to_email' => $isIncoming ? get_setting('contact_email', SITE_EMAIL) : $addr,
            'subject' => $subj, 'preview' => mb_substr($body, 0, 160),
            'body_html' => '<p>' . e($body) . '</p>', 'body_text' => $body,
            'is_read' => $read, 'is_starred' => $star, 'is_important' => $imp,
            'thread_id' => substr(bin2hex(random_bytes(8)), 0, 16),
            'open_token' => bin2hex(random_bytes(12)),
            'sent_at' => $folder === 'sent' ? date('Y-m-d H:i:s', $ts) : null,
            'created_at' => date('Y-m-d H:i:s', $ts),
        ]);
    }
    echo "Seeded " . count($demo) . " demo messages.\n";
}

if ((int) db_value("SELECT COUNT(*) FROM contacts") === 0) {
    $lid = db_insert('contact_lists', ['name' => 'Newsletter Subscribers', 'description' => 'Everyone opted into the monthly newsletter', 'color' => '#ec4899']);
    $lid2 = db_insert('contact_lists', ['name' => 'Major Donors', 'description' => 'Donors giving ₹10,000+', 'color' => '#58A42F']);
    $contacts = [
        ['Priya','Sharma','priya@example.com','+91-9800000001','Self','donor,newsletter','active'],
        ['Rahul','Verma','rahul@example.com','+91-9800000002','Self','volunteer','active'],
        ['Anita','Devi','anita@example.com','+91-9800000003','Community','donor','active'],
        ['BizCorp','CSR','csr@bizcorp.example','+91-9800000004','BizCorp Ltd','partner,sponsor','active'],
    ];
    foreach ($contacts as $i => [$fn,$ln,$em,$ph,$co,$tags,$st]) {
        $cid = db_insert('contacts', ['first_name'=>$fn,'last_name'=>$ln,'email'=>$em,'phone'=>$ph,'company'=>$co,'tags'=>$tags,'status'=>$st,'source'=>'seed']);
        db_insert('contact_list_members', ['list_id' => $lid, 'contact_id' => $cid]);
        if ($i === 2 || $i === 3) { db_insert('contact_list_members', ['list_id' => $lid2, 'contact_id' => $cid]); }
    }
    echo "Seeded " . count($contacts) . " demo contacts across 2 lists.\n";
}

echo "Done.\n";
