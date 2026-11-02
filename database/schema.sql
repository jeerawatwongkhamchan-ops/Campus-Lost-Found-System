-- Campus Lost and Found System Schema

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Users table
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `security_logs`;
DROP TABLE IF EXISTS `claims`;
DROP TABLE IF EXISTS `item_reports`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `users` (
    `user_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(50) NOT NULL UNIQUE,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `phone` VARCHAR(30) NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('student', 'staff', 'admin') NOT NULL DEFAULT 'student',
    `account_status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_account_status` (`account_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories table
CREATE TABLE `categories` (
    `category_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(100) NOT NULL UNIQUE,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_categories_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Item Reports table
CREATE TABLE `item_reports` (
    `report_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reporter_id` BIGINT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `report_type` ENUM('lost', 'found') NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `location` VARCHAR(255) NOT NULL,
    `event_datetime` DATETIME NOT NULL,
    `image_filename` VARCHAR(255) NULL,
    `private_note` TEXT NULL,
    `status` ENUM('open', 'claimed', 'resolved', 'closed') NOT NULL DEFAULT 'open',
    `resolved_at` DATETIME NULL,
    `deleted_at` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_reports_reporter_id` (`reporter_id`),
    INDEX `idx_reports_category_id` (`category_id`),
    INDEX `idx_reports_report_type` (`report_type`),
    INDEX `idx_reports_status` (`status`),
    INDEX `idx_reports_event_datetime` (`event_datetime`),
    INDEX `idx_reports_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_reports_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reports_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Claims table
CREATE TABLE `claims` (
    `claim_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `report_id` BIGINT UNSIGNED NOT NULL,
    `claimant_id` BIGINT UNSIGNED NOT NULL,
    `claim_type` VARCHAR(50) NOT NULL,
    `message` TEXT NOT NULL,
    `proof_image` VARCHAR(255) NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    `reviewed_by` BIGINT UNSIGNED NULL,
    `reviewed_at` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_claims_report_id` (`report_id`),
    INDEX `idx_claims_claimant_id` (`claimant_id`),
    INDEX `idx_claims_status` (`status`),
    INDEX `idx_claims_reviewed_by` (`reviewed_by`),
    CONSTRAINT `fk_claims_report` FOREIGN KEY (`report_id`) REFERENCES `item_reports` (`report_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_claims_claimant` FOREIGN KEY (`claimant_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_claims_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications table
CREATE TABLE `notifications` (
    `notification_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `link` VARCHAR(255) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notifications_user_id` (`user_id`),
    INDEX `idx_notifications_is_read` (`is_read`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security Logs table
CREATE TABLE `security_logs` (
    `log_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NULL,
    `event_type` VARCHAR(100) NOT NULL,
    `target_type` VARCHAR(100) NULL,
    `target_id` VARCHAR(100) NULL,
    `outcome` VARCHAR(50) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(500) NULL,
    `details` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_security_logs_user_id` (`user_id`),
    INDEX `idx_security_logs_event_type` (`event_type`),
    INDEX `idx_security_logs_created_at` (`created_at`),
    CONSTRAINT `fk_security_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
