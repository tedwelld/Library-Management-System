##Library-Management-System

A complete web-based Library Management System with librarian authentication, book/member tracking, loan management, and reporting.The system uses clear UI, normalized database and modular code. It contains multi-librarian roles, email notifications and barcode scanning.

>>Features<<

# 🔐 Authentication
- Secure librarian login (`librarian@library.com` / `SecureLib@123`)
- Argon2 password hashing (upgradable from bcrypt)
- Session-based access control

# 📚 Book Management
- Metadata management (ISBN, title, author, etc.)
- Individual copy tracking (barcodes with statuses)
- Prevent deletion of books with active copies

# 👥 Member System
- CRUD operations for library members
- Soft-delete with status toggle
- Unique email/phone validation

# 🔄 Loan Workflow
- Checkout books (14-day auto due date)
- Return books with fine calculation ($1/day overdue)
- Block returns with unpaid fines

# 📊 Reporting
- Active loans with overdue highlights
- Member transaction history
- Unpaid fines dashboard

# Tech Stack
- Frontend: HTML5, CSS3, JavaScript (Vanilla)
- Backend: PHP 8.0+
- Database: MySQL 5.7+ (PDO with prepared statements)
- Security: Bcrypt hashing, input sanitization

# Database Schema
>>sql
CREATE TABLE librarians (id INT AUTO_INCREMENT, email VARCHAR(255) UNIQUE, password_hash VARCHAR(255));
CREATE TABLE members (id INT AUTO_INCREMENT, full_name VARCHAR(255), email VARCHAR(255) UNIQUE, ...);
CREATE TABLE books (isbn VARCHAR(20) PRIMARY KEY, title VARCHAR(255), ...);
CREATE TABLE book_copies (copy_id VARCHAR(50) PRIMARY KEY, isbn VARCHAR(20), status ENUM(...));
CREATE TABLE loans (id INT AUTO_INCREMENT, copy_id VARCHAR(50), member_id INT, ...);
CREATE TABLE fines (id INT AUTO_INCREMENT, loan_id INT, amount DECIMAL(10,2), ...);

## Installation
1. Clone repo:
   >>bash
   git clone https://github.com/yourusername/library-management.git
  
2. Import database:
   >>bash
   mysql -u root -p library_management < schema.sql
  
3. Configure `config.php`:
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');

4. Access via web server:
   http://localhost/library-management/login.php
   
# Roadmap
-  Multi-librarian roles
-  Barcode scanning integration
-  Email notifications
-  REST API version



