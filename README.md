# CADET Attendance Management System (PHP + MySQL, XAMPP)

## Setup (new install)
1. Copy this folder to `C:\xampp\htdocs\cadet-attendance`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. In phpMyAdmin, click **Import** and choose `database.sql`.
4. Open `http://localhost:8080/cadet-attendance/` (use `http://localhost/...` if Apache runs on port 80).

Default admin login: `admin` / `Admin@123` (change it after the first login).

## Updating an existing install
1. Extract the update over `C:\xampp\htdocs\cadet-attendance` (Yes to All).
2. In phpMyAdmin, click **Import** and choose `database_update.sql` (adds the 1st & 2nd Class tables, safe to run again).

## Menus (admin and ROTC officer are the same)
Students, 1st & 2nd Class, ROTC Officers, Departments, Reports, Logout.
- **Admin**: add, edit, delete everything, reopen a submitted day.
- **ROTC officer**: takes attendance (Students and 1st & 2nd Class tabs: Take Attendance), and can only view records, departments, today's attendance and reports.
- **Reports**: separate Students and 1st & 2nd Class reports, same print format.

## Subject (MS-1 / MS-2)
The student form has a Subject dropdown (MS-1 or MS-2). It shows in the Student Records list and in the printed Students report. The column is added to the database automatically the first time you log in (or import `database_update_subject.sql`).

## QR check-in (cadets scan with their own phone)
1. Admin or officer: open **QR Codes**, check the **Server address** (the laptop's IP, for example `http://192.168.1.10:8080/cadet-attendance`), then print the Students QR and the 1st & 2nd Class QR (any number of copies).
2. ROTC officer: on the attendance page click **Open QR check-in**.
3. Cadet: scans the QR with the phone, types the ID No, and chooses Present, Absent or Excuse. Excuse needs a photo of the medical or excuse letter.
4. The answers appear on the officer's screen by themselves. The officer clicks **Submit Attendance**; cadets who did not check in become Absent.
Excuse-letter photos are saved in the `uploads` folder (private) and can be opened with **View letter** in the attendance page and in Reports.
