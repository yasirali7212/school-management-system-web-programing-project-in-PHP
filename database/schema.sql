-- ==============================================================
-- Roots School System Database Schema
-- Database Name: school_management_db
-- ==============================================================

CREATE DATABASE IF NOT EXISTS `school_management_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `school_management_db`;

-- 1. Users Table (Core Auth for Admin, Teachers, Students, Parents)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'teacher', 'student', 'parent') NOT NULL DEFAULT 'student',
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Classes Table
CREATE TABLE IF NOT EXISTS `classes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_name` VARCHAR(50) NOT NULL,
    `section` VARCHAR(10) NOT NULL,
    `room_number` VARCHAR(20) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_class_section` (`class_name`, `section`)
) ENGINE=InnoDB;

-- 3. Teachers Profile Table
CREATE TABLE IF NOT EXISTS `teachers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `qualification` VARCHAR(100) DEFAULT NULL,
    `hire_date` DATE DEFAULT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Students Profile Table
CREATE TABLE IF NOT EXISTS `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `class_id` INT DEFAULT NULL,
    `roll_number` VARCHAR(30) NOT NULL UNIQUE,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `gender` ENUM('Male', 'Female', 'Other') DEFAULT NULL,
    `dob` DATE DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `admission_date` DATE DEFAULT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. Subjects Table
CREATE TABLE IF NOT EXISTS `subjects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `subject_name` VARCHAR(100) NOT NULL,
    `subject_code` VARCHAR(20) NOT NULL UNIQUE,
    `class_id` INT DEFAULT NULL,
    `teacher_id` INT DEFAULT NULL,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 6. Attendance Table
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `class_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `status` ENUM('Present', 'Absent', 'Late', 'Excused') NOT NULL,
    `remarks` VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_student_date` (`student_id`, `date`)
) ENGINE=InnoDB;

-- 7. Exams Table
CREATE TABLE IF NOT EXISTS `exams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `exam_name` VARCHAR(100) NOT NULL,
    `session` VARCHAR(50) NOT NULL,
    `start_date` DATE DEFAULT NULL
) ENGINE=InnoDB;

-- 8. Marks Table
CREATE TABLE IF NOT EXISTS `marks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `exam_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `marks_obtained` DECIMAL(5,2) NOT NULL,
    `max_marks` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_exam_student_subject` (`exam_id`, `student_id`, `subject_id`)
) ENGINE=InnoDB;

-- 9. Notices Table
CREATE TABLE IF NOT EXISTS `notices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(150) NOT NULL,
    `content` TEXT NOT NULL,
    `posted_by` INT NOT NULL,
    `target_role` ENUM('all', 'teacher', 'student', 'parent') DEFAULT 'all',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`posted_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==============================================================
-- Seed Demo Accounts for All 4 Roles
-- Passwords:
-- admin   : admin123
-- teacher : teacher123
-- student : student123
-- parent  : parent123
-- ==============================================================

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `status`)
VALUES 
(1, 'admin', 'admin.super@roots.edu', '$2y$10$F/v3wEPDEqfgy/J5jl5K5ug06pJyfsdfZSoD7tjHE4D.pLlc3ctBG', 'admin', 'active'),
(2, 'teacher1', 'faculty.first@roots.edu', '$2y$10$NGbcKBgUa4ePFtiaoH1z4.jm7eopgXFI9Ef2cX9tTFkhTqzBDrWEO', 'teacher', 'active'),
(3, 'student1', 'student.id@roots.edu', '$2y$10$Emqfu04munuPBTYFIqicI.Vv8lJgwkH5gNEX7DL0KIiZ1zw6t3lye', 'student', 'active'),
(4, 'parent1', 'guardian@domain.com', '$2y$10$aj2OZuTzN5oR/0EwraN4W.67qAqEjlknlMWgcU0jE8dP792LeYoRC', 'parent', 'active')
ON DUPLICATE KEY UPDATE `username`=`username`;
