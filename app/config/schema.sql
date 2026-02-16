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
    invitation_credit_total INT NOT NULL DEFAULT 0,
    event_credit_total INT NOT NULL DEFAULT 0,
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
    public_registration_token VARCHAR(64) UNIQUE,
    public_registration_enabled BOOLEAN DEFAULT TRUE,
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

CREATE TABLE credit_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    requested_invitation_credits INT NOT NULL DEFAULT 0,
    requested_event_credits INT NOT NULL DEFAULT 0,
    unit_price_usd DECIMAL(10,2) NOT NULL DEFAULT 0.30,
    amount_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    request_note TEXT NULL,
    admin_note TEXT NULL,
    approved_by_admin_id INT NULL,
    approved_at DATETIME NULL,
    rejected_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_credit_requests_user_status (user_id, status),
    INDEX idx_credit_requests_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE communication_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    event_id INT NULL,
    channel ENUM('email', 'sms', 'whatsapp', 'manual') NOT NULL DEFAULT 'email',
    recipient_scope ENUM('all', 'pending', 'confirmed', 'declined') NOT NULL DEFAULT 'all',
    message_text TEXT NOT NULL,
    recipient_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_communication_logs_user_created (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
);
