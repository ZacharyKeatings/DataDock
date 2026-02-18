# DataDock Roadmap

A forward-looking plan for upcoming features, improvements, and maintenance of the DataDock self-hosted file manager. Items are grouped by area; checkboxes indicate implementation status.

**Legend:** `[x]` Done · `[ ]` Planned

---

## Table of Contents

- [User & Account Settings](#-user--account-settings)
- [File Upload & Storage](#-file-upload--storage-settings)
- [Admin Panel](#-admin-panel)
- [Interface & Branding](#-interface--branding-settings)
- [Email & Notifications](#-email--notification-settings)
- [Security Settings](#-security-settings)
- [Versioning & Updates](#-versioning--updates)
- [Reporting & Abuse](#-reporting--abuse)
- [Access Control (Stronger, Still Simple)](#-access-control-stronger-still-simple)
- [Operational Robustness](#-operational-robustness)
- [Security Hardening (Minimal)](#-security-hardening-minimal)
- [User Trust & Transparency](#-user-trust--transparency)
- [Minimal Collaboration](#-minimal-collaboration)
- [System Maturity](#-system-maturity)
- [Rate Limiting (Extended)](#-rate-limiting-extended)
- [Archive / Read-Only Mode](#-archive--read-only-mode)

---

## ✅ User & Account Settings

| Status | Feature | Description |
|--------|---------|-------------|
| [x] | User registration, login, and dashboard | Core auth and user home. |
| [x] | Session-based authentication | Native PHP sessions. |
| [x] | Change user roles and delete users (admin only) | Role management in admin panel. |
| [x] | Enable/disable user registration | Site-wide registration toggle. |
| [x] | Optional guest uploads with quota enforcement | File count and size limits for unauthenticated uploads. |
| [x] | Enforce max storage and file limits per user | Per-user quotas. |
| [ ] | User account & profile system | Profile page and editable account details. |
| [x] | Public file browsing | Anonymous access to uploads when enabled; public files on homepage. |
| [x] | Default file expiry duration setting | Site default for new uploads (e.g. 1 hour, 1 day, never). |
| [x] | Enforce unique email toggle | Strict (unique email) vs relaxed (username only unique). |
| [ ] | Invite-only registration | Signups only via link/token created by admin. |
| [ ] | Password reset flow | Admin-initiated or token-based reset for forgotten passwords. |
| [x] | User-to-user file sharing | Share files with specific usernames; "Shared with You" on dashboard. |

---

## 🗂️ File Upload & Storage Settings

| Status | Feature | Description |
|--------|---------|-------------|
| [x] | File upload with optional expiry | Per-file expiry at upload time. |
| [x] | Auto-thumbnail generation for image files | Thumbnails via GD; toggle in settings. |
| [x] | Upload file size validation | Frontend and backend validation. |
| [x] | Drag-and-drop + preview file upload | Hero and card-style upload UI with progress. |
| [x] | Date/time storage in UTC | Stored in UTC with frontend conversion. |
| [x] | Max number of files per user | Configurable file count limit. |
| [x] | Max total storage per user (quota) | Total bytes per user. |
| [x] | File management view for all users | Admin panel file list and actions. |
| [x] | Allowed file types | Restrict by extension and/or MIME type. |
| [x] | Enable/disable thumbnail generation | Site setting to turn thumbnails off. |
| [x] | Custom storage path support | Uploads and thumbnails outside web root. |
| [x] | File upload progress bar | Progress feedback during upload. |
| [x] | Zip multiple files for download | Multi-select and download as single .zip. |
| [x] | Download as QR code | QR code for one-time link (e.g. mobile sharing). |
| [x] | One-time download links | Token-based link that expires after one use. |
| [x] | File checksum display | MD5 and SHA256 with copy buttons. |
| [x] | Terms of Service / Acceptable Use | Configurable ToS; users must accept before uploading. |
| [x] | Download counter per file | Count and display downloads. |
| [ ] | File search and filter | Search by filename, date, type; filter by visibility, expiry. |
| [ ] | File metadata editing | Rename, change expiry, add description without re-upload. |
| [ ] | Soft delete / trash | Deleted files in trash, restorable for a period. |
| [ ] | Folders or tags | Organize files (folders or flat tags). |
| [ ] | Duplicate detection | Deduplicate by file hash to save storage. |

---

## 👑 Admin Panel

| Status | Feature | Description |
|--------|---------|-------------|
| [x] | Admin panel with user and file management | Central admin UI. |
| [x] | Update site settings with card-based layout | Grouped sections: General, User Permissions, Storage, etc. |
| [x] | Enable/disable brute force protection | Configurable thresholds and lockout. |
| [x] | Configure guest upload limits | Max files and storage for guests. |
| [x] | Manual purging of expired files | Purge with stats. |
| [x] | View all uploaded files with summary stats | File list with delete/download options. |
| [x] | Reset site to post-install state | Remove all users, files, settings except admin. |
| [x] | Sidebar-based admin panel UI | Clear navigation. |
| [x] | Maintenance mode toggle | Admin-only access when enabled. |
| [x] | Debug mode toggle | Control PHP error display (disable in production). |
| [x] | Log file path and verbosity setting | File-based logging with level. |
| [x] | Site stats overview | Uploads, storage, user count, file types, expiring soon. |
| [ ] | Activity / audit log | Who uploaded, downloaded, shared, or deleted what and when. |
| [ ] | Storage / quota alerts | Notify admin when storage or quotas approach limits. |
| [ ] | Backup / export | Export database and optionally file metadata for disaster recovery. |

---

## 💬 Interface & Branding Settings

| Status | Feature | Description |
|--------|---------|-------------|
| [x] | Install.php warning if not deleted | Toggleable in settings. |
| [x] | Custom logo and favicon URLs | Branding in site settings. |
| [x] | Welcome banner or message field | Editable homepage message. |
| [x] | Install.php warning toggle | Turn warning off if needed. |
| [x] | Dark mode / light mode UI toggle | Theme switcher; preference via cookie. |
| [x] | Responsive layout | Sections and file tables scale; horizontal scroll on small screens. |
| [ ] | Localization/multilanguage support | Multiple languages. |
| [ ] | Accessibility (WCAG) improvements | Improve accessibility. |
| [x] | Custom file icons | Flat SVG icons per type; custom URLs in settings. |

---

## 📧 Email / Notification Settings

| Status | Feature | Description |
|--------|---------|-------------|
| [x] | Admin contact email field | Shown in footer and admin sidebar. |
| [ ] | Email notifications on upload, expiry, etc. | Notify users or admin on events. |
| [ ] | SMTP configuration | Host, port, user, pass, encryption. |
| [ ] | Email registration confirmation | Confirm email on signup. |

---

## 🔒 Security Settings

| Status | Feature | Description |
|--------|---------|-------------|
| [x] | Secure password hashing | `password_hash` (bcrypt/argon2). |
| [x] | Session management and role-based access | Sessions and role checks. |
| [x] | `config/` directory secured via `.htaccess` | Protect config from web access. |
| [x] | CSRF-safe architecture | Form-only POST; tokens where needed. |
| [x] | Brute-force login protection | Configurable limits and lockout window. |
| [ ] | CAPTCHA on login/register forms | Reduce automated abuse. |
| [ ] | Rate limiting on uploads | Throttle upload frequency per user or IP. |
| [x] | Session timeout duration setting | Configurable session lifetime. |

---

## 🔁 Versioning & Updates

| Status | Feature | Description |
|--------|---------|-------------|
| [x] | Version display in admin panel / footer | Show current version. |
| [x] | One-click update system | GitHub release fetcher with semver comparison. |
| [x] | Changelog viewer | Most recent changelog section and release notes in admin. |

---

## ⚠️ Reporting & Abuse

| Status | Feature | Description |
|--------|---------|-------------|
| [ ] | Users can report files | Report files for malicious or inappropriate content. |

---

## 🔐 Access Control (Stronger, Still Simple)

Planned improvements that keep access control simple while adding stronger options.

| Status | Feature | Description |
|--------|---------|-------------|
| [ ] | Per-file access passwords | Optional password gate before download; no full encryption. |
| [ ] | IP-restricted downloads | Restrict file access to specific IPs or ranges (e.g. internal use). |
| [ ] | Download expiration by count + time | Expire after N downloads and/or after a time window; extends one-time links. |
| [ ] | Signed URLs | Time-limited HMAC-signed URLs for public temp links; reduces DB lookup overhead. |

---

## 🛠️ Operational Robustness

Admin and ops tools without introducing daemons or heavy infrastructure.

| Status | Feature | Description |
|--------|---------|-------------|
| [ ] | Background purge via cron | CLI script runnable via cron; no daemon or worker. |
| [ ] | Disk usage integrity checker | Admin tool: scan uploads dir vs DB; find orphaned or missing files. |
| [ ] | File integrity verification | Admin re-hash and verify checksums (e.g. after storage issues). |
| [ ] | Storage partition support | Multiple storage roots; assign users/roles to specific paths (no cloud layer). |

---

## 🛡️ Security Hardening (Minimal)

Additional hardening with minimal complexity.

| Status | Feature | Description |
|--------|---------|-------------|
| [ ] | Content-Disposition enforcement | Force download for risky types to prevent inline execution. |
| [ ] | Optional file extension rewriting | Store files without original extension; restore on download. |
| [ ] | Upload quarantine mode | New uploads invisible until admin approval (for public instances). |
| [ ] | Automatic MIME anomaly detection | Flag when extension and MIME type disagree for admin review. |

---

## 👤 User Trust & Transparency

Lightweight transparency and control for users.

| Status | Feature | Description |
|--------|---------|-------------|
| [ ] | User activity page | User sees own uploads, when/how often downloaded, approximate country. |
| [ ] | Storage usage graph | Simple usage-over-time per user. |
| [ ] | Data export | Export own file metadata, account info, and activity history (privacy-conscious). |

---

## 📤 Minimal Collaboration

Light collaboration without a full folder/collab system.

| Status | Feature | Description |
|--------|---------|-------------|
| [ ] | Temporary share folders | One share container, multiple files, one link, expiry (no full folder system). |
| [ ] | Comment field per file | Optional description or note visible to recipients. |

---

## 🚢 System Maturity

Makes DataDock easier to deploy and operate in production environments.

| Status | Feature | Description |
|--------|---------|-------------|
| [ ] | CLI installer | Install via CLI for VPS/automation (alongside web install.php). |
| [ ] | Environment-based config override | Env vars override DB-stored config (containers, 12-factor). |
| [ ] | Docker support | Official Dockerfile and docker-compose example. |
| [ ] | Health check endpoint | e.g. `/health` returning JSON for uptime monitoring. |

---

## ⏱️ Rate Limiting (Extended)

| Status | Feature | Description |
|--------|---------|-------------|
| [ ] | Per-IP upload throttling | Throttle uploads per IP or per account. |
| [ ] | Adaptive cooldown | Progressive slowdown for repeated failed logins from one IP across usernames. |

---

## 📥 Archive / Read-Only Mode

| Status | Feature | Description |
|--------|---------|-------------|
| [ ] | Read-only instance mode | Admin toggle: no uploads, no new accounts, downloads only (archival deployments). |

---

*For release history and detailed changes, see [CHANGELOG.md](CHANGELOG.md).*
