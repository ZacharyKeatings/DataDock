# 📁 DataDock

A lightweight, self-hosted PHP web application that allows registered users to upload, manage, and share files securely. Includes an admin dashboard for site management, user control, and file purging functionality.

<div align="center">
<a href="https://github.com/ZacharyKeatings/DataDock/releases">
   <img alt="DataDock Release" src="https://img.shields.io/github/v/release/ZacharyKeatings/DataDock">
</a>

<a href="https://github.com/ZacharyKeatings/DataDock/blob/main/LICENSE">
   <img alt="DataDock License" src="https://img.shields.io/github/license/ZacharyKeatings/DataDock">
</a>

</div>

> ⭐ **If you found this project useful, please consider [starring the repository](https://github.com/ZacharyKeatings/DataDock)** — it helps others discover the project and keeps development going!

---

# DataDock Roadmap

A forward-looking plan for upcoming features, improvements, and maintenance of the DataDock self-hosted file manager.

---

## 📦 Features

### ✅ User & Account Settings
- [x]  User registration, login, and dashboard
- [x]  Session-based authentication
- [x]  Change user roles and delete users (admin only)
- [x]  Enable/disable user registration
- [x]  Optional guest uploads with quota enforcement (file count + size)
- [x]  Enforce max storage and file limits per user
- [ ]  User account & profile system
- [ ]  Public file browsing (anonymous access to uploads)
- [x]  Default file expiry duration setting
- [x]  Enforce unique email toggle
- [ ]  Invite-only registration – Only allow signups from a link/token created by the admin.
- [ ]  User-to-user file sharing – Share a file only with specific usernames.

### 🗂️ File Upload & Storage Settings
- [x]  File upload with optional expiry
- [x]  Auto-thumbnail generation for image files
- [x]  Upload file size validation (frontend + backend)
- [x]  Drag-and-drop + preview file upload support
- [x]  Date/time storage in UTC with frontend conversion
- [x]  Max number of files per user
- [x]  Max total storage per user (quota)
- [x]  File management view for all users (admin panel)
- [x]  Allowed file types (restrict extensions/MIME types)
- [x]  Enable/disable thumbnail generation
- [ ]  Custom storage path support
- [ ]  File upload progress bar
- [x]  Zip multiple files for download – Let users select files and download them as a single .zip.
- [x]  Download as QR code – Generate and display QR code to link directly to file.
- [x]  One-time download links (auto-expire after single use)
- [x]  File checksum display – Show MD5/SHA256 so users can verify integrity.
- [x]  Terms of Service / Acceptable Use – User must agree before uploading.
- [x]  Download counter per file

### 👑 Admin Panel
- [x]  Admin panel with user and file management
- [x]  Update site settings (e.g., site name, max upload size)
- [x]  Enable/disable brute force protection and configure thresholds
- [x]  Configure guest upload limits (max files and storage)
- [x]  Manual purging of expired files with stats
- [x]  View all uploaded files (with admin delete/download options)
- [x]  Reset site to post-install state (remove all users, files, and settings except admin)
- [x]  Sidebar-based admin panel UI improvements
- [x]  Maintenance mode toggle (admin-only access)
- [x]  Debug mode toggle (verbose errors)
- [x]  Log file path and verbosity setting
- [x]  Site stats overview (uploads, storage used, user count, etc.)

### 💬 Interface & Branding Settings
- [x]  Install.php warning if not deleted post-setup (toggleable in settings)
- [x]  Custom logo and favicon URLs
- [x]  Welcome banner or message field
- [x]  Install.php warning toggle
- [x]  Dark mode / light mode UI toggle
- [ ]  Mobile responsiveness improvements
- [ ]  Localization/multilanguage support
- [ ]  Accessibility (WCAG) improvements
- [x]  Custom file icons – Icon preview per file type (PDF, MP3, PNG, etc.)

### 📧 Email / Notification Settings
- [x]  Admin contact email field
- [ ]  Email notifications on upload, expiry, etc.
- [ ]  SMTP configuration (host, port, user, pass, encryption)
- [ ]  Email registration confirmation

### 🔒 Security Settings
- [x]  Secure password hashing
- [x]  Session management and role-based access
- [x]  `config/` directory secured via `.htaccess`
- [x]  CSRF-safe architecture (form-only POST)
- [x]  Brute-force login protection (with configurable limits and lockout window)
- [ ]  CAPTCHA on login/register forms
- [x]  Session timeout duration setting

### 🔁 Versioning & Updates
- [x]  Version display in admin panel / footer
- [x]  One-click update system (GitHub release fetcher)
- [x]  Changelog and release notes viewer

### ⚠️ Reporting & Abuse
- [ ]  Users can report files for malicious/adult content

---

## ⚙️ Tech Stack

- **Backend**: PHP 8+ (Vanilla, no frameworks)
- **Frontend**: HTML5, CSS3 (Vanilla)
- **Database**: MySQL (via PDO)
- **Thumbnailing**: GD library
- **Sessions**: Native PHP session handling
- **Security**:
  - CSRF-safe architecture (form-only POST)
  - Passwords hashed with `password_hash`
  - Session management and role-based access
  - `install.php` warning if not deleted post-setup

---

## 🚀 Installation

1. **Clone the repo**
   ```bash
   git clone https://github.com/ZacharyKeatings/DataDock.git
   cd datadock
   ```

2. **Upload to your server**  
   Host it on Apache, Nginx, or your shared hosting.

3. **Create a MySQL database**

4. **Run the installer**  
   Visit `/install.php` in your browser and fill out:
   - Database credentials
   - Site name
   - Admin user info

5. [x]  You're live!  
   **Delete `install.php` immediately.**

---

## 🛡️ Security Notes

- All user inputs are sanitized
- Uses `htmlspecialchars()` and custom `sanitize_data()`
- File extensions are preserved, but MIME-type is validated via `mime_content_type`
- Admin panel is locked behind session + role checks
- `install.php` existence triggers an admin warning until deleted (toggleable in Site Settings)

---

## 🧑‍💻 Contributing

Pull requests are welcome. Please open an issue to discuss any major changes before submitting one.

---

## 📄 License

This project is open-source and licensed under the [UniLicense](LICENSE).
