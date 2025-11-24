-- Create the database
CREATE DATABASE IF NOT EXISTS job_portal;
USE job_portal;

-- USERS TABLE with is_approved and employer-specific fields
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'employer', 'jobseeker') NOT NULL,
    resume VARCHAR(255) DEFAULT NULL, -- Resume for jobseekers
    business_name VARCHAR(255) DEFAULT NULL, -- Business name for employers
    business_id VARCHAR(100) DEFAULT NULL, -- Business ID for employers
    contact_number VARCHAR(15) DEFAULT NULL, -- Contact number for employers
    is_approved TINYINT(1) DEFAULT 0, -- 1 = approved, 0 = pending (for employers)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- JOBS TABLE
CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employer_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(100) NOT NULL,
    salary DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- APPLICATIONS TABLE with `notified` column
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    jobseeker_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    notified TINYINT(1) DEFAULT 0, -- 1 = notified, 0 = not yet
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (jobseeker_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,  -- the jobseeker id
    message TEXT NOT NULL,
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0,  -- to track read/unread notifications
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default admin user (password: 12345)
INSERT INTO users (name, email, password, role)
VALUES (
    'Admin',
    'admin@gmail.com',
    '$2y$10$u1eUD0kPzFzFwXx0tMYP0uFQy3ZZXEOzU0Z.pWpV/ht/1XOVG7J7C', -- hashed '12345'
    'admin'
);

-- Optional: Update admin password again if needed (hashed)
UPDATE users 
SET password = '$2y$10$4eBJTYL2PD.vYWrpAGwlregSqCrcjn3Oi13WZcWsVGqRNGmvpmFBu' 
WHERE email = 'admin@gmail.com';

ALTER TABLE jobs
ADD COLUMN job_type VARCHAR(50) DEFAULT NULL AFTER salary,
ADD COLUMN experience_level VARCHAR(50) DEFAULT NULL AFTER job_type,
ADD COLUMN education_level VARCHAR(100) DEFAULT NULL AFTER experience_level,
ADD COLUMN skills_required TEXT DEFAULT NULL AFTER education_level;

ALTER TABLE users
ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER resume;

ALTER TABLE users
ADD COLUMN address TEXT DEFAULT NULL AFTER contact_number;

