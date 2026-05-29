# IRCTC Hygiene Rating System

A digital monitoring platform for Indian Railway catering hygiene. This PHP/MySQL application lets passengers rate vendors, submit complaints, and lets admins/officers track vendor performance with weighted hygiene scoring and alert workflows.

## 🔍 Project Overview

This system is designed to track and improve food hygiene across railway catering vendors using a structured rating model. It supports:

- Passenger registration, login, and vendor rating
- Complaint submission and tracking
- Vendor hygiene scoring with weighted parameters
- Admin and inspection officer dashboards for vendor management and complaint workflows
- Alerts for low-performing vendors and automated inspection recommendations
- Messaging and complaint history logging for incident follow-up

## 👥 User Roles

- **Passenger**: register, login, rate vendors, and file complaints
- **Vendor**: view vendor dashboard and receive hygiene scores/complaint updates
- **Officer**: inspect complaints, review vendor performance, update complaint status
- **Admin**: manage users, vendors, complaints, alerts, and analytics

## ⚙️ Key Features

- Weighted hygiene score calculation using:
  - Cleanliness
  - Food quality
  - Staff hygiene
  - Packaging
  - Timeliness
- Vendor classification tiers: Excellent, Good, Average, Poor, Critical
- Complaint lifecycle management with status tracking and history logs
- Alert generation for low scores and critical hygiene issues
- Basic AI sentiment analysis support via `api/ai_sentiment.php`
- Auto database migrations via `includes/migrate.php`
- **8-Hour Ticket SLA:** Integrated cron / trigger system flags issues untouched within 8 hours,auto-escalating tickets directly to the Master Admin command queue.
- **PNR-Journey Lockout:** Enforces deterministic validation to guarantee only verified commuters with live bookings can post reviews against associated journey vendors, preventing malicious review manipulation.
- **Comprehensive Audit Trail:** Captures database hooks inside a rolling history table logging user actions, state changes, and inspector annotations for complete accountability.

## 📁 Project Structure

- `index.php` — public home page with system stats and vendor highlights
- `login.php`, `register.php`, `logout.php` — authentication pages
- `vendors_list.php` — browse all vendors
- `vendor_profile.php` — vendor detail page
- `passenger/` — passenger actions like rating and complaint submission
- `officer/` — officer complaint review and vendor inspection workflows
- `admin/` — admin dashboard, analytics, complaint management, users, vendors
- `api/` — AI sentiment endpoint and related services
- `includes/` — configuration, helper functions, migration logic, shared layout
- `database.sql` — initial database schema and seed data
- `database_migration.sql` — migration script for schema updates

## 🛠️ Technology Stack

* **Backend:** PHP 8.x (Procedural paradigm for architectural simplicity and modular maintenance)
* **Database:** MySQL 8.x via optimized MySQLi parameterized queries
* **Frontend Library:** Bootstrap 5.3, JavaScript (ES6+ Fetch API, 1-second asynchronous debounce handlers)
* **Visual Enhancements:** Font Awesome 6.5, Google Fonts (*Poppins* for crisp UI copy, *Rajdhani* for modern telemetry numbers)
* **Local Server Setup:** XAMPP 8.2.x local execution wrapper


## 📂 System Architecture Diagram

```
                       +-------------------------------------------------+
                       |               PRESENTATION TIER                 |
                       |  Bootstrap 5.3 | JS ES6 | Header/Footer Engines |
                       +-------------------------------------------------+
                                                |
               +--------------------------------+--------------------------------+
               |                                |                                |
    +--------------------+            +--------------------+            +--------------------+
    |  Passenger Portal  |            |   Officer Console  |            |    Admin Dashboard |
    | (Complaints/Rates) |            | (Field Inspections)|            | (Analytics/Control)|
    +--------------------+            +--------------------+            +--------------------+
               |                                |                                |
               +--------------------------------+--------------------------------+
                                                |
                                                v
                       +-------------------------------------------------+
                       |             APPLICATION LOGIC TIER              |
                       |       PHP 8.x Core Controllers & Modules        |
                       +-------------------------------------------------+
                       | * config.php (Scoring Engines & Global Weights) |
                       | * ai_module.php (Linear Regression & Sentiments)|
                       | * pnr_module.php (Format Parsing & Mock Engine) |
                       | * migrate.php (Auto-Migration Database Schema)  |
                       +-------------------------------------------------+
                                                |
                                                v
                       +-------------------------------------------------+
                       |                   DATA TIER                     |
                       |     MySQL 8.x Database (irctc_hygiene_db)      |
                       +-------------------------------------------------+
                       |   Ten Schema Tables with Relational Constraints |
                       |   Optimized Secondary Indices (Read/Write Locks)|
                       +-------------------------------------------------+
```

## 🛠️ Installation

1. Copy the project folder to your local web server document root.
   - Example: `C:\xampp\htdocs\irctc_hygiene`
2. Ensure PHP and MySQL/MariaDB are installed.
3. Open `includes/config.php` and update database settings:
   - `DB_HOST`
   - `DB_USER`
   - `DB_PASS`
   - `DB_NAME`
   - `BASE_URL`
4. Import the database schema using `database.sql`.
   - Via phpMyAdmin or MySQL CLI:
     ```sql
     CREATE DATABASE irctc_hygiene_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     USE irctc_hygiene_db;
     SOURCE database.sql;
     ```
5. Open the app in your browser using the configured base URL.

## 🚀 Running the App

- Access the homepage at `http://localhost/irctc_hygiene`
- Register as a passenger using `register.php`
- Login using `login.php`
- Use the admin or officer pages after manually assigning roles in the database or seeding admin accounts

## 🧩 Configuration Notes

- `includes/config.php` contains constants for application settings and the scoring formula.
- `includes/migrate.php` runs automatically when the app loads and applies missing schema updates safely.
- If your MySQL password is not blank, update `DB_PASS` before loading the app.

## 🧪 Testing & Validation

- `test_features.php` contains quick smoke-test routines for application functions.
- `database_migration.sql` provides a sample migration script for database upgrades.

## 💡 Tips

- Use `admin/dashboard.php` to review vendor performance and analytics.
- If the app shows a database connection error, verify MySQL credentials and that the database exists.
- The AI sentiment and chat features are located in `api/ai_sentiment.php` and `includes/ai_chat.php`.

## 📌 Important Files

- `database.sql` — core schema and initial dataset
- `includes/config.php` — database connection and helper utilities
- `includes/migrate.php` — automatic schema migration logic
- `index.php` — landing page with rating summary

## 📜 License

This repository does not include an open-source license. Add a `LICENSE` file if you wish to share it publicly.

## 👥 Capstone Project Members 
* **Sukhdeep Singh** 
* **Sidharth Singh** 
* **Sanchit** 
* **Naseem Akhtar** 
* **Ravi Kumar Kushwaha** 

**Project Mentor:** Mr. Aabid Mushtaq Najar, Assistant Professor (School of Computer Applications, Lovely Professional University)
