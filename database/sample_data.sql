-- =============================================================================
--  EDUSKILL INDIA FOUNDATION — Sample / seed data
-- =============================================================================
--  Run AFTER schema.sql + schema_v2.sql + schema_v3.sql:
--    C:\xampp\mysql\bin\mysql.exe -u root pwf < database\sample_data.sql
--
--  Admin login (created below):
--    URL       : /admin/login
--    Email     : admin@eduskillindia.org
--    Password  : Admin@123      <-- change immediately after first login
--
--  Safe to re-run: content tables are TRUNCATEd first; accounts use INSERT IGNORE.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
USE `pwf`;

TRUNCATE `settings`; TRUNCATE `menus`; TRUNCATE `hero_slides`; TRUNCATE `programs`;
TRUNCATE `projects`; TRUNCATE `blog_categories`; TRUNCATE `blog_tags`; TRUNCATE `blogs`;
TRUNCATE `blog_tag_map`; TRUNCATE `blog_comments`; TRUNCATE `gallery_albums`; TRUNCATE `gallery_media`;
TRUNCATE `videos`; TRUNCATE `achievements`; TRUNCATE `certificates`; TRUNCATE `events`;
TRUNCATE `event_registrations`; TRUNCATE `awareness_calendar`; TRUNCATE `team_members`;
TRUNCATE `testimonials`; TRUNCATE `partners`; TRUNCATE `sponsors`; TRUNCATE `faqs`;
TRUNCATE `volunteers`; TRUNCATE `internships`; TRUNCATE `donations`; TRUNCATE `campaigns`;
TRUNCATE `newsletter_subscribers`; TRUNCATE `feedback`; TRUNCATE `contact_messages`;
TRUNCATE `documents`; TRUNCATE `seo_meta`; TRUNCATE `email_templates`; TRUNCATE `social_links`;
TRUNCATE `announcements`; TRUNCATE `careers`; TRUNCATE `membership_plans`; TRUNCATE `schemes`;
TRUNCATE `scholarships`; TRUNCATE `issued_certificates`; TRUNCATE `pages`;

-- ----- Roles / permissions / admin user (idempotent) ------------------------
INSERT IGNORE INTO `roles` (`id`,`name`,`slug`,`description`,`is_system`) VALUES
 (1,'Super Admin','super-admin','Full, unrestricted access',1),
 (2,'Editor','editor','Manage content, not system settings',0);

INSERT IGNORE INTO `permissions` (`id`,`name`,`slug`,`module`) VALUES
 (1,'Manage Content','manage-content','content'),
 (2,'Manage Blog','manage-blog','blog'),
 (3,'Manage Media','manage-media','media'),
 (4,'Manage Events','manage-events','events'),
 (5,'Manage People','manage-people','people'),
 (6,'Manage Donations','manage-donations','finance'),
 (7,'Manage Users','manage-users','system'),
 (8,'Manage Settings','manage-settings','system');
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`) VALUES
 (2,1),(2,2),(2,3),(2,4),(2,5);

INSERT IGNORE INTO `users` (`id`,`role_id`,`name`,`email`,`password`,`phone`,`status`) VALUES
 (1,1,'Prashant Kumar','admin@eduskillindia.org',
  '$2y$10$YMw9MxX21WlF0Vlp0BpCWOHpo/30YKoSWwMn3IcqeRpsbvBCJuX5q','+91-9955446477','active');

-- ----- Settings --------------------------------------------------------------
INSERT INTO `settings` (`group_name`,`key_name`,`value`,`type`) VALUES
 ('general','site_name','EDUSKILL INDIA FOUNDATION','text'),
 ('general','site_tagline','Empowering Communities • Spreading Hope • Creating Change','text'),
 ('general','site_description','EDUSKILL INDIA FOUNDATION is a registered non-profit in Patna, Bihar working to empower communities through education, healthcare, skill development and relief.','textarea'),
 ('general','site_keywords','NGO Patna, Bihar NGO, charity India, donation, volunteer, education, healthcare','text'),
 ('general','footer_about','EDUSKILL INDIA FOUNDATION works across Bihar to empower communities through education, healthcare, skill development and relief — spreading hope and creating lasting change.','textarea'),
 ('contact','contact_email','info@eduskillindia.org','email'),
 ('contact','contact_phone','+91-9955446477','text'),
 ('contact','contact_address','Patna, Bihar, India 840007','text'),
 ('contact','whatsapp_number','919955446477','text'),
 ('homepage','home_about_title','A movement for dignity, hope and opportunity','text'),
 ('homepage','home_about_text','EDUSKILL INDIA FOUNDATION is a registered non-profit (CIN U88900BR2025NPL079155) based in Patna, Bihar. We work hand-in-hand with communities to deliver education, healthcare, skill development, and emergency relief — always with compassion and accountability.','textarea'),
 ('homepage','mission_short','To empower underserved communities by providing access to quality education, healthcare, and sustainable livelihoods.','textarea'),
 ('homepage','vision_short','An equitable society where every individual has the opportunity to live with dignity and reach their full potential.','textarea'),
 ('org','cin','U88900BR2025NPL079155','text'),
 ('org','pan','AAJCP1234K','text'),
 ('org','tan','PTNP12345K','text'),
 ('org','incorporation_date','2025-01-15','text');

-- ----- Menus (header with children, footer) ---------------------------------
INSERT INTO `menus` (`id`,`parent_id`,`title`,`url`,`location`,`sort_order`,`status`) VALUES
 (1,NULL,'Home','/','header',1,1),
 (2,NULL,'About','about','header',2,1),
 (3,NULL,'Programs','programs','header',3,1),
 (4,NULL,'Media','gallery','header',4,1),
 (5,NULL,'Get Involved','volunteer','header',5,1),
 (6,NULL,'Contact','contact','header',6,1),
 (10,2,'Who We Are','about','header',1,1),
 (11,2,'Our Story','our-story','header',2,1),
 (12,2,'Mission & Vision','mission-vision','header',3,1),
 (13,2,'Leadership','leadership-team','header',4,1),
 (14,2,'NGO Details','ngo-details','header',5,1),
 (20,3,'All Programs','programs','header',1,1),
 (21,3,'Causes We Support','causes','header',2,1),
 (22,3,'Schemes','schemes','header',3,1),
 (23,3,'Scholarships','scholarship','header',4,1),
 (24,3,'Campaigns','campaigns','header',5,1),
 (30,4,'Photo Gallery','gallery','header',1,1),
 (31,4,'Videos','media','header',2,1),
 (32,4,'Blog','blogs','header',3,1),
 (33,4,'News & Media','news-media','header',4,1),
 (34,4,'Testimonials','testimonials','header',5,1),
 (40,5,'Volunteer','volunteer','header',1,1),
 (41,5,'Internship','internship','header',2,1),
 (42,5,'Membership','membership','header',3,1),
 (43,5,'Careers','career','header',4,1),
 (44,5,'Become a Partner','become-partner','header',5,1),
 (50,NULL,'About Us','about','footer',1,1),
 (51,NULL,'Our Programs','programs','footer',2,1),
 (52,NULL,'Events','events','footer',3,1),
 (53,NULL,'Blog','blogs','footer',4,1),
 (54,NULL,'Verify Certificate','verify-certificate','footer',5,1),
 (55,NULL,'Contact','contact','footer',6,1);

-- ----- Hero slides -----------------------------------------------------------
INSERT INTO `hero_slides` (`title`,`subtitle`,`description`,`image`,`button_text`,`button_url`,`button2_text`,`button2_url`,`sort_order`,`status`) VALUES
 ('Empowering Communities, Creating Change','Together We Can','We work across Bihar to bring education, healthcare and opportunity to those who need it most.','slides/hero-1.svg','Donate Now','donate','Become a Volunteer','volunteer',1,1),
 ('Education for Every Child','Learning Changes Lives','Free schooling, scholarships and learning kits for underprivileged children.','slides/hero-2.svg','Support Education','donate','Our Programs','programs',2,1),
 ('Healthcare Within Reach','Health for All','Free medical camps and awareness drives in rural and underserved areas.','slides/hero-3.svg','Join Us','volunteer','Learn More','about',3,1);

-- ----- Achievements (impact counters) ---------------------------------------
INSERT INTO `achievements` (`title`,`icon`,`value`,`suffix`,`sort_order`,`status`) VALUES
 ('Lives Impacted','👥',25000,'+',1,1),
 ('Projects Completed','🎓',120,'+',2,1),
 ('Active Volunteers','🤝',800,'+',3,1),
 ('Villages Reached','🏘️',60,'+',4,1);

-- ----- Programs --------------------------------------------------------------
INSERT INTO `programs` (`title`,`slug`,`short_description`,`description`,`icon`,`color`,`is_featured`,`sort_order`,`status`) VALUES
 ('Education for All','education-for-all','Free schooling, scholarships and learning kits for underprivileged children across Bihar.','<p>Our flagship education program provides free schooling, digital learning kits, scholarships and mentorship to children from underserved communities.</p>','📚','#2563eb',1,1,'active'),
 ('Healthcare & Camps','healthcare-and-camps','Free medical camps, health awareness and access to essential care in rural areas.','<p>We run regular free medical camps, health-awareness drives and facilitate access to essential care in rural and underserved areas.</p>','🩺','#0ea5e9',1,2,'active'),
 ('Women Empowerment','women-empowerment','Skill training, self-help groups and livelihood support for women.','<p>Through skill training, self-help groups and micro-livelihood support, we help women achieve financial independence and dignity.</p>','👩','#f59e0b',1,3,'active'),
 ('Skill Development','skill-development','Vocational training that prepares youth for sustainable employment.','<p>Vocational and digital-skill training programs that prepare youth for sustainable employment and entrepreneurship.</p>','🛠️','#16a34a',0,4,'active'),
 ('Clean Water & Sanitation','clean-water-sanitation','Safe drinking water, hygiene drives and sanitation infrastructure.','<p>Ensuring safe drinking water, hygiene awareness and sanitation infrastructure in villages across Bihar.</p>','💧','#0891b2',0,5,'active'),
 ('Relief & Rehabilitation','relief-rehabilitation','Rapid disaster relief, food distribution and long-term rehabilitation.','<p>Rapid relief during floods and disasters, food and essentials distribution, and long-term rehabilitation support.</p>','🆘','#dc2626',0,6,'active');

-- ----- Projects --------------------------------------------------------------
INSERT INTO `projects` (`program_id`,`title`,`slug`,`summary`,`location`,`beneficiaries`,`progress`,`status`,`is_featured`,`sort_order`) VALUES
 (1,'Shiksha Setu — Rural Schools','shiksha-setu','Bridging the learning gap in 25 rural schools.','Patna & Nalanda, Bihar',3200,72,'ongoing',1,1),
 (2,'Swasthya Shivir — Health Camps','swasthya-shivir','Monthly free health camps across 12 villages.','Bihar',8500,60,'ongoing',0,2),
 (3,'Nari Shakti — Skill Centres','nari-shakti','Tailoring and computer skill centres for women.','Patna, Bihar',450,88,'ongoing',1,3);

-- ----- Blog categories / tags / blogs ---------------------------------------
INSERT INTO `blog_categories` (`name`,`slug`,`description`,`sort_order`,`status`) VALUES
 ('News','news','Latest news and updates',1,1),
 ('Stories','stories','Field stories and reflections',2,1),
 ('Success Stories','success-stories','Lives changed through our work',3,1),
 ('Health','health','Health awareness and camps',4,1),
 ('Education','education','Education initiatives',5,1);

INSERT INTO `blog_tags` (`name`,`slug`) VALUES
 ('Education','education'),('Health','health'),('Women','women'),
 ('Volunteering','volunteering'),('Bihar','bihar'),('Impact','impact');

INSERT INTO `blogs` (`category_id`,`author_id`,`title`,`slug`,`excerpt`,`content`,`is_featured`,`status`,`published_at`) VALUES
 (5,1,'How 500 Children Returned to School This Year','children-return-to-school','Our Shiksha Setu program brought 500 out-of-school children back to classrooms across rural Bihar.','<p>This year, through our Shiksha Setu program, more than 500 children who had dropped out of school returned to the classroom. Working closely with families, teachers and local volunteers, we provided learning kits, uniforms and mentorship.</p><p>Each child''s journey is a reminder that access to education transforms not just one life, but an entire community.</p>',1,'published','2026-05-10 09:00:00'),
 (4,1,'Free Health Camp Screens 1,200 Villagers','health-camp-1200','A single weekend health camp in Nalanda screened 1,200 people and referred 80 for further care.','<p>Our monthly Swasthya Shivir health camp reached a new milestone, screening 1,200 villagers over a single weekend and referring 80 for specialised care.</p>',0,'published','2026-04-22 10:30:00'),
 (3,1,'From Beneficiary to Entrepreneur: Anita''s Story','anita-story','After training at our Nari Shakti centre, Anita now runs a thriving tailoring business.','<p>Anita joined our Nari Shakti skill centre two years ago. Today she runs her own tailoring business, employs two women from her village, and supports her family with dignity.</p>',1,'published','2026-03-15 08:00:00'),
 (1,1,'EDUSKILL INDIA FOUNDATION Launches Clean Water Drive','clean-water-drive','A new initiative to install safe drinking-water points in 20 villages.','<p>We are proud to announce the launch of our Clean Water &amp; Sanitation drive, aiming to install safe drinking-water points in 20 villages this year.</p>',0,'published','2026-06-01 11:00:00');

INSERT INTO `blog_tag_map` (`blog_id`,`tag_id`) VALUES (1,1),(1,5),(1,6),(2,2),(2,5),(3,3),(3,6),(4,5);

INSERT INTO `blog_comments` (`blog_id`,`name`,`email`,`comment`,`status`) VALUES
 (1,'Ravi Sharma','ravi@example.com','This is wonderful work. Proud of the team!','approved'),
 (3,'Sunita Devi','sunita@example.com','Anita is an inspiration to us all.','approved');

-- ----- Testimonials ----------------------------------------------------------
INSERT INTO `testimonials` (`name`,`designation`,`message`,`rating`,`sort_order`,`status`) VALUES
 ('Anita Kumari','Beneficiary, Patna','The skill program changed my life — I now run my own tailoring business and support my family.',5,1,1),
 ('Rakesh Singh','Volunteer','Volunteering with Eduskill has been the most rewarding experience. The team truly cares about impact.',5,2,1),
 ('Dr. Meena Rao','Partner, Health Camp','Their organisation and dedication during our medical camps was exceptional. Real change on the ground.',5,3,1),
 ('Amit Verma','Donor','Transparent, accountable and genuinely impactful. I know exactly where my contribution goes.',5,4,1);

-- ----- Partners / sponsors ---------------------------------------------------
INSERT INTO `partners` (`name`,`website`,`sort_order`,`status`) VALUES
 ('Bihar Rural Livelihoods',NULL,1,1),('CareIndia Trust',NULL,2,1),
 ('EduBridge Foundation',NULL,3,1),('HealthFirst NGO',NULL,4,1),('GreenEarth Collective',NULL,5,1);
INSERT INTO `sponsors` (`name`,`tier`,`sort_order`,`status`) VALUES
 ('Tata Trusts','platinum',1,1),('Infosys Foundation','gold',2,1),
 ('HDFC Parivartan','gold',3,1),('Local Business Council','silver',4,1);

-- ----- FAQs ------------------------------------------------------------------
INSERT INTO `faqs` (`question`,`answer`,`category`,`sort_order`,`status`) VALUES
 ('How can I donate?','You can donate securely through our Donate page. We accept UPI, bank transfer and cards. All donations are eligible for 80G tax exemption.','donations',1,1),
 ('Are donations tax-deductible?','Yes. EDUSKILL INDIA FOUNDATION is registered and donations are eligible for tax deduction under Section 80G. A receipt is issued for every donation.','donations',2,1),
 ('How can I volunteer?','Fill out the volunteer form on our Volunteer page. Our team will contact you with opportunities that match your interests and availability.','volunteering',3,1),
 ('Where does my money go?','Every rupee is tracked from donation to delivery. We publish impact reports and maintain full financial transparency.','general',4,1),
 ('Can I visit your projects?','Absolutely! We welcome supporters to visit our project sites. Contact us to arrange a visit.','general',5,1),
 ('How do I verify a certificate?','Use our Verify Certificate page and enter the certificate number to instantly confirm its authenticity.','general',6,1);

-- ----- Events ----------------------------------------------------------------
INSERT INTO `events` (`title`,`slug`,`excerpt`,`description`,`location`,`venue`,`start_datetime`,`end_datetime`,`capacity`,`registration_required`,`is_featured`,`status`) VALUES
 ('Annual Charity Run 2026','charity-run-2026','Run for a cause — support education for children.','<p>Join our Annual Charity Run to raise funds for children''s education. Every step counts!</p>','Patna, Bihar','Gandhi Maidan','2026-09-15 06:30:00','2026-09-15 10:00:00',500,1,1,'published'),
 ('Free Health Camp — Nalanda','health-camp-nalanda','Free health check-ups and medicines for all.','<p>A free health camp offering check-ups, medicines and awareness sessions.</p>','Nalanda, Bihar','Community Hall','2026-08-20 09:00:00','2026-08-20 16:00:00',300,1,0,'published'),
 ('Skill Development Workshop','skill-workshop','Hands-on vocational training for youth.','<p>A hands-on workshop introducing vocational and digital skills to local youth.</p>','Patna, Bihar','Eduskill Skill Centre','2026-08-05 10:00:00','2026-08-05 15:00:00',80,1,0,'published'),
 ('Tree Plantation Drive','tree-plantation-2026','Plant a tree, grow a greener Bihar.','<p>A community tree-plantation drive to promote a greener, healthier environment.</p>','Patna, Bihar','Riverfront','2026-06-05 07:00:00','2026-06-05 11:00:00',200,0,0,'published');

-- ----- Awareness calendar ----------------------------------------------------
INSERT INTO `awareness_calendar` (`title`,`description`,`event_date`,`category`,`color`,`status`) VALUES
 ('World Health Day','Promoting health awareness in our communities.','2026-04-07','Health','#0ea5e9',1),
 ('International Women''s Day','Celebrating and empowering women.','2026-03-08','Social','#ec4899',1),
 ('World Environment Day','Tree plantation and clean-up drives.','2026-06-05','Environment','#16a34a',1),
 ('International Literacy Day','Championing education for all.','2026-09-08','Education','#2563eb',1),
 ('World Water Day','Clean water access awareness.','2026-03-22','Environment','#0891b2',1),
 ('International Day of Charity','Celebrating giving and volunteering.','2026-09-05','Social','#f59e0b',1);

-- ----- Team members (directors are leadership) ------------------------------
INSERT INTO `team_members` (`name`,`slug`,`designation`,`department`,`bio`,`email`,`socials`,`is_leadership`,`sort_order`,`status`) VALUES
 ('Shanti Devi','shanti-devi','Director','Governance','Co-founder and Director, Shanti Devi has dedicated her life to community welfare and women''s empowerment across Bihar.','shanti@eduskillindia.org','{"linkedin":"https://linkedin.com"}',1,1,1),
 ('Prashant Kumar','prashant-kumar','Director','Governance','Co-founder and Director, Prashant Kumar leads strategy, partnerships and program delivery for the foundation.','prashant@eduskillindia.org','{"linkedin":"https://linkedin.com","twitter":"https://twitter.com"}',1,2,1),
 ('Dr. Neha Gupta','neha-gupta','Head of Health Programs','Health','A public-health specialist leading our medical camps and health-awareness initiatives.',NULL,NULL,0,3,1),
 ('Arjun Mehta','arjun-mehta','Education Coordinator','Education','Oversees the Shiksha Setu program and coordinates with schools and volunteers.',NULL,NULL,0,4,1),
 ('Priya Sinha','priya-sinha','Volunteer Manager','Operations','Manages our growing community of 800+ volunteers across Bihar.',NULL,NULL,0,5,1),
 ('Rohit Anand','rohit-anand','Finance & Compliance','Administration','Ensures financial transparency, compliance and accountable use of every contribution.',NULL,NULL,0,6,1);

-- ----- Certificates (recognitions) ------------------------------------------
INSERT INTO `certificates` (`title`,`description`,`image`,`issued_by`,`issue_date`,`sort_order`,`status`) VALUES
 ('Certificate of Registration','Registered non-profit under Section 8.','','Ministry of Corporate Affairs','2025-01-15',1,1),
 ('80G Tax Exemption','Donations eligible for tax deduction.','','Income Tax Department','2025-03-01',2,1),
 ('12A Registration','Registered charitable organisation.','','Income Tax Department','2025-03-01',3,1);

-- ----- Gallery ---------------------------------------------------------------
INSERT INTO `gallery_albums` (`id`,`title`,`slug`,`description`,`cover_image`,`event_date`,`sort_order`,`status`) VALUES
 (1,'Health Camp 2026','health-camp-2026','Moments from our free health camp.','slides/hero-3.svg','2026-04-07',1,1),
 (2,'Education Drive','education-drive','Distributing learning kits to children.','slides/hero-1.svg','2026-05-10',2,1),
 (3,'Women Empowerment','women-empowerment-gallery','Skill training and self-help groups.','slides/hero-2.svg','2026-03-08',3,1);
INSERT INTO `gallery_media` (`album_id`,`title`,`file_path`,`type`,`sort_order`) VALUES
 (1,'Registration desk','slides/hero-3.svg','image',1),(1,'Doctor consultation','slides/hero-1.svg','image',2),
 (2,'Kit distribution','slides/hero-1.svg','image',1),(2,'Classroom','slides/hero-2.svg','image',2),
 (3,'Tailoring class','slides/hero-2.svg','image',1),(3,'Group meeting','slides/hero-3.svg','image',2);

-- ----- Videos ----------------------------------------------------------------
INSERT INTO `videos` (`title`,`slug`,`description`,`youtube_id`,`category`,`sort_order`,`status`) VALUES
 ('Our Journey So Far','our-journey','A look at the impact we''ve created together.','dQw4w9WgXcQ','Impact',1,1),
 ('Voices from the Field','voices-field','Beneficiaries share their stories.','dQw4w9WgXcQ','Stories',2,1);

-- ----- Campaigns -------------------------------------------------------------
INSERT INTO `campaigns` (`title`,`slug`,`short_description`,`image`,`goal_amount`,`raised_amount`,`start_date`,`end_date`,`is_featured`,`status`) VALUES
 ('Educate a Child','educate-a-child','Sponsor a child''s education for one year.','slides/hero-1.svg',500000.00,325000.00,'2026-01-01','2026-12-31',1,'active'),
 ('Health for Villages','health-for-villages','Fund free medical camps across rural Bihar.','slides/hero-3.svg',300000.00,142000.00,'2026-02-01','2026-11-30',1,'active'),
 ('Flood Relief Fund','flood-relief','Emergency relief for flood-affected families.','slides/hero-2.svg',1000000.00,780000.00,'2026-03-01','2026-10-31',0,'active');

-- ----- Social links ----------------------------------------------------------
INSERT INTO `social_links` (`platform`,`url`,`icon`,`sort_order`,`status`) VALUES
 ('facebook','https://facebook.com/eduskillindiafoundation','f',1,1),
 ('twitter','https://twitter.com/eduskillindia','𝕏',2,1),
 ('instagram','https://instagram.com/eduskillindiafoundation','◎',3,1),
 ('linkedin','https://linkedin.com/company/eduskillindiafoundation','in',4,1),
 ('youtube','https://youtube.com/@eduskillindiafoundation','▶',5,1);

-- ----- Announcement ----------------------------------------------------------
INSERT INTO `announcements` (`message`,`link_text`,`link_url`,`bg_color`,`text_color`,`status`) VALUES
 ('🎉 Our Annual Charity Run is on 15 Sep 2026 — register today!','Register','events','#1d4ed8','#ffffff',1);

-- ----- Email templates -------------------------------------------------------
INSERT INTO `email_templates` (`name`,`slug`,`subject`,`body`,`variables`,`status`) VALUES
 ('Welcome','welcome','Welcome to {{site_name}}','<p>Hi {{name}},</p><p>Welcome to {{site_name}}! Thank you for joining our community.</p>','{{name}}, {{site_name}}',1),
 ('Donation Thank You','donation-thanks','Thank you for your donation','<p>Dear {{name}},</p><p>Thank you for your generous donation of {{amount}}.</p>','{{name}}, {{amount}}',1);

-- ----- Schemes ---------------------------------------------------------------
INSERT INTO `schemes` (`title`,`slug`,`category`,`department`,`short_description`,`description`,`eligibility`,`benefits`,`status`) VALUES
 ('Girl Child Education Scheme','girl-child-education','Education','State Government','Financial support for girl-child education.','<p>Supports the education of girl children from economically weaker sections.</p>','Girl children from BPL families; enrolled in school','Annual scholarship, learning kit, mentorship','active'),
 ('Skill India Youth Program','skill-india-youth','Skill','Central Government','Free vocational training for youth.','<p>Free skill training and certification for youth aged 18-35.</p>','Youth aged 18-35; unemployed','Free training, certification, placement support','active'),
 ('Rural Health Initiative','rural-health-initiative','Health','State Government','Subsidised healthcare in rural areas.','<p>Access to subsidised healthcare and free camps for rural families.</p>','Residents of notified rural areas','Free check-ups, subsidised medicines','active');

-- ----- Scholarships ----------------------------------------------------------
INSERT INTO `scholarships` (`title`,`slug`,`description`,`eligibility`,`amount`,`level`,`deadline`,`status`) VALUES
 ('Merit Scholarship','merit-scholarship','For meritorious students from low-income families.','Family income < ₹2L; 75%+ marks','₹10,000/year','School & College','2026-12-31','open'),
 ('Girl Child Scholarship','girl-child-scholarship','Encouraging girl-child education.','Girl students; enrolled full-time','₹8,000/year','School','2026-12-31','open');

-- ----- Membership plans ------------------------------------------------------
INSERT INTO `membership_plans` (`name`,`slug`,`price`,`duration`,`benefits`,`is_featured`,`sort_order`,`status`) VALUES
 ('Supporter','supporter',500.00,'Annual','Newsletter updates\nInvitation to events\nAnnual impact report',0,1,1),
 ('Member','member',1000.00,'Annual','All Supporter benefits\nMembership certificate\nVoting rights at AGM',1,2,1),
 ('Patron','patron',5000.00,'Annual','All Member benefits\nRecognition on website\nPersonal impact briefing',0,3,1);

-- ----- Careers ---------------------------------------------------------------
INSERT INTO `careers` (`title`,`slug`,`department`,`location`,`type`,`description`,`requirements`,`openings`,`deadline`,`status`) VALUES
 ('Program Coordinator','program-coordinator','Programs','Patna, Bihar','full-time','<p>Coordinate and monitor field programs and volunteers.</p>','<p>Graduate; 2+ years in NGO/social sector; fluent Hindi &amp; English.</p>',2,'2026-09-30','open'),
 ('Field Volunteer','field-volunteer','Operations','Bihar','volunteer','<p>Support field activities, camps and events.</p>','<p>Passion for social work; willingness to travel.</p>',10,'2026-12-31','open'),
 ('Content & Communications Intern','comms-intern','Communications','Remote','internship','<p>Create content, manage social media and document impact.</p>','<p>Student/fresher; strong writing skills.</p>',3,'2026-10-15','open');

-- ----- Issued certificates (for public verification) ------------------------
INSERT INTO `issued_certificates` (`certificate_number`,`holder_name`,`email`,`type`,`program`,`issue_date`,`status`) VALUES
 ('PWF-VOL-2026-0001','Rakesh Singh','rakesh@example.com','volunteer','Volunteer Program 2026','2026-06-01','valid'),
 ('PWF-INT-2026-0002','Priya Kumari','priya@example.com','internship','Communications Internship','2026-05-20','valid'),
 ('PWF-TRN-2026-0003','Anita Kumari','anita@example.com','training','Nari Shakti Skill Training','2026-04-10','valid');

-- ----- SEO meta --------------------------------------------------------------
INSERT INTO `seo_meta` (`page_key`,`meta_title`,`meta_description`,`robots`) VALUES
 ('home','EDUSKILL INDIA FOUNDATION — NGO in Patna, Bihar','Empowering communities across Bihar through education, healthcare, skill development and relief. Donate or volunteer today.','index,follow'),
 ('about','About Us — EDUSKILL INDIA FOUNDATION','Learn about our mission, vision, leadership and the communities we serve across Bihar.','index,follow'),
 ('donate','Donate — EDUSKILL INDIA FOUNDATION','Support our work with a tax-deductible (80G) donation. Every rupee is tracked from donation to delivery.','index,follow');

-- ----- CMS pages -------------------------------------------------------------
INSERT INTO `pages` (`title`,`slug`,`subtitle`,`content`,`status`) VALUES
 ('Privacy Policy','privacy-policy','How we protect your data','<h2>Introduction</h2><p>EDUSKILL INDIA FOUNDATION respects your privacy and is committed to protecting your personal data.</p><h2>Information We Collect</h2><p>We collect information you provide when donating, volunteering or contacting us.</p><h2>How We Use It</h2><p>Your data is used only to process your request and communicate with you. We never sell your data.</p><h2>Contact</h2><p>Questions? Email info@eduskillindia.org.</p>','published'),
 ('Terms & Conditions','terms','Terms of use','<h2>Acceptance</h2><p>By using this website you agree to these terms.</p><h2>Donations</h2><p>Donations are voluntary and used to further our charitable objectives.</p><h2>Governing Law</h2><p>These terms are governed by the laws of India, jurisdiction Patna, Bihar.</p>','published');

-- ----- Sample inbox data (so admin looks alive) -----------------------------
INSERT INTO `contact_messages` (`name`,`email`,`phone`,`subject`,`message`,`status`) VALUES
 ('Vikram Rao','vikram@example.com','+91-9876543210','Partnership enquiry','We would like to explore a CSR partnership with your foundation.','unread'),
 ('Sneha Jain','sneha@example.com',NULL,'Volunteering','How can I volunteer for the health camps?','read');
INSERT INTO `feedback` (`name`,`email`,`subject`,`message`,`rating`,`status`) VALUES
 ('Manoj Kumar','manoj@example.com','Great work','The transparency and impact are truly commendable.',5,'new');
INSERT INTO `volunteers` (`name`,`email`,`phone`,`city`,`area_of_interest`,`availability`,`message`,`status`) VALUES
 ('Kavita Sharma','kavita@example.com','+91-9123456780','Patna','Education','Weekends','I would love to help teach children.','new'),
 ('Deepak Roy','deepak@example.com','+91-9988776655','Nalanda','Healthcare','Flexible','Available for medical camps.','reviewing');
INSERT INTO `donations` (`donor_name`,`email`,`amount`,`payment_method`,`status`,`campaign_id`) VALUES
 ('Amit Verma','amit@example.com',5000.00,'UPI','completed',1),
 ('Anonymous','',2500.00,'Bank Transfer','completed',2),
 ('Ritu Singh','ritu@example.com',1000.00,'Card','pending',1);
INSERT INTO `newsletter_subscribers` (`name`,`email`,`status`) VALUES
 ('Ravi Sharma','ravi@example.com','subscribed'),
 (NULL,'subscriber2@example.com','subscribed'),
 (NULL,'subscriber3@example.com','subscribed');
INSERT INTO `event_registrations` (`event_id`,`name`,`email`,`phone`,`guests`,`status`) VALUES
 (1,'Rahul Gupta','rahul@example.com','+91-9000011111',2,'confirmed'),
 (2,'Meena Kumari','meena@example.com','+91-9000022222',1,'pending');

-- ----- Documents (Download Center) ------------------------------------------
INSERT INTO `documents` (`title`,`slug`,`description`,`file_path`,`file_type`,`category`,`status`) VALUES
 ('Annual Report 2025-26','annual-report-2025-26','Our impact, finances and highlights for the year.','documents/eduskill-brochure.txt','txt','report',1),
 ('Organisation Brochure','organisation-brochure','An overview of who we are and what we do.','documents/eduskill-brochure.txt','txt','brochure',1),
 ('Volunteer Handbook','volunteer-handbook','Everything a new volunteer needs to know.','documents/eduskill-brochure.txt','txt','general',1);

SET FOREIGN_KEY_CHECKS = 1;
-- =============================================================================
--  End of sample data
-- =============================================================================
