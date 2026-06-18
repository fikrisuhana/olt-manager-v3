-- Default admin user
-- Username : admin
-- Password : admin123
-- GANTI PASSWORD setelah pertama kali login!

INSERT INTO users (name, username, email, password, role, created_at, updated_at)
VALUES (
    'Administrator',
    'admin',
    'admin@gpon.local',
    '$2y$10$n4OqEGThSlM3K4r98RWOKukIGPRhqKlWK3P108gSCQmO8BZfZWUUy',
    'admin',
    NOW(),
    NOW()
);
