# school-management-system-web-programing-project-in-PHP

## Roots School System — Comprehensive Web-Based School Management System

A production-grade, institutional School Management System built strictly with **PHP (PDO), MySQL, HTML5, CSS3, JavaScript, Bootstrap 5.3, and Bootstrap Icons**. Designed for real-world school administration with beginner-friendly, clean, and well-structured code.

---

## 🌟 Overview & Key Highlights

- **Pure Native Stack**: Zero external backend frameworks or build tools — runs immediately on standard XAMPP (Apache + MySQL) or PHP's built-in server.
- **Human-Crafted "Unslop" UI**: Clean typography, crisp borders, neutral palette, and dense data tables following modern software ergonomics rather than generic AI gradients.
- **Strict Role-Based Access Control (RBAC)**: 4 dedicated portals (**Admin**, **Teacher**, **Student**, **Parent**) with secure session verification, authorization guards, and CSRF token protection.
- **Relational Integrity**: 19 normalized MySQL tables with foreign key constraints, cascading rules, and unique indices to prevent duplicate entries.
- **Ready-to-Print Official Documents**: Integrated printable Student Report Cards (transcripts) and Student Fee Vouchers / Payment Receipts.
- **Zero-Friction Local Setup**: Multi-port MySQL detection (port 3307 and port 3306) ensures smooth plug-and-play execution across various XAMPP setups.

---

## 👥 System Users & Role Portals

### 1. 🛡️ Administrator Portal (`admin/`)
- **Executive KPI Dashboard (`admin/index.php`)**: Real-time metric counters (Total Students, Teachers, Classes, Today's Attendance %, Collected Fees, Pending Receivables), monthly fee revenue chart, attendance ratio doughnut, recent admissions list, and staff notices.
- **Student Management (`admin/students.php`)**: Full CRUD operations, auto-generated admission numbers (`ADM-XXXX`), class/section assignment, parent/guardian contact profiles, photo uploads, search, filters, and pagination.
- **Student 360° Profile (`admin/student_view.php`)**: Complete academic transcript, monthly attendance rate, subject-by-subject exam score history, and fee invoice log.
- **Teacher Directory (`admin/teachers.php`)**: Faculty records, employee codes, designations, qualifications, subject assignments, and automated user account provisioning.
- **Classes & Sections (`admin/classes.php`)**: Grade level builder (Nursery through Grade 10) and Section allocator (A, B, C) with seat capacity tracking and class teacher assignments.
- **Curriculum & Subjects (`admin/subjects.php`)**: Subject directory (code, type, credit hours) and teacher allocations mapped to classes and sections.
- **Daily Attendance Register (`admin/attendance.php`)**: Roll sheet by class, section, and date with "Mark All Present" toggle and duplicate prevention (`ON DUPLICATE KEY UPDATE`).
- **Examinations Scheduler (`admin/examinations.php`)**: Exam terms scheduler, subject paper date/time slots, maximum and passing marks, and result publish/unpublish toggle.
- **Grading & Marks Entry (`admin/marks.php`)**: Paper marks grading, absent toggles, automated percentage calculation, standard grade allocations (A+, A, B, C, D, F), and teacher remarks.
- **Printable Student Report Cards (`admin/report_card.php`)**: Official academic transcript with student bio, tabular marks breakdown, aggregate percentage, GPA, grade scale reference, and dual signature blocks (Teacher & Principal).
- **Tuition & Fee Management (`admin/fees.php`)**: Class-wise monthly fee structures, bulk monthly voucher generator, payment collection recording (Cash, Bank Transfer, Online), and real-time outstanding balance tracking.
- **Printable Fee Receipt (`admin/fee_receipt.php`)**: Institutional fee voucher with student information, payment line items, and cashier signature block.
- **Weekly Timetable Builder (`admin/timetables.php`)**: Weekly period scheduling (Monday through Saturday) by class, section, teacher, subject, room, and timings with printable grid view.
- **Announcements Board (`admin/announcements.php`)**: Official notice publisher targeted to specific audiences (`all`, `teacher`, `student`, `parent`) with active and expiration date tracking.
- **Consolidated Reports (`admin/reports.php`)**: Exportable and printable institutional reports for Student Enrollment, Daily Attendance Summary, and Monthly Fee Recovery.

### 2. 👩‍🏫 Teacher Portal (`teacher/`)
- **Teacher Dashboard (`teacher/index.php`)**: Active class assignments count, enrolled students, today's lecture schedule, quick attendance shortcuts, and staff bulletins.
- **Class Attendance (`teacher/attendance.php`)**: Roll calling for assigned classes with quick one-click "Mark All Present" support.
- **Marks Entry (`teacher/marks.php`)**: Record exam marks, exam remarks, and absent flags for assigned subject courses.
- **Homework & Assignments Desk (`teacher/homework.php`)**: Post homework with submission deadlines, review and download student submissions, and assign numerical scores with feedback.
- **Teaching Schedule (`teacher/timetable.php`)**: Weekly schedule of assigned lectures, classroom locations, and timings with print view.
- **Class Rosters (`teacher/students.php`)**: View pupil lists, roll numbers, and parent emergency contacts for assigned classes.

### 3. 🎓 Student Portal (`student/`)
- **Student Dashboard (`student/index.php`)**: Personal attendance percentage, pending homework counter, today's timetable, latest exam results snapshot, and school notices.
- **Personal Profile (`student/profile.php`)**: Full profile sheet with personal bio, academic data, class information, and parent contact details.
- **Attendance Records (`student/attendance.php`)**: Month-by-month attendance breakdown and daily presence status.
- **Examination Results (`student/results.php`)**: View published exam results, percentage, GPA, and one-click link to print the official report card.
- **Homework Submission (`student/homework.php`)**: View assigned homework, submit typed text or upload files (PDF, DOCX, ZIP), and view teacher grades and review remarks.
- **Class Timetable (`student/timetable.php`)**: Weekly timetable schedule with period timings and classroom locations.
- **Notice Board (`student/announcements.php`)**: School alerts, event announcements, and holiday circulars.

### 4. 👨‍👩‍👧 Parent / Guardian Portal (`parent/`)
- **Parent Dashboard (`parent/index.php`)**: Multi-child summary cards showing each enrolled child's class, roll number, attendance rate, and pending fee balance.
- **Children Profiles (`parent/children.php`)**: Complete academic enrolment profiles for all children under guardian care.
- **Children Attendance (`parent/attendance.php`)**: Daily attendance logs and monthly presence percentages for each child.
- **Report Cards (`parent/results.php`)**: Inspect examination scores and print official school report cards.
- **Fee Vouchers & Payment Receipts (`parent/fees.php`)**: Track monthly fee invoices, payment history, outstanding balances, and print official payment receipts.
- **School Notices (`parent/announcements.php`)**: Bulletins and event circulars targeted to parents.

---

## 🗄️ Database Architecture (MySQL)

The database schema (`school_management_system.sql`) is fully normalized with InnoDB foreign key constraints:

| # | Table | Purpose |
| :-: | :--- | :--- |
| 1 | `users` | Core authentication accounts (Admin, Teacher, Student, Parent) with hashed passwords |
| 2 | `classes` | Academic grade levels (Nursery through Grade 10) |
| 3 | `sections` | Class sections (A, B, C) with room numbers and capacities |
| 4 | `teachers` | Teacher bios, employee codes, designations, qualifications, and phone numbers |
| 5 | `parents` | Guardian contact information, relations, and occupations |
| 6 | `students` | Admission records, admission numbers (`ADM-XXXX`), photos, and parent mappings |
| 7 | `subjects` | Academic subjects, codes (`ENG-101`, `MATH-101`), and credit hours |
| 8 | `teacher_assignments` | Mappings between teachers, classes, sections, and subjects |
| 9 | `attendance` | Daily attendance roll records (`Present`, `Absent`, `Late`, `Leave`) |
| 10 | `examinations` | Examination sessions, terms, and publish status flags |
| 11 | `exam_subjects` | Exam schedule papers, date/timings, pass marks, and maximum marks |
| 12 | `marks` | Student marks, absent flags, and teacher remarks |
| 13 | `fee_structures` | Monthly tuition and activity fee structures per class |
| 14 | `fees` | Monthly fee invoices, amounts due, amounts paid, and invoice status |
| 15 | `payments` | Transaction logs with unique payment receipt codes (`REC-XXXX`) |
| 16 | `homework` | Homework descriptions, deadlines, and assigned classes |
| 17 | `homework_submissions` | Student submission answers, file attachments, grades, and teacher feedback |
| 18 | `timetables` | Weekly period schedules (Monday to Saturday) |
| 19 | `announcements` | Institutional circulars with role targeting (`all`, `teacher`, `student`, `parent`) |

---

## 🔑 Default Demo Accounts

All demo accounts are pre-seeded in the database:

| Role | Username / Identity | Password | Direct Portal URL |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin` (or `admin@roots.edu`) | `admin123` | `/admin/index.php` |
| **Teacher** | `teacher1` | `teacher123` | `/teacher/index.php` |
| **Student** | `student1` | `student123` | `/student/index.php` |
| **Parent** | `parent1` | `parent123` | `/parent/index.php` |

*(Note: On the login page, you can also click any of the 4 quick "Demo Credentials" buttons to auto-populate credentials and switch roles automatically!)*

---

## 🚀 Installation & Local Setup

### Requirements
- **PHP**: >= 7.4 (PHP 8.x recommended)
- **MySQL**: >= 5.7 or MariaDB >= 10.3
- **XAMPP** (or WampServer / LAMP / native PHP)

---

### Method 1: Using XAMPP (Recommended)
1. **Clone or Copy Repository**:
   Clone or copy the project folder into your XAMPP web root (`htdocs`):
   ```bash
   cd C:/xampp/htdocs/
   git clone https://github.com/yasirali7212/school-management-system-web-programing-project-in-PHP.git "School management system"
   ```

2. **Start Services in XAMPP Control Panel**:
   - Start **Apache**.
   - Start **MySQL**.

3. **Import Database**:
   - Open **phpMyAdmin**: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
   - Create a new database named `school_management_system` with collation `utf8mb4_unicode_ci`.
   - Click the **Import** tab and upload `database/school_management_system.sql`.
   - Click **Go** to run the import.

4. **Launch Application**:
   Open your browser and visit:
   ```text
   http://localhost/School%20management%20system/
   ```
   *(If Apache is configured on port `8080`, visit `http://localhost:8080/School%20management%20system/`)*

---

### Method 2: Using PHP Built-In Web Server
1. Start MySQL in XAMPP (or MySQL service).
2. Open terminal in the project directory:
   ```bash
   cd "d:/semester 6/web programing/School management system"
   php -S localhost:8000
   ```
3. Open your browser and navigate to:
   ```text
   http://localhost:8000
   ```

---

## 📁 Directory Structure

```text
School management system/
├── admin/                      # Administrator Portal Modules
│   ├── announcements.php       # School circulars & notice board
│   ├── attendance.php          # Daily student attendance register
│   ├── classes.php             # Class & section creator
│   ├── examinations.php        # Exam terms & paper scheduler
│   ├── fee_receipt.php         # Printable fee payment voucher
│   ├── fees.php                # Class fee structures & voucher billing
│   ├── index.php               # Admin KPI analytics dashboard
│   ├── marks.php               # Paper marks entry & auto-grading
│   ├── report_card.php         # Printable official student report card
│   ├── reports.php             # Consolidated analytics reports
│   ├── student_view.php        # Detailed student 360° profile
│   ├── students.php            # Student CRUD & photo uploads
│   ├── subjects.php            # Subject manager & teacher allocator
│   ├── teachers.php            # Teacher directory & user accounts
│   └── timetables.php          # Class weekly timetable builder
│
├── teacher/                    # Teacher Portal
│   ├── attendance.php          # Roll sheet for assigned classes
│   ├── homework.php            # Homework creation & submission grading
│   ├── index.php               # Faculty dashboard & today's schedule
│   ├── marks.php               # Marks entry for assigned subjects
│   ├── students.php            # Class roster & guardian phone numbers
│   └── timetable.php           # Personal teaching weekly timetable
│
├── student/                    # Student Portal
│   ├── announcements.php       # School circulars & alerts
│   ├── attendance.php          # Monthly presence rate & daily log
│   ├── homework.php            # Assignments desk & file uploader
│   ├── index.php               # Student home & schedule overview
│   ├── profile.php             # Personal, academic & parent profile
│   ├── results.php             # Examination scores & report cards
│   └── timetable.php           # Class weekly timetable schedule
│
├── parent/                     # Parent / Guardian Portal
│   ├── announcements.php       # Parent-targeted circulars
│   ├── attendance.php          # Child attendance records & rates
│   ├── children.php            # Enrolled children profiles
│   ├── fees.php                # Tuition fee vouchers & payment receipts
│   ├── index.php               # Guardian overview dashboard
│   └── results.php             # Children term report cards
│
├── assets/                     # Frontend Assets
│   ├── css/                    # Dashboard (dashboard.css) & Auth CSS
│   ├── images/                 # Logo SVG, campus library wallpaper
│   └── js/                     # Client scripts & login helpers
│
├── config/                     # Core Configuration
│   ├── config.php              # App constants & dynamic base URL
│   └── database.php            # Multi-port PDO connection (3307 / 3306)
│
├── database/                   # Database Schemas & Migrations
│   └── school_management_system.sql # 19 relational tables & seed data
│
├── includes/                   # Reusable Components
│   ├── auth.php                # Role guards, CSRF, flash, grades, badges
│   ├── footer.php              # Standard layout footer
│   ├── header.php              # Responsive header & top navigation
│   └── sidebar.php             # Role-specific responsive sidebar
│
├── uploads/                    # File Upload Storage
│   ├── avatars/
│   ├── documents/
│   ├── homework/
│   └── photos/
│
├── .gitignore                  # Git ignore rules
├── index.php                   # Portal gateway & auto-redirector
├── login.php                   # Unified multi-role login screen
├── logout.php                  # Session destruction & sign-out
└── README.md                   # Project documentation
```

---

## 🛡️ Security Features Implemented

1. **Password Hashing**: Passwords stored using PHP's native `password_hash()` with `PASSWORD_DEFAULT` (bcrypt).
2. **SQL Injection Prevention**: All database queries executed with parameterized prepared statements via PDO (`PDO::ATTR_EMULATE_PREPARES => false`).
3. **Cross-Site Scripting (XSS)**: All server-rendered strings sanitized via `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`.
4. **Cross-Site Request Forgery (CSRF)**: Random cryptographic tokens generated per session, embedded in forms, and validated on submission via `validateCSRF()`.
5. **Route Protection**: Unauthenticated access blocked on all portal pages with `requireRole($allowedRoles)`.
6. **File Upload Security**: Uploaded files validated for MIME types, file size limits, and stored with sanitized randomized filenames.

---

## 📜 License & Credits

Developed as a modern Web Programming semester project. Open for educational and institutional usage.
