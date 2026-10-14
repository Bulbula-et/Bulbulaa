-- ============================================================================
-- BULBULA.ET V1 — BOLE BULBULA OPERATIONAL PILOT SEED DATA (D-31)
-- Always pin the client connection charset: schema and seed may be imported in separate sessions.
SET NAMES utf8mb4;

-- Grounded in real Bole Bulbula, Addis Ababa local context (English + Amharic)
-- Every PUBLISHED branch has a linked GRANTED Permission (C-19) & PASSED QC (C-21).
-- ============================================================================

-- 1. System Readiness & Decision Gates (Page 14: D-04 to D-45)
INSERT INTO system_gates (gate_key, decision_ref, is_enabled, config_json) VALUES
('D_39_PAID_CAMPAIGNS_ENABLED', 'D-39', 1, '{"note":"Enabled for pilot preview; requires commercial/billing sign-off before commercial invoicing"}'),
('D_40_LAUNCH_AREA_BOUNDARY',   'D-40', 1, '{"launch_area_slug":"bole-bulbula","sub_city":"Bole"}'),
('D_45_STAFF_2FA_REQUIRED',     'D-45', 1, '{"require_totp_for_admin":true}'),
('D_31_OPERATIONAL_PILOT',      'D-31', 1, '{"pilot_target_businesses":20}')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- 2. Staff Users (Operator + Administrator — Gate D-45)
-- Default dev password hash corresponds to "BulbulaStaff2026!" (Bcrypt)
INSERT INTO staff_users (id, email, full_name, role, password_hash, totp_enabled, is_active) VALUES
(1, 'admin@bulbula.et',    'Dawit Tadesse (Admin)',    'ADMINISTRATOR', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1),
(2, 'operator@bulbula.et', 'Meron Alemu (Operator)',   'OPERATOR',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1),
(3, 'qc@bulbula.et',       'Yonas Bekele (QC Lead)',   'ADMINISTRATOR', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- 3. Controlled Two-Level Taxonomy (C-04, C-23: Level 1 Categories -> Level 2 Subcategories)
INSERT INTO categories (id, parent_id, depth_level, slug, name_en, name_am, description_en, icon_key, display_order, is_active) VALUES
-- Level 1 Categories
(1,  NULL, 1, 'health-medical',     'Health & Medical',       'ጤና እና ሕክምና',          'Clinics, pharmacies, dental practices, and diagnostic labs.', 'heart-pulse', 1, 1),
(2,  NULL, 1, 'food-coffee',        'Food, Coffee & Dining',  'ምግብ፣ ቡና እና ሬስቶራንት',  'Specialty coffee roasters, restaurants, bakeries, and cafes.', 'coffee',      2, 1),
(3,  NULL, 1, 'groceries-retail',   'Supermarkets & Retail',  'ሱፐርማርኬት እና ችርቻሮ',    'Supermarkets, mini-markets, fresh produce, and household essentials.', 'shopping-bag', 3, 1),
(4,  NULL, 1, 'education-childcare','Education & Childcare',  'ትምህርት እና ህፃናት ክብካቤ', 'Kindergartens, academies, language schools, and training centers.', 'graduation-cap', 4, 1),
(5,  NULL, 1, 'home-auto-services', 'Home, Auto & Services',  'የቤት፣ መኪና እና አገልግሎቶች','Garages, salons, tailoring, laundry, and electronics repair.', 'wrench',      5, 1),
(6,  NULL, 1, 'finance-professional','Finance & Professional','ፋይናንስ እና ሙያዊ አገልግሎት','Bank branches, insurance offices, legal, and accounting services.', 'building-bank', 6, 1),

-- Level 2 Subcategories
(11, 1, 2, 'clinics-medical-centers', 'Clinics & Medical Centers', 'ክሊኒኮች እና ሕክምና ማዕከላት', 'General and medium clinics in Bole Bulbula.', 'stethoscope', 1, 1),
(12, 1, 2, 'pharmacies',              'Pharmacies',                'ፋርማሲዎች / መድኃኒት ቤቶች',  'Licensed community pharmacies and drug stores.', 'pill',        2, 1),
(13, 1, 2, 'dental-clinics',          'Dental Clinics',            'የጥርስ ሕክምና ክሊኒኮች',     'Dental care, orthodontics, and oral surgery.', 'smile',       3, 1),
(21, 2, 2, 'cafes-coffee-roasters',   'Cafes & Coffee Roasters',   'ካፌዎች እና የቡና መቆያዎች',   'Traditional and modern specialty coffee houses.', 'coffee',      1, 1),
(22, 2, 2, 'restaurants',             'Restaurants',               'ሬስቶራንቶች / ምግብ ቤቶች',    'Ethiopian and international dining branches.', 'utensils',    2, 1),
(23, 2, 2, 'bakeries-pastry',         'Bakeries & Pastry',         'ዳቦ ቤቶች እና ኬክ ቤቶች',    'Fresh bread, pastries, and custom cakes.', 'croissant',   3, 1),
(31, 3, 2, 'supermarkets',            'Supermarkets',              'ሱፐርማርኬቶች',            'Full-service grocery and household supermarkets.', 'shopping-cart', 1, 1),
(41, 4, 2, 'kindergartens-schools',   'Kindergartens & Schools',   'አፀደ ህፃናት እና ትምህርት ቤቶች','Early childhood and primary education.', 'book-open',   1, 1),
(51, 5, 2, 'auto-repair-garages',     'Auto Repair & Car Wash',    'ጋራዥ እና የመኪና እጥበት',    'Mechanical maintenance, tire service, and car wash.', 'car',         1, 1),
(52, 5, 2, 'salons-barbershops',      'Salons & Barbershops',      'የውበት ሳሎኖች እና ፀጉር ቤቶች','Hair styling, grooming, and spa services.', 'scissors',    2, 1)
ON DUPLICATE KEY UPDATE name_en = VALUES(name_en);

-- Controlled Category Aliases (C-02, C-03, C-04)
INSERT INTO category_aliases (category_id, alias_term, language_code) VALUES
(11, 'hakim bet', 'translit'),
(11, 'doctor', 'en'),
(11, 'ሐኪም ቤት', 'am'),
(12, 'medhanit bet', 'translit'),
(12, 'drug store', 'en'),
(21, 'buna', 'translit'),
(21, 'macchiato', 'en'),
(23, 'dabo bet', 'translit'),
(31, 'grocery', 'en'),
(51, 'garage', 'en'),
(51, 'mechanic', 'en')
ON DUPLICATE KEY UPDATE language_code = VALUES(language_code);

-- 4. Controlled Local Areas & Sub-Cities (C-05, C-24, Gate D-40)
INSERT INTO areas (id, slug, name_en, name_am, sub_city_en, sub_city_am, center_latitude, center_longitude, is_launch_area, is_active, display_order) VALUES
(1, 'bole-bulbula',       'Bole Bulbula',             'ቦሌ ቡልቡላ',             'Bole', 'ቦሌ', 8.9538000, 38.7892000, 1, 1, 1),
(2, 'bulbula-93-mazoria', 'Bulbula 93 Mazoria',       'ቡልቡላ 93 ማዞሪያ',        'Bole', 'ቦሌ', 8.9482000, 38.7915000, 1, 1, 2),
(3, 'bulbula-mariam',     'Bulbula St. Mary (Mariam)','ቡልቡላ ማርያም አካባቢ',      'Bole', 'ቦሌ', 8.9574000, 38.7861000, 1, 1, 3),
(4, 'bulbula-condominium','Bulbula Condominium',      'ቡልቡላ ኮንዶሚኒየም',        'Bole', 'ቦሌ', 8.9441000, 38.7948000, 1, 1, 4)
ON DUPLICATE KEY UPDATE name_en = VALUES(name_en);

INSERT INTO area_aliases (area_id, alias_term, language_code) VALUES
(1, 'bulbula', 'en'),
(1, 'ቡልቡላ', 'am'),
(2, '93 mazoria', 'translit'),
(2, '93 ማዞሪያ', 'am'),
(3, 'mariam', 'translit'),
(3, 'bulbula mariam', 'en')
ON DUPLICATE KEY UPDATE language_code = VALUES(language_code);

-- 5. Businesses (Brand-Level Facts Only — Page 3, C-08)
INSERT INTO businesses (id, slug, name_en, name_am, primary_category_id, brand_summary_en, brand_description_en, website_url, lifecycle_status, created_by_staff_id) VALUES
(1, 'birhan-medium-clinic',  'Birhan Medium Clinic',         'ብርሃን መካከለኛ ክሊኒክ',     11, 'Trusted family medical clinic and diagnostic laboratory serving Bole Bulbula.', 'Birhan Medium Clinic provides outpatient consultations, maternal care, pediatric checkups, and rapid laboratory diagnostics with experienced physicians.', 'https://birhanclinic.et', 'PUBLISHED', 2),
(2, 'kalehiwot-pharmacy',    'Kalehiwot Community Pharmacy', 'ቃለሕይወት ፋርማሲ',         12, 'Licensed community pharmacy with prescription medications and pharmacist consultation.', 'Full-service licensed pharmacy stocking essential prescription medicines, pediatric care items, blood pressure monitoring, and medical supplies.', NULL, 'PUBLISHED', 2),
(3, 'adey-specialty-coffee', 'Adey Specialty Coffee & Roastery', 'አደይ ስፔሻሊቲ ቡና እና ካፌ', 21, 'Single-origin Ethiopian Arabica coffee, fresh pastries, and quiet workspace in Bulbula.', 'Roasting washed Yirgacheffe and Guji beans daily. Offers espresso, traditionaljebena coffee, fresh croissants, and reliable Wi-Fi for neighborhood visitors.', 'https://adeycoffee.et', 'PUBLISHED', 2),
(4, 'zemen-fresh-supermarket','Zemen Fresh Mart & Bakery',   'ዘመን ፍሬሽ ሱፐርማርኬት',     31, 'Neighborhood supermarket stocking fresh produce, dairy, pantry staples, and in-house bakery.', 'Multi-branch local supermarket offering farm-fresh fruits and vegetables, packaged groceries, household goods, and warm bread baked every morning and evening.', 'https://zemenfresh.et', 'PUBLISHED', 2),
(5, 'abyssinia-dental-care', 'Abyssinia Precision Dental',   'አቢሲኒያ የጥርስ ሕክምና ክሊኒክ', 13, 'Modern dental clinic offering preventive care, root canals, and pediatric dentistry.', 'Equipped with digital X-ray imaging and sterilization standards, providing gentle dental cleanings, fillings, crowns, and emergency tooth pain relief.', NULL, 'PUBLISHED', 2),
(6, 'tesfa-auto-garage',     'Tesfa Diagnostic Auto Garage', 'ተስፋ ዘመናዊ ጋራዥ',        51, 'Computerized engine diagnostics, brake service, oil change, and Express Car Wash.', 'Serving Bole Bulbula motorists withOBD-II computerized diagnostics, suspension repair, genuine oil filters, and eco-friendly high-pressure car washing.', NULL, 'PUBLISHED', 2)
ON DUPLICATE KEY UPDATE name_en = VALUES(name_en);

-- Secondary Categories (C-04: Zero or more, never duplicating Primary Category)
INSERT INTO business_secondary_categories (business_id, category_id) VALUES
(3, 23), -- Adey Coffee also in Bakeries & Pastry
(4, 23)  -- Zemen Fresh Mart also in Bakeries & Pastry
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id);

-- 6. Business Permission Records (Hard Publication Gate C-19)
INSERT INTO business_permissions (id, business_id, status, rep_full_name, rep_role_title, rep_phone, rep_email, permission_method, evidence_storage_path, scope_notes, collected_by_staff_id, granted_at) VALUES
(1, 1, 'GRANTED', 'Dr. Solomon Mengistu', 'Medical Director', '+251911234501', 'dr.solomon@birhanclinic.et', 'SIGNED_FORM', 'permissions_private/perm_001_birhan.pdf', 'Approved brand profile, 2 branches, hours, and exterior/interior photos.', 2, '2026-10-05 10:15:00'),
(2, 2, 'GRANTED', 'Pharmacist Hanna Girma','Owner & Chief Pharmacist', '+251911234502', NULL, 'RECORDED_VISIT', 'permissions_private/perm_002_kalehiwot.pdf', 'In-person verification & signed consent form at Mariam branch.', 2, '2026-10-06 11:30:00'),
(3, 3, 'GRANTED', 'Brook Tesfaye',        'Co-Founder',       '+251911234503', 'brook@adeycoffee.et', 'SIGNED_FORM', 'permissions_private/perm_003_adey.pdf', 'Full permission for listing, photos, and opening hours.', 2, '2026-10-07 14:20:00'),
(4, 4, 'GRANTED', 'Ato Elias Kebede',     'General Manager',  '+251911234504', 'info@zemenfresh.et', 'SIGNED_FORM', 'permissions_private/perm_004_zemen.pdf', 'Covers both Bole Bulbula Main and 93 Mazoria Express branches.', 2, '2026-10-08 09:45:00'),
(5, 5, 'GRANTED', 'Dr. Selamawit Haile',  'Lead Dentist',     '+251911234505', NULL, 'VERIFIED_EMAIL', 'permissions_private/perm_005_abyssinia.pdf', 'Approved clinic contact details and appointment hours.', 2, '2026-10-09 16:00:00'),
(6, 6, 'GRANTED', 'Eng.Teshome Abebe',    'Workshop Manager', '+251911234506', NULL, 'RECORDED_VISIT', 'permissions_private/perm_006_tesfa.pdf', 'Field collection signed on site at 93 Mazoria.', 2, '2026-10-10 13:10:00')
ON DUPLICATE KEY UPDATE status = VALUES(status);

-- 7. Physical Branches (Page 3, C-05..C-12)
-- Note: Zemen Fresh Supermarket (business_id=4) has TWO branches to demonstrate C-08 multi-branch support!
INSERT INTO branches (
    id, business_id, active_permission_id, branch_slug, branch_name_en, branch_name_am,
    is_primary_branch, area_id, sub_city_en, address_line_en, landmark_notes_en,
    latitude, longitude, phone_primary, phone_secondary, branch_email,
    opening_hours_status, opening_hours_json, services_products_json,
    publication_status, is_verified, last_verified_at, last_verified_by_staff_id,
    published_at, published_rating_avg, published_review_count, created_by_staff_id
) VALUES
(
    1, 1, 1, 'bole-bulbula-main', 'Birhan Medium Clinic — Bole Bulbula Main', 'ብርሃን መካከለኛ ክሊኒክ (ዋና ቅርንጫፍ)',
    1, 1, 'Bole', 'Bole Bulbula Main Asphalt, Woreda 12', '150m south of Bulbula Total Fuel Station, Ground + 2 White Building',
    8.9541200, 38.7894500, '+251911234501', '+251116678901', 'main@birhanclinic.et',
    'KNOWN',
    '{"mon":{"open":"07:30","close":"21:00"},"tue":{"open":"07:30","close":"21:00"},"wed":{"open":"07:30","close":"21:00"},"thu":{"open":"07:30","close":"21:00"},"fri":{"open":"07:30","close":"21:00"},"sat":{"open":"08:00","close":"20:00"},"sun":{"open":"08:30","close":"18:00"}}',
    '["General Outpatient Consultation","Complete Blood Count (CBC) Lab","Ultrasound & ECG","Maternal & Pediatric Checkups"]',
    'PUBLISHED', 1, '2026-10-11 09:00:00', 3, '2026-10-11 09:05:00', 4.67, 3, 2
),
(
    2, 2, 2, 'bulbula-mariam-branch', 'Kalehiwot Pharmacy — St. Mary Branch', 'ቃለሕይወት ፋርማሲ (ማርያም ቅርንጫፍ)',
    1, 3, 'Bole', 'Bulbula St. Mary Road, Woreda 12', 'Directly opposite St. Mary Church commercial plaza',
    8.9571000, 38.7864000, '+251911234502', NULL, NULL,
    'KNOWN',
    '{"mon":{"open":"08:00","close":"22:00"},"tue":{"open":"08:00","close":"22:00"},"wed":{"open":"08:00","close":"22:00"},"thu":{"open":"08:00","close":"22:00"},"fri":{"open":"08:00","close":"22:00"},"sat":{"open":"08:00","close":"22:00"},"sun":{"open":"09:00","close":"21:00"}}',
    '["Prescription Fulfillment","Infant Formula & Care","Blood Pressure & Glucose Check","First-Aid Supplies"]',
    'PUBLISHED', 1, '2026-10-11 10:30:00', 3, '2026-10-11 10:35:00', 5.00, 2, 2
),
(
    3, 3, 3, 'bulbula-roastery-lounge', 'Adey Specialty Coffee — Bulbula Lounge', 'አደይ ስፔሻሊቲ ቡና (ቡልቡላ ላውንጅ)',
    1, 1, 'Bole', 'Bole Bulbula Main Road, Amen Plaza Ground Floor', 'Next to Dashen Bank Bulbula Branch',
    8.9534000, 38.7889000, '+251911234503', NULL, 'lounge@adeycoffee.et',
    'KNOWN',
    '{"mon":{"open":"06:30","close":"20:30"},"tue":{"open":"06:30","close":"20:30"},"wed":{"open":"06:30","close":"20:30"},"thu":{"open":"06:30","close":"20:30"},"fri":{"open":"06:30","close":"21:00"},"sat":{"open":"07:00","close":"21:00"},"sun":{"open":"07:00","close":"20:30"}}',
    '["Washed Yirgacheffe Pour-Over","Traditional Clay-Pot Buna","Almond Croissants & Danish","250g & 500g Roasted Whole Beans"]',
    'PUBLISHED', 1, '2026-10-11 11:15:00', 3, '2026-10-11 11:20:00', 4.50, 2, 2
),
(
    4, 4, 4, 'bulbula-main-mart', 'Zemen Fresh Mart — Bulbula Main Branch', 'ዘመን ፍሬሽ ሱፐርማርኬት (ዋና ቅርንጫፍ)',
    1, 1, 'Bole', 'Bole Bulbula Central Square', 'Ground floor of Edna Commercial Center',
    8.9529000, 38.7898000, '+251911234504', '+251116679922', 'bulbula@zemenfresh.et',
    'KNOWN',
    '{"mon":{"open":"07:00","close":"21:30"},"tue":{"open":"07:00","close":"21:30"},"wed":{"open":"07:00","close":"21:30"},"thu":{"open":"07:00","close":"21:30"},"fri":{"open":"07:00","close":"21:30"},"sat":{"open":"07:00","close":"22:00"},"sun":{"open":"07:30","close":"21:00"}}',
    '["Fresh Organic Produce","In-House Wood-Fired Bakery","Imported & Local Groceries","Dairy & Butcher Counter"]',
    'PUBLISHED', 1, '2026-10-11 14:00:00', 3, '2026-10-11 14:10:00', 4.00, 1, 2
),
(
    5, 4, 4, '93-mazoria-express', 'Zemen Fresh Mart — 93 Mazoria Express', 'ዘመን ፍሬሽ ሱፐርማርኬት (93 ማዞሪያ ቅርንጫፍ)',
    0, 2, 'Bole', 'Bulbula 93 Mazoria Roundabout', '50m east of 93 Mazoria taxi terminal',
    8.9484000, 38.7918000, '+251911234599', NULL, '93mazoria@zemenfresh.et',
    'KNOWN',
    '{"mon":{"open":"07:30","close":"21:00"},"tue":{"open":"07:30","close":"21:00"},"wed":{"open":"07:30","close":"21:00"},"thu":{"open":"07:30","close":"21:00"},"fri":{"open":"07:30","close":"21:00"},"sat":{"open":"07:30","close":"21:00"},"sun":{"open":"08:00","close":"20:30"}}',
    '["Daily Fresh Bread","Express Grocery Essentials","Bottled Water & Beverages"]',
    'PUBLISHED', 1, '2026-10-11 14:30:00', 3, '2026-10-11 14:35:00', NULL, 0, 2
),
(
    6, 5, 5, 'bulbula-dental-center', 'Abyssinia Precision Dental — Bulbula Branch', 'አቢሲኒያ የጥርስ ሕክምና ክሊኒክ',
    1, 3, 'Bole', 'Bulbula Mariam Junction, 2nd Floor', 'Above Abyssinia Bank Bulbula Mariam Branch',
    8.9569000, 38.7867000, '+251911234505', NULL, NULL,
    'BY_APPOINTMENT',
    NULL,
    '["Ultrasonic Scaling & Polishing","Root Canal Therapy","Pediatric Dentistry","Digital Panoramic X-Ray"]',
    'PUBLISHED', 1, '2026-10-12 09:00:00', 3, '2026-10-12 09:10:00', 5.00, 1, 2
),
(
    7, 6, 6, '93-mazoria-workshop', 'Tesfa Diagnostic Auto Garage — 93 Mazoria', 'ተስፋ ዘመናዊ ጋራዥ (93 ማዞሪያ)',
    1, 2, 'Bole', 'Bulbula 93 Mazoria Industrial Compound', 'Behind NOC Fuel Station at 93 Mazoria',
    8.9479000, 38.7921000, '+251911234506', NULL, NULL,
    'UNKNOWN',
    NULL,
    '["OBD-II Engine Diagnostics","Brake Pad & Disc Service","Synthetic Oil Change","High-Pressure Steam Car Wash"]',
    'PUBLISHED', 0, NULL, NULL, '2026-10-12 11:00:00', NULL, 0, 2
)
ON DUPLICATE KEY UPDATE branch_name_en = VALUES(branch_name_en);

-- 8. Verification & Quality Control Records (Hard Gate C-21)
INSERT INTO verification_reviews (id, branch_id, permission_id, submitted_by_staff_id, reviewed_by_staff_id, outcome, checklist_json, reviewer_notes) VALUES
(1, 1, 1, 2, 3, 'PASSED', '{"permission_verified":true,"coordinates_checked":true,"category_checked":true,"phone_tested":true}', 'All physical details and Medical Director permission verified on site.'),
(2, 2, 2, 2, 3, 'PASSED', '{"permission_verified":true,"coordinates_checked":true,"category_checked":true,"phone_tested":true}', 'Verified pharmacy license display and phone contact.'),
(3, 3, 3, 2, 3, 'PASSED', '{"permission_verified":true,"coordinates_checked":true,"category_checked":true,"phone_tested":true}', 'Passed second-reviewer QC check.'),
(4, 4, 4, 2, 3, 'PASSED', '{"permission_verified":true,"coordinates_checked":true,"category_checked":true,"phone_tested":true}', 'Main branch verified.'),
(5, 5, 4, 2, 3, 'PASSED', '{"permission_verified":true,"coordinates_checked":true,"category_checked":true,"phone_tested":true}', '93 Mazoria sibling branch verified under permission #4.'),
(6, 6, 5, 2, 3, 'PASSED', '{"permission_verified":true,"coordinates_checked":true,"category_checked":true,"phone_tested":true}', 'Dental clinic verified; hours marked BY_APPOINTMENT per C-09.'),
(7, 7, 6, 2, 3, 'PASSED', '{"permission_verified":true,"coordinates_checked":true,"category_checked":true,"phone_tested":true}', 'Passed publication QC; hours left UNKNOWN per C-09 until confirmed.')
ON DUPLICATE KEY UPDATE outcome = VALUES(outcome);

-- 9. Customers & Branch Reviews (C-13, C-25, C-30..C-35)
INSERT INTO customers (id, email, display_name, account_status, email_verified_at, last_active_surface) VALUES
(1, 'abebe.kebede@example.com', 'Abebe K.',   'ACTIVE', '2026-10-11 12:00:00', 'WEB'),
(2, 'liya.tadesse@example.com', 'Liya T.',    'ACTIVE', '2026-10-11 15:30:00', 'TELEGRAM_MINI_APP'),
(3, 'samuel.hailu@example.com', 'Samuel H.',  'ACTIVE', '2026-10-12 08:20:00', 'WEB')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

INSERT INTO reviews (id, branch_id, customer_id, rating, review_text, status, edit_count, first_submitted_at, moderated_by_staff_id, moderated_at, moderation_reason_code) VALUES
(1, 1, 1, 5, 'Very clean clinic on the main Bulbula road. The lab results for my child came back in under 40 minutes and the doctor explained everything clearly.', 'PUBLISHED', 0, '2026-10-11 16:10:00', 3, '2026-10-11 17:00:00', 'POLICY_COMPLIANT'),
(2, 1, 2, 4, 'Reliable evening service when most clinics nearby are already closed. Parking in front can be busy around 6 PM.', 'PUBLISHED', 0, '2026-10-12 09:15:00', 3, '2026-10-12 10:00:00', 'POLICY_COMPLIANT'),
(3, 1, 3, 5, 'Friendly reception and accurate opening hours.', 'PUBLISHED', 0, '2026-10-12 14:00:00', 3, '2026-10-12 14:30:00', 'POLICY_COMPLIANT'),
(4, 2, 1, 5, 'The pharmacist took time to explain the dosage in Amharic and English. Always well stocked near Mariam.', 'PUBLISHED', 0, '2026-10-12 11:20:00', 3, '2026-10-12 12:00:00', 'POLICY_COMPLIANT'),
(5, 2, 2, 5, 'Open until 10 PM as listed. Saved us a late-night trip to Bole Medhanialem.', 'PUBLISHED', 0, '2026-10-12 19:45:00', 3, '2026-10-12 20:10:00', 'POLICY_COMPLIANT'),
(6, 3, 2, 5, 'Best macchiato and almond croissant in Bole Bulbula! Quiet upstairs seating for working.', 'PUBLISHED', 0, '2026-10-12 13:00:00', 3, '2026-10-12 13:30:00', 'POLICY_COMPLIANT'),
(7, 3, 3, 4, 'Great roasted beans to take home, slightly busy on Sunday mornings.', 'PUBLISHED', 0, '2026-10-13 09:00:00', 3, '2026-10-13 09:40:00', 'POLICY_COMPLIANT'),
(8, 4, 1, 4, 'Fresh vegetables and warm dabo every evening. Very convenient having both the main branch and the 93 Mazoria branch listed separately.', 'PUBLISHED', 0, '2026-10-13 10:15:00', 3, '2026-10-13 11:00:00', 'POLICY_COMPLIANT'),
(9, 6, 3, 5, 'Booked by phone ahead of time—zero waiting time and spotless equipment.', 'PUBLISHED', 0, '2026-10-13 12:00:00', 3, '2026-10-13 12:30:00', 'POLICY_COMPLIANT'),
-- 1 Pending Moderation Review to demonstrate Staff Moderation Queue (C-25)
(10, 4, 2, 5, 'Their new bakery section has excellent whole-wheat bread.', 'PENDING_MODERATION', 0, '2026-10-14 08:30:00', NULL, NULL, NULL)
ON DUPLICATE KEY UPDATE status = VALUES(status);

-- 10. Staff-Managed Sponsorship Campaigns (C-16, C-27)
-- Clearly separated from organic rankings; shows all 3 approved V1 formats.
INSERT INTO sponsorship_campaigns (
    id, campaign_code, business_id, branch_id, placement_format,
    target_category_id, target_area_id, package_name, agreed_price_etb,
    starts_at, ends_at, status, created_by_staff_id, approved_by_admin_id, approved_at
) VALUES
(1, 'SPON-2026-001', 1, 1, 'HOMEPAGE_PROMOTION',   1,    1, 'V1 Pilot Homepage Spotlight (14 Days)', 4500.00, '2026-10-01 00:00:00', '2026-12-31 23:59:59', 'ACTIVE', 2, 1, '2026-10-11 09:30:00'),
(2, 'SPON-2026-002', 3, 3, 'CATEGORY_SPONSORSHIP', 2,    1, 'Food & Coffee Category Feature (30 Days)', 3500.00, '2026-10-01 00:00:00', '2026-12-31 23:59:59', 'ACTIVE', 2, 1, '2026-10-11 11:45:00'),
(3, 'SPON-2026-003', 4, 4, 'SEARCH_PLACEMENT',     31,   1, 'Supermarket Search Placement (30 Days)', 3000.00, '2026-10-01 00:00:00', '2026-12-31 23:59:59', 'ACTIVE', 2, 1, '2026-10-11 15:00:00')
ON DUPLICATE KEY UPDATE status = VALUES(status);
