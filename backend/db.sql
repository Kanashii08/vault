-- =====================================================
-- Book Cafe Database Schema (Laravel-style)
-- =====================================================
-- Run all migrations in order, then run the seeder.
-- 
-- Individual migration files are in: database/migrations/
-- Seeder file is in: database/seeders/BookCafeSeeder.sql
-- =====================================================

-- Database: u779443399_bookDE
-- Note: On Hostinger, the database is already created. Just run the table creation and seeder below.
USE u779443399_bookDE;

-- =====================================================
-- Migration 001: Users Table
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'user') NOT NULL DEFAULT 'user',
    avatar_url VARCHAR(255) DEFAULT NULL,
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Migration 002: Personal Access Tokens (Sanctum-style)
-- =====================================================
CREATE TABLE IF NOT EXISTS personal_access_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    abilities TEXT NULL,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tokenable (tokenable_type, tokenable_id),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Migration 003: Cubicles Table
-- =====================================================
CREATE TABLE IF NOT EXISTS cubicles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    hourly_rate DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    has_beer TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Migration 004: Bookings Table
-- =====================================================
CREATE TABLE IF NOT EXISTS bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    cubicle_id BIGINT UNSIGNED NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'booked',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_cubicle (cubicle_id),
    INDEX idx_start_time (start_time),
    CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookings_cubicle FOREIGN KEY (cubicle_id) REFERENCES cubicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Seeder: Default Users
-- Admin: admin@bc.com / admin123
-- Staff: staff@bc.com / staff123
-- =====================================================
-- Note: The admin user (id=1) is protected and cannot be deleted.
INSERT INTO users (first_name, last_name, email, password, role, avatar_url, created_at, updated_at) VALUES
('Admin', 'User', 'admin@bc.com', '$2y$10$xPLP0LD0YS5pXmGdLU4zAOWhXPKgAOhFfLaGQlHmourxwKH8RvEGq', 'admin', 'https://moodleaands.muccs.site/backend/storage/avatars/default-admin.png', NOW(), NOW()),
('Staff', 'User', 'staff@bc.com', '$2y$10$Dowt/FMjLn3ES.YgDDdoMeZXZ6T.PYE0YRlHO0FMjvPNzVMHKKdDy', 'staff', 'https://moodleaands.muccs.site/backend/storage/avatars/default-staff.png', NOW(), NOW());

INSERT INTO cubicles (name, description, hourly_rate, has_beer, created_at, updated_at) VALUES
('Quiet Nook 1', 'Perfect for solo reading and napping. Cozy corner with soft lighting.', 120.00, 0, NOW(), NOW()),
('Study Pod 2', 'Great for group work. Includes whiteboard and extra seating.', 150.00, 0, NOW(), NOW()),
('Chill Booth 3', 'Relaxed atmosphere with soft lighting. Beer service available.', 180.00, 1, NOW(), NOW()),
('Reading Room 4', 'Large space with comfortable seating. Ideal for long reading sessions.', 100.00, 0, NOW(), NOW()),
('Premium Suite 5', 'Our best cubicle. Private bathroom, snacks, and beer included.', 250.00, 1, NOW(), NOW());
