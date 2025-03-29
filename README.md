# 📁 DataDock

A lightweight, self-hosted PHP web application that allows registered users to upload, manage, and share files securely. Includes an admin dashboard for site management, user control, and file purging functionality.

---

## ✨ Features

### ✅ General Users
- Register/login/logout with secure password hashing
- Upload files with optional expiry times
- View and download your uploaded files
- Auto-thumbnail generation for images
- Friendly dashboard with file info and actions
- Session-based authentication

### 👑 Admin Panel
- Update site settings (e.g., site name)
- View and manage all registered users
- Delete users and their uploaded files
- Purge expired files manually with:
  - Total files deleted
  - Total size freed
  - Filetype breakdown

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
   git clone https://github.com/yourusername/datadock.git
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

5. ✅ You're live!  
   **Delete `install.php` immediately.**

---

## 📦 To-Do / Planned Features

- ✅ Admin panel expansion
- ✅ File purge breakdown
- ⏳ Role management UI
- ⏳ Expired file auto-deletion (cron)
- ⏳ Public sharing links (with expiration)
- ⏳ Email notifications for expiring files

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
