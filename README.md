# 📁 DataDock

A lightweight, self-hosted PHP web application that allows registered users to upload, manage, and share files securely. Includes an admin dashboard for site management, user control, and file purging functionality.

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
- [ ]  Enforce max storage and file limits per user
- [ ]  User account & profile system
- [ ]  Public file browsing (anonymous access to uploads)
- [ ]  Default file expiry duration setting
- [ ]  Enforce unique email toggle

### 🗂️ File Upload & Storage Settings
- [x]  File upload with optional expiry
- [x]  Auto-thumbnail generation for image files
- [x]  Upload file size validation (frontend + backend)
- [x]  Drag-and-drop + preview file upload support
- [x]  Date/time storage in UTC with frontend conversion
- [ ]  File management view for all users (admin panel)
- [ ]  Allowed file types (restrict extensions/MIME types)
- [ ]  Max number of files per user
- [ ]  Max total storage per user (quota)
- [ ]  Enable/disable thumbnail generation
- [ ]  Custom storage path support
- [ ]  File upload progress bar

### 👑 Admin Panel
- [x]  Admin panel with user and file management
- [x]  Update site settings (e.g., site name, max upload size)
- [x]  Enable/disable brute force protection and configure thresholds
- [x]  Configure guest upload limits (max files and storage)
- [x]  Manual purging of expired files with stats
- [x]  View all uploaded files (with admin delete/download options)
- [ ]  Sidebar-based admin panel UI improvements
- [ ]  Maintenance mode toggle (admin-only access)
- [ ]  Debug mode toggle (verbose errors)
- [ ]  Log file path and verbosity setting

### 💬 Interface & Branding Settings
- [x]  Install.php warning if not deleted post-setup
- [ ]  Custom logo and favicon URLs
- [ ]  Welcome banner or message field
- [ ]  Install.php warning toggle
- [ ]  Dark mode / light mode UI toggle
- [ ]  Mobile responsiveness improvements
- [ ]  Localization/multilanguage support
- [ ]  Accessibility (WCAG) improvements

### 📧 Email / Notification Settings
- [ ]  Admin contact email field
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
- [ ]  Session timeout duration setting

### 🔁 Versioning & Updates
- [ ]  Version display in admin panel
- [ ]  One-click update system (GitHub release fetcher)
- [ ]  Changelog and release notes viewer

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
- `install.php` existence triggers a global warning until deleted

---

## 🧑‍💻 Contributing

Pull requests are welcome. Please open an issue to discuss any major changes before submitting one.

---

## 📄 License

This project is open-source and licensed under the [UniLicense](LICENSE).
