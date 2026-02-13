# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),  
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


---

## [v1.4.0] - 2025-02-12
### ✨ New Features
- **Site stats overview** — Admin panel Overview section: total uploads, storage used, user count, file type breakdown, and files expiring soon.
- **Maintenance mode** — Block non-admins when enabled; admins can still access the site and log in via login.php.
- **Debug mode toggle** — Control PHP error reporting and display in Site Settings (disable in production).
- **Log file path and verbosity** — File-based logging with configurable path and level (debug, info, warning, error).
- **Custom logo and favicon URLs** — Branding settings for logo image and favicon; templates updated.
- **Welcome banner or message** — Editable message on homepage.
- **Dark mode / light mode toggle** — Theme switcher (☀️/🌙) in header; preference saved via cookie; dark theme added.
- **Custom file icons** — Per-type icon mapping for file lists; defaults for PDF, images, audio, video, etc.; JSON override in settings.

### Improved
- Admin panel defaults to Overview section; sidebar includes new Overview link.
- File lists (homepage, dashboard, admin) show type-specific icons next to filenames.

---

## [v1.4.1] - 2025-02-12
### ✨ New Features (File & UX)
- **Download counter per file** — Count and display downloads in dashboard, admin file management, and homepage.
- **File checksum display** — MD5 and SHA256 computed on upload; shown with copy buttons in dashboard.
- **Zip multiple files for download** — Multi-select files on dashboard and download as a single .zip.
- **One-time download links** — Generate shareable links that expire after one use; token stored in `download_tokens` table.
- **Download as QR code** — One-time link page includes a QR code for easy mobile sharing (via api.qrserver.com).
- **Terms of Service / Acceptable Use** — Configurable ToS text and checkbox in Site Settings; users must accept before uploading.

### Improved
- Database migrations run automatically via `includes/db.php`; new installs and upgrades add `download_count`, `checksum_md5`, `checksum_sha256` columns and `download_tokens` table.
- Dashboard reorganized with select-all checkbox, zip download, and one-time link actions.

---

## [v1.3.1] - 2025-02-12
### ✨ New Features
- **Default file expiry duration** — Site setting for upload form; admins can set 1 min, 30 min, 1 hr, 6 hr, 1 day, 1 week, 1 month, 1 year, or never.
- **Thumbnail generation toggle** — Enable/disable thumbnail creation for image uploads in Site Settings; when off, thumbnails are skipped.
- **Configurable session timeout** — Site setting for session lifetime in minutes; 0 = until browser close.
- **Install.php warning toggle** — Option in Site Settings to disable the security warning when `install.php` still exists.
- **Admin contact email** — Optional field in Site Settings; shown in footer as “Contact Admin” and in Admin Panel sidebar when set.
- **Enforce unique email toggle** — Toggle strict (disallow duplicate emails) vs relaxed (only username must be unique) in registration. When relaxed, the email UNIQUE constraint is dropped for existing installs; new installs use a non-unique email column.

### Improved
- Index page now checks for `thumbnail_path` before displaying thumbnail, so images without thumbnails (when disabled) render correctly.

---

## [v1.3.0] - 2025-02-11
### Fixed
- 🛠️ Added `init_session()` to `download.php` and `delete.php` so logged-in users can successfully download and delete files.
- 🛠️ Corrected settings key mismatch: `upload.php` now reads `user_limits` (with `max_files_enabled`, `max_storage_enabled`) so per-user quotas apply when configured in Site Settings.
- 🛠️ Admin can now download and delete any file from the File Management panel (ownership check bypassed for admin role).
- 🛠️ Admin file list and homepage now include guest uploads via `LEFT JOIN`; guest files display "Guest" as the user.
- 🛠️ Theme map in `header.php` now only references existing theme files (`default`, `light`).
- 🛠️ Removed unused `login_ip_attempts` table from installer.

### Improved
- 🧠 AJAX upload now parses JSON response and displays errors inline instead of blindly redirecting; users see which files failed and why, with redirect delayed on success.
- 🧠 Admin delete from File Management now redirects back to `admin.php?section=files` instead of dashboard.

---

## [v1.2.1] - 2025-04-07
### Fixed
- 🛠️ Extraction bug causing `admin_sections/` to spill into the root folder.
- 🛠️ Subfolders like `includes/` and `assets/` not updating properly after running updater.
- 🛠️ Recursive update path incorrectly using first-level folder instead of project root.
- 🛠️ Error thrown when `index.php` was not found due to invalid root detection.
- 🛠️ Dry run would sometimes show inaccurate overwrite targets.

### Improved
- 🧠 Project root detection now based on `index.php` presence.
- 🧪 Dry-run simulation logs enhanced for clarity.
- 🧩 Smart fallback to `.tar.gz` when `.zip` isn’t found in release assets.
- 🧼 Cleaned up extracted content properly after update.
- ✅ Update now properly skips sensitive directories (`uploads/`, `config/`, etc.) and only touches necessary files.


---

## [v1.2.0] - 2025-04-07
### ✨ New Features
- Added **one-click GitHub updater** for automated project updates via Admin Panel.
- Implemented **dry-run mode** for safe testing of update behavior before applying changes.
- Added **release notes viewer** using GitHub's latest release API.
- Integrated **full changelog viewer**, rendering Markdown beautifully inside the admin panel.
- Created custom **basic Markdown parser** to safely render GitHub-style release notes (supports bold, italic, links, lists, horizontal rules, code blocks).
- Display current version in the admin panel for transparency.

### 💄 UI / UX Improvements
- Changelog box now wraps long lines to prevent overflow.
- Markdown viewer formatting improved to support lists, links, and code blocks with styling.

### 🛠 Enhancements
- Version comparison now strips `v` prefix for accurate matching (e.g., `v1.2.0` == `1.2.0`).
- Admin panel sidebar updated to include new updater link.
- All version/update-related features are modular and maintainable.

---

## [1.1.0] - 2025-04-06
### Added
- Global flash messaging system supporting multiple `success`, `error`, and `warning` messages across all pages.
- Visual breakdown and display of purged files in File Management (with filetype summary).
- Inline flash message system for Admin Panel (site settings, user management, file management, reset).
- JavaScript-based UTC → local time conversion across dashboard, admin, and homepage.
- Flash success message on file upload (redirects to dashboard).
- Flash success message on logout via `logout_success` flag (preserved via query param).
- `CHANGELOG.md` and version tracking.

### Changed
- Merged all Admin Panel sections (Site Settings, User Management, File Management, Reset Site) into a single `admin.php` controller with sectioned includes.
- Switched from URL-based success indicators (`?uploaded=1`, `?deleted=file`) to flash message system.
- Improved security by preventing direct access to admin section includes.
- Site Settings form now uses `section` hidden input to route POST submissions.
- Refactored session handling order for consistent messaging display.

### Fixed
- `logout.php` no longer loses flash messages due to `session_destroy()` timing.
- Fixed `<code>` tag in flash message appearing as literal text instead of rendering as HTML.
- Fixed missing variable initialization warnings in `site_settings.php`.

---

