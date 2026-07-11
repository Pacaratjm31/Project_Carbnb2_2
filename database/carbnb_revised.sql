CREATE DATABASE IF NOT EXISTS carbnb;
USE carbnb;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('renter','owner','admin') NOT NULL,

    login_attempts INT DEFAULT 0,
    locked_until DATETIME NULL,

    face_image VARCHAR(255) DEFAULT NULL,
    face_verified TINYINT(1) DEFAULT 0,

    status ENUM('pending','approved','disapproved') DEFAULT 'pending',
    disapproval_reason TEXT NULL,

    is_deleted TINYINT(1) DEFAULT 0,
    deleted_at DATETIME NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE user_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    document_type ENUM(
        'id1',
        'id2',
        'proof_of_billing',
        'drivers_license',
        'nbi_clearance',
        'intro_video'
    ) NOT NULL,

    file_path VARCHAR(255) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);

CREATE TABLE vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,

    name VARCHAR(100) NOT NULL,
    description TEXT,
    model_year YEAR NOT NULL,

    category ENUM('4-5_seater','6-7_seater','8-9_seater','10+_seater') NOT NULL,
    transmission ENUM('manual','automatic') NOT NULL,

    price_per_day DECIMAL(10,2) NOT NULL,
    image VARCHAR(255) NULL,

    availability_status ENUM('available','rented','maintenance') DEFAULT 'available',
    approval_status ENUM('pending','approved','disapproved') DEFAULT 'pending',
    approval_feedback TEXT NULL,

    is_deleted TINYINT(1) DEFAULT 0,
    deleted_at DATETIME NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    renter_id INT NOT NULL,
    vehicle_id INT NOT NULL,

    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,

    status ENUM('pending','approved','disapproved','completed') DEFAULT 'pending',
    admin_id INT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (renter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,

    amount DECIMAL(10,2) NOT NULL,
    proof_image VARCHAR(255) NULL,
    payment_method ENUM('gcash','paymaya','cash','bank_transfer') NULL,
    transaction_reference VARCHAR(100) NULL,
    gateway_response TEXT NULL,
    paid_at DATETIME NULL,

    status ENUM('pending','verified','disapproved') DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

CREATE TABLE earnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,

    owner_income DECIMAL(10,2) NOT NULL,
    platform_commission DECIMAL(10,2) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    renter_id INT NOT NULL,
    owner_id INT NOT NULL,
    vehicle_id INT NOT NULL,

    rating INT CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    reply TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (renter_id) REFERENCES users(id),
    FOREIGN KEY (owner_id) REFERENCES users(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
);

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    reply TEXT NULL,
    is_replied TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    replied_at DATETIME NULL
);
