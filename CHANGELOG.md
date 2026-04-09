# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),  
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


---

## [v2.0.1] - 2026-04-08
### Hotlink awareness

#### New Features
- **Hotlink log** — Optional logging when downloads, ZIP downloads, thumbnails, or avatars are requested with a `Referer` from another hostname (Admin → **Hotlink log**). Same-site and empty referers are skipped. Site Settings: **Hotlink monitoring** (enable/disable, extra trusted hostnames for CDNs). Table `hotlink_log`; migrations and reset site clear it.

---

## [v2.0.0] - 2026-04-08
### Organization & Storage Efficiency

#### New Features
- **Folders** — Nested folders per user (`parent_id` with `0` = root). Dashboard breadcrumb, subfolder links, create folder, and move file via the Actions menu. Filter bar preserves folder scope; uploads can target the current folder (`upload.php?folder=` or “Upload to this folder”).
- **Tags** — Comma-separated tags on **Edit file**; optional tag filter on the dashboard when tags exist. Settings can disable tags or folders independently.
- **Storage partitions** — Multiple storage roots (`storage_partitions`); each root uses the same layout as the site default (`uploads/`, `thumbnails/`). Empty partition root inherits **Custom Storage Base Path** from Site Settings. Admin **Storage partitions** lists usage and sets the default partition. **User Management** assigns a user to a partition (new uploads use that root; existing files stay where they are until moved by re-upload or admin tooling).
- **Deduplication** — Optional SHA-256 deduplication per partition (`storage_objects` + `files.storage_object_id`): identical content reuses one on-disk file and increments reference counts. Site setting **Deduplicate by SHA-256** (with hidden-field safe defaults). Deletes and purges decrement refs and remove the blob when the last reference is gone.
- **Disk path resolution** — `includes/storage.php` centralizes partition-aware paths for downloads, thumbnails, ZIP, one-time links, delete/purge, and uploads.

#### Improved
- Database migrations add `storage_partitions`, `storage_objects`, `folders`, `tags`, `file_tags`, and extend `files` / `users`. Fresh installs pick this up on first DB connection after deploy.
- **Reset site** clears `storage_objects` and `folders` / `tags`, and empties uploads/thumbnails under **every** partition root.
- **Delete user** (admin) releases on-disk storage for that user’s files before removing the account.
- Dashboard folder create and file move use **same-page POST** (`includes/dashboard_actions.php`) so subdirectories and servers with odd `SCRIPT_NAME` / rewrites do not 404 on separate endpoints.

### Security
- **Upload hardening** — Rejects filenames where **any** dotted segment is an executable/server extension (e.g. `evil.php.jpg`). For common image extensions, requires **MIME + magic bytes** to match the extension, and scans image/SVG bodies (first 512KB–1MB) for embedded **`<?php` / `<?=` / short open tags** (blocks GIF/JPEG polyglot webshells). Expanded forbidden extension list (e.g. `phps`, `pht`, `ashx`, `jspf`). Client-side upload UI mirrors segment checks.

---

## [v1.9.0] - 2026-02-23
### File Management & Discovery

#### New Features
- **File search and filter** — Dashboard filter bar: search by filename or description; filter by upload date range, file type (MIME), visibility (public/private when public browsing is on), and expiry (has expiry / no expiry). Uses a shared query helper reused for metadata and trash.
- **File metadata editing** — **Edit** in file actions opens `edit_file.php`: change display name, optional description (max 500 chars), and expiry (Never, Keep current, or preset durations). File on disk is unchanged.
- **Soft delete / trash** — Deleting a file (single or bulk) moves it to **Trash** instead of removing it. **Trash** page lists deleted files with **Restore** and **Delete permanently**. Restore returns the file to Your Files. Site setting **Trash retention (days)** (default 30): files older than that are eligible for **Purge Trash** in Admin → File Management. Set to 0 to keep trash until manually purged.

#### Improved
- Database: `files.description` (VARCHAR 500, optional), `files.deleted_at` (DATETIME NULL, indexed). Migrations and fresh installs include the new columns.
- All file lists and download/share/one-time flows exclude trashed files (`deleted_at IS NULL`). Quota and stats count only non-trashed files.
- Admin File Management: list excludes trashed by default; **Purge Trash** button runs retention-based purge (or purges all trashed if retention is 0). **Purge Expired Files** only purges non-trashed expired files.
- Admin Site Settings: **Trash retention (days)** under Upload & Session (0–3650). Default 30.
- Nav: **Trash** link for logged-in users. Config/settings: `trash_retention_days` in `config/settings.php.example` and default settings writer.

---

## [v1.8.0] - 2026-02-20
### Security Hardening & Rate Limiting (Bot mitigation, abuse prevention)

#### New Features
- **Rate limiting on uploads** — Configurable per-IP and per-user upload throttling. Site setting **Upload Rate Limiting** with window (minutes), max uploads per IP, and max uploads per user. When exceeded, uploads are blocked with a short message.
- **Per-IP upload throttling** — Upload events logged in `upload_rate_log`; throttle applies by IP for guests and by IP + user for logged-in users.
- **Adaptive cooldown for failed logins** — Progressive lockout by IP across usernames. After repeated failed logins from the same IP (in a configurable window), lockout duration increases (e.g. 5 → 15 → 60 minutes). Site setting **Adaptive cooldown** under Brute Force; per-IP failure window and steps configurable. Failed attempts now store `ip_address` in `login_attempts`.
- **Content-Disposition enforcement** — Risky MIME types (HTML, SVG, JS, PDF, etc.) are always served with `Content-Disposition: attachment` to prevent inline execution in the browser. Helper `is_risky_mime_for_inline()` and `send_download_disposition()` in download flow.
- **Optional file extension rewriting** — Site setting **Store files without original extension**: files are stored on disk without the original extension (e.g. `uuid` only); original filename is restored on download. Reduces risk of executing uploaded files by extension on the server.
- **Upload quarantine mode** — Site setting **Upload quarantine**: new uploads are stored with `quarantine_status = 'pending'` and are invisible in public/one-time flows until an admin approves them in File Management. Pending files do not appear on the public index even if marked public. Uploaders see their own pending files on the dashboard with a “Pending approval” badge; Download, Share, One-time link, and Make public are disabled until approved. Admins see **Pending** / **Approved** and an **Approve** action.
- **Automatic MIME anomaly detection** — On upload, extension vs detected MIME is compared; if they don’t match expected mapping, the file is flagged with `mime_anomaly` for admin review. Admin File Management shows a **MIME?** badge on such files.
- **Upload page: Accepted file types** — Expandable section on the upload page (“Accepted file types”) that lists allowed types by category (Images, Documents, Spreadsheets, Presentations, Archives, Audio, Video, Data & code) with extensions, plus a reminder of the forbidden list. Uses native `<details>`/`<summary>`; no JavaScript required.

#### Improved
- Database migrations: `login_attempts.ip_address`, table `upload_rate_log`, `files.quarantine_status`, `files.mime_anomaly`. New installs and reset get the full schema.
- Admin File Management: Status column (Pending/Approved), MIME anomaly badge, Approve button for pending files. Reset Site clears `upload_rate_log`.
- Dashboard, shared files, download, one-time, public download, share, create-onetime, and download ZIP respect quarantine (only approved files visible or allowed for non-admins). Public index (`index.php`) shows only approved files.
- `approve_file.php` added for admin to approve a single quarantined file.
- **Known file types expanded** — Support for many additional common types across MIME anomaly detection, friendly type labels, and file icons: Images (bmp, tiff, tif, ico, heic, avif), Documents (rtf, odt, epub), Spreadsheets (ods, csv), Presentations (ppt, pptx, odp), Archives (rar, 7z, tar, gz), Audio (ogg, flac, m4a), Video (mov, webm, mkv, avi). Reduces false “MIME?” flags and improves display of file type and icon in lists.

---

## [v1.7.0] - 2026-02-19
### ✨ New Features (User System & Access Control)
- **User profile & account settings** — Profile page (`profile.php`) with view and edit for username, email, and optional display name; change-password form (current password required). Profile link added to main nav when logged in.
- **Profile statistics** — Private profile shows: files currently stored, storage used, total files/size (all time), expired files count, total downloads, public files count, average file size, files shared with you / shared by you, oldest and newest upload, and top file types and extensions by MIME and extension.
- **Public user profile** — Public profile page (`user.php?username=…`) viewable without login: display name (or username), @username, optional avatar and bio, member since, public file stats (count, total size, total downloads, most common type), and a table of the user’s public files with download link when public browsing is enabled. Usernames across the site (homepage, dashboard, share page, admin user/file management, header) link to the corresponding public profile.
- **Avatar & bio** — Optional profile picture: set by URL (http/https) or upload (JPEG, PNG, GIF, WebP; max 2 MB). Optional bio (max 500 characters). Both editable on profile and shown on public profile. Avatars served via `avatar.php`; uploads stored under `uploads/avatars/`.
- **Profile completion reminder** — When display name, bio, and avatar are all empty, profile page shows a warning flash encouraging users to complete their public profile.
- **Invite-only registration** — Site setting **Invite-only registration** in User Permissions. When enabled, new users must use a signup link; admins generate single-use, 7-day tokens from User Management. Table `signup_tokens` stores tokens; register flow validates token and marks it used on success.
- **Password reset flow** — **Forgot password** link on login page; user enters email and receives a one-time reset link (shown on page; no email sent). **Reset password** page sets new password from token. Admins can generate a reset link for any user from User Management (**Copy reset link**); table `password_reset_tokens` with 1-hour expiry.

### ♿ Accessibility (WCAG)
- **Skip link** — “Skip to main content” link at top of page; visible on keyboard focus for screen-reader and keyboard users.
- **ARIA & semantics** — `role="banner"` on header, `aria-label="Main navigation"` on nav, `id="main-content"` and `role="main"` on main content; flash messages use `role="alert"` and `aria-live="polite"`; theme toggle and close buttons have `aria-label`.
- **Keyboard focus** — Visible focus ring (`:focus-visible`) on links, buttons, and form controls; nav links use high-contrast outline. CSS variables `--focus-ring` and `--focus-offset` for theming.

### Improved
- Database migrations add `display_name`, `avatar`, and `bio` to `users`, and create `signup_tokens` and `password_reset_tokens` tables. New installs get default `invite_only_registration` in settings; existing installs receive the new setting via migration/defaults.
- Profile stat values use header background colour for consistency. Bio on public profile appears in a dedicated "Bio" card matching other sections. Bio textarea on profile matches width and background of other form fields. Nav "Logout (username)" spacing fixed so the username link has no extra gap (valid HTML and CSS for logout wrap).

---

## [v1.6.1] - 2026-02-18
### ✨ New Features
- **Bulk actions on dashboard** — Multi-select files for **Zip download**, **Toggle public/private**, and **Delete**; bulk action dropdown with confirmation for delete.
- **File size and storage units in Site Settings** — Max file size and max storage per user can be set in bytes, KB, MB, or GB via unit selector; live byte-size hints and server limit display.
- **Optional server PHP limit override** — New "Server PHP Limits" card in Site Settings: optionally write a `.user.ini` in the project root to override `upload_max_filesize` and `post_max_size`; option to remove overrides (delete `.user.ini`). Effective on next request where supported.

### Improved
- **File display** — Dashboard and admin file lists show human-readable dates and sizes; dashboard shows checksum (MD5/SHA256) with copy buttons and file sharing (Share, Public/Private) per file.
- **Documentation** — Added [CONTRIBUTING.md](CONTRIBUTING.md) (fork, branch naming, PR workflow), [ROADMAP.md](ROADMAP.md) (feature checklist and plans), and [PRIVACY.md](PRIVACY.md) (data handling, no telemetry). ROADMAP legend updated for clearer status symbols (✓ / —).

---

## [v1.6.0] - 2026-02-16
### ✨ New Features (Security & Storage)
- **Custom storage path support** — Configurable `storage_base_path` in Site Settings; uploads and thumbnails can be stored outside the project root (e.g. `/var/data/datadock`).
- **Public file browsing** — Optional anonymous access to uploads; when enabled, files marked "public" appear on the homepage and can be downloaded without logging in.
- **User-to-user file sharing** — Share files with specific users by username; "Shared with You" section on dashboard; shared users can download and add shared files to ZIP downloads.

### ✨ UI & Design
- **Flat SVG icons** — Replaced emoji icons with flat SVG icons across the site (upload, folder, theme toggle, file types, copy, lock, etc.); icons use `assets/icons.svg` sprite.
- **Redesigned upload page** — Hero section, improved drop zone with hover/dragover states, card-style options block, refined preview and progress display.
- **Responsive sizing** — Page sections, titles, and descriptions use `clamp()` for better fit at various screen resolutions; file lists have appropriate min-widths and horizontal scroll on small screens.
- **Admin Site Settings** — Card-based layout with grouped sections (General, User Permissions, Storage, Brute Force, etc.); live byte-size hints for storage inputs.
- **Admin File Management** — Summary bar with file count and total size; cleaner toolbar and table layout.
- **Updater & Changelog** — Displays only the most recent changelog section; proper semver comparison (shows "up to date" when current version ≥ latest release, e.g. development v1.6.0 vs. stable v1.4.1).

### Improved
- Thumbnails now served via `thumbnail.php` for compatibility with custom storage paths.
- Upload form shows "Make public" checkbox when public browsing is enabled.
- Dashboard shows Public/Private toggle and Share button per file when applicable.
- File table columns (Downloads, Uploaded) no longer wrap awkwardly; 8-column layout for public browsing with Download button.
- Database migrations add `is_public` column to `files` and create `file_shares` table.

---

## [v1.4.1] - 2026-02-12
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

## [v1.4.0] - 2026-02-12
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

## [v1.3.1] - 2026-02-12
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

## [v1.3.0] - 2026-02-11
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

