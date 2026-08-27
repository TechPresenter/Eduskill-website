-- =============================================================================
--  Kanya Daan scheme + organisation bank details
-- -----------------------------------------------------------------------------
--  database/eduskill.sql only carries the table structure and demo seed. This
--  file adds the content entered since: the Kanya Daan project page and the
--  Axis Bank details shown on /donate and /ngo-details.
--
--  Safe to re-run — both statements replace rather than duplicate.
--      mysql -u eduskill -p eduskill < database/seed-kanyadaan.sql
-- =============================================================================

DELETE FROM `schemes` WHERE `slug` = 'kanya-daan-project';
INSERT INTO `schemes` (`title`, `subtitle`, `slug`, `category`, `department`, `short_description`, `description`, `eligibility`, `benefits`, `documents_required`, `apply_url`, `donate_url`, `image`, `brochure`, `brochures`, `deadline`, `is_featured`, `sort_order`, `status`, `created_at`, `updated_at`, `objectives`, `support_items`, `budget_note`, `process_steps`, `partnership`, `transparency`, `guidelines`, `faq`) VALUES
('Kanya Daan Project', 'बेटी के विवाह में सम्मानपूर्वक सहयोग', 'kanya-daan-project', 'Social Welfare', 'Eduskill India Foundation', 'Dignified support for economically weaker and needy families during the marriage of their eligible daughters — household essentials, marriage-related assistance, livelihood support and community participation.', '<p><strong>Kanya Daan Project</strong> is an initiative of Eduskill India Foundation to provide dignified support to
economically weaker and needy families during the marriage of their eligible daughters. The project focuses on
essential household support, marriage-related assistance, livelihood support and community participation.</p>

<p lang="hi"><strong>कन्या दान परियोजना</strong> Eduskill India Foundation की एक पहल है, जिसका उद्देश्य आर्थिक रूप से कमजोर
एवं जरूरतमंद परिवारों की बेटियों के विवाह में सम्मानजनक सहयोग प्रदान करना है। परियोजना के अंतर्गत आवश्यक घरेलू सामग्री,
विवाह संबंधी सहायता, आजीविका सहयोग एवं सामुदायिक भागीदारी को बढ़ावा दिया जाता है।</p>

<p><em>Support may include marriage-related household goods, clothing and essentials, utensils, necessary furniture,
educational or skill-development support, limited financial assistance and other necessary help. Actual assistance
depends on beneficiary need, verification, available funding and project approval.</em></p>', 'परिवार आर्थिक रूप से कमजोर हो।
बेटी विवाह योग्य आयु की हो और विवाह कानूनी रूप से वैध हो।
आवश्यक दस्तावेज उपलब्ध हों।
परिवार की वास्तविक आर्थिक आवश्यकता सत्यापित हो।
एक ही परिवार को एक निश्चित अवधि में एक बार सहायता देने को प्राथमिकता दी जाएगी।
लाभार्थी का चयन सत्यापन प्रक्रिया के बाद किया जाएगा।', 'जरूरतमंद परिवारों को आर्थिक राहत।
बेटियों के विवाह में सम्मानजनक सहयोग।
समुदाय में सामाजिक सहयोग एवं संवेदनशीलता में वृद्धि।
CSR एवं donor participation को बढ़ावा।
जरूरतमंद परिवारों की सामाजिक एवं आर्थिक कठिनाइयों में कमी।
समाज में बेटियों के सम्मान और आत्मनिर्भरता की भावना को बढ़ावा।', 'पहचान प्रमाण (Identity proof)
निवास प्रमाण (Residence proof)
आय प्रमाण / आर्थिक स्थिति का प्रमाण (Income proof)
बेटी की आयु का प्रमाण (Age proof of the daughter)
बैंक खाता विवरण (Bank account details)
परिवार की जानकारी (Family details)
विवाह संबंधी प्रमाण / आमंत्रण पत्र, जहाँ लागू हो
लाभार्थी की फोटो (Photograph)
Declaration / Undertaking', 'kanyadaan-apply', 'donate', 'schemes/wedding-garland-kanya-daan.webp', NULL, NULL, NULL, '1', '1', 'active', '2026-08-22 12:35:55', '2026-08-24 10:45:57', 'जरूरतमंद परिवारों की बेटियों के विवाह में सहयोग।
विवाह के लिए आवश्यक घरेलू एवं उपयोगी सामग्री उपलब्ध कराना।
आर्थिक रूप से कमजोर परिवारों को सामाजिक एवं मानवीय सहायता देना।
बेटियों के सम्मान और आत्मनिर्भरता को बढ़ावा देना।
समाज में सामुदायिक सहयोग एवं सामाजिक जिम्मेदारी की भावना विकसित करना।', 'विवाह उपयोगी सामग्री | ₹15,000
कपड़े एवं आवश्यक सामान | ₹8,000
घरेलू सामान | ₹7,000
अन्य आवश्यक सहायता | ₹5,000
कुल अनुमानित सहायता | ₹35,000 प्रति लाभार्थी', 'The indicative amount may vary according to donor/CSR funding, beneficiary requirements and the available project budget.
वास्तविक सहायता राशि donor/CSR funding, लाभार्थी की आवश्यकता और उपलब्ध project budget के अनुसार बदल सकती है।', 'आवेदन प्राप्त करना (Application received)
दस्तावेजों की जाँच (Document verification)
परिवार की आर्थिक स्थिति का सत्यापन (Economic verification)
पात्र लाभार्थियों की सूची तैयार करना (Eligible list prepared)
Project Committee द्वारा अनुमोदन (Committee approval)
सहायता सामग्री / सहायता राशि प्रदान करना (Support provided)
वितरण का रिकॉर्ड एवं Beneficiary Acknowledgement', 'CSR Companies
Corporate Partners
Individual Donors
Community Partners
Philanthropic Organisations', 'Beneficiary Verification
Proper Documentation
Approval Records
Distribution Records
Utilization Reporting
Donor / CSR Reporting', '<p><strong>Kanya Daan Project is a social-welfare initiative of Eduskill India Foundation.</strong> Assistance is
provided subject to eligibility, verification, project resources and approval.</p>
<p lang="hi">कन्या दान परियोजना Eduskill India Foundation की सामाजिक कल्याण पहल है। सहायता पात्रता, सत्यापन, उपलब्ध
संसाधनों एवं परियोजना की स्वीकृति के अधीन प्रदान की जाएगी।</p>
<p><strong>No dowry, ever.</strong> This project supports families in need — it is not, and must never be represented as,
assistance towards dowry, which is prohibited under the Dowry Prohibition Act, 1961. Support is provided only where the
marriage is legally permissible: the foundation does not assist any marriage involving a person below the legal age of
marriage, in line with the Prohibition of Child Marriage Act, 2006. Age is verified before approval.</p>
<p lang="hi"><strong>दहेज से कोई संबंध नहीं।</strong> यह परियोजना जरूरतमंद परिवारों की सहायता के लिए है; इसे किसी भी रूप
में दहेज सहायता के रूप में प्रस्तुत नहीं किया जाएगा। सहायता केवल उन्हीं विवाहों में दी जाएगी जो कानूनी रूप से वैध हों —
बाल विवाह में किसी भी प्रकार की सहायता नहीं दी जाएगी। आयु का सत्यापन अनुमोदन से पहले किया जाता है।</p>', 'Who can apply for support? :: Economically weaker and needy families with a daughter of legally marriageable age, in both rural and urban areas, who are found eligible after verification.
क्या सहायता नकद दी जाती है? :: सहायता मुख्यतः सामग्री के रूप में दी जाती है। सीमित आर्थिक सहायता परियोजना की स्वीकृति एवं उपलब्ध बजट पर निर्भर करती है।
How is a beneficiary selected? :: Applications are checked for documents, the family\x27s economic condition is verified in the field, an eligible list is prepared, and the Project Committee approves the final beneficiaries.
क्या एक परिवार एक से अधिक बार आवेदन कर सकता है? :: एक ही परिवार को एक निश्चित अवधि में एक बार सहायता देने को प्राथमिकता दी जाती है।
Does applying guarantee assistance? :: No. Assistance depends on eligibility, verification, available funding and project approval.
How can a company or donor support the project? :: Through CSR collaboration, financial support, material donations, volunteering or community outreach — write to info@eduskillindia.org.');

INSERT INTO `settings` (`key_name`, `value`, `group_name`, `type`) VALUES ('bank_account_name', 'EDUSKILL INDIA FOUNDATION', 'donation', 'text') ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
INSERT INTO `settings` (`key_name`, `value`, `group_name`, `type`) VALUES ('bank_name', 'Axis Bank', 'donation', 'text') ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
INSERT INTO `settings` (`key_name`, `value`, `group_name`, `type`) VALUES ('bank_account_number', '926020036340854', 'donation', 'text') ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
INSERT INTO `settings` (`key_name`, `value`, `group_name`, `type`) VALUES ('bank_ifsc', 'UTIB0002067', 'donation', 'text') ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
