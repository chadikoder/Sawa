-- Sawa — Seed lookups + default admin (change password immediately in production)

SET NAMES utf8mb4;

INSERT INTO categories (slug, name_en, name_ar, sort_order) VALUES
('medical', 'Medical', 'طبي', 1),
('educational', 'Educational', 'تعليمي', 2),
('food', 'Food', 'غذاء', 3),
('shelter', 'Shelter', 'مأوى', 4),
('other', 'Other', 'أخرى', 99)
ON DUPLICATE KEY UPDATE name_en = VALUES(name_en);

INSERT INTO locations (slug, name_en, name_ar, region, sort_order) VALUES
('beirut', 'Beirut', 'بيروت', 'Beirut', 1),
('tripoli', 'Tripoli', 'طرابلس', 'North', 2),
('akkar', 'Akkar', 'عكار', 'North', 3),
('batroun', 'Batroun', 'البترون', 'North', 4),
('baalbek', 'Baalbek', 'بعلبك', 'Bekaa', 5),
('south_lb', 'South Lebanon', 'جنوب لبنان', 'South', 6)
ON DUPLICATE KEY UPDATE name_en = VALUES(name_en);

-- Default admin password set by migrate.php (Admin123 — change in production)
INSERT INTO users (email, password_hash, full_name, role, email_verified, active)
SELECT 'admin@sawa.local',
       '$2y$10$placeholderwillbeupdatedbymigratephp000000000000000000000',
       'Sawa Admin',
       'admin',
       1,
       1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@sawa.local');

-- Demo accounts (passwords set by migrate.php — Demo123!)
INSERT INTO users (email, password_hash, full_name, role, email_verified, active)
SELECT 'donor@sawa.local',
       '$2y$10$placeholderwillbeupdatedbymigratephp000000000000000000000',
       'Sawa Donor',
       'user',
       1,
       1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'donor@sawa.local');

INSERT INTO users (email, password_hash, full_name, role, email_verified, active)
SELECT 'beneficiary@sawa.local',
       '$2y$10$placeholderwillbeupdatedbymigratephp000000000000000000000',
       'Lina Recipient',
       'beneficiary',
       1,
       1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'beneficiary@sawa.local');

INSERT INTO users (email, password_hash, full_name, role, email_verified, active)
SELECT 'org@sawa.local',
       '$2y$10$placeholderwillbeupdatedbymigratephp000000000000000000000',
       'Community Care NGO',
       'organisation',
       1,
       1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'org@sawa.local');

INSERT INTO organisations (user_id, name, description, verified, verified_at)
SELECT u.id,
       'Community Care NGO',
       'Verified Lebanese charity supporting families in need.',
       1,
       NOW()
FROM users u
WHERE u.email = 'org@sawa.local'
  AND NOT EXISTS (SELECT 1 FROM organisations o WHERE o.user_id = u.id);

INSERT INTO campaigns (
    owner_user_id, title, summary, description, goal_amount, raised_amount,
    category_id, location_id, status, ends_at
)
SELECT u.id,
       'Medical aid for my daughter Lina',
       'Lina needs ongoing treatment at Saint George Hospital.',
       'Lina is 4 and needs ongoing treatment at Saint George Hospital. Every donation helps cover medication and hospital visits.',
       10000.00,
       3200.00,
       (SELECT id FROM categories WHERE slug = 'medical' LIMIT 1),
       (SELECT id FROM locations WHERE slug = 'beirut' LIMIT 1),
       'active',
       DATE_ADD(NOW(), INTERVAL 5 DAY)
FROM users u
WHERE u.email = 'beneficiary@sawa.local'
  AND NOT EXISTS (
      SELECT 1 FROM campaigns c WHERE c.owner_user_id = u.id AND c.title = 'Medical aid for my daughter Lina'
  );

INSERT INTO campaigns (
    organisation_id, title, summary, description, goal_amount, raised_amount,
    category_id, location_id, status, ends_at
)
SELECT o.id,
       'Help Sara Beat Cancer',
       '6-year-old Sara needs urgent chemotherapy.',
       '6-year-old Sara needs urgent chemotherapy in Beirut. Every dollar brings her closer to recovery.',
       20000.00,
       14400.00,
       (SELECT id FROM categories WHERE slug = 'medical' LIMIT 1),
       (SELECT id FROM locations WHERE slug = 'beirut' LIMIT 1),
       'active',
       DATE_ADD(NOW(), INTERVAL 4 DAY)
FROM organisations o
INNER JOIN users u ON u.id = o.user_id AND u.email = 'org@sawa.local'
WHERE NOT EXISTS (
    SELECT 1 FROM campaigns c WHERE c.organisation_id = o.id AND c.title = 'Help Sara Beat Cancer'
);

INSERT INTO campaigns (
    organisation_id, title, summary, description, goal_amount, raised_amount,
    category_id, location_id, status, ends_at
)
SELECT o.id,
       'Winter Food Packages',
       'Warm meals for 50 families in Tripoli.',
       'Winter Food Packages delivers warm meals and staples to 50 families in Tripoli during the cold season.',
       10000.00,
       8800.00,
       (SELECT id FROM categories WHERE slug = 'food' LIMIT 1),
       (SELECT id FROM locations WHERE slug = 'tripoli' LIMIT 1),
       'active',
       DATE_ADD(NOW(), INTERVAL 14 DAY)
FROM organisations o
INNER JOIN users u ON u.id = o.user_id AND u.email = 'org@sawa.local'
WHERE NOT EXISTS (
    SELECT 1 FROM campaigns c WHERE c.organisation_id = o.id AND c.title = 'Winter Food Packages'
);

INSERT INTO campaigns (
    organisation_id, title, summary, description, goal_amount, raised_amount,
    category_id, location_id, status, ends_at
)
SELECT o.id,
       'School Supplies for Akkar',
       '120 children need notebooks and uniforms.',
       'School Supplies for Akkar equips 120 children with notebooks, uniforms, and backpacks before the new term.',
       5000.00,
       2250.00,
       (SELECT id FROM categories WHERE slug = 'educational' LIMIT 1),
       (SELECT id FROM locations WHERE slug = 'akkar' LIMIT 1),
       'active',
       DATE_ADD(NOW(), INTERVAL 21 DAY)
FROM organisations o
INNER JOIN users u ON u.id = o.user_id AND u.email = 'org@sawa.local'
WHERE NOT EXISTS (
    SELECT 1 FROM campaigns c WHERE c.organisation_id = o.id AND c.title = 'School Supplies for Akkar'
);

INSERT INTO campaigns (
    organisation_id, title, summary, description, goal_amount, raised_amount,
    category_id, location_id, status, ends_at
)
SELECT o.id,
       'Emergency Roof Repair',
       'Families in Beirut need safe shelter before winter rain.',
       'Emergency Roof Repair funds urgent roof sealing and tarpaulins for families whose homes were damaged.',
       10000.00,
       3100.00,
       (SELECT id FROM categories WHERE slug = 'shelter' LIMIT 1),
       (SELECT id FROM locations WHERE slug = 'beirut' LIMIT 1),
       'active',
       DATE_ADD(NOW(), INTERVAL 3 DAY)
FROM organisations o
INNER JOIN users u ON u.id = o.user_id AND u.email = 'org@sawa.local'
WHERE NOT EXISTS (
    SELECT 1 FROM campaigns c WHERE c.organisation_id = o.id AND c.title = 'Emergency Roof Repair'
);
