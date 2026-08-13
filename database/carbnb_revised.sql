-- ============================================================
-- CARBNB DATABASE
-- InfinityFree / phpMyAdmin Compatible Version
-- ============================================================
--
-- IMPORTANT:
-- Select your actual Carbnb database in phpMyAdmin BEFORE
-- running this SQL.
--
-- Do NOT use:
-- USE carbnb;
--
-- InfinityFree database names normally have an account prefix.
-- ============================================================


-- ============================================================
-- USERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,

    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,

    role ENUM(
        'renter',
        'owner',
        'admin'
    ) NOT NULL,

    login_attempts INT DEFAULT 0,
    locked_until DATETIME NULL,

    face_image VARCHAR(255) DEFAULT NULL,
    face_verified TINYINT(1) DEFAULT 0,

    status ENUM(
        'pending',
        'approved',
        'disapproved'
    ) DEFAULT 'pending',

    disapproval_reason TEXT NULL,

    is_deleted TINYINT(1) DEFAULT 0,
    deleted_at DATETIME NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- USER DOCUMENTS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS user_documents (
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

    CONSTRAINT fk_user_documents_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- VEHICLES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,

    owner_id INT NOT NULL,

    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    model_year YEAR NOT NULL,

    category ENUM(
        '4-5_seater',
        '6-7_seater',
        '8-9_seater',
        '10+_seater'
    ) NOT NULL,

    transmission ENUM(
        'manual',
        'automatic'
    ) NOT NULL,

    price_per_day DECIMAL(10,2) NOT NULL,

    image VARCHAR(255) NULL,

    availability_status ENUM(
        'available',
        'rented',
        'maintenance'
    ) DEFAULT 'available',

    approval_status ENUM(
        'pending',
        'approved',
        'disapproved'
    ) DEFAULT 'pending',

    approval_feedback TEXT NULL,

    is_deleted TINYINT(1) DEFAULT 0,
    deleted_at DATETIME NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_vehicles_owner
        FOREIGN KEY (owner_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- BOOKINGS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,

    renter_id INT NOT NULL,
    vehicle_id INT NOT NULL,

    start_date DATE NOT NULL,
    end_date DATE NOT NULL,

    total_days INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,

    status ENUM(
        'pending',
        'approved',
        'disapproved',
        'completed'
    ) DEFAULT 'pending',

    admin_id INT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_bookings_renter
        FOREIGN KEY (renter_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_bookings_vehicle
        FOREIGN KEY (vehicle_id)
        REFERENCES vehicles(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_bookings_admin
        FOREIGN KEY (admin_id)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- PAYMENTS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,

    booking_id INT NOT NULL,

    amount DECIMAL(10,2) NOT NULL,

    proof_image VARCHAR(255) NULL,

    payment_method ENUM(
        'gcash',
        'paymaya',
        'cash',
        'bank_transfer',
        'xendit'
    ) NULL,

    transaction_reference VARCHAR(100) NULL,

    gateway_response TEXT NULL,

    paid_at DATETIME NULL,

    status ENUM(
        'pending',
        'verified',
        'disapproved'
    ) DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    gateway_payment_id VARCHAR(255) NULL,

    payment_url TEXT NULL,

    gateway_status VARCHAR(50) NULL,

    CONSTRAINT fk_payments_booking
        FOREIGN KEY (booking_id)
        REFERENCES bookings(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- EARNINGS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS earnings (
    id INT AUTO_INCREMENT PRIMARY KEY,

    booking_id INT NOT NULL,

    owner_income DECIMAL(10,2) NOT NULL,

    platform_commission DECIMAL(10,2) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_earnings_booking
        FOREIGN KEY (booking_id)
        REFERENCES bookings(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- REVIEWS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,

    renter_id INT NOT NULL,
    owner_id INT NOT NULL,
    vehicle_id INT NULL,

    -- Changed from:
    -- rating INT CHECK (rating BETWEEN 1 AND 5)
    --
    -- TINYINT UNSIGNED is more compatible with older
    -- MySQL/MariaDB versions used by some hosting services.
    rating TINYINT UNSIGNED NOT NULL,

    feedback TEXT NULL,

    comment TEXT NULL,

    reply TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reviews_renter
        FOREIGN KEY (renter_id)
        REFERENCES users(id),

    CONSTRAINT fk_reviews_owner
        FOREIGN KEY (owner_id)
        REFERENCES users(id),

    CONSTRAINT fk_reviews_vehicle
        FOREIGN KEY (vehicle_id)
        REFERENCES vehicles(id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- MESSAGES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,

    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,

    message TEXT NOT NULL,

    is_read TINYINT(1) DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_messages_sender
        FOREIGN KEY (sender_id)
        REFERENCES users(id),

    CONSTRAINT fk_messages_receiver
        FOREIGN KEY (receiver_id)
        REFERENCES users(id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- CONTACT MESSAGES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    email VARCHAR(100) NOT NULL,

    message TEXT NOT NULL,

    reply TEXT NULL,

    is_replied TINYINT(1) DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    replied_at DATETIME NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- INSPECT TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS inspect (
    id INT AUTO_INCREMENT PRIMARY KEY,

    booking_id INT NOT NULL,

    renter_id INT NOT NULL,

    owner_id INT NOT NULL,

    vehicle_id INT NOT NULL,

    front_image VARCHAR(255) NULL,
    back_image VARCHAR(255) NULL,
    left_image VARCHAR(255) NULL,
    right_image VARCHAR(255) NULL,

    reason TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_inspect_booking
        FOREIGN KEY (booking_id)
        REFERENCES bookings(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_inspect_renter
        FOREIGN KEY (renter_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_inspect_owner
        FOREIGN KEY (owner_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_inspect_vehicle
        FOREIGN KEY (vehicle_id)
        REFERENCES vehicles(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- LOCATION TRACKER TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS location_tracker (
    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    latitude DECIMAL(10,8) NOT NULL,

    longitude DECIMAL(11,8) NOT NULL,

    accuracy DECIMAL(10,2) DEFAULT 0,

    recorded_at DATETIME NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_location_user_id (user_id),

    INDEX idx_location_recorded_at (recorded_at),

    INDEX idx_location_user_recorded (
        user_id,
        recorded_at
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- END OF CARBNB DATABASE
-- ============================================================

