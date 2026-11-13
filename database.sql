CREATE DATABASE IF NOT EXISTS asset_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE asset_management;

CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

INSERT IGNORE INTO admin (username, password)
VALUES
    ('admin@gmail.com', '$2y$10$4mXkTfUs6CC3R7OjlZfmg.umpfzmc7ojJyf20i8Re1lhY52HFRHIu'); -- Password: admin123

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS sub_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_sub_category (category_id, name),
    FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100) NOT NULL,
    mobile VARCHAR(30) NOT NULL,
    email VARCHAR(150) NULL,
    address TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vendor_name (name)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category_id INT NULL,
    sub_category_id INT NULL,
    brand_id INT NULL,
    model_number VARCHAR(100) NULL,
    serial_number VARCHAR(100) NULL,
    purchase_date DATE NOT NULL,
    purchase_cost DECIMAL(12, 2) NOT NULL DEFAULT 0,
    vendor_id INT NULL,
    invoice_number VARCHAR(100) NULL,
    warranty_months INT NOT NULL DEFAULT 0,
    warranty_expiry DATE NULL,
    department_id INT NULL,
    location_id INT NULL,
    condition_status ENUM('New', 'Good', 'Fair', 'Poor', 'Damaged') NOT NULL DEFAULT 'New',
    description TEXT NULL,
    photo VARCHAR(255) NULL,
    depreciation_enabled TINYINT(1) NOT NULL DEFAULT 0,
    depreciation_method ENUM('Straight Line', 'Written Down Value') NOT NULL DEFAULT 'Straight Line',
    useful_life_years DECIMAL(6, 2) NOT NULL DEFAULT 0,
    salvage_value DECIMAL(12, 2) NOT NULL DEFAULT 0,
    wdv_rate DECIMAL(5, 2) NOT NULL DEFAULT 20,
    status ENUM('Available', 'Assigned', 'Under Maintenance', 'Disposed') NOT NULL DEFAULT 'Available',
    current_holder VARCHAR(150) NULL,
    holder_mobile VARCHAR(30) NULL,
    holder_department_id INT NULL,
    maintenance_previous_status ENUM('Available', 'Assigned') NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
    FOREIGN KEY (sub_category_id) REFERENCES sub_categories (id) ON DELETE SET NULL,
    FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE SET NULL,
    FOREIGN KEY (vendor_id) REFERENCES vendors (id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
    FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE SET NULL,
    FOREIGN KEY (holder_department_id) REFERENCES departments (id) ON DELETE SET NULL,
    INDEX idx_assets_status (status),
    INDEX idx_assets_purchase (purchase_date),
    INDEX idx_assets_warranty (warranty_expiry),
    INDEX idx_assets_holder (current_holder),
    INDEX idx_assets_code (asset_code)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_date DATE NOT NULL,
    assigned_to VARCHAR(150) NOT NULL,
    department_id INT NULL,
    mobile VARCHAR(30) NULL,
    condition_at_assignment ENUM('New', 'Good', 'Fair', 'Poor', 'Damaged') NOT NULL DEFAULT 'Good',
    notes TEXT NULL,
    assigned_by VARCHAR(50) NOT NULL DEFAULT 'Admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
    INDEX idx_assignment_date (assignment_date),
    INDEX idx_assigned_to (assigned_to)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS assignment_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    asset_id INT NOT NULL,
    FOREIGN KEY (assignment_id) REFERENCES assignments (id) ON DELETE RESTRICT,
    FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_assignment_asset (assignment_id, asset_id),
    INDEX idx_assignment_asset (asset_id)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NULL,
    asset_id INT NOT NULL,
    holder_name VARCHAR(150) NOT NULL,
    return_date DATE NOT NULL,
    condition_on_return ENUM('New', 'Good', 'Fair', 'Poor', 'Damaged') NOT NULL DEFAULT 'Good',
    is_damaged TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    returned_by VARCHAR(50) NOT NULL DEFAULT 'Admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES assignments (id) ON DELETE SET NULL,
    FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE RESTRICT,
    INDEX idx_return_date (return_date),
    INDEX idx_return_asset (asset_id)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    old_holder VARCHAR(150) NOT NULL,
    new_holder VARCHAR(150) NOT NULL,
    new_department_id INT NULL,
    new_location_id INT NULL,
    transfer_date DATE NOT NULL,
    reason TEXT NULL,
    transferred_by VARCHAR(50) NOT NULL DEFAULT 'Admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE RESTRICT,
    FOREIGN KEY (new_department_id) REFERENCES departments (id) ON DELETE SET NULL,
    FOREIGN KEY (new_location_id) REFERENCES locations (id) ON DELETE SET NULL,
    INDEX idx_transfer_date (transfer_date),
    INDEX idx_transfer_asset (asset_id)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    vendor_id INT NULL,
    maintenance_date DATE NOT NULL,
    maintenance_type ENUM('Upgrade', 'Inspection', 'Corrective') NOT NULL,
    cost DECIMAL(12, 2) NOT NULL DEFAULT 0,
    next_service_date DATE NULL,
    status ENUM('In Progress', 'Completed') NOT NULL DEFAULT 'In Progress',
    previous_asset_status ENUM('Available', 'Assigned') NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE RESTRICT,
    FOREIGN KEY (vendor_id) REFERENCES vendors (id) ON DELETE SET NULL,
    INDEX idx_maintenance_status (status),
    INDEX idx_next_service (next_service_date),
    INDEX idx_maintenance_asset (asset_id)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS disposals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL UNIQUE,
    disposal_date DATE NOT NULL,
    reason TEXT NOT NULL,
    disposal_value DECIMAL(12, 2) NOT NULL DEFAULT 0,
    disposed_by VARCHAR(50) NOT NULL DEFAULT 'Admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE RESTRICT
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    category VARCHAR(100) NOT NULL,
    involved_person VARCHAR(150) NULL,
    amount DECIMAL(12, 2) NOT NULL,
    expense_date DATE NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE RESTRICT,
    INDEX idx_expense_date (expense_date),
    INDEX idx_expense_category (category)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NULL,
    activity_type ENUM('Purchase', 'Assignment', 'Return', 'Transfer', 'Maintenance', 'Disposal', 'Edit') NOT NULL,
    activity_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    description TEXT NOT NULL,
    performed_by VARCHAR(50) NOT NULL DEFAULT 'Admin',
    FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE SET NULL,
    INDEX idx_activity_asset (asset_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_activity_date (activity_date)
) ENGINE = InnoDB;

INSERT IGNORE INTO categories (name)
VALUES
    ('Computer Equipment'),
    ('Office Furniture'),
    ('Office Equipment'),
    ('Communication Equipment');

INSERT IGNORE INTO sub_categories (category_id, name)
SELECT id, 'Laptop'
FROM categories
WHERE name = 'Computer Equipment';

INSERT IGNORE INTO sub_categories (category_id, name)
SELECT id, 'Desktop Computer'
FROM categories
WHERE name = 'Computer Equipment';

INSERT IGNORE INTO sub_categories (category_id, name)
SELECT id, 'Monitor'
FROM categories
WHERE name = 'Computer Equipment';

INSERT IGNORE INTO sub_categories (category_id, name)
SELECT id, 'Office Chair'
FROM categories
WHERE name = 'Office Furniture';

INSERT IGNORE INTO sub_categories (category_id, name)
SELECT id, 'Office Table'
FROM categories
WHERE name = 'Office Furniture';

INSERT IGNORE INTO sub_categories (category_id, name)
SELECT id, 'Printer'
FROM categories
WHERE name = 'Office Equipment';

INSERT IGNORE INTO brands (name)
VALUES
    ('Dell'),
    ('HP'),
    ('Lenovo'),
    ('Acer'),
    ('Canon');

INSERT IGNORE INTO departments (name)
VALUES
    ('Administration'),
    ('Finance'),
    ('IT Department'),
    ('Human Resources'),
    ('Operations');

INSERT IGNORE INTO locations (name)
VALUES
    ('Main Office'),
    ('Storage Room'),
    ('IT Office'),
    ('Finance Office');

INSERT IGNORE INTO vendors (name, contact_person, mobile, email)
VALUES
    ('ABC Computer Supplies', 'Juan Dela Cruz', '09123456789', 'abc@example.com');

INSERT IGNORE INTO assets (
    asset_code,
    name,
    category_id,
    sub_category_id,
    brand_id,
    model_number,
    serial_number,
    purchase_date,
    purchase_cost,
    vendor_id,
    invoice_number,
    warranty_months,
    warranty_expiry,
    department_id,
    location_id,
    condition_status,
    depreciation_enabled,
    depreciation_method,
    useful_life_years,
    salvage_value,
    status
)
SELECT
    'AST-0001',
    'Office Laptop',
    c.id,
    s.id,
    b.id,
    'Latitude 5420',
    'DEMO-001',
    '2026-01-15',
    45000,
    v.id,
    'INV-001',
    12,
    '2027-01-15',
    d.id,
    l.id,
    'Good',
    1,
    'Straight Line',
    5,
    5000,
    'Available'
FROM categories c
JOIN sub_categories s ON s.category_id = c.id
    AND s.name = 'Laptop'
JOIN brands b ON b.name = 'Dell'
JOIN vendors v ON v.name = 'ABC Computer Supplies'
JOIN departments d ON d.name = 'IT Department'
JOIN locations l ON l.name = 'Main Office';

INSERT INTO activity_log (asset_id, activity_type, description, performed_by)
SELECT id, 'Purchase', 'Initial sample asset purchase and registration.', 'Admin'
FROM assets
WHERE asset_code = 'AST-0001';
