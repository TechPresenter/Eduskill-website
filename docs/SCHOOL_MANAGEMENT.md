# School Management

Manage partner schools and their students, staff, batches, fees and attendance
inside the PWF admin. No new dependencies — same conventions as the rest of the
admin (PDO prepared statements, CSRF, `partials/head.php`+`foot.php`).

---

## 1. Install / upgrade

```bash
C:\xampp\mysql\bin\mysql.exe -u root pwf < database\eduskill.sql
```

There is one SQL file for the whole project. On a database that already holds
real data, run **Section 2 only** (see the banner inside the file). The tables
and settings this module needs are listed below.

Adds `schools` + `school_staff`, `school_batches`, `school_students`,
`school_fee_structures`, `school_fee_payments`, `school_attendance`, and three
settings (`school_code_prefix`, `student_code_prefix`, `school_receipt_prefix`).
Deleting a school cascades to all of its child records.

---

## 2. Features → files

| Feature | Where |
|---|---|
| **School registration** (profile, address, accreditation, UDISE, logo) | `admin/schools.php` |
| **School dashboard** (student count, fee collection, dues, attendance %, batches, staff, student-status chart) | `admin/school-dashboard.php?id=` |
| **Staff assignment** (assign `team_members` or manual, with role + subject) | `admin/school-staff.php` |
| **Student enrolment** (CRUD + bulk CSV import/export, link to NGO program + batch) | `admin/school-students.php` |
| **Courses & batches** (schedule, teacher, dates, capacity, status) | `admin/school-batches.php` |
| **Fee management** (fee structures + collection + dues + receipts) | `admin/school-fees.php`, receipt: `admin/school-receipt.php?id=` |
| **Attendance** (per-batch daily marking + rate) | `admin/school-attendance.php` |

Shared helpers: `includes/school.php` (loaded in `bootstrap.php`) —
`school_stats()`, `school_attendance_rate()`, `school_fee_status()`,
`school_generate_code()` / `student_generate_code()` / `school_receipt_no()`,
`school_programs()`, `school_options()`, and the status-pill helpers.

Navigation: a **School Management** group in the admin sidebar (Schools,
Students, Batches, Staff, Fees, Attendance). Each dashboard deep-links into the
school's students / batches / staff / fees / attendance via `?school=<id>`.

---

## 3. How it fits together

- **Schools** get an auto ID like `SCH-0001`. Students get `STU-<school>-<n>`.
- **Students** belong to one school and may be linked to an NGO **program**
  (`programs` table) and a school **batch**. Bulk-import a roster from CSV
  (columns: `name, guardian_name, gender, dob, grade, roll_no, phone, email,
  address, program, status`).
- **Batches** are school-specific course groups with a schedule, an optional NGO
  program, and a teacher drawn from that school's staff.
- **Fees**: define fee heads in *Fee Structures*, then record collections against
  a student. Each payment tracks `amount` (due) vs `amount_paid`; status is
  `pending` / `partial` / `paid` / `waived`, and **dues = amount − amount_paid**.
  Every payment gets a receipt number (`RCP-2026-00001`) and a printable receipt.
- **Attendance** is marked per batch per day (present / absent / late / excused);
  the dashboard shows the 30-day rate.

Fees are collected **offline** (cash/UPI/cheque/bank) with printable receipts.
The Cashfree adapter added for memberships (`includes/payments.php`) can be wired
in later if online school-fee collection is wanted.
