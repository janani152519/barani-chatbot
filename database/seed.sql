-- Seed Data for gri_db Database
USE gri_db;

-- Disable Foreign Keys during seeding
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE audit_logs;
TRUNCATE TABLE mail_recipients;
TRUNCATE TABLE reports;
TRUNCATE TABLE chat_messages;
TRUNCATE TABLE chat_sessions;
TRUNCATE TABLE tasks;
TRUNCATE TABLE payroll;
TRUNCATE TABLE leave_records;
TRUNCATE TABLE attendance;
TRUNCATE TABLE users;
TRUNCATE TABLE employees;
TRUNCATE TABLE departments;

-- 1. Departments
INSERT INTO departments (id, name, code, description) VALUES
(1, 'Human Resources', 'HR', 'Manages employee relations, recruiting, and company policy'),
(2, 'Finance', 'FIN', 'Handles payroll, accounting, and financial management'),
(3, 'Engineering', 'ENG', 'Product development and technical infrastructure'),
(4, 'Operations', 'OPS', 'Day-to-day business operations and logistics');

-- 2. Employees
-- Note: Janani joined after Jan 2025 (2025-01-15), salary 35000.00
INSERT INTO employees (id, employee_code, first_name, last_name, email, phone, department_id, designation, joining_date, salary, manager_id, status) VALUES
(1, 'EMP001', 'Janani', 'Prakash', 'janani@company.com', '9876543210', 1, 'Data Analyst', '2025-01-15', 35000.00, 2, 'active'),
(2, 'EMP002', 'Rajesh', 'Kumar', 'rajesh@company.com', '9876543211', 1, 'HR Manager', '2024-01-10', 65000.00, NULL, 'active'),
(3, 'EMP003', 'Priya', 'Sharma', 'priya@company.com', '9876543212', 3, 'Software Engineer', '2025-02-01', 75000.00, NULL, 'active'),
(4, 'EMP004', 'Amit', 'Patel', 'amit@company.com', '9876543213', 2, 'Finance Lead', '2024-03-15', 85000.00, NULL, 'active'),
(5, 'EMP005', 'Sneha', 'Rao', 'sneha@company.com', '9876543214', 4, 'Operations Executive', '2025-02-10', 42000.00, NULL, 'active'),
(6, 'EMP006', 'Kiran', 'Verma', 'kiran@company.com', '9876543215', 1, 'HR Specialist', '2025-01-20', 40000.00, 2, 'active');

-- Users (Accounts with default passwords)
-- Admin Password Hash for 'admin123': $2y$10$lCEBtGeFEFqCAY69aZKT7uq.j6jbfOiVWhkv9jX74js7lTKGnOuj2
-- Other Users Password Hash for 'password123': $2y$10$LRVY4emFxDcYhBGEVZ9HOuzgqViHNyjNdIWwXaVmGLOH9bVZcNE2q
INSERT INTO users (id, username, email, password_hash, role, employee_id, is_active) VALUES
(1, 'admin', 'admin@barani.com', '$2y$10$lCEBtGeFEFqCAY69aZKT7uq.j6jbfOiVWhkv9jX74js7lTKGnOuj2', 'admin', NULL, 1),
(2, 'janani', 'janani@company.com', '$2y$10$LRVY4emFxDcYhBGEVZ9HOuzgqViHNyjNdIWwXaVmGLOH9bVZcNE2q', 'employee', 1, 1),
(3, 'rajesh', 'rajesh@company.com', '$2y$10$LRVY4emFxDcYhBGEVZ9HOuzgqViHNyjNdIWwXaVmGLOH9bVZcNE2q', 'hr', 2, 1),
(4, 'priya', 'priya@company.com', '$2y$10$LRVY4emFxDcYhBGEVZ9HOuzgqViHNyjNdIWwXaVmGLOH9bVZcNE2q', 'manager', 3, 1),
(5, 'amit', 'amit@company.com', '$2y$10$LRVY4emFxDcYhBGEVZ9HOuzgqViHNyjNdIWwXaVmGLOH9bVZcNE2q', 'finance', 4, 1);

-- 4. Attendance (August 2025 / August 2026 data for absence queries)
INSERT INTO attendance (employee_id, date, status, check_in, check_out, notes) VALUES
(1, '2025-08-01', 'present', '09:00:00', '17:30:00', 'On time'),
(1, '2025-08-02', 'absent', NULL, NULL, 'Personal reason'),
(1, '2025-08-03', 'present', '09:10:00', '17:35:00', 'On time'),
(2, '2025-08-01', 'present', '08:55:00', '17:30:00', 'On time'),
(2, '2025-08-02', 'absent', NULL, NULL, 'Sick leave'),
(3, '2025-08-01', 'present', '09:05:00', '18:00:00', 'On time'),
(3, '2025-08-02', 'absent', NULL, NULL, 'Casual leave'),
(4, '2025-08-01', 'present', '09:00:00', '17:30:00', 'On time'),
(4, '2025-08-02', 'present', '09:00:00', '17:30:00', 'On time'),
(5, '2025-08-01', 'absent', NULL, NULL, 'Unplanned absence'),
(6, '2025-08-01', 'present', '09:15:00', '17:30:00', 'Late entry'),
(6, '2025-08-02', 'absent', NULL, NULL, 'Medical emergency');

-- 5. Leave Records
INSERT INTO leave_records (employee_id, leave_type, start_date, end_date, days, status, reason) VALUES
(1, 'casual', '2025-08-02', '2025-08-02', 1, 'approved', 'Personal work'),
(2, 'sick', '2025-08-02', '2025-08-02', 1, 'approved', 'Fever'),
(3, 'annual', '2025-08-02', '2025-08-02', 1, 'approved', 'Family trip'),
(6, 'sick', '2025-08-02', '2025-08-02', 1, 'approved', 'Hospital visit');

-- 6. Payroll Records (August 2025 & August 2026)
INSERT INTO payroll (employee_id, month, year, basic_salary, allowances, deductions, net_salary, payment_status, payment_date) VALUES
(1, 8, 2025, 30000.00, 5000.00, 1000.00, 34000.00, 'paid', '2025-08-31'),
(2, 8, 2025, 55000.00, 10000.00, 3000.00, 62000.00, 'paid', '2025-08-31'),
(3, 8, 2025, 65000.00, 10000.00, 4000.00, 71000.00, 'paid', '2025-08-31'),
(4, 8, 2025, 75000.00, 10000.00, 5000.00, 80000.00, 'paid', '2025-08-31'),
(5, 8, 2025, 36000.00, 6000.00, 1500.00, 40500.00, 'paid', '2025-08-31'),
(6, 8, 2025, 35000.00, 5000.00, 1000.00, 39000.00, 'paid', '2025-08-31');

-- 7. Tasks
INSERT INTO tasks (title, description, employee_id, assigned_by, status, priority, due_date) VALUES
('Prepare HR Analytics Report', 'Analyze Q3 hiring metrics', 1, 2, 'in_progress', 'high', '2025-09-15'),
('Audit Financial Records', 'Review August expenses', 4, 1, 'pending', 'urgent', '2025-09-20'),
('Update Backend APIs', 'Optimize database queries', 3, 4, 'completed', 'medium', '2025-08-30');

-- 8. Mail Recipients Group Mapping
INSERT INTO mail_recipients (group_name, recipient_name, email, is_active) VALUES
('HR', 'HR Department', 'hr@company.com', 1),
('Finance', 'Finance Team', 'finance@company.com', 1),
('Management', 'Executive Board', 'management@company.com', 1),
('Admin', 'System Administrator', 'admin@company.com', 1);

-- Re-enable Foreign Keys
SET FOREIGN_KEY_CHECKS = 1;
