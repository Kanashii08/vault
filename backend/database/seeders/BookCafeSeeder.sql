-- Seeder: Insert default users and cubicles
-- Run this SQL in phpMyAdmin or MySQL CLI AFTER running migrations
-- 
-- Default accounts:
--   Admin: admin@bc.com / admin123
--   Staff: staff@bc.com / staff123
--
-- Note: The admin user (id=1) is protected and cannot be deleted.
-- New users can register via the frontend.

-- Clear existing data (optional, be careful in production)
-- TRUNCATE TABLE bookings;
-- TRUNCATE TABLE personal_access_tokens;
-- TRUNCATE TABLE cubicles;
-- TRUNCATE TABLE users;

-- Insert default users
-- Admin: admin@bc.com / admin123
-- Staff: staff@bc.com / staff123
INSERT INTO users (first_name, last_name, email, password, role, avatar_url, created_at, updated_at) VALUES
('Admin', 'User', 'admin@bc.com', '$2y$10$xPLP0LD0YS5pXmGdLU4zAOWhXPKgAOhFfLaGQlHmourxwKH8RvEGq', 'admin', 'https://moodleaands.muccs.site/backend/storage/avatars/default-admin.png', NOW(), NOW()),
('Staff', 'User', 'staff@bc.com', '$2y$10$Dowt/FMjLn3ES.YgDDdoMeZXZ6T.PYE0YRlHO0FMjvPNzVMHKKdDy', 'staff', 'https://moodleaands.muccs.site/backend/storage/avatars/default-staff.png', NOW(), NOW());

-- Insert sample cubicles
INSERT INTO cubicles (name, description, hourly_rate, has_beer, created_at, updated_at) VALUES
('Quiet Nook 1', 'Perfect for solo reading and napping. Cozy corner with soft lighting.', 120.00, 0, NOW(), NOW()),
('Study Pod 2', 'Great for group work. Includes whiteboard and extra seating.', 150.00, 0, NOW(), NOW()),
('Chill Booth 3', 'Relaxed atmosphere with soft lighting. Beer service available.', 180.00, 1, NOW(), NOW()),
('Reading Room 4', 'Large space with comfortable seating. Ideal for long reading sessions.', 100.00, 0, NOW(), NOW()),
('Premium Suite 5', 'Our best cubicle. Private bathroom, snacks, and beer included.', 250.00, 1, NOW(), NOW());
