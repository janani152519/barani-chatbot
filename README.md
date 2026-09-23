# ⚙️ Barani Hydraulics — SCADA Machine Intelligence & AI Platform

[![Tech Stack](https://img.shields.io/badge/Stack-PHP_8%2B_%7C_React_%7C_Vite_%7C_Python_%7C_MariaDB-10a37f?style=flat-square)](https://github.com/janani152519/barani-chatbot)
[![License](https://img.shields.io/badge/License-Proprietary_Enterprise-0284c7?style=flat-square)](https://github.com/janani152519/barani-chatbot)
[![Theme](https://img.shields.io/badge/Theme-ChatGPT_Dark_Palette-212121?style=flat-square)](#-user-interface--chatgpt-dark-theme)

An enterprise-grade, full-stack Industrial SCADA Telemetry, AI Chatbot Assistant, and Email Automation Platform custom-engineered for **Barani Hydraulics (India) Pvt. Ltd.**

The platform connects directly to plant-floor supervisory data (hydraulic presses, cycle efficiency, machine telemetry, alarms, work orders, personnel, and payroll), enabling engineers and leadership to monitor telemetry in real-time, query database metrics via conversational natural language, generate executive PDF and Word reports, and automate email dispatches on customizable schedules.

---

## 📑 Table of Contents

- [🏛️ System Architecture](#️-system-architecture)
- [🛠️ Tech Stack](#️-tech-stack)
- [📁 Project Structure](#-project-structure)
- [🔄 Complete End-to-End Workflow](#-complete-end-to-end-workflow)
  - [1. Authentication & Role-Based Access Control](#1-authentication--role-based-access-control)
  - [2. SCADA Telemetry & Machine Intelligence](#2-scada-telemetry--machine-intelligence)
  - [3. Natural Language AI Chatbot](#3-natural-language-ai-chatbot)
  - [4. Customizable Report Generation (PDF & Word)](#4-customizable-report-generation-pdf--word)
  - [5. Formal Email Dispatch Standard](#5-formal-email-dispatch-standard)
  - [6. Automated Background Email Scheduler](#6-automated-background-email-scheduler)
  - [7. Live Temp Mailbox & Ethereal Webmail Integration](#7-live-temp-mailbox--ethereal-webmail-integration)
  - [8. Admin Mailbox & Outbox Audit Vault](#8-admin-mailbox--outbox-audit-vault)
- [🚀 Quickstart & Setup Guide](#-quickstart--setup-guide)
  - [Prerequisites](#prerequisites)
  - [1. Database Setup (MariaDB / MySQL)](#1-database-setup-mariadb--mysql)
  - [2. Backend Setup (PHP 8+)](#2-backend-setup-php-8)
  - [3. Frontend Setup (React + Vite)](#3-frontend-setup-react--vite)
  - [4. Background Services (Scheduler & Relay)](#4-background-services-scheduler--relay)
- [⚙️ Environment Configuration (`.env`)](#️-environment-configuration-env)
- [📡 API Reference](#-api-reference)
- [🔒 Security & Compliance](#-security--compliance)

---

## 🏛️ System Architecture

```text
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     Barani Hydraulics Frontend (React / Vite)                   │
│          • ChatGPT Dark Theme (#212121 / #171717 / #10a37f / #ececec)           │
│   [Live SCADA Gauges]  [AI Intelligence Chat]  [Reports & Automated Email Hub]  │
└────────────────────────────────────────┬────────────────────────────────────────┘
                                         │ JSON over REST API (Port 8000)
                                         ▼
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           PHP 8+ Core REST API Engine                           │
│   • Auth (Bearer Token / RBAC)               • Query & Intent Sanitization      │
│   • Dompdf PDF Generator                     • PHPMailer SMTP Dispatch Engine   │
│   • TempMailService (Ethereal Sync)          • Outbox & Admin Mailbox Vault     │
└───────────────────────┬───────────────────────────────┬─────────────────────────┘
                        │                               │
            PDO Parameterized Queries          Subprocess / JSON IPC
                        │                               │
                        ▼                               ▼
      ┌───────────────────────────────────┐  ┌────────────────────────────────────┐
      │      MariaDB / MySQL Database     │  │       Python AI & Daemon Layer     │
      │  • Machines, Telemetry & Cycles   │  │  • TF-IDF Semantic Index & Search │
      │  • Work Orders, Alarms & Logs     │  │  • python-docx Word Engine         │
      │  • Employees, Attendance, Payroll │  │  • scheduler_daemon.py (24/7)      │
      │  • Scheduled Email Jobs & Logs    │  │  • smtp_relay_service.py           │
      └───────────────────────────────────┘  └────────────────────────────────────┘
```

---

## 🛠️ Tech Stack

| Layer | Technology | Details |
| :--- | :--- | :--- |
| **Frontend** | React 18, TypeScript, Vite, Lucide Icons | Clean ChatGPT-style dark mode palette, responsive dashboard, real-time gauges. |
| **Backend** | PHP 8.1+ (Core Framework-free) | Fast, secure REST API, strict PDO prepared statements, session & Bearer token auth. |
| **Database** | MariaDB 10.4+ / MySQL 8.0+ | Relational schema with 12 enterprise tables, foreign keys, and indexes. |
| **AI Intelligence** | Python 3.10+, TF-IDF, Vector Search | Natural language intent extraction, semantic database querying, and insights. |
| **Document Generation** | Dompdf, python-docx, PhpSpreadsheet | Programmatic high-definition PDF and customizable Microsoft Word (`.docx`) exports. |
| **Email Infrastructure** | PHPMailer 6.9+, SMTP, Ethereal API | Port 587/465 TLS/SSL dispatch, Google App Password compatibility, instant Temp Mail sandbox. |
| **Scheduler** | Python Daemon (`scheduler_daemon.py`) | 24/7 background scheduler monitoring scheduled jobs and auto-firing due emails. |

---

## 📁 Project Structure

```text
barani-chatbot/
├── api/                             # REST API Endpoints (JSON)
│   ├── auth.php                     # Authentication (login, logout, session check)
│   ├── chat.php                     # AI conversational chat assistant
│   ├── email.php                    # Direct customizable email dispatch
│   ├── report.php                   # On-demand PDF/Word report generator
│   ├── download.php                 # Protected file download streaming
│   ├── scheduled_emails.php         # Schedule job CRUD, send-now, check-scheduler
│   ├── run_scheduler.php            # Headless cron/daemon runner for scheduled jobs
│   ├── temp_mail.php                # Ethereal temporary mailbox API & sync
│   └── mailbox.php                  # Admin and Outbox retrieval API
├── config/                          # Central Configuration Files
│   ├── database.php                 # PDO MySQL connection singleton
│   ├── mail.php                     # SMTP credentials and defaults
│   └── ai.php                       # AI model and vector index settings
├── core/                            # Core Framework Utilities
│   ├── auth.php                     # Session verification & token validator
│   ├── permissions.php              # Role-Based Access Control (RBAC) guards
│   ├── response.php                 # Standardized JSON response envelope
│   ├── validator.php                # Input sanitization and validation
│   └── logger.php                   # Audit logging to database & disk
├── database/                        # Database Schemas & Migrations
│   ├── schema.sql                   # Full 12-table relational schema
│   ├── seed.sql                     # Seed records (users, employees, payroll, machines)
│   └── gri_db_full.sql              # Extended dataset backup
├── frontend/                        # React + TypeScript + Vite Dashboard
│   ├── src/
│   │   ├── components/
│   │   │   ├── Dashboard.tsx        # Master dashboard, telemetry, chat & email tabs
│   │   │   ├── Login.tsx            # Corporate authentication screen
│   │   │   └── ...                  # Modals, charts, gauges, report tables
│   │   ├── index.css                # ChatGPT Dark Theme design system tokens
│   │   └── App.tsx                  # App root & state router
│   ├── package.json
│   └── vite.config.ts
├── services/                        # Business Logic & Document Services
│   ├── ReportService.php            # Master report coordinator (HTML, PDF, DOCX)
│   ├── PdfReportService.php         # Dompdf executive report generator
│   ├── WordReportService.php        # Word (.docx) document generator
│   ├── EmailService.php             # PHPMailer dispatcher & formal cover letter builder
│   ├── TempMailService.php          # Ethereal public webmail integration
│   ├── QueryService.php             # Parameterized SQL query executor
│   └── AIService.php                # AI intent parser & context memory
├── scripts/                         # Python Supporting Scripts
│   ├── generate_docx.py             # python-docx template builder
│   └── ...
├── storage/                         # Generated Files & Audit Logs
│   ├── assets/                      # Company logos and corporate brand assets
│   ├── logs/                        # System & audit logs (`audit.log`, `outbox/`)
│   ├── mailbox/                     # Admin and Temp mailbox JSON stores
│   └── reports/                     # Generated PDF and Word documents
├── .env.example                     # Environment template
├── .gitignore                       # Production-grade gitignore
├── composer.json                    # PHP composer dependencies
├── gri_ai_service.py                # Python SCADA AI service
├── scheduler_daemon.py              # 24/7 background scheduler daemon
└── README.md                        # Master documentation
```

---

## 🔄 Complete End-to-End Workflow

```text
  [User Action]
        │
        ├─► 1. Login & Authentication ──────────► JWT/Bearer Token Issued
        │
        ├─► 2. Live SCADA Monitoring ───────────► Real-time Gauges & Telemetry
        │
        ├─► 3. AI Assistant Inquiry ────────────► Semantic Natural Language DB Query
        │
        ├─► 4. Report Creation ─────────────────► Generates Formal PDF / Word File
        │
        ├─► 5. Direct Mail Dispatch ────────────► Dispatches with Formal Cover Letter
        │
        └─► 6. Scheduler Automation ────────────► Daemon Fires at Scheduled Time
                                                  (Delivers via SMTP & Temp Mail)
```

### 1. Authentication & Role-Based Access Control
* Users sign in with their corporate credentials via `/api/auth.php`.
* Roles supported: `admin`, `engineer`, `operator`, `manager`.
* Every API request validates authorization headers via `core/auth.php` and enforces table and field-level permissions via `core/permissions.php`.

### 2. SCADA Telemetry & Machine Intelligence
* Displays real-time operational status for industrial machinery (e.g., *500T Hydraulic Press*, *Cycle Efficiency*, *Pressure / Temperature Sensors*, *Fault Alarms*).
* Live status badges: `ONLINE`, `WARNING`, `FAULT`, `IDLE`.

### 3. Natural Language AI Chatbot
* Users ask questions like:
  * *"Show me the payroll summary for September 2026"*
  * *"Which machine had the highest downtime this week?"*
  * *"List all active maintenance work orders"*
* The system resolves intent, maps to safe allowlisted SQL queries, runs parameterized PDO queries, and formulates human-readable corporate responses.

### 4. Customizable Report Generation (PDF & Word)
* Supports multiple enterprise report types:
  * **Payroll Report** (Gross salary, deductions, net pay, employee rosters)
  * **Production Output Report** (Machine cycle counts, target vs actuals, scrap rates)
  * **Heat Calculation Report** (Thermodynamic press analysis, oil degradation)
  * **Machine Downtime Report** (Mean Time to Repair, stoppage breakdown)
  * **SCADA Alarm Report** (Emergency stops, pressure violations)
* Formats available:
  * **PDF (Dompdf)**: High-resolution corporate layout with Barani Hydraulics identity.
  * **Word (.docx via python-docx)**: Fully editable document for management editing and archiving.

### 5. Formal Email Dispatch Standard
All emails dispatched (both manually and automatically) strictly follow the **Barani Hydraulics Corporate Email Standard**:
1. **Executive Header**: Deep industrial blue banner with company title, report subject, and date.
2. **Formal Cover Letter Body**:
   * Professional greeting (`Dear Sir/Madam,`)
   * Purpose of dispatch and document summary
   * Closing and official sign-off (`Barani Hydraulics Team`)
3. **1 Downloadable Attachment**: Only **one** standalone PDF or Word document is attached. The email body stays clean and readable without messy embedded database table dumps.

### 6. Automated Background Email Scheduler
* Schedules reports by **Everyday (Daily Mode)** or **Specific Calendar Dates** at chosen times (e.g., `08:00`, `18:00`).
* Runs completely autonomously via `scheduler_daemon.py` without requiring the browser or dashboard to remain open.
* Tracks execution in the database (`last_sent_at`) and triggers the exact formal email with the fresh PDF/Word report attached.

### 7. Live Temp Mailbox & Ethereal Webmail Integration
* Built-in sandbox testing environment using Ethereal Mail.
* If a recipient is set to `temp@ethereal.email` or any temporary address, the report is dispatched to Ethereal's live public webmail.
* Users can click **"🌐 Open in Ethereal Webmail"** in the dashboard to view the delivered email in a real webmail client, verify attachment downloads, and test without emailing real clients.

### 8. Admin Mailbox & Outbox Audit Vault
* **Outbox Vault**: Logs every outgoing email, timestamp, status (`delivered` or `outbox_saved`), recipient list, and download link for the attached file.
* **Admin Mailbox**: Centralized administrative inbox in the dashboard capturing all dispatches for supervisor inspection.
* **Audit Trail**: Every action logged to MySQL `audit_logs` and `storage/logs/audit.log`.

---

## 🚀 Quickstart & Setup Guide

### Prerequisites
- **PHP 8.1+** with extensions enabled: `pdo_mysql`, `curl`, `mbstring`, `fileinfo`, `openssl`, `gd`.
- **Node.js 18+** & **npm**.
- **Python 3.10+** (with `pip`).
- **MariaDB 10.4+** or **MySQL 8.0+** (e.g. via XAMPP).

---

### 1. Database Setup (MariaDB / MySQL)

1. Start your MySQL/MariaDB server (default port `3306`).
2. Create the database:
   ```sql
   CREATE DATABASE company_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Import the schema and seed data:
   ```bash
   mysql -u root -p company_ai < database/schema.sql
   mysql -u root -p company_ai < database/seed.sql
   ```
4. Verify default admin account:
   * **Username:** `admin`
   * **Password:** `admin123`

---

### 2. Backend Setup (PHP 8+)

1. Navigate to the root directory:
   ```bash
   cd barani-chatbot
   ```
2. Copy `.env.example` to `.env` and verify database credentials:
   ```bash
   cp .env.example .env
   ```
3. Install PHP dependencies:
   ```bash
   composer install
   ```
4. Start the PHP backend development server:
   ```bash
   php -S 127.0.0.1:8000 index.php
   ```
   *(Backend will be live at `http://127.0.0.1:8000`)*

---

### 3. Frontend Setup (React + Vite)

1. Open a new terminal and navigate to the frontend directory:
   ```bash
   cd barani-chatbot/frontend
   ```
2. Install frontend dependencies:
   ```bash
   npm install
   ```
3. Start the Vite dev server:
   ```bash
   npm run dev
   ```
   *(Frontend dashboard will be accessible at `http://localhost:3000`)*

---

### 4. Background Services (Scheduler & Relay)

1. Install Python requirements:
   ```bash
   pip install python-docx reportlab requests
   ```
2. Start the **Automated Email Scheduler Daemon** (runs 24/7):
   ```bash
   python scheduler_daemon.py
   ```
3. *(Optional)* Start the local SMTP relay helper if testing direct relaying:
   ```bash
   python smtp_relay_service.py
   ```

---

## ⚙️ Environment Configuration (`.env`)

```ini
# Application
APP_ENV=production
APP_URL=http://localhost:3000
API_URL=http://127.0.0.1:8000

# Database Credentials
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=company_ai
DB_USER=root
DB_PASS=

# SMTP Email Configuration (Gmail / Corporate SMTP)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your_company_email@gmail.com
MAIL_PASSWORD=your_16_digit_app_password
MAIL_FROM_ADDRESS=your_company_email@gmail.com
MAIL_FROM_NAME="Barani Hydraulics"
```

> [!TIP]
> **Gmail App Password Setup (1 Minute):**
> 1. Go to [Google App Passwords](https://myaccount.google.com/apppasswords).
> 2. Create a new password named **"Barani Reports"**.
> 3. Paste the generated 16-character code into `MAIL_PASSWORD` in your `.env`.

---

## 📡 API Reference

| Endpoint | Method | Description |
| :--- | :---: | :--- |
| `/api/auth.php` | `POST` | User login, session verification, token generation. |
| `/api/chat.php` | `POST` | AI conversational endpoint for SCADA & database queries. |
| `/api/report.php` | `POST` | Generate customized PDF or Word (.docx) documents. |
| `/api/download.php?file=...` | `GET` | Securely download generated report files. |
| `/api/email.php` | `POST` | Dispatch customized reports with formal cover letters via SMTP. |
| `/api/scheduled_emails.php` | `GET/POST/DELETE` | Manage scheduled automated email jobs (`create`, `send_now`, `check`). |
| `/api/run_scheduler.php` | `GET` | Headless execution trigger invoked by cron or background daemon. |
| `/api/temp_mail.php` | `GET/POST` | Fetch or create live Ethereal sandbox temporary mailboxes. |
| `/api/mailbox.php` | `GET` | Retrieve sent outbox records and received admin mailbox items. |

---

## 🔒 Security & Compliance

* **SQL Injection Immunity**: Zero raw SQL string interpolation. All queries execute through strictly parameterized PDO prepared statements.
* **Path Traversal Protection**: File downloads through `/api/download.php` use strict `basename()` and `realpath()` checks bounded inside `storage/reports/`.
* **RBAC & Field-Level Redaction**: Table and column permissions are validated against the user's role before queries reach the database.
* **Audit Trail**: Every login, report generation, download, and email dispatch is timestamped and recorded in the audit database.

---

## 🏢 About Barani Hydraulics

**Barani Hydraulics (India) Pvt. Ltd.**  
*Industrial Machinery & Automation Systems*  
SF No. 248/2, Trichy Road, Sulur, Coimbatore – 641 402, Tamil Nadu, India.
