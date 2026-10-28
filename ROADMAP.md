# DataDock Roadmap

A forward-looking plan for upcoming features, improvements, and maintenance of the DataDock self-hosted file manager. Items are grouped by area; checkboxes indicate implementation status.

**Legend:** ✓ Done · — Planned

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
- [Automation & Integrations](#-automation--integrations)
- [Observability (Extended)](#-observability-extended)
- [Account Security (Extended)](#-account-security-extended)
- [HTTP & Deployment Security](#-http--deployment-security)

---

## ✅ User & Account Settings

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | User registration, login, and dashboard | Core auth and user home. |
| ✓ | Session-based authentication | Native PHP sessions. |
| ✓ | Change user roles and delete users (admin only) | Role management in admin panel. |
| ✓ | Enable/disable user registration | Site-wide registration toggle. |
| ✓ | Optional guest uploads with quota enforcement | File count and size limits for unauthenticated uploads. |
| ✓ | Enforce max storage and file limits per user | Per-user quotas. |
| ✓ | User account & profile system | Profile page and editable account details; profile statistics (files, storage, downloads, sharing, file types). |
| ✓ | Public user profile page | View by username (user.php); avatar, bio, stats, public files list; usernames site-wide link to profile. |
| ✓ | Profile avatar and bio | Optional avatar (URL or upload), bio (500 chars); profile completion reminder when empty. |
| ✓ | Public file browsing | Anonymous access to uploads when enabled; public files on homepage. |
| ✓ | Default file expiry duration setting | Site default for new uploads (e.g. 1 hour, 1 day, never). |
| ✓ | Enforce unique email toggle | Strict (unique email) vs relaxed (username only unique). |
| ✓ | Invite-only registration | Signups only via link/token created by admin. |
| ✓ | Password reset flow | Admin-initiated or token-based reset for forgotten passwords. |
| ✓ | User-to-user file sharing | Share files with specific usernames; "Shared with You" on dashboard. |
| ✓ | Suspend user | Admin can disable an account so login and new shares are blocked while files, quotas, and the audit trail stay in place. |
| ✓ | Close your own account | Password-confirmed self-service deletion. Files are purged, an audit row is written, and the last active admin cannot be removed. |
| ✓ | Claim a guest upload | One-time claim token so a guest upload can be attached to an account at registration or login and counted against that user's quota. |
| ✓ | Account theme | Profile setting for a default theme, applied while signed in. Guests use the site default. |

---

## 🗂️ File Upload & Storage Settings

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | File upload with optional expiry | Per-file expiry at upload time. |
| ✓ | Auto-thumbnail generation for image files | Thumbnails via GD; toggle in settings. |
| ✓ | Upload file size validation | Frontend and backend validation. |
| ✓ | Drag-and-drop + preview file upload | Hero and card-style upload UI with progress. |
| ✓ | Date/time storage in UTC | Stored in UTC with frontend conversion. |
| ✓ | Max number of files per user | Configurable file count limit. |
| ✓ | Max total storage per user (quota) | Total bytes per user. |
| ✓ | File management view for all users | Admin panel file list and actions. |
| ✓ | Bulk actions on dashboard | Multi-select: zip download, toggle public/private, delete. |
| ✓ | Allowed file types | Admin allow-list or block-list by extension. Executable and server-script types stay blocked. Listing risky types such as apk, html, or svg warns before save. |
| ✓ | Known file types | Friendly names and icons for packages (including .apk), fonts, Markdown, YAML, SQL, and WebAssembly, in addition to the existing image, document, and media types. |
| ✓ | Upload result link | After a successful upload, the result stays on screen with a link to the file details page (download page for guests). |
| ✓ | Enable/disable thumbnail generation | Site setting to turn thumbnails off. |
| ✓ | Custom storage path support | Uploads and thumbnails outside web root. |
| ✓ | File upload progress bar | Progress feedback during upload. |
| ✓ | Zip multiple files for download | Multi-select and download as single .zip. |
| ✓ | Download as QR code | QR code for one-time link (e.g. mobile sharing). |
| ✓ | One-time download links | Token-based link that expires after one use. |
| ✓ | File checksum display | MD5 and SHA256 with copy buttons. |
| ✓ | Terms of Service / Acceptable Use | Configurable ToS; users must accept before uploading. |
| ✓ | Download counter per file | Count and display downloads. |
| ✓ | File search and filter | Search by filename, date, type; filter by visibility, expiry. |
| ✓ | File metadata editing | Rename, change expiry, add description without re-upload. |
| ✓ | Soft delete / trash | Deleted files in trash, restorable for a period. |
| ✓ | Folders | Nested folders per user; breadcrumb, create, move; uploads can target a folder (`upload.php?folder=`). |
| ✓ | Tags | Comma-separated tags on edit file; optional tag filter on dashboard; can disable in settings. |
| ✓ | SHA-256 deduplication | Optional per storage partition (`storage_objects`); identical content shares one blob; refs decremented on delete/purge. |
| ✓ | Partition-aware storage paths | `includes/storage.php` resolves paths for downloads, thumbnails, ZIP, one-time links, uploads, delete/purge across partitions. |
| ✓ | Plaintext pastes | Unlisted pastebin-style text sharing (`paste.php`): expiry, one-time view, raw/download, dashboard list. Toggle in Site Settings. |
| ✓ | Expiring soon on the dashboard | Owner warning for files expiring within seven days, matching the admin Overview notice. |
| ✓ | Safe inline preview | Viewer for full images, audio, video, and plain text. HTML, SVG, and script types stay forced downloads. |
| ✓ | Bulk move to folder | Dashboard bulk action to move the selection into a folder. |
| ✓ | Replace file contents | Upload new bytes for an existing file and keep the id, shares, and links. The previous blob follows trash retention. |
| ✓ | Chunked upload | Client sends slices; the server reassembles into the user's partition, then runs MIME, quarantine, deduplication, and quota checks once. |
| ✓ | Paste passwords | Optional password gate on pastes, including raw and download URLs, using the same idea as per-file download passwords. |

---

## 👑 Admin Panel

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | Admin panel with user and file management | Central admin UI. |
| ✓ | Update site settings with card-based layout | Grouped sections: General, User Permissions, Storage, etc. |
| ✓ | File size and storage inputs with units | Bytes, KB, MB, GB selectors; optional .user.ini override for PHP upload limits. |
| ✓ | Enable/disable brute force protection | Configurable thresholds and lockout. |
| ✓ | Configure guest upload limits | Max files and storage for guests. |
| ✓ | Manual purging of expired files | Purge with stats. |
| ✓ | View all uploaded files with summary stats | File list with delete/download options. |
| ✓ | Reset site to post-install state | Remove all users, files, settings except admin. |
| ✓ | Sidebar-based admin panel UI | Clear navigation. |
| ✓ | Maintenance mode toggle | Admin-only access when enabled. |
| ✓ | Debug mode toggle | Control PHP error display (disable in production). |
| ✓ | Log file path and verbosity setting | File-based logging with level. |
| ✓ | Site stats overview | Uploads, storage, user count, file types, expiring soon. |
| ✓ | Activity / audit log | `activity_log` + Admin → Activity log; actor, file, detail JSON, IP; optional purge of entries older than *N* days. |
| ✓ | Storage / quota alerts | Site Settings → Operational alerts; warnings on partition disk usage and user/guest quota approach; shown on Admin → Overview. |
| ✓ | Backup / export | Admin → Backup & integrity: full SQL dump or JSON `files` metadata (`backup_download.php`). |
| ✓ | Hotlink log | Optional referer logging for downloads, ZIP, thumbnails, avatars from other hostnames; Admin → Hotlink log; Site Settings → Hotlink monitoring. |
| ✓ | Storage partitions | Admin UI: multiple roots, default partition, per-user assignment; usage listing. |
| ✓ | Transfer file ownership | Move a user's files to another account before deletion, respecting storage partitions. |
| ✓ | Audit log download | CSV or JSON export of the activity log from Admin, beside the existing backup action. |

---

## 💬 Interface & Branding Settings

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | Install.php warning if not deleted | Toggleable in settings. |
| ✓ | Custom logo and favicon URLs | Branding in site settings. |
| ✓ | Welcome banner or message field | Editable homepage message. |
| ✓ | Install.php warning toggle | Turn warning off if needed. |
| ✓ | Themes | Light, Dark, and bundled Midnight, Ocean, Forest, Sunset, High contrast, and Paper. Header switcher and Site Settings default. |
| ✓ | Custom theme editor | Admin → Themes: color, font, and radius controls with a live sample, plus optional extra CSS. Saved names appear in the default-theme list. |
| ✓ | Responsive layout | Sections and file tables scale from phones through tablets. Admin navigation stacks, wide account rows become labeled cards, and pagers wrap. |
| — | Localization/multilanguage support | Multiple languages; string extraction and locale selection. |
| ✓ | Editable privacy page | Site setting for a public privacy notice, separate from the repository `PRIVACY.md`. |
| ✓ | Public thumbnail grid | Optional grid layout for the public file index when public browsing is on. |
| ✓ | Accessibility (WCAG) improvements | Improve accessibility. |
| ✓ | Pagination | Page controls on files, shared files, share-folder links, trash, activity, notices, sessions, pastes, the public index, and admin user and file lists. |
| ✓ | Custom file icons | Flat SVG icons per type; custom URLs in settings. |

---

## 📧 Email / Notification Settings

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | Admin contact email field | Shown in footer and admin sidebar. |
| — | Email notifications on upload, expiry, etc. | Notify users or admin on events. |
| — | SMTP configuration | Host, port, user, pass, encryption. Required before verification links are emailed. |
| ✓ | Email verification toggle | Admins can require confirmation for new local accounts. Until SMTP exists, signup shows the link and an admin can confirm the address from User Management. Existing accounts stay confirmed. |
| ✓ | In-app notices | Per-user list for shares received, report reviews, quota warnings, and files expiring soon. Works with SMTP off; email can deliver the same events later. |

---

## 🔒 Security Settings

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | Secure password hashing | `password_hash` (bcrypt/argon2). |
| ✓ | Session management and role-based access | Sessions and role checks. |
| ✓ | `config/` directory secured via `.htaccess` | Protect config from web access. |
| ✓ | CSRF-safe architecture | Form-only POST; tokens where needed. |
| ✓ | Brute-force login protection | Configurable limits and lockout window. |
| — | CAPTCHA on login/register forms | Reduce automated abuse. |
| ✓ | Rate limiting on uploads | Throttle upload frequency per user or IP. |
| ✓ | Session timeout duration setting | Configurable session lifetime. |
| ✓ | Block hotlinks | Optional deny when the referer is another host and is not in the trusted-host list. Applies to downloads, ZIP, thumbnails, and avatars. |
| ✓ | Admin IP allowlist | Optional IP and CIDR list for the admin panel and other destructive actions, using the same style as per-file download allowlists. |

---

## 🔁 Versioning & Updates

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | Version display in admin panel / footer | Show current version. |
| ✓ | One-click update system | GitHub release fetcher with semver comparison. |
| ✓ | Changelog viewer | Most recent changelog section and release notes in admin. |

---

## ⚠️ Reporting & Abuse

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | Users can report files | Report files for malicious or inappropriate content. |
| ✓ | Admin handling of reports | Review, dismiss, and take moderation action. |
| ✓ | Purge hold | Flag so expiry and trash retention skip a file until an admin clears it. Set from the report queue when a file should stay available for review. |

---

## 🔐 Access Control (Stronger, Still Simple)

Improvements that keep access control simple while adding stronger options (v2.3.0).

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | Per-file access passwords | Optional password gate before download; no full encryption. **Edit file** + session unlock on `download.php` / thumbnails. |
| ✓ | IP-restricted downloads | Restrict anonymous downloads to listed IPs/CIDR (JSON on `files.ip_allowlist`). Owners and logged-in shared access bypass. |
| ✓ | Download expiration by count + time | `download_tokens`: `max_uses`, `use_count`, `expires_at`. **Share link (token)** flow (`create_onetime.php`). |
| ✓ | Signed URLs | Time-limited HMAC (`download.php?id=&exp=&sig=`), secret in `app_secrets`. **Signed link** (`create_signed.php`). |
| ✓ | Outstanding links | Dashboard list of live token links (uses left, expiry, file) with revoke. Signed links gain an id so one URL can be revoked without rotating the site secret. |

---

## 🛠️ Operational Robustness

Admin and ops tools without introducing daemons or heavy infrastructure.

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | Background purge via cron | `scripts/datadock-cron-purge.php`; purges expired files and trash past retention; flags `--no-trash` / `--no-expired`; shared logic with Admin purge (`includes/purge_ops.php`). |
| ✓ | Disk usage integrity checker | Admin → Backup & integrity: scan uploads vs database per partition (DB rows missing on disk; on-disk blobs not in DB). |
| ✓ | File integrity verification | Verify MD5/SHA256 vs bytes on disk (optional row limit); re-hash from disk to update DB (optional limit). |
| ✓ | Storage partition support | Multiple roots (`storage_partitions`); user assignment; default partition; empty root inherits custom storage base path. |
| ✓ | Full backup and restore | Admin → Backup & integrity: download a partition-aware archive of the database plus storage roots, and restore that archive from the same page. |

---

## 🛡️ Security Hardening (Minimal)

Additional hardening with minimal complexity.

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | Content-Disposition enforcement | Force download for risky types to prevent inline execution. |
| ✓ | Optional file extension rewriting | Store files without original extension; restore on download. |
| ✓ | Upload quarantine mode | New uploads invisible until admin approval (for public instances). |
| ✓ | Automatic MIME anomaly detection | Flag when extension and MIME type disagree for admin review. |
| ✓ | Upload hardening (v2.0.0) | Reject dangerous filename segments; MIME + magic-byte checks for images; scan image/SVG bodies for embedded PHP; expanded forbidden extensions; upload UI mirrors checks. |
| ✓ | Optional upload scan hook | Off-by-default local command or `clamd` socket. A failed scan leaves the upload pending. No scanner or signatures ship with the app. |

---

## 👤 User Trust & Transparency

Lightweight transparency and control for users (v2.3.0).

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | User activity page | **Activity** nav: uploads list, `file_download_events` tail, IP / optional country (e.g. `CF-IPCountry`). |
| ✓ | Storage usage graph | `user_storage_snapshots` (hourly cap), line chart on Activity page. |
| ✓ | Data export | **`export_data.php`**: JSON with account, files metadata, own activity log rows, download events for owned files. |

---

## 📤 Minimal Collaboration

Light collaboration without a full folder/collab system.

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | Temporary share folders | One share container, multiple files, one link, expiry (no full folder system). |
| ✓ | Comment field per file | Optional recipient note per file on share-folder page (v2.4+). |
| ✓ | Live folder link | Link to a real folder that follows adds and removes, with the same expiry, password, and IP options as files. Distinct from snapshot share bundles. |

---

## 🚢 System Maturity

Makes DataDock easier to deploy and operate in production environments.

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | CLI installer | `scripts/datadock-install.php` (alongside web install.php). |
| ✓ | Environment-based config override | `DATADOCK_*` env vars via `includes/settings_loader.php`. |
| ✓ | Docker support | `Dockerfile` and `docker-compose.example.yml`. |
| ✓ | Health check endpoint | `health.php` returns JSON for uptime monitoring. |
| ✓ | Admin account tools | Admin → User Management: create an account, set a per-user storage quota, and review the account list. |

---

## ⏱️ Rate Limiting (Extended)

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | Per-IP upload throttling | Throttle uploads per IP or per account. |
| ✓ | Adaptive cooldown | Progressive slowdown for repeated failed logins from one IP across usernames. |
| ✓ | Download quota | Per-user or per-file download byte budget over a time window, using the same pattern as upload throttling. |

---

## 📥 Archive / Read-Only Mode

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | Read-only instance mode | Admin toggle + `DATADOCK_READ_ONLY`; no uploads, no new accounts, downloads only. |

---

## 🔗 Automation & Integrations

Scripting and external systems without requiring email.

| Status | Feature | Description |
|--------|---------|-------------|
| — | HTTP webhooks | POST callbacks for selected events (e.g. upload, share created, expiry warning); optional signing secret. |
| — | Scoped API tokens | Issued per user or admin; rate-limited access for automation. |
| — | Minimal JSON API | Authenticated endpoints for common operations (e.g. list, upload) aligned with CLI and container use. |

---

## 📡 Observability (Extended)

Beyond the health check planned in **System Maturity**.

| Status | Feature | Description |
|--------|---------|-------------|
| — | Structured JSON logging | Optional machine-readable log lines for aggregation stacks (e.g. ELK, Loki). |
| — | Metrics endpoint | Counters/gauges or Prometheus exposition; complements `/health` for monitoring. |

---

## 👤 Account Security (Extended)

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | Password rules | Site setting for minimum length and a simple complexity toggle. |
| ✓ | TOTP two-factor authentication | Authenticator-app 2FA; typically admin-first, then optional for all users. |
| ✓ | Session / device management | e.g. list active sessions and revoke others (optional; schedule with 2FA or later). |
| ✓ | OIDC login | Optional OpenID Connect sign-in against a self-hosted provider such as Authentik (also Authelia, Keycloak, and similar). Map a group or role claim onto DataDock roles. Local accounts stay available so an admin can still sign in if the provider is down. |
| ✓ | LDAP login | Optional bind against a self-hosted directory (Authentik's LDAP outpost, OpenLDAP, Active Directory). Map a directory group onto DataDock roles. Local accounts stay available as a break-glass login. |
| ✓ | Forward auth / SSO | For basic shared hosting, where a local directory bind or a bundled identity provider is not available. Optional trust of identity headers from a reverse proxy or SSO gateway (Authentik proxy provider, Authelia, oauth2-proxy, nginx `auth_request`). DataDock signs in the forwarded user and maps groups to roles. Headers are honored only from a configured proxy. Local accounts stay available as a break-glass login. |

---

## 🌐 HTTP & Deployment Security

| Status | Feature | Description |
|--------|---------|-------------|
| ✓ | Documented security headers | PHP modes + README; nginx/apache examples under `examples/` (v2.5+). |

---

*For release history and detailed changes, see [CHANGELOG.md](CHANGELOG.md).*
