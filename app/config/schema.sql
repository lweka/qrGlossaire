CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(191) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    status ENUM('active', 'suspended') DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(191) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    phone VARCHAR(20),
    user_type ENUM('admin', 'organizer') DEFAULT 'organizer',
    status ENUM('pending', 'active', 'suspended', 'expired') DEFAULT 'pending',
    payment_confirmed BOOLEAN DEFAULT FALSE,
    payment_date DATE,
    unique_link_token VARCHAR(64) UNIQUE,
    token_expiry DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    title VARCHAR(255),
    event_type ENUM('wedding', 'birthday', 'corporate', 'other'),
    event_date DATETIME,
    location TEXT,
    invitation_design JSON,
    settings JSON,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE guests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT,
    guest_code VARCHAR(50) UNIQUE,
    full_name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(20),
    rsvp_status ENUM('pending', 'confirmed', 'declined') DEFAULT 'pending',
    qr_code_path VARCHAR(500),
    check_in_time DATETIME,
    check_in_count INT DEFAULT 0,
    custom_answers JSON,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE TABLE templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    category VARCHAR(50),
    html_content LONGTEXT,
    css_styles LONGTEXT,
    thumbnail_path VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT,
    action_type VARCHAR(100),
    target_user_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
