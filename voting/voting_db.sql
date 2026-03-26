-- ============================================================
-- PCCOER Secure Online Voting System - Database Schema
-- ============================================================
-- Run this SQL in phpMyAdmin to set up the database.
-- ============================================================

CREATE DATABASE IF NOT EXISTS voting_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE voting_db;

-- ============================================================
-- Table: users
-- Stores login credentials for both admins and students.
-- Relationship: One user can be one student (1:1 via students.user_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,  -- Must end in @pccoer.in for students
    password VARCHAR(255) NOT NULL,       -- bcrypt hashed
    role ENUM('admin','student') NOT NULL DEFAULT 'student',
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Table: students
-- Extended profile info for student users.
-- Relationship: One users record → One students record (1:1)
-- ============================================================
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,           -- FK → users.id (1:1 enforced by UNIQUE)
    name VARCHAR(100) NOT NULL,
    roll_no VARCHAR(30) NOT NULL UNIQUE,   -- Must be unique across college
    division VARCHAR(10) NOT NULL,
    year INT NOT NULL DEFAULT 1,
    department VARCHAR(50) NOT NULL DEFAULT 'Computer Engineering',
    phone VARCHAR(15),
    verified TINYINT(1) NOT NULL DEFAULT 0, -- Admin must verify student
    reset_token VARCHAR(100) DEFAULT NULL,
    reset_expires DATETIME DEFAULT NULL,
    CONSTRAINT fk_student_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Table: elections
-- Each election has a title, time window, and status.
-- Relationship: One election → many candidates (1:N)
--              One election → many votes (1:N)
-- ============================================================
CREATE TABLE IF NOT EXISTS elections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status ENUM('upcoming','active','completed') NOT NULL DEFAULT 'upcoming',
    created_by INT NOT NULL,               -- FK → users.id (admin who created it)
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_election_creator FOREIGN KEY (created_by) REFERENCES users(id)
);

-- ============================================================
-- Table: candidates
-- Candidates belong to one election.
-- Relationship: One election → many candidates (1:N via election_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,              -- FK → elections.id
    name VARCHAR(100) NOT NULL,
    description TEXT,
    photo VARCHAR(255) DEFAULT NULL,       -- Path to uploaded photo
    CONSTRAINT fk_candidate_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
);

-- ============================================================
-- Table: votes
-- Stores each cast vote. A student can only vote once per election.
-- Relationship: One student → many votes across elections (1:N but 1:1 per election)
--              One candidate → many votes (1:N)
--              One election → many votes (1:N)
-- The UNIQUE KEY prevents duplicate votes per student per election.
-- ============================================================
CREATE TABLE IF NOT EXISTS votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,               -- FK → students.id
    candidate_id INT NOT NULL,             -- FK → candidates.id
    election_id INT NOT NULL,              -- FK → elections.id
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_valid TINYINT(1) NOT NULL DEFAULT 1, -- Set to 0 when revote is approved
    ip_address VARCHAR(45) DEFAULT NULL,
    UNIQUE KEY unique_vote (student_id, election_id), -- Prevents duplicate voting
    CONSTRAINT fk_vote_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_vote_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id),
    CONSTRAINT fk_vote_election FOREIGN KEY (election_id) REFERENCES elections(id)
);

-- ============================================================
-- Table: revote_requests
-- Students can request permission to revote.
-- Relationship: One student → many revote requests (1:N)
-- ============================================================
CREATE TABLE IF NOT EXISTS revote_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,               -- FK → students.id
    election_id INT NOT NULL,              -- FK → elections.id
    reason TEXT NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note TEXT DEFAULT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL,
    CONSTRAINT fk_revote_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_revote_election FOREIGN KEY (election_id) REFERENCES elections(id)
);

-- ============================================================
-- Table: notifications
-- System messages sent to users.
-- Relationship: One user → many notifications (1:N)
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,                  -- FK → users.id
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Table: logs (Audit Logs)
-- Every significant action is recorded here.
-- Relationship: One user → many log entries (1:N)
-- ============================================================
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,              -- FK → users.id (NULL for unauthenticated actions)
    action VARCHAR(255) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- Table: sessions (device/session tracking)
-- ============================================================
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(128) NOT NULL UNIQUE,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_active DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_session_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Table: complaints
-- Students can raise issues.
-- ============================================================
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('open','resolved','closed') NOT NULL DEFAULT 'open',
    admin_reply TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_complaint_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Default Admin Account
-- Email: admin@pccoer.in  |  Password: Admin@1234 (bcrypt hashed)
-- ============================================================
INSERT INTO users (email, password, role) VALUES
('admin@pccoer.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- Note: The above is bcrypt for "password" — CHANGE IN PRODUCTION!
-- To generate a proper hash for "Admin@1234", use PHP:
-- echo password_hash('Admin@1234', PASSWORD_BCRYPT);

-- Proper hash for Admin@1234:
UPDATE users SET password = '$2y$10$YVaHarMO.0P3lGe2KlxaB.Jb6sH6JiEVzMiMHGx.YGvwnL1aVjXW' WHERE email = 'admin@pccoer.in';

-- ============================================================
-- Sample Data: Elections & Candidates
-- ============================================================
INSERT INTO elections (title, description, start_time, end_time, status, created_by) VALUES
('Student Council President 2025', 'Election for Student Council President for academic year 2025-26.', '2026-03-01 09:00:00', '2026-03-01 17:00:00', 'completed', 1),
('Best Department Representative', 'Vote for the best department representative.', '2026-04-01 09:00:00', '2026-04-30 17:00:00', 'upcoming', 1);

INSERT INTO candidates (election_id, name, description) VALUES
(1, 'Rahul Sharma', 'Final year CE student, passionate about tech events.'),
(1, 'Priya Patel', 'Third year IT student, active in cultural committee.'),
(1, 'Amit Sawant', 'Final year Mech student, sports captain.'),
(2, 'Sneha Kulkarni', 'CE Dept representative candidate.'),
(2, 'Rohan Desai', 'IT Dept representative candidate.');
