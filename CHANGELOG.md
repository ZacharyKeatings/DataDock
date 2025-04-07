# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),  
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


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

