DROP DATABASE IF EXISTS academic_tracker;
CREATE DATABASE academic_tracker;
USE academic_tracker;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student', 'parent') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15),
    address TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role_status (role, status),
    INDEX idx_full_name (full_name)
);

CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    grade_level INT NOT NULL,
    section VARCHAR(10) NOT NULL,
    academic_year VARCHAR(9) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_grade_section (grade_level, section),
    INDEX idx_academic_year (academic_year),
    INDEX idx_status (status)
);

CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    class_id INT NOT NULL,
    semester INT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_status (status),
    INDEX idx_class_semester (class_id, semester),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE teacher_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    can_manage_attendance BOOLEAN DEFAULT false,
    can_manage_grades BOOLEAN DEFAULT false,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_teacher_status (teacher_id, status),
    INDEX idx_class_subject (class_id, subject_id),
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_teacher_class_subject (teacher_id, class_id, subject_id, status)
);

CREATE TABLE teacher_subject (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_teacher (teacher_id),
    INDEX idx_subject (subject_id),
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_teacher_subject (teacher_id, subject_id)
);

CREATE TABLE student_class (
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    roll_number VARCHAR(20),
    enrollment_date DATE NOT NULL DEFAULT (CURRENT_DATE),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id, class_id),
    INDEX idx_class_status (class_id, status),
    INDEX idx_enrollment (enrollment_date),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE parent_ward (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NOT NULL,
    student_id INT NOT NULL,
    relationship ENUM('father', 'mother', 'guardian') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_parent_status (parent_id, status),
    INDEX idx_student_status (student_id, status),
    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_parent_ward (parent_id, student_id, status)
);

CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'late') NOT NULL DEFAULT 'present',
    marked_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_date (student_id, date),
    INDEX idx_class_date (class_id, date),
    INDEX idx_subject_date (subject_id, date),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_attendance (student_id, class_id, subject_id, date)
);

CREATE TABLE grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    test_name VARCHAR(100) NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    max_score DECIMAL(5,2) NOT NULL DEFAULT 100,
    test_date DATE NOT NULL,
    marked_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_class (student_id, class_id),
    INDEX idx_subject_date (subject_id, test_date),
    INDEX idx_test_name (test_name),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Insert admin user
INSERT INTO users (username, password, role, full_name, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', 'admin@school.com');

-- Insert BTech classes (Years)
INSERT INTO classes (name, grade_level, section, academic_year) VALUES
('BTech Year 1', 1, 'A', '2024-2025'),
('BTech Year 2', 2, 'A', '2024-2025'),
('BTech Year 3', 3, 'A', '2024-2025'),
('BTech Year 4', 4, 'A', '2024-2025');

-- Insert subjects for BTech years, 5 per semester
-- Assumes class IDs are 1, 2, 3, 4 for the years respectively

-- Year 1 Sem 1 (class_id 1)
INSERT INTO subjects (name, class_id, semester) VALUES
('Mathematics I', 1, 1),
('Physics', 1, 1),
('Chemistry', 1, 1),
('Programming Basics', 1, 1),
('Engineering Graphics', 1, 1);

-- Year 1 Sem 2 (class_id 1)
INSERT INTO subjects (name, class_id, semester) VALUES
('Mathematics II', 1, 2),
('Electrical Circuits', 1, 2),
('Mechanics', 1, 2),
('Data Structures', 1, 2),
('Communication Skills', 1, 2);

-- Year 2 Sem 1 (class_id 2)
INSERT INTO subjects (name, class_id, semester) VALUES
('Mathematics III', 2, 1),
('Digital Logic Design', 2, 1),
('Object Oriented Programming', 2, 1),
('Database Management', 2, 1),
('Environmental Science', 2, 1);

-- Year 2 Sem 2 (class_id 2)
INSERT INTO subjects (name, class_id, semester) VALUES
('Computer Organization', 2, 2),
('Operating Systems Concepts', 2, 2),
('Algorithm Design', 2, 2),
('Software Engineering Principles', 2, 2),
('Probability & Statistics', 2, 2);

-- Year 3 Sem 1 (class_id 3)
INSERT INTO subjects (name, class_id, semester) VALUES
('Computer Networks', 3, 1),
('Theory of Computation', 3, 1),
('Web Development', 3, 1),
('Artificial Intelligence Intro', 3, 1),
('Microprocessors', 3, 1);

-- Year 3 Sem 2 (class_id 3)
INSERT INTO subjects (name, class_id, semester) VALUES
('Compiler Design', 3, 2),
('Machine Learning Basics', 3, 2),
('Cyber Security Fundamentals', 3, 2),
('Mobile Application Dev', 3, 2),
('Elective I', 3, 2);

-- Year 4 Sem 1 (class_id 4)
INSERT INTO subjects (name, class_id, semester) VALUES
('Cloud Computing', 4, 1),
('Big Data Analytics', 4, 1),
('Project Management', 4, 1),
('Elective II', 4, 1),
('Project Work I', 4, 1);

-- Year 4 Sem 2 (class_id 4)
INSERT INTO subjects (name, class_id, semester) VALUES
('Internet of Things', 4, 2),
('Deep Learning', 4, 2),
('Elective III', 4, 2),
('Ethics in Engineering', 4, 2),
('Project Work II / Internship', 4, 2);

-- Table structure for table `activities`
--

CREATE TABLE `activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `activity_date` date NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Constraints for table `activities`
--
ALTER TABLE `activities`
  ADD CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `activities_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,          -- ID of the user the log pertains to (often the student)
  `actor_id` INT NULL,           -- ID of the user who performed the action (e.g., teacher, admin, or student themselves)
  `activity_type` VARCHAR(50) NOT NULL, -- e.g., 'grade_added', 'attendance_marked', 'profile_updated'
  `description` TEXT NOT NULL,     -- Detailed description of the activity
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- When the activity occurred
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`actor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;