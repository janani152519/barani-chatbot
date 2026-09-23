<?php
require_once __DIR__ . '/../config/database.php';
$pdo = Database::getConnection();

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

$pdo->exec("
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NULL,
    department_id INT NOT NULL,
    designation VARCHAR(100) NOT NULL,
    joining_date DATE NOT NULL,
    salary DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    manager_id INT NULL,
    status ENUM('active', 'inactive', 'on_leave') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'half_day', 'late', 'on_leave') NOT NULL,
    check_in TIME NULL,
    check_out TIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month TINYINT NOT NULL,
    year INT NOT NULL,
    basic_salary DECIMAL(10,2) NOT NULL,
    allowances DECIMAL(10,2) DEFAULT 0.00,
    deductions DECIMAL(10,2) DEFAULT 0.00,
    net_salary DECIMAL(10,2) NOT NULL,
    payment_status ENUM('pending', 'processed', 'paid') DEFAULT 'pending',
    payment_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mail_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(50) NOT NULL,
    recipient_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Insert default HR seed records if empty
$deptCount = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
if ($deptCount == 0) {
    $pdo->exec("
    INSERT INTO departments (id, name, code, description) VALUES
    (1, 'Human Resources', 'HR', 'Manages employee relations, recruiting, and company policy'),
    (2, 'Finance', 'FIN', 'Handles payroll, accounting, and financial management'),
    (3, 'Engineering', 'ENG', 'Product development and technical infrastructure'),
    (4, 'Operations', 'OPS', 'Day-to-day business operations and logistics');

    INSERT INTO employees (id, employee_code, first_name, last_name, email, phone, department_id, designation, joining_date, salary, manager_id, status) VALUES
    (1, 'EMP001', 'Janani', 'Prakash', 'janani@company.com', '9876543210', 1, 'Data Analyst', '2025-01-15', 35000.00, 2, 'active'),
    (2, 'EMP002', 'Rajesh', 'Kumar', 'rajesh@company.com', '9876543211', 1, 'HR Manager', '2024-01-10', 65000.00, NULL, 'active'),
    (3, 'EMP003', 'Priya', 'Sharma', 'priya@company.com', '9876543212', 2, 'Senior Accountant', '2023-05-20', 55000.00, NULL, 'active'),
    (4, 'EMP004', 'Amit', 'Shah', 'amit@company.com', '9876543213', 2, 'Finance Lead', '2022-11-01', 75000.00, NULL, 'active');

    INSERT INTO attendance (id, employee_id, date, status, check_in, check_out) VALUES
    (1, 1, '2025-08-01', 'absent', NULL, NULL),
    (2, 2, '2025-08-01', 'present', '09:00:00', '18:00:00'),
    (3, 3, '2025-08-01', 'absent', NULL, NULL);

    INSERT INTO payroll (id, employee_id, month, year, basic_salary, allowances, deductions, net_salary, payment_status, payment_date) VALUES
    (1, 1, 8, 2025, 30000.00, 5000.00, 1000.00, 34000.00, 'paid', '2025-08-31');

    INSERT INTO mail_recipients (group_name, recipient_name, email) VALUES
    ('HR', 'HR Department', 'hr@barani.com'),
    ('Finance', 'Finance Team', 'finance@barani.com');
    ");
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "HR tables verified and seeded successfully!\n";
