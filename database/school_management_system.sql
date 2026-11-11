-- ==============================================================
-- Complete School Management System Database Schema
-- Database Name: school_management_system
-- Technology: MySQL / MariaDB (Compatible with XAMPP)
-- ==============================================================

CREATE DATABASE IF NOT EXISTS `school_management_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `school_management_system`;

-- Disable foreign key checks during initialization
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Users Table (Core Authentication)
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(60) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'teacher', 'student', 'parent') NOT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_role_status` (`role`, `status`)
) ENGINE=InnoDB;

-- 2. Classes Table
DROP TABLE IF EXISTS `classes`;
CREATE TABLE `classes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_name` VARCHAR(50) NOT NULL,
    `numeric_level` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Sections Table
DROP TABLE IF EXISTS `sections`;
CREATE TABLE `sections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_id` INT NOT NULL,
    `section_name` VARCHAR(10) NOT NULL,
    `room_no` VARCHAR(20) DEFAULT NULL,
    `capacity` INT DEFAULT 40,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_class_section` (`class_id`, `section_name`)
) ENGINE=InnoDB;

-- 4. Teachers Table
DROP TABLE IF EXISTS `teachers`;
CREATE TABLE `teachers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `employee_id` VARCHAR(30) NOT NULL UNIQUE,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
    `phone` VARCHAR(25) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `qualification` VARCHAR(100) DEFAULT NULL,
    `joining_date` DATE NOT NULL,
    `salary` DECIMAL(10,2) DEFAULT 0.00,
    `address` TEXT DEFAULT NULL,
    `photo` VARCHAR(255) DEFAULT 'default_avatar.png',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Parents Table
DROP TABLE IF EXISTS `parents`;
CREATE TABLE `parents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `father_name` VARCHAR(100) NOT NULL,
    `mother_name` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(25) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `occupation` VARCHAR(100) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. Students Table
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `admission_no` VARCHAR(30) NOT NULL UNIQUE,
    `roll_no` VARCHAR(30) NOT NULL,
    `class_id` INT NOT NULL,
    `section_id` INT NOT NULL,
    `parent_id` INT DEFAULT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
    `dob` DATE NOT NULL,
    `admission_date` DATE NOT NULL,
    `phone` VARCHAR(25) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `photo` VARCHAR(255) DEFAULT 'default_avatar.png',
    `status` ENUM('active', 'inactive', 'graduated') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`parent_id`) REFERENCES `parents`(`id`) ON DELETE SET NULL,
    INDEX `idx_class_section` (`class_id`, `section_id`)
) ENGINE=InnoDB;

-- 7. Subjects Table
DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `subject_name` VARCHAR(100) NOT NULL,
    `subject_code` VARCHAR(30) NOT NULL UNIQUE,
    `class_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8. Teacher Assignments Table
DROP TABLE IF EXISTS `teacher_assignments`;
CREATE TABLE `teacher_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT NOT NULL,
    `class_id` INT NOT NULL,
    `section_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_assignment` (`teacher_id`, `class_id`, `section_id`, `subject_id`)
) ENGINE=InnoDB;

-- 9. Attendance Table
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `class_id` INT NOT NULL,
    `section_id` INT NOT NULL,
    `attendance_date` DATE NOT NULL,
    `status` ENUM('Present', 'Absent', 'Late', 'Leave') NOT NULL DEFAULT 'Present',
    `remarks` VARCHAR(255) DEFAULT NULL,
    `recorded_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_student_attendance_date` (`student_id`, `attendance_date`),
    INDEX `idx_attendance_query` (`class_id`, `section_id`, `attendance_date`)
) ENGINE=InnoDB;

-- 10. Examinations Table
DROP TABLE IF EXISTS `examinations`;
CREATE TABLE `examinations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `exam_name` VARCHAR(100) NOT NULL,
    `exam_type` VARCHAR(50) NOT NULL,
    `session_year` VARCHAR(20) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `is_published` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 11. Exam Subjects Table (Schedule)
DROP TABLE IF EXISTS `exam_subjects`;
CREATE TABLE `exam_subjects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `exam_id` INT NOT NULL,
    `class_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `exam_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `max_marks` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    `pass_marks` DECIMAL(5,2) NOT NULL DEFAULT 33.00,
    FOREIGN KEY (`exam_id`) REFERENCES `examinations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_exam_class_subject` (`exam_id`, `class_id`, `subject_id`)
) ENGINE=InnoDB;

-- 12. Marks Table
DROP TABLE IF EXISTS `marks`;
CREATE TABLE `marks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `exam_subject_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `marks_obtained` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `is_absent` TINYINT(1) DEFAULT 0,
    `remarks` VARCHAR(255) DEFAULT NULL,
    `entered_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_exam_student_marks` (`exam_subject_id`, `student_id`)
) ENGINE=InnoDB;

-- 13. Fee Structures Table
DROP TABLE IF EXISTS `fee_structures`;
CREATE TABLE `fee_structures` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_id` INT NOT NULL,
    `fee_title` VARCHAR(100) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 14. Fees Invoices Table
DROP TABLE IF EXISTS `fees`;
CREATE TABLE `fees` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `fee_structure_id` INT DEFAULT NULL,
    `fee_title` VARCHAR(100) NOT NULL,
    `month` VARCHAR(20) NOT NULL,
    `year` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `discount` DECIMAL(10,2) DEFAULT 0.00,
    `fine` DECIMAL(10,2) DEFAULT 0.00,
    `paid_amount` DECIMAL(10,2) DEFAULT 0.00,
    `status` ENUM('Paid', 'Unpaid', 'Partial') NOT NULL DEFAULT 'Unpaid',
    `due_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structures`(`id`) ON DELETE SET NULL,
    INDEX `idx_fee_student_status` (`student_id`, `status`)
) ENGINE=InnoDB;

-- 15. Fee Payments Table
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fee_id` INT NOT NULL,
    `payment_no` VARCHAR(50) NOT NULL UNIQUE,
    `amount_paid` DECIMAL(10,2) NOT NULL,
    `payment_date` DATE NOT NULL,
    `payment_method` ENUM('Cash', 'Bank Transfer', 'Cheque', 'Online') NOT NULL DEFAULT 'Cash',
    `transaction_id` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `received_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`fee_id`) REFERENCES `fees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 16. Homework Table
DROP TABLE IF EXISTS `homework`;
CREATE TABLE `homework` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT NOT NULL,
    `class_id` INT NOT NULL,
    `section_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `description` TEXT NOT NULL,
    `assigned_date` DATE NOT NULL,
    `due_date` DATE NOT NULL,
    `attachment` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 17. Homework Submissions Table
DROP TABLE IF EXISTS `homework_submissions`;
CREATE TABLE `homework_submissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `homework_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `submission_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `submission_text` TEXT DEFAULT NULL,
    `file_path` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('Submitted', 'Reviewed', 'Late') DEFAULT 'Submitted',
    `grade` VARCHAR(10) DEFAULT NULL,
    `feedback` TEXT DEFAULT NULL,
    FOREIGN KEY (`homework_id`) REFERENCES `homework`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_student_homework` (`homework_id`, `student_id`)
) ENGINE=InnoDB;

-- 18. Timetables Table
DROP TABLE IF EXISTS `timetables`;
CREATE TABLE `timetables` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_id` INT NOT NULL,
    `section_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `teacher_id` INT NOT NULL,
    `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `room_no` VARCHAR(20) DEFAULT NULL,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 19. Announcements Table
DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `content` TEXT NOT NULL,
    `target_role` ENUM('all', 'teacher', 'student', 'parent') NOT NULL DEFAULT 'all',
    `start_date` DATE NOT NULL,
    `expiry_date` DATE NOT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================
-- Comprehensive Seed Data
-- Passwords:
-- admin: admin123
-- teacher1: teacher123
-- student1: student123
-- parent1: parent123
-- Hash: $2y$10$F/v3wEPDEqfgy/J5jl5K5ug06pJyfsdfZSoD7tjHE4D.pLlc3ctBG (admin123)
-- Hash: $2y$10$NGbcKBgUa4ePFtiaoH1z4.jm7eopgXFI9Ef2cX9tTFkhTqzBDrWEO (teacher123)
-- Hash: $2y$10$Emqfu04munuPBTYFIqicI.Vv8lJgwkH5gNEX7DL0KIiZ1zw6t3lye (student123)
-- Hash: $2y$10$aj2OZuTzN5oR/0EwraN4W.67qAqEjlknlMWgcU0jE8dP792LeYoRC (parent123)
-- ==============================================================

-- 1. Seed Users
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `status`) VALUES
(1, 'admin', 'admin@roots.edu', '$2y$10$F/v3wEPDEqfgy/J5jl5K5ug06pJyfsdfZSoD7tjHE4D.pLlc3ctBG', 'admin', 'active'),
(2, 'teacher1', 'sarah.khan@roots.edu', '$2y$10$NGbcKBgUa4ePFtiaoH1z4.jm7eopgXFI9Ef2cX9tTFkhTqzBDrWEO', 'teacher', 'active'),
(3, 'teacher2', 'ahmed.bilal@roots.edu', '$2y$10$NGbcKBgUa4ePFtiaoH1z4.jm7eopgXFI9Ef2cX9tTFkhTqzBDrWEO', 'teacher', 'active'),
(4, 'parent1', 'tariq.mehmood@gmail.com', '$2y$10$aj2OZuTzN5oR/0EwraN4W.67qAqEjlknlMWgcU0jE8dP792LeYoRC', 'parent', 'active'),
(5, 'student1', 'zain.tariq@roots.edu', '$2y$10$Emqfu04munuPBTYFIqicI.Vv8lJgwkH5gNEX7DL0KIiZ1zw6t3lye', 'student', 'active'),
(6, 'student2', 'ayesha.noor@roots.edu', '$2y$10$Emqfu04munuPBTYFIqicI.Vv8lJgwkH5gNEX7DL0KIiZ1zw6t3lye', 'student', 'active'),
(7, 'student3', 'hamza.ali@roots.edu', '$2y$10$Emqfu04munuPBTYFIqicI.Vv8lJgwkH5gNEX7DL0KIiZ1zw6t3lye', 'student', 'active'),
(8, 'student4', 'fatima.zahra@roots.edu', '$2y$10$Emqfu04munuPBTYFIqicI.Vv8lJgwkH5gNEX7DL0KIiZ1zw6t3lye', 'student', 'active')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- 2. Seed Classes
INSERT INTO `classes` (`id`, `class_name`, `numeric_level`) VALUES
(1, 'Grade 1', 1),
(2, 'Grade 2', 2),
(3, 'Grade 3', 3),
(4, 'Grade 4', 4),
(5, 'Grade 5', 5),
(6, 'Grade 6', 6),
(7, 'Grade 7', 7),
(8, 'Grade 8', 8),
(9, 'Grade 9', 9),
(10, 'Grade 10', 10)
ON DUPLICATE KEY UPDATE `class_name`=`class_name`;

-- 3. Seed Sections
INSERT INTO `sections` (`id`, `class_id`, `section_name`, `room_no`, `capacity`) VALUES
(1, 9, 'A', 'Room 201', 35),
(2, 9, 'B', 'Room 202', 35),
(3, 10, 'A', 'Room 301', 35),
(4, 10, 'B', 'Room 302', 35),
(5, 8, 'A', 'Room 101', 35)
ON DUPLICATE KEY UPDATE `section_name`=`section_name`;

-- 4. Seed Teachers
INSERT INTO `teachers` (`id`, `user_id`, `employee_id`, `first_name`, `last_name`, `gender`, `phone`, `email`, `qualification`, `joining_date`, `salary`, `address`) VALUES
(1, 2, 'EMP-101', 'Sarah', 'Khan', 'Female', '+92 300 1234567', 'sarah.khan@roots.edu', 'M.Sc. Mathematics', '2021-08-15', 85000.00, 'House 42, Street 7, Islamabad'),
(2, 3, 'EMP-102', 'Ahmed', 'Bilal', 'Male', '+92 321 7654321', 'ahmed.bilal@roots.edu', 'M.Phil Physics', '2020-03-01', 92000.00, 'Sector F-10/2, Islamabad')
ON DUPLICATE KEY UPDATE `employee_id`=`employee_id`;

-- 5. Seed Parents
INSERT INTO `parents` (`id`, `user_id`, `father_name`, `mother_name`, `phone`, `email`, `occupation`, `address`) VALUES
(1, 4, 'Tariq Mehmood', 'Samina Tariq', '+92 333 5556677', 'tariq.mehmood@gmail.com', 'Senior Engineer', 'Plot 18, Block B, Bahria Town, Islamabad')
ON DUPLICATE KEY UPDATE `father_name`=`father_name`;

-- 6. Seed Students
INSERT INTO `students` (`id`, `user_id`, `admission_no`, `roll_no`, `class_id`, `section_id`, `parent_id`, `first_name`, `last_name`, `gender`, `dob`, `admission_date`, `phone`, `address`, `status`) VALUES
(1, 5, 'ADM-2024-001', '101', 9, 1, 1, 'Zain', 'Tariq', 'Male', '2009-04-12', '2023-08-10', '+92 333 5556677', 'Plot 18, Block B, Bahria Town, Islamabad', 'active'),
(2, 6, 'ADM-2024-002', '102', 9, 1, 1, 'Ayesha', 'Noor', 'Female', '2009-07-25', '2023-08-10', '+92 333 9998877', 'House 55, Street 12, G-11/3, Islamabad', 'active'),
(3, 7, 'ADM-2024-003', '103', 9, 1, NULL, 'Hamza', 'Ali', 'Male', '2009-02-18', '2023-08-12', '+92 301 4443322', 'Sector I-8/4, Islamabad', 'active'),
(4, 8, 'ADM-2024-004', '104', 10, 3, NULL, 'Fatima', 'Zahra', 'Female', '2008-11-05', '2022-08-15', '+92 345 6667788', 'Street 4, Sector F-7/1, Islamabad', 'active')
ON DUPLICATE KEY UPDATE `admission_no`=`admission_no`;

-- 7. Seed Subjects
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`) VALUES
(1, 'Mathematics', 'MATH-09', 9),
(2, 'Physics', 'PHY-09', 9),
(3, 'Chemistry', 'CHEM-09', 9),
(4, 'English Literature', 'ENG-09', 9),
(5, 'Computer Science', 'CS-09', 9),
(6, 'Mathematics', 'MATH-10', 10),
(7, 'Physics', 'PHY-10', 10)
ON DUPLICATE KEY UPDATE `subject_code`=`subject_code`;

-- 8. Teacher Assignments
INSERT INTO `teacher_assignments` (`id`, `teacher_id`, `class_id`, `section_id`, `subject_id`) VALUES
(1, 1, 9, 1, 1), -- Sarah teaches Math to Grade 9-A
(2, 2, 9, 1, 2), -- Ahmed teaches Physics to Grade 9-A
(3, 1, 10, 3, 6) -- Sarah teaches Math to Grade 10-A
ON DUPLICATE KEY UPDATE `teacher_id`=`teacher_id`;

-- 9. Seed Attendance (Recent Days)
INSERT INTO `attendance` (`student_id`, `class_id`, `section_id`, `attendance_date`, `status`, `remarks`, `recorded_by`) VALUES
(1, 9, 1, CURDATE(), 'Present', 'On time', 2),
(2, 9, 1, CURDATE(), 'Present', 'On time', 2),
(3, 9, 1, CURDATE(), 'Late', 'Late by 15 mins', 2),
(1, 9, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Present', 'On time', 2),
(2, 9, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Present', 'On time', 2),
(3, 9, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Absent', 'Sick leave approved', 2)
ON DUPLICATE KEY UPDATE `status`=`status`;

-- 10. Seed Examinations
INSERT INTO `examinations` (`id`, `exam_name`, `exam_type`, `session_year`, `start_date`, `end_date`, `is_published`) VALUES
(1, 'Mid-Term Examinations 2026', 'Term Exam', '2026-2027', '2026-10-10', '2026-10-25', 1),
(2, 'Final Annual Examinations 2026', 'Annual', '2026-2027', '2026-12-05', '2026-12-20', 0)
ON DUPLICATE KEY UPDATE `exam_name`=`exam_name`;

-- 11. Exam Subjects
INSERT INTO `exam_subjects` (`id`, `exam_id`, `class_id`, `subject_id`, `exam_date`, `start_time`, `end_time`, `max_marks`, `pass_marks`) VALUES
(1, 1, 9, 1, '2026-10-12', '09:00:00', '12:00:00', 100.00, 33.00),
(2, 1, 9, 2, '2026-10-14', '09:00:00', '12:00:00', 100.00, 33.00),
(3, 1, 9, 5, '2026-10-16', '09:00:00', '12:00:00', 100.00, 33.00)
ON DUPLICATE KEY UPDATE `exam_date`=`exam_date`;

-- 12. Seed Marks
INSERT INTO `marks` (`exam_subject_id`, `student_id`, `marks_obtained`, `is_absent`, `remarks`, `entered_by`) VALUES
(1, 1, 92.50, 0, 'Outstanding mathematical aptitude', 2),
(2, 1, 88.00, 0, 'Excellent experimental understanding', 3),
(3, 1, 95.00, 0, 'Brilliant algorithm design', 2),
(1, 2, 85.00, 0, 'Very good performance', 2),
(2, 2, 79.50, 0, 'Good conceptual clarity', 3),
(3, 2, 91.00, 0, 'Impressive programming skills', 2)
ON DUPLICATE KEY UPDATE `marks_obtained`=`marks_obtained`;

-- 13. Fee Structures
INSERT INTO `fee_structures` (`id`, `class_id`, `fee_title`, `amount`) VALUES
(1, 9, 'Monthly Tuition Fee', 12500.00),
(2, 10, 'Monthly Tuition Fee', 14000.00),
(3, 8, 'Monthly Tuition Fee', 11000.00)
ON DUPLICATE KEY UPDATE `amount`=`amount`;

-- 14. Seed Fees (Invoices)
INSERT INTO `fees` (`id`, `student_id`, `fee_structure_id`, `fee_title`, `month`, `year`, `amount`, `discount`, `fine`, `paid_amount`, `status`, `due_date`) VALUES
(1, 1, 1, 'Monthly Tuition Fee - November', 'November', 2026, 12500.00, 0.00, 0.00, 12500.00, 'Paid', '2026-11-10'),
(2, 2, 1, 'Monthly Tuition Fee - November', 'November', 2026, 12500.00, 1000.00, 0.00, 11500.00, 'Paid', '2026-11-10'),
(3, 3, 1, 'Monthly Tuition Fee - November', 'November', 2026, 12500.00, 0.00, 500.00, 0.00, 'Unpaid', '2026-11-10'),
(4, 4, 2, 'Monthly Tuition Fee - November', 'November', 2026, 14000.00, 0.00, 0.00, 7000.00, 'Partial', '2026-11-10'),
(5, 1, 1, 'Monthly Tuition Fee - October', 'October', 2026, 12500.00, 0.00, 0.00, 12500.00, 'Paid', '2026-10-10')
ON DUPLICATE KEY UPDATE `status`=`status`;

-- 15. Fee Payments
INSERT INTO `payments` (`id`, `fee_id`, `payment_no`, `amount_paid`, `payment_date`, `payment_method`, `transaction_id`, `notes`, `received_by`) VALUES
(1, 1, 'REC-2026-0001', 12500.00, '2026-11-05', 'Online', 'TXN-99882211', 'Paid via mobile banking', 1),
(2, 2, 'REC-2026-0002', 11500.00, '2026-11-06', 'Bank Transfer', 'IBFT-55443322', 'Sibling discount applied', 1),
(3, 4, 'REC-2026-0003', 7000.00, '2026-11-08', 'Cash', 'CASH-REC-104', 'First installment paid', 1)
ON DUPLICATE KEY UPDATE `payment_no`=`payment_no`;

-- 16. Homework
INSERT INTO `homework` (`id`, `teacher_id`, `class_id`, `section_id`, `subject_id`, `title`, `description`, `assigned_date`, `due_date`) VALUES
(1, 1, 9, 1, 1, 'Quadratic Equations Exercise 4.2', 'Complete problems 1 through 15 on Chapter 4 review sheet. Show step-by-step factorization.', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY)),
(2, 2, 9, 1, 2, 'Newton Laws Lab Report Summary', 'Write a 2-page concise laboratory report on the friction and acceleration experiment conducted this Thursday.', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 5 DAY))
ON DUPLICATE KEY UPDATE `title`=`title`;

-- 17. Homework Submissions
INSERT INTO `homework_submissions` (`id`, `homework_id`, `student_id`, `submission_text`, `status`, `grade`, `feedback`) VALUES
(1, 1, 1, 'Attached step-by-step solutions for Exercise 4.2 questions 1-15.', 'Reviewed', 'A+', 'Clean handwriting and accurate algebraic simplification.')
ON DUPLICATE KEY UPDATE `status`=`status`;

-- 18. Timetables
INSERT INTO `timetables` (`id`, `class_id`, `section_id`, `subject_id`, `teacher_id`, `day_of_week`, `start_time`, `end_time`, `room_no`) VALUES
(1, 9, 1, 1, 1, 'Monday', '08:30:00', '09:15:00', 'Room 201'),
(2, 9, 1, 2, 2, 'Monday', '09:15:00', '10:00:00', 'Physics Lab'),
(3, 9, 1, 5, 2, 'Monday', '10:15:00', '11:00:00', 'Computer Lab 1'),
(4, 9, 1, 1, 1, 'Tuesday', '08:30:00', '09:15:00', 'Room 201'),
(5, 9, 1, 2, 2, 'Tuesday', '09:15:00', '10:00:00', 'Room 201'),
(6, 9, 1, 4, 1, 'Wednesday', '08:30:00', '09:15:00', 'Room 201')
ON DUPLICATE KEY UPDATE `room_no`=`room_no`;

-- 19. Announcements
INSERT INTO `announcements` (`id`, `title`, `content`, `target_role`, `start_date`, `expiry_date`, `created_by`) VALUES
(1, 'Annual Sports Gala 2026', 'We are pleased to announce the Annual Sports Gala will commence from next Friday. Students must register with their sports captain by Wednesday.', 'all', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 1),
(2, 'Parent-Teacher Meeting (PTM) Notification', 'The First Term Parent-Teacher meeting will take place this Saturday from 9:00 AM to 1:00 PM. All parents are requested to attend.', 'parent', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 1),
(3, 'Faculty Curriculum Review Meeting', 'All department heads and teaching staff are requested to assemble in the Conference Room at 3:00 PM for the quarterly curriculum assessment.', 'teacher', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 1)
ON DUPLICATE KEY UPDATE `title`=`title`;

