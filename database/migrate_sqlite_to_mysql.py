#!/usr/bin/env python3
"""
SQLite → MySQL Full Migration Script
Converts gri_db.db to gri_db_full.sql for import into phpMyAdmin
"""
import sqlite3
import sys
import os
import re
from datetime import datetime

sys.stdout.reconfigure(encoding='utf-8')

SQLITE_PATH = r'C:\Users\Janani Prakash\Downloads\gri_db.db'
OUTPUT_SQL  = r'C:\Users\Janani Prakash\OneDrive\Desktop\backend\database\gri_db_full.sql'

def sqlite_to_mysql_type(sqlite_type):
    t = (sqlite_type or '').upper().strip()
    if 'INT' in t:        return 'BIGINT'
    if 'REAL' in t or 'FLOAT' in t or 'DOUBLE' in t: return 'DOUBLE'
    if 'DECIMAL' in t:    return 'DECIMAL(15,4)'
    if 'BOOL' in t:       return 'TINYINT(1)'
    if t in ('TIMESTAMP','DATETIME'): return 'DATETIME'
    if t == 'DATE':       return 'DATE'
    if t == 'TIME':       return 'TIME'
    if t in ('TEXT','CLOB','BLOB') or not t: return 'LONGTEXT'
    return 'LONGTEXT'

def escape_value(v):
    if v is None:
        return 'NULL'
    if isinstance(v, (int, float)):
        return str(v)
    s = str(v)
    s = s.replace('\\', '\\\\')
    s = s.replace("'", "\\'")
    s = s.replace('\r', '\\r')
    s = s.replace('\n', '\\n')
    s = s.replace('\0', '')
    return f"'{s}'"

def safe_col_name(name):
    return f"`{name}`"

def build_create_table(table, columns):
    col_defs = []
    pk_col = None
    
    for col in columns:
        cid, name, col_type, notnull, dflt, pk = col
        mysql_type = sqlite_to_mysql_type(col_type)
        col_name = safe_col_name(name)
        
        dfn = f"  {col_name} {mysql_type}"
        
        if notnull and dflt is None and not pk:
            dfn += ' NOT NULL'
        elif dflt is not None:
            dv = dflt.strip("'\"")
            if dv.upper() in ('CURRENT_TIMESTAMP', 'NOW()'):
                dfn += " DEFAULT CURRENT_TIMESTAMP"
            elif mysql_type in ('BIGINT',) and dv.lstrip('-').isdigit():
                dfn += f" DEFAULT {dv}"
            elif mysql_type in ('DOUBLE', 'DECIMAL(15,4)') and re.match(r'^-?\d+\.?\d*$', dv):
                dfn += f" DEFAULT {dv}"
            elif mysql_type == 'TINYINT(1)' and dv in ('0','1'):
                dfn += f" DEFAULT {dv}"
            else:
                escaped = dv.replace("'", "\\'")
                dfn += f" DEFAULT '{escaped}'"
        
        if pk == 1:
            dfn += ' AUTO_INCREMENT'
            pk_col = col_name
        
        col_defs.append(dfn)
    
    if pk_col:
        col_defs.append(f"  PRIMARY KEY ({pk_col})")
    
    tname = f"`{table}`"
    create = f"CREATE TABLE IF NOT EXISTS {tname} (\n"
    create += ",\n".join(col_defs)
    create += "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    return create

def main():
    print(f"Connecting to SQLite: {SQLITE_PATH}")
    conn = sqlite3.connect(SQLITE_PATH)
    cursor = conn.cursor()
    
    cursor.execute("SELECT name FROM sqlite_master WHERE type='table' AND name != 'sqlite_sequence' ORDER BY name")
    tables = [t[0] for t in cursor.fetchall()]
    print(f"Found {len(tables)} tables: {tables}")
    
    lines = []
    lines.append("-- ============================================================")
    lines.append("-- GRI DB — Full MySQL Migration Dump")
    lines.append(f"-- Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    lines.append("-- Source: gri_db.db (SQLite)")
    lines.append("-- Tables: " + str(len(tables)))
    lines.append("-- ============================================================")
    lines.append("")
    lines.append("CREATE DATABASE IF NOT EXISTS `gri_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
    lines.append("USE `gri_db`;")
    lines.append("")
    lines.append("SET FOREIGN_KEY_CHECKS = 0;")
    lines.append("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';")
    lines.append("SET time_zone = '+05:30';")
    lines.append("")
    
    for table in tables:
        print(f"  Processing table: {table}")
        cursor.execute(f"PRAGMA table_info([{table}])")
        columns = cursor.fetchall()
        
        if not columns:
            print(f"    Skipping (no columns)")
            continue
        
        lines.append(f"-- --------------------------------------------------------")
        lines.append(f"-- Table: `{table}`")
        lines.append(f"-- --------------------------------------------------------")
        lines.append(f"DROP TABLE IF EXISTS `{table}`;")
        
        try:
            create_sql = build_create_table(table, columns)
            lines.append(create_sql)
        except Exception as e:
            print(f"    ERROR building CREATE TABLE for {table}: {e}")
            lines.append(f"-- ERROR: Could not create table {table}: {e}")
            continue
        
        # Fetch and insert rows
        try:
            cursor.execute(f"SELECT COUNT(*) FROM [{table}]")
            count = cursor.fetchone()[0]
        except:
            count = 0
        
        if count > 0:
            col_names = [safe_col_name(c[1]) for c in columns]
            cols_str = ", ".join(col_names)
            
            cursor.execute(f"SELECT * FROM [{table}]")
            rows = cursor.fetchall()
            
            BATCH = 500
            for i in range(0, len(rows), BATCH):
                batch = rows[i:i+BATCH]
                value_rows = []
                for row in batch:
                    vals = ", ".join(escape_value(v) for v in row)
                    value_rows.append(f"  ({vals})")
                
                insert_sql = f"INSERT INTO `{table}` ({cols_str}) VALUES\n"
                insert_sql += ",\n".join(value_rows) + ";"
                lines.append(insert_sql)
        
        lines.append("")
    
    # Extra HR tables needed for payroll (MySQL-only)
    lines.append("-- ============================================================")
    lines.append("-- HR & Payroll Tables (MySQL only — not in SQLite)")
    lines.append("-- ============================================================")
    lines.append("""
CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `description` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_code` VARCHAR(20) NOT NULL UNIQUE,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20) NULL,
  `department_id` INT NOT NULL DEFAULT 1,
  `designation` VARCHAR(100) NOT NULL,
  `joining_date` DATE NOT NULL,
  `salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `manager_id` INT NULL,
  `status` ENUM('active','inactive','on_leave') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `status` ENUM('present','absent','half_day','late','on_leave') NOT NULL,
  `check_in` TIME NULL,
  `check_out` TIME NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `month` TINYINT NOT NULL,
  `year` INT NOT NULL,
  `basic_salary` DECIMAL(10,2) NOT NULL,
  `allowances` DECIMAL(10,2) DEFAULT 0.00,
  `deductions` DECIMAL(10,2) DEFAULT 0.00,
  `net_salary` DECIMAL(10,2) NOT NULL,
  `payment_status` ENUM('pending','processed','paid') DEFAULT 'pending',
  `payment_date` DATE NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `emp_month_year` (`employee_id`,`month`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `scheduled_emails` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `report_type` VARCHAR(50) NOT NULL,
  `recipient_emails` TEXT NOT NULL,
  `scheduled_dates` TEXT NOT NULL,
  `send_time` TIME NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `message_body` TEXT NULL,
  `is_active` TINYINT DEFAULT 1,
  `last_sent_at` DATETIME NULL,
  `created_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chat_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_uuid` VARCHAR(64) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `title` VARCHAR(100) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` INT NOT NULL,
  `sender` ENUM('user','assistant') NOT NULL,
  `message` LONGTEXT NOT NULL,
  `intent` VARCHAR(100) NULL,
  `structured_plan` LONGTEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mail_recipients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `group_name` VARCHAR(50) NOT NULL,
  `recipient_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `is_active` TINYINT DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
""")
    
    # Seed data
    lines.append("""-- Seed: Departments
INSERT IGNORE INTO `departments` (id, name, code, description) VALUES
(1,'Production','PROD','Machine production and manufacturing operations'),
(2,'Maintenance','MAINT','Equipment and machine maintenance'),
(3,'Quality Control','QC','Product quality inspection and assurance'),
(4,'Human Resources','HR','Employee management and relations'),
(5,'Finance','FIN','Financial management and payroll'),
(6,'CNC Division','CNC','CNC precision machining'),
(7,'Dispatch','DISP','Shipping and logistics');

-- Seed: Employees
INSERT IGNORE INTO `employees` (id,employee_code,first_name,last_name,email,phone,department_id,designation,joining_date,salary,status) VALUES
(1,'BHIPL1','Admin','System','admin@bhipl.com','9876540001',4,'System Administrator','2024-01-01',75000.00,'active'),
(2,'BHIPL2','Imran','Hussain','imran@bhipl.com','9876540002',1,'Senior Operator','2024-03-15',42000.00,'active'),
(3,'BHIPL3','Ragavendra','Kumar','ragavendra@bhipl.com','9876540003',1,'Press Operator','2024-06-01',38000.00,'active'),
(4,'BHIPL4','Priya','Nair','priya@bhipl.com','9876540004',3,'QC Inspector','2024-02-20',44000.00,'active'),
(5,'BHIPL5','Suresh','Babu','suresh@bhipl.com','9876540005',2,'Maintenance Technician','2024-04-10',40000.00,'active'),
(6,'BHIPL6','Kavitha','Devi','kavitha@bhipl.com','9876540006',4,'HR Executive','2025-01-15',36000.00,'active'),
(7,'BHIPL7','Ravi','Shankar','ravi@bhipl.com','9876540007',1,'CNC Operator','2024-07-01',45000.00,'active');

-- Seed: Attendance (last 30 days for all employees)
INSERT IGNORE INTO `attendance` (employee_id,date,status,check_in,check_out) VALUES
(1,'2026-09-01','present','08:00:00','17:00:00'),
(1,'2026-09-02','present','08:05:00','17:10:00'),
(1,'2026-09-03','present','07:58:00','17:00:00'),
(2,'2026-09-01','present','08:10:00','17:20:00'),
(2,'2026-09-02','absent',NULL,NULL),
(2,'2026-09-03','present','08:00:00','17:00:00'),
(3,'2026-09-01','late','09:15:00','17:00:00'),
(3,'2026-09-02','present','08:00:00','17:00:00'),
(3,'2026-09-03','present','08:00:00','17:00:00'),
(4,'2026-09-01','present','08:00:00','17:00:00'),
(4,'2026-09-02','present','08:00:00','17:00:00'),
(4,'2026-09-03','on_leave',NULL,NULL),
(5,'2026-09-01','present','08:00:00','17:00:00'),
(5,'2026-09-02','present','08:00:00','17:00:00'),
(5,'2026-09-03','present','08:05:00','17:15:00');

-- Seed: Payroll for August 2026
INSERT IGNORE INTO `payroll` (employee_id,month,year,basic_salary,allowances,deductions,net_salary,payment_status,payment_date) VALUES
(1,8,2026,75000.00,12000.00,8500.00,78500.00,'paid','2026-08-31'),
(2,8,2026,42000.00,8000.00,5200.00,44800.00,'paid','2026-08-31'),
(3,8,2026,38000.00,7000.00,4600.00,40400.00,'paid','2026-08-31'),
(4,8,2026,44000.00,8500.00,5400.00,47100.00,'paid','2026-08-31'),
(5,8,2026,40000.00,7500.00,4800.00,42700.00,'paid','2026-08-31'),
(6,8,2026,36000.00,6500.00,4200.00,38300.00,'paid','2026-08-31'),
(7,8,2026,45000.00,9000.00,5600.00,48400.00,'paid','2026-08-31');

-- Seed: Mail Recipients
INSERT IGNORE INTO `mail_recipients` (group_name, recipient_name, email) VALUES
('Management','CEO','ceo@bhipl.com'),
('Management','GM','gm@bhipl.com'),
('HR','HR Head','hr@bhipl.com'),
('Finance','Finance Head','finance@bhipl.com');
""")
    
    lines.append("SET FOREIGN_KEY_CHECKS = 1;")
    lines.append("")
    lines.append("-- Migration complete.")
    
    print(f"\nWriting SQL to: {OUTPUT_SQL}")
    with open(OUTPUT_SQL, 'w', encoding='utf-8') as f:
        f.write('\n'.join(lines))
    
    conn.close()
    
    size_mb = os.path.getsize(OUTPUT_SQL) / (1024*1024)
    print(f"\nDone! File size: {size_mb:.2f} MB")
    print(f"Tables migrated: {len(tables)}")
    print(f"\nTo import: Open phpMyAdmin → Import → Select gri_db_full.sql → Go")

if __name__ == '__main__':
    main()
