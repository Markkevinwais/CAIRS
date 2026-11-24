CREATE DATABASE IF NOT EXISTS `cairs` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cairs`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('resident', 'volunteer', 'admin') NOT NULL DEFAULT 'resident',
  `phone` VARCHAR(40) NULL,
  `address` VARCHAR(200) NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `avatar_type` ENUM('badge', 'image') NOT NULL DEFAULT 'badge',
  `avatar_style` VARCHAR(30) NOT NULL DEFAULT 'initials',
  `avatar_shape` ENUM('circle', 'badge') NOT NULL DEFAULT 'circle',
  `avatar_url` VARCHAR(500) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(64) PRIMARY KEY,
  `v` TEXT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `reports` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `resident_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `volunteer_id` INT UNSIGNED NULL,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `latitude` DECIMAL(10,7) NULL,
  `longitude` DECIMAL(10,7) NULL,
  `photo` VARCHAR(120) NULL,
  `priority` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
  `status` ENUM('pending', 'assigned', 'in_progress', 'resolved', 'rejected') NOT NULL DEFAULT 'pending',
  `is_sos` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_reports_resident` FOREIGN KEY (`resident_id`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_reports_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`),
  CONSTRAINT `fk_reports_volunteer` FOREIGN KEY (`volunteer_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `report_updates` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `report_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `status` VARCHAR(30) NOT NULL,
  `remark` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_updates_report` FOREIGN KEY (`report_id`) REFERENCES `reports`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_updates_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `report_id` INT UNSIGNED NULL,
  `message` VARCHAR(255) NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_report` FOREIGN KEY (`report_id`) REFERENCES `reports`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `body` TEXT NOT NULL,
  `level` ENUM('info', 'warning', 'critical') NOT NULL DEFAULT 'info',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_ann_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(150) NOT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO `categories` (`id`, `name`) VALUES
  (1, 'Fire'),
  (2, 'Flood'),
  (3, 'Theft / Crime'),
  (4, 'Road accident'),
  (5, 'Medical emergency'),
  (6, 'Noise disturbance'),
  (7, 'Garbage / Sanitation'),
  (8, 'Broken streetlight / Road'),
  (9, 'Other');

INSERT IGNORE INTO `settings` (`k`, `v`) VALUES
  ('site_name', 'CAIRS'),
  ('barangay_name', 'Barangay Sample'),
  ('contact_phone', '0912-345-6789'),
  ('map_lat', '8.4542'),
  ('map_lng', '124.6319'),
  ('sms_enabled', '0'),
  ('sms_api_key', ''),
  ('sms_sender', ''),
  ('emergency_hotline', '911'),
  ('hotline_fire', ''),
  ('hotline_police', ''),
  ('hotline_medical', '');
