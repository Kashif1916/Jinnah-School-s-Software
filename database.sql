-- School Finance Management System Database
-- Created for XAMPP MySQL

-- Create Database
CREATE DATABASE IF NOT EXISTS `school_finance`;
USE `school_finance`;

-- Table 1: Users (Authentication)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('master', 'finance') NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 2: Students
CREATE TABLE IF NOT EXISTS `students` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `father_name` VARCHAR(100) NOT NULL,
  `class` VARCHAR(50) NOT NULL,
  `section` VARCHAR(10) NOT NULL,
  `monthly_fee` DECIMAL(10, 2) NOT NULL,
  `contact_number` VARCHAR(15),
  `contact_number2` VARCHAR(15),
  `whatsapp_number` VARCHAR(15),
  `concession_amount` DECIMAL(10,2) DEFAULT 0,
  `concession_reason` VARCHAR(255),
  `status` ENUM('active', 'dropped') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 3: Fee Records
CREATE TABLE IF NOT EXISTS `fee_records` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `student_id` INT NOT NULL,
  `month` VARCHAR(20) NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `status` ENUM('paid', 'unpaid') DEFAULT 'unpaid',
  `payment_date` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_student_month` (`student_id`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 4: Payments
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `student_id` INT NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `paid_for_month` VARCHAR(20) NOT NULL,
  `payment_date` DATETIME NOT NULL,
  `received_by` VARCHAR(100) NOT NULL,
  `payment_mode` VARCHAR(20) DEFAULT 'cash',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 5: Expenses
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `amount` DECIMAL(10, 2) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `user_id` INT NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Insert Default Admin Users
INSERT INTO `users` (`username`, `password`, `role`) VALUES 
('master', '1234', 'master'),
('finance', '1234', 'finance');

-- Create Indexes for Performance
CREATE INDEX `idx_student_status` ON `students`(`status`);
CREATE INDEX `idx_student_class` ON `students`(`class`, `section`);
CREATE INDEX `idx_fee_status` ON `fee_records`(`status`);
CREATE INDEX `idx_fee_month` ON `fee_records`(`month`);
CREATE INDEX `idx_payment_date` ON `payments`(`payment_date`);

COMMIT;
