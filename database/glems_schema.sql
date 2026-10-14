-- =====================================================================
-- GARMENT LINE EFFICIENCY MONITORING SYSTEM (GLEMS)
-- Database Schema + Sample Data (with foreign key fixes)
-- Engine: InnoDB | Charset: utf8mb4
-- =====================================================================

CREATE DATABASE IF NOT EXISTS glems_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE glems_db;

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- 1. ROLES & PERMISSIONS
-- =====================================================================
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    role_slug VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
    permission_id INT AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(100) NOT NULL UNIQUE,
    module VARCHAR(50) NOT NULL,
    description VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 2. USERS
-- =====================================================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    employee_id VARCHAR(20) DEFAULT NULL,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    profile_photo VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    remember_token VARCHAR(100) DEFAULT NULL,
    reset_token VARCHAR(100) DEFAULT NULL,
    reset_token_expiry DATETIME DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB;

-- =====================================================================
-- 3. BUYERS
-- =====================================================================
CREATE TABLE buyers (
    buyer_id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    country VARCHAR(50) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================================
-- 4. PRODUCTION LINES
-- =====================================================================
CREATE TABLE production_lines (
    line_id INT AUTO_INCREMENT PRIMARY KEY,
    line_number VARCHAR(20) NOT NULL UNIQUE,
    floor VARCHAR(30) DEFAULT NULL,
    department VARCHAR(50) DEFAULT NULL,
    buyer_id INT DEFAULT NULL,
    style_number VARCHAR(50) DEFAULT NULL,
    product_name VARCHAR(100) DEFAULT NULL,
    supervisor_id INT DEFAULT NULL,
    operator_count INT DEFAULT 0,
    machine_count INT DEFAULT 0,
    working_hours DECIMAL(4,2) DEFAULT 8.00,
    status ENUM('running','stopped','maintenance','idle') DEFAULT 'idle',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES buyers(buyer_id) ON DELETE SET NULL,
    FOREIGN KEY (supervisor_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 5. ORDERS
-- =====================================================================
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    po_number VARCHAR(50) NOT NULL UNIQUE,
    style_number VARCHAR(50) DEFAULT NULL,
    order_quantity INT NOT NULL DEFAULT 0,
    delivery_date DATE DEFAULT NULL,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    current_progress INT DEFAULT 0,
    status ENUM('pending','in_progress','completed','delayed','cancelled') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES buyers(buyer_id)
) ENGINE=InnoDB;

-- =====================================================================
-- 6. PRODUCTION TARGETS
-- =====================================================================
CREATE TABLE production_targets (
    target_id INT AUTO_INCREMENT PRIMARY KEY,
    line_id INT NOT NULL,
    target_date DATE NOT NULL,
    target_type ENUM('daily','weekly','monthly') DEFAULT 'daily',
    target_quantity INT NOT NULL DEFAULT 0,
    expected_working_hours DECIMAL(4,2) DEFAULT 8.00,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (line_id) REFERENCES production_lines(line_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY uq_target (line_id, target_date, target_type)
) ENGINE=InnoDB;

-- =====================================================================
-- 7. PRODUCTION RECORDS (hourly entries)
-- =====================================================================
CREATE TABLE production_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    line_id INT NOT NULL,
    record_date DATE NOT NULL,
    hour_slot TINYINT NOT NULL COMMENT '1-24 representing the working hour',
    produced_qty INT NOT NULL DEFAULT 0,
    rejected_qty INT NOT NULL DEFAULT 0,
    remarks VARCHAR(255) DEFAULT NULL,
    entered_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (line_id) REFERENCES production_lines(line_id) ON DELETE CASCADE,
    FOREIGN KEY (entered_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY uq_hourly_entry (line_id, record_date, hour_slot)
) ENGINE=InnoDB;

-- =====================================================================
-- 8. OPERATORS
-- =====================================================================
CREATE TABLE operators (
    operator_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    nic VARCHAR(20) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    department VARCHAR(50) DEFAULT NULL,
    line_id INT DEFAULT NULL,
    position VARCHAR(50) DEFAULT NULL,
    date_joined DATE DEFAULT NULL,
    status ENUM('active','inactive','resigned') DEFAULT 'active',
    photo VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (line_id) REFERENCES production_lines(line_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 9. ATTENDANCE
-- =====================================================================
CREATE TABLE attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    operator_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    check_in TIME DEFAULT NULL,
    check_out TIME DEFAULT NULL,
    working_hours DECIMAL(4,2) DEFAULT 0,
    late_arrival TINYINT(1) DEFAULT 0,
    overtime_hours DECIMAL(4,2) DEFAULT 0,
    status ENUM('present','absent','leave','half_day') DEFAULT 'present',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (operator_id) REFERENCES operators(operator_id) ON DELETE CASCADE,
    UNIQUE KEY uq_attendance (operator_id, attendance_date)
) ENGINE=InnoDB;

-- =====================================================================
-- 10. MACHINES
-- =====================================================================
CREATE TABLE machines (
    machine_id INT AUTO_INCREMENT PRIMARY KEY,
    machine_number VARCHAR(20) NOT NULL UNIQUE,
    machine_name VARCHAR(100) DEFAULT NULL,
    machine_type VARCHAR(50) DEFAULT NULL,
    brand VARCHAR(50) DEFAULT NULL,
    model VARCHAR(50) DEFAULT NULL,
    serial_number VARCHAR(50) DEFAULT NULL,
    purchase_date DATE DEFAULT NULL,
    warranty_expiry DATE DEFAULT NULL,
    line_id INT DEFAULT NULL,
    current_status ENUM('running','stopped','maintenance','idle') DEFAULT 'idle',
    machine_image VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (line_id) REFERENCES production_lines(line_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 11. MACHINE DOWNTIME
-- =====================================================================
CREATE TABLE machine_downtime (
    downtime_id INT AUTO_INCREMENT PRIMARY KEY,
    machine_id INT NOT NULL,
    problem VARCHAR(255) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME DEFAULT NULL,
    duration_minutes INT DEFAULT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    technician VARCHAR(100) DEFAULT NULL,
    repair_cost DECIMAL(10,2) DEFAULT 0,
    solution TEXT DEFAULT NULL,
    status ENUM('open','in_progress','resolved') DEFAULT 'open',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (machine_id) REFERENCES machines(machine_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 12. QUALITY DEFECTS
-- =====================================================================
CREATE TABLE quality_defects (
    defect_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(100) DEFAULT NULL,
    line_id INT NOT NULL,
    operator_id INT DEFAULT NULL,
    defect_type ENUM('Open Seam','Broken Stitch','Oil Mark','Needle Damage','Missing Stitch','Wrong Size','Fabric Damage','Loose Thread','Other') NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    remarks VARCHAR(255) DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    defect_date DATE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (line_id) REFERENCES production_lines(line_id) ON DELETE CASCADE,
    FOREIGN KEY (operator_id) REFERENCES operators(operator_id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 13. NOTIFICATIONS
-- =====================================================================
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL COMMENT 'NULL = broadcast to all',
    type VARCHAR(50) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message VARCHAR(255) NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 14. ACTIVITY LOGS
-- =====================================================================
CREATE TABLE activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50) DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 15. SETTINGS
-- =====================================================================
CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SAMPLE DATA
-- =====================================================================

-- Roles
INSERT INTO roles (role_name, role_slug, description) VALUES
('Admin', 'admin', 'Full system access'),
('Factory Manager', 'factory_manager', 'Factory-wide oversight'),
('Production Manager', 'production_manager', 'Manages production planning'),
('Line Supervisor', 'line_supervisor', 'Manages a single production line'),
('Maintenance Officer', 'maintenance_officer', 'Handles machine maintenance'),
('Quality Inspector', 'quality_inspector', 'Handles quality checks');

-- Permissions (sample set per module)
INSERT INTO permissions (permission_key, module, description) VALUES
('lines.view','production_lines','View production lines'),
('lines.manage','production_lines','Create/edit/delete production lines'),
('targets.manage','production_targets','Manage production targets'),
('production.entry','production_records','Enter hourly production'),
('operators.manage','operators','Manage operators'),
('machines.manage','machines','Manage machines'),
('downtime.manage','machine_downtime','Manage downtime records'),
('quality.manage','quality_defects','Manage quality defects'),
('attendance.manage','attendance','Manage attendance'),
('orders.manage','orders','Manage orders'),
('reports.view','reports','View and export reports'),
('users.manage','users','Manage system users'),
('settings.manage','settings','Manage system settings');

-- Admin gets everything
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, permission_id FROM permissions;

-- Default admin user, password: Admin@123 (bcrypt hash generated below)
-- NOTE: Replace the hash below with the output of: php -r "echo password_hash('Admin@123', PASSWORD_BCRYPT);"
INSERT INTO users (role_id, employee_id, full_name, username, email, phone, password_hash, status)
VALUES (1, 'EMP-0001', 'System Administrator', 'admin', 'admin@glems.local', '0771234567',
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active');
-- ^ This is a placeholder; generate a real hash and update it.

-- Sample buyers
INSERT INTO buyers (buyer_name, contact_person, email, phone, country, status) VALUES
('H&M', 'Erik Lindqvist', 'erik@hm.com', '+46701234567', 'Sweden', 'active'),
('Marks & Spencer', 'James Carter', 'james@mands.com', '+44207123456', 'UK', 'active'),
('Next Plc', 'Sarah Wilson', 'sarah@next.co.uk', '+44161234567', 'UK', 'active'),
('Decathlon', 'Pierre Dubois', 'pierre@decathlon.com', '+33612345678', 'France', 'active');

-- Sample supervisors (as users)
INSERT INTO users (role_id, employee_id, full_name, username, email, phone, password_hash, status) VALUES
(4, 'EMP-0002', 'Nimal Perera', 'nimal.p', 'nimal@glems.local', '0711112222', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'),
(4, 'EMP-0003', 'Kamala Silva', 'kamala.s', 'kamala@glems.local', '0712223333', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'),
(5, 'EMP-0004', 'Ruwan Fernando', 'ruwan.f', 'ruwan@glems.local', '0713334444', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'),
(6, 'EMP-0005', 'Dilani Jayawardena', 'dilani.j', 'dilani@glems.local', '0714445555', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active');

-- Sample production lines
INSERT INTO production_lines (line_number, floor, department, buyer_id, style_number, product_name, supervisor_id, operator_count, machine_count, working_hours, status) VALUES
('LINE-01', 'Floor 1', 'Sewing', 1, 'STY-1001', 'Men''s Polo T-Shirt', 2, 45, 48, 8.00, 'running'),
('LINE-02', 'Floor 1', 'Sewing', 2, 'STY-1002', 'Ladies Blouse', 3, 40, 42, 8.00, 'running'),
('LINE-03', 'Floor 2', 'Sewing', 3, 'STY-1003', 'Kids Hoodie', NULL, 38, 40, 8.00, 'stopped'),
('LINE-04', 'Floor 2', 'Sewing', 4, 'STY-1004', 'Sports Shorts', NULL, 42, 44, 8.00, 'idle');

-- Sample orders
INSERT INTO orders (buyer_id, po_number, style_number, order_quantity, delivery_date, priority, current_progress, status) VALUES
(1, 'PO-88001', 'STY-1001', 50000, '2026-09-15', 'high', 62, 'in_progress'),
(2, 'PO-88002', 'STY-1002', 30000, '2026-09-01', 'urgent', 40, 'in_progress'),
(3, 'PO-88003', 'STY-1003', 20000, '2026-10-05', 'medium', 0, 'pending'),
(4, 'PO-88004', 'STY-1004', 25000, '2026-08-25', 'high', 78, 'in_progress');

-- Sample production targets (today)
INSERT INTO production_targets (line_id, target_date, target_type, target_quantity, expected_working_hours, created_by) VALUES
(1, CURDATE(), 'daily', 1200, 8.00, 1),
(2, CURDATE(), 'daily', 1000, 8.00, 1),
(3, CURDATE(), 'daily', 900, 8.00, 1),
(4, CURDATE(), 'daily', 1100, 8.00, 1);

-- Sample hourly production records (today, line 1 & 2)
INSERT INTO production_records (line_id, record_date, hour_slot, produced_qty, rejected_qty, remarks, entered_by) VALUES
(1, CURDATE(), 1, 140, 3, NULL, 2),
(1, CURDATE(), 2, 150, 2, NULL, 2),
(1, CURDATE(), 3, 145, 4, NULL, 2),
(2, CURDATE(), 1, 120, 2, NULL, 3),
(2, CURDATE(), 2, 118, 3, NULL, 3);

-- Sample operators
INSERT INTO operators (employee_id, full_name, nic, phone, address, department, line_id, position, date_joined, status) VALUES
('OP-0001', 'Sunil Rathnayake', '881234567V', '0761112222', 'Colombo', 'Sewing', 1, 'Machine Operator', '2022-03-01', 'active'),
('OP-0002', 'Chamari Wijesinghe', '905678123V', '0762223333', 'Gampaha', 'Sewing', 1, 'Machine Operator', '2021-07-15', 'active'),
('OP-0003', 'Nadeeka Bandara', '925647382V', '0763334444', 'Kalutara', 'Sewing', 2, 'Line Leader', '2020-01-10', 'active'),
('OP-0004', 'Ishara Madushani', '958471234V', '0764445555', 'Negombo', 'Sewing', 2, 'Machine Operator', '2023-02-20', 'active');

-- Sample attendance (today)
INSERT INTO attendance (operator_id, attendance_date, check_in, check_out, working_hours, late_arrival, overtime_hours, status) VALUES
(1, CURDATE(), '08:00:00', NULL, 0, 0, 0, 'present'),
(2, CURDATE(), '08:15:00', NULL, 0, 1, 0, 'present'),
(3, CURDATE(), '08:00:00', NULL, 0, 0, 0, 'present'),
(4, CURDATE(), NULL, NULL, 0, 0, 0, 'absent');

-- Sample machines
INSERT INTO machines (machine_number, machine_name, machine_type, brand, model, serial_number, purchase_date, warranty_expiry, line_id, current_status) VALUES
('MC-001', 'Single Needle Lockstitch', 'Sewing Machine', 'Juki', 'DDL-8700', 'JK2021001', '2021-01-15', '2024-01-15', 1, 'running'),
('MC-002', 'Overlock Machine', 'Sewing Machine', 'Brother', 'B737', 'BR2021002', '2021-02-10', '2024-02-10', 1, 'running'),
('MC-003', 'Flatlock Machine', 'Sewing Machine', 'Juki', 'MF-7900', 'JK2021003', '2021-03-05', '2024-03-05', 2, 'stopped'),
('MC-004', 'Button Hole Machine', 'Specialized', 'Brother', 'HE-800B', 'BR2021004', '2021-04-20', '2024-04-20', 3, 'maintenance');

-- Sample machine downtime
INSERT INTO machine_downtime (machine_id, problem, start_time, end_time, duration_minutes, reason, technician, repair_cost, solution, status) VALUES
(3, 'Thread tension fault', CONCAT(CURDATE(), ' 09:00:00'), CONCAT(CURDATE(), ' 09:45:00'), 45, 'Mechanical', 'Ruwan Fernando', 1500.00, 'Adjusted tension disc', 'resolved'),
(4, 'Motor overheating', CONCAT(CURDATE(), ' 10:00:00'), NULL, NULL, 'Electrical', 'Ruwan Fernando', 0.00, NULL, 'in_progress');

-- Sample quality defects (FIXED: recorded_by changed from 6 to 1)
INSERT INTO quality_defects (product_name, line_id, operator_id, defect_type, quantity, remarks, recorded_by, defect_date) VALUES
('Men''s Polo T-Shirt', 1, 1, 'Broken Stitch', 3, 'Needle thread breaking frequently', 1, CURDATE()),
('Ladies Blouse', 2, 3, 'Open Seam', 2, 'Side seam opening', 1, CURDATE());

-- Sample settings
INSERT INTO settings (setting_key, setting_value) VALUES
('company_name', 'Ceylon Apparel Exports (Pvt) Ltd'),
('company_address', 'No. 45, Industrial Zone, Katunayake, Sri Lanka'),
('company_phone', '+94 11 2345678'),
('company_email', 'info@ceylonapparel.lk'),
('currency', 'LKR'),
('efficiency_warning_threshold', '75'),
('timezone', 'Asia/Colombo');